<?php

declare(strict_types=1);

namespace SilverShop\Newsletter\Service;

use SilverShop\Newsletter\Job\NewsletterSyncJob;
use SilverShop\Newsletter\Model\NewsletterSubscription;
use SilverShop\Newsletter\Provider\NewsletterProviderRegistry;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * The orchestration layer for subscriptions: it upserts a {@link NewsletterSubscription} from any signup surface
 * (footer form, checkout opt-in, account section, import/api), drives the double-opt-in / unsubscribe lifecycle,
 * sends the tokenised confirm email (sender identity resolved via silvershop/store-profile when present), applies
 * ESP webhook events back onto the local list, and enqueues a {@link NewsletterSyncJob} on each change.
 *
 * It is the one place the opt-in policy lives, so every surface behaves identically.
 */
class NewsletterService
{
    use Configurable;
    use Extensible;
    use Injectable;

    /**
     * Require guests to confirm via a double-opt-in email before they are counted as subscribed (GDPR-friendly).
     */
    private static bool $double_opt_in = true;

    /**
     * Logged-in members (identity already established) are auto-confirmed instead of being emailed a confirm link.
     */
    private static bool $member_auto_confirm = true;

    /**
     * Sender address for the confirm email; falls back to store-profile contact email, then the framework admin.
     */
    private static string $from_email = '';

