<?php

declare(strict_types=1);

namespace SilverShop\Newsletter\Extension;

use SilverShop\Newsletter\Service\NewsletterService;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;

/**
 * Reads the checkout opt-in checkbox back when the order is placed and upserts a subscription for the order's
 * email. Uses the order's `onPlaceOrder` extend hook (core's OrderProcessor fires it mid-placement, while the
 * submitting request — carrying the checkbox POST value — is still the registered HTTPRequest).
 *
 * Only attached when silvershop/core is installed (guarded in the module's _config.php). Guests go through the
 * normal double-opt-in; a logged-in member is auto-confirmed per NewsletterService config.
 *
 * Slice 3 ecommerce hook point: when a remote provider that supportsEcommerce() is active, this is where an
 * order/customer push would be enqueued. No shipped ESP adapter needs it yet, so it is left as the seam.
 */
class NewsletterOrderExtension extends Extension
{
    public function onPlaceOrder(): void
    {
        // Only the live submitting web request carries the opt-in checkbox. Read it from the registered
        // HTTPRequest service rather than Controller::curr(): on CLI / queued-job order placement no request
        // is registered, so we skip cleanly (mirrors Director::currentRequest(), and avoids incidentally
        // constructing an empty singleton). Controller::has_curr() was removed in SilverStripe 6.
        $injector = Injector::inst();
        $request = $injector->has(HTTPRequest::class) ? $injector->get(HTTPRequest::class) : null;
        if (!$request instanceof HTTPRequest || !$request->postVar('SubscribeNewsletter')) {
            return;
        }

        $order = $this->getOwner();
        $email = (string) $order->getLatestEmail();
        if ($email === '') {
            return;
        }

        $member = $order->Member();
        NewsletterService::create()->subscribe($email, [
            'Source' => 'checkout',
            'FirstName' => (string) $order->FirstName,
            'Surname' => (string) $order->Surname,
            'Member' => ($member && $member->exists()) ? $member : null,
        ]);
    }
}
