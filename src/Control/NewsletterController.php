<?php

declare(strict_types=1);

namespace SilverShop\Newsletter\Control;

use SilverShop\Newsletter\Model\NewsletterSubscription;
use SilverShop\Newsletter\Provider\NewsletterProviderRegistry;
use SilverShop\Newsletter\Service\NewsletterService;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Security\Security;

/**
 * Public endpoint for the newsletter: the footer/sidebar signup form POST, the tokenised double-opt-in
 * confirm and one-click unsubscribe landings, and the per-provider ESP webhook. Routed at `newsletter`
 * (see _config/routes.yml), mirroring silvershop-suite/reviews' ReviewInvitationController.
 */
class NewsletterController extends Controller
{
    private static string $url_segment = 'newsletter';

    private static array $allowed_actions = [
        'index',
        'confirm',
        'unsubscribe',
        'webhook',
        'SignupForm',
        'doSignup',
    ];

    private static array $url_handlers = [
        'confirm/$Token!' => 'confirm',
        'unsubscribe/$Token!' => 'unsubscribe',
        'webhook/$Provider!' => 'webhook',
        'SignupForm' => 'SignupForm',
        '' => 'index',
    ];

    /**
     * Honeypot field name — a hidden input real users never fill. A non-empty value marks a bot submission.
     */
    private static string $honeypot_field = 'website';

    public function Link($action = null): string
    {
        return Controller::join_links(Director::baseURL(), 'newsletter', $action);
    }

    public function index(HTTPRequest $request): HTTPResponse
    {
        return $this->renderState('signup');
    }

    public function confirm(HTTPRequest $request): HTTPResponse
    {
        $subscription = NewsletterService::create()->confirmByToken((string) $request->param('Token'));

        return $this->renderState($subscription ? 'confirmed' : 'invalid');
    }

    public function unsubscribe(HTTPRequest $request): HTTPResponse
    {
        $subscription = NewsletterService::create()->unsubscribeByToken((string) $request->param('Token'));

        return $this->renderState($subscription ? 'unsubscribed' : 'invalid');
    }

    /**
     * ESP → local webhook. Dispatches to the named provider's verify + parse, then applies the resulting
     * status changes (unsubscribe / bounce / cleaned) to the local list. Returns a plain 200 so the ESP
     * records a successful delivery.
     */
    public function webhook(HTTPRequest $request): HTTPResponse
    {
        $key = (string) $request->param('Provider');
        $provider = NewsletterProviderRegistry::create()->get($key);

        if (!$provider || !$provider->isRemote() || !$provider->supportsWebhooks()) {
            return $this->plainResponse(404, 'Unknown provider');
        }
        if (!$provider->verifyWebhook($request)) {
            return $this->plainResponse(403, 'Invalid webhook signature');
        }

        $service = NewsletterService::create();
        $applied = 0;
        foreach ($provider->parseWebhook($request) as $event) {
            $email = (string) ($event['email'] ?? '');
            $status = (string) ($event['status'] ?? '');
            if ($email !== '' && $status !== '' && $service->applyWebhookEvent($email, $status)) {
                $applied++;
            }
        }

        return $this->plainResponse(200, 'OK (' . $applied . ' applied)');
    }

    public function SignupForm(): Form
    {
        $fields = FieldList::create(
            EmailField::create('Email', _t(self::class . '.Email', 'Email address'))
                ->setAttribute('placeholder', _t(self::class . '.EmailPlaceholder', 'you@example.com')),
            CheckboxField::create(
                'Consent',
                _t(self::class . '.Consent', 'Yes, send me the newsletter. I can unsubscribe at any time.')
            ),
            // Honeypot: visually hidden in the include, must stay empty.
            TextField::create((string) static::config()->get('honeypot_field'), 'Leave this field empty')
                ->setAttribute('tabindex', '-1')
                ->setAttribute('autocomplete', 'off')
                ->addExtraClass('newsletter-signup__hp')
        );

        $actions = FieldList::create(
            FormAction::create('doSignup', _t(self::class . '.Subscribe', 'Subscribe'))
        );

        $validator = RequiredFieldsValidator::create('Email', 'Consent');

        $form = Form::create($this, 'SignupForm', $fields, $actions, $validator);
        $this->applySpamProtection($form);
        $this->extend('updateSignupForm', $form);

        return $form;
    }

    public function doSignup(array $data, Form $form): HTTPResponse
    {
        // Honeypot tripped → pretend success without doing anything.
        $honeypot = (string) static::config()->get('honeypot_field');
        if (!empty($data[$honeypot])) {
            $form->sessionMessage($this->thanksMessage(), 'good');

            return $this->redirectBack();
        }

        if (empty($data['Consent'])) {
            $form->sessionMessage(
                _t(self::class . '.ConsentRequired', 'Please tick the box to give your consent.'),
                'bad'
            );

            return $this->redirectBack();
        }

        $member = Security::getCurrentUser();
        $subscription = NewsletterService::create()->subscribe((string) ($data['Email'] ?? ''), [
            'Source' => 'footer',
            'Member' => $member,
            'FirstName' => $member && $member->exists() ? (string) $member->FirstName : '',
            'Surname' => $member && $member->exists() ? (string) $member->Surname : '',
        ]);

        if (!$subscription) {
            $form->sessionMessage(
                _t(self::class . '.InvalidEmail', 'Please enter a valid email address.'),
                'bad'
            );

            return $this->redirectBack();
        }

        $form->sessionMessage(
            $subscription->Status === NewsletterSubscription::STATUS_PENDING
                ? _t(self::class . '.CheckInbox', 'Almost there! Please check your inbox to confirm your subscription.')
                : $this->thanksMessage(),
            'good'
        );

        return $this->redirectBack();
    }

    /**
     * Enable silverstripe/spamprotection when installed + configured; a no-op otherwise, so the built-in
     * honeypot stays the fallback. Copied from silvershop-suite/reviews' guard.
     */
    protected function applySpamProtection(Form $form): void
    {
        if ($form->hasMethod('enableSpamProtection')) {
            try {
                $form->enableSpamProtection();
            } catch (\Exception) {
                // No default spam protector configured — fall back to the honeypot.
            }
        }
    }

    protected function thanksMessage(): string
    {
        return _t(self::class . '.Thanks', 'Thanks for subscribing!');
    }

    protected function renderState(string $state): HTTPResponse
    {
        $html = $this->customise([
            'Title' => _t(self::class . '.Title', 'Newsletter'),
            'ShopName' => NewsletterService::create()->senderName(),
            'State' => $state,
            'IsSignup' => $state === 'signup',
            'IsConfirmed' => $state === 'confirmed',
            'IsUnsubscribed' => $state === 'unsubscribed',
            'IsInvalid' => $state === 'invalid',
            'SignupForm' => $state === 'signup' ? $this->SignupForm() : null,
        ])->renderWith('SilverShop\\Newsletter\\NewsletterPage');

        return HTTPResponse::create((string) $html);
    }

    protected function plainResponse(int $code, string $body): HTTPResponse
    {
        $response = HTTPResponse::create($body, $code);
        $response->addHeader('Content-Type', 'text/plain');

        return $response;
    }
}