    /**
     * Upsert a subscription for an email address and start the appropriate opt-in flow. Returns the record, or
     * null when the email is invalid.
     *
     * @param array{
     *     Source?: string,
     *     FirstName?: string,
     *     Surname?: string,
     *     Locale?: string,
     *     Member?: ?Member,
     *     Consent?: bool
     * } $context
     */
    public function subscribe(string $email, array $context = []): ?NewsletterSubscription
    {
        $email = trim(mb_strtolower($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $locale = (string) ($context['Locale'] ?? $this->currentLocale());
        $subscription = $this->findOrMake($email, $locale);

        // Merge data (last non-empty value wins, so checkout/account name enriches a bare footer signup).
        foreach (['FirstName', 'Surname'] as $field) {
            if (!empty($context[$field])) {
                $subscription->$field = (string) $context[$field];
            }
        }
        if (!$subscription->Source || !$subscription->isInDB()) {
            $subscription->Source = (string) ($context['Source'] ?? 'footer');
        }
        $member = $context['Member'] ?? null;
        if ($member instanceof Member && $member->exists()) {
            $subscription->MemberID = $member->ID;
        }

        // Already an active subscriber: just refresh merge fields on the ESP, no re-confirmation.
        if ($subscription->Status === NewsletterSubscription::STATUS_SUBSCRIBED) {
            $subscription->write();
            $this->enqueue($subscription, NewsletterSyncJob::ACTION_UPDATE);

            return $subscription;
        }

        $isMember = $member instanceof Member && $member->exists();
        $autoConfirm = ($isMember && (bool) static::config()->get('member_auto_confirm'))
            || !(bool) static::config()->get('double_opt_in')
            || !empty($context['Consent']);

        if ($autoConfirm) {
            $subscription->confirm();
            $subscription->write();
            $this->enqueue($subscription, NewsletterSyncJob::ACTION_SUBSCRIBE);
        } else {
            $subscription->Status = NewsletterSubscription::STATUS_PENDING;
            $subscription->write();
            $this->sendConfirmationEmail($subscription);
        }

        $this->extend('onAfterSubscribe', $subscription, $context);

        return $subscription;
    }

    /**
     * Confirm (double-opt-in) a pending subscription by its token.
     */
    public function confirmByToken(string $token): ?NewsletterSubscription
    {
        $subscription = $this->byToken($token);
        if (!$subscription) {
            return null;
        }

        if ($subscription->Status !== NewsletterSubscription::STATUS_SUBSCRIBED) {
            $subscription->confirm();
            $subscription->write();
            $this->enqueue($subscription, NewsletterSyncJob::ACTION_SUBSCRIBE);
        }

        return $subscription;
    }

    /**
     * Unsubscribe a subscription by its token.
     */
    public function unsubscribeByToken(string $token): ?NewsletterSubscription
    {
        $subscription = $this->byToken($token);
        if (!$subscription) {
            return null;
        }

        if ($subscription->Status !== NewsletterSubscription::STATUS_UNSUBSCRIBED) {
            $subscription->markUnsubscribed();
            $subscription->write();
            $this->enqueue($subscription, NewsletterSyncJob::ACTION_UNSUBSCRIBE);
        }

        return $subscription;
    }

    /**
     * Apply an ESP webhook event (unsubscribe / bounce / cleaned) to the local list. No re-sync is enqueued — the
     * change originated at the ESP.
     */
    public function applyWebhookEvent(string $email, string $status): ?NewsletterSubscription
    {
        $email = trim(mb_strtolower($email));
        $allowed = [
            NewsletterSubscription::STATUS_SUBSCRIBED,
            NewsletterSubscription::STATUS_UNSUBSCRIBED,
            NewsletterSubscription::STATUS_BOUNCED,
            NewsletterSubscription::STATUS_CLEANED,
        ];
        if ($email === '' || !in_array($status, $allowed, true)) {
            return null;
        }

        $subscription = NewsletterSubscription::get()->filter('Email', $email)->first();
        if (!$subscription) {
            return null;
        }

        $subscription->Status = $status;
        if ($status === NewsletterSubscription::STATUS_UNSUBSCRIBED && !$subscription->UnsubscribedAt) {
            $subscription->UnsubscribedAt = DBDatetime::now()->getValue();
        }
        $subscription->write();

        return $subscription;
    }

    /**
     * Send the double-opt-in confirmation email. Returns false (without throwing) when sending fails.
     */
    public function sendConfirmationEmail(NewsletterSubscription $subscription): bool
    {
        $data = ArrayData::create([
            'ShopName' => $this->senderName(),
            'Email' => $subscription->Email,
            'ConfirmLink' => $subscription->ConfirmLink(),
            'UnsubscribeLink' => $subscription->UnsubscribeLink(),
        ]);
        $this->extend('updateConfirmationEmailData', $data, $subscription);

        try {
            Email::create()
                ->setHTMLTemplate('SilverShop\\Newsletter\\Email\\ConfirmEmail')
                ->setPlainTemplate('SilverShop\\Newsletter\\Email\\ConfirmEmailPlain')
                ->setData($data)
                ->setTo($subscription->Email)
                ->setFrom($this->senderEmail(), $this->senderName())
                ->setSubject(_t(
                    self::class . '.ConfirmSubject',
                    'Please confirm your newsletter subscription to {shop}',
                    ['shop' => $this->senderName()]
                ))
                ->send();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function senderEmail(): string
    {
        $configured = (string) static::config()->get('from_email');
        if ($configured) {
            return $configured;
        }

        $siteConfig = SiteConfig::current_site_config();
        if ($siteConfig->hasField('ContactEmail') && $siteConfig->ContactEmail) {
            return (string) $siteConfig->ContactEmail;
        }

        $admin = Email::config()->get('admin_email');
        if (is_array($admin)) {
            $admin = array_key_first($admin) ?: '';
        }

        return $admin ?: ('no-reply@' . (parse_url(Director::absoluteBaseURL(), PHP_URL_HOST) ?: 'example.com'));
    }

    public function senderName(): string
    {
        $siteConfig = SiteConfig::current_site_config();
        if ($siteConfig->hasMethod('StoreProfileName')) {
            $name = $siteConfig->StoreProfileName();
            if ($name) {
                return (string) $name;
            }
        }

        return (string) $siteConfig->Title;
    }

    protected function findOrMake(string $email, string $locale): NewsletterSubscription
    {
        $existing = NewsletterSubscription::get()
            ->filter(['Email' => $email, 'Locale' => $locale])
            ->first();
        if ($existing) {
            return $existing;
        }

        $subscription = NewsletterSubscription::create();
        $subscription->Email = $email;
        $subscription->Locale = $locale;

        return $subscription;
    }

    protected function byToken(string $token): ?NewsletterSubscription
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return NewsletterSubscription::get()->filter('Token', $token)->first();
    }

    protected function enqueue(NewsletterSubscription $subscription, string $action): void
    {
        NewsletterSyncJob::enqueue($subscription, $action);
    }

    /**
     * Current content locale (Fluent-aware when installed), used to key per-locale lists.
     */
    protected function currentLocale(): string
    {
        if (class_exists(\TractorCow\Fluent\State\FluentState::class)) {
            $locale = \TractorCow\Fluent\State\FluentState::singleton()->getLocale();
            if ($locale) {
                return (string) $locale;
            }
        }

        return (string) \SilverStripe\i18n\i18n::get_locale();
    }
}
