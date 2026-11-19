<?php

declare(strict_types=1);

namespace SilverShop\Newsletter\Service;

use SilverShop\Newsletter\Model\NewsletterSubscription;

/**
 * The canonical marketing-consent source of truth for the shop. Sibling modules (abandoned-cart, stock-alerts)
 * call {@see has()} behind a moduleexists('silvershop-suite/newsletter') guard to gate marketing emails on a real,
 * explicit, timestamped opt-in — and keep their own transactional-nudge fallback when this module is absent.
 */
class NewsletterConsent
{
    /**
     * Whether the given email address has given marketing consent and is actively subscribed (in any locale).
     */
    public static function has(string $email): bool
    {
        $email = trim(mb_strtolower($email));
        if ($email === '') {
            return false;
        }

        return NewsletterSubscription::get()
            ->filter([
                'Email' => $email,
                'Consent' => true,
                'Status' => NewsletterSubscription::STATUS_SUBSCRIBED,
            ])
            ->exists();
    }

    /**
     * The consenting subscription for an email (the most recent), or null.
     */
    public static function subscription(string $email): ?NewsletterSubscription
    {
        $email = trim(mb_strtolower($email));
        if ($email === '') {
            return null;
        }

        return NewsletterSubscription::get()
            ->filter(['Email' => $email, 'Status' => NewsletterSubscription::STATUS_SUBSCRIBED])
            ->sort('ConsentAt DESC')
            ->first();
    }
}
