<?php

declare(strict_types=1);

namespace SilverShop\Newsletter\Extension;

use SilverShop\Newsletter\Service\NewsletterService;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;

/**
 * Adds the newsletter opt-in checkbox to the single-page checkout OrderForm via the composable
 * `updateOrderForm` extend hook (core's CheckoutPageController::OrderForm() fires it). This stacks with any
 * other checkout-touching module instead of overriding the SinglePageCheckoutComponentConfig Injector service
 * (which silvershop-suite/vat-compliance already claims) — the plan's "one real checkout design decision".
 *
 * Only attached when silvershop/core is installed (guarded in the module's _config.php), so it is never analysed
 * or loaded on a core-less site. The checkbox is read back by {@link NewsletterOrderExtension::onPlaceOrder()}.
 */
class NewsletterCheckoutExtension extends Extension
{
    public function updateOrderForm($form): void
    {
        if (!(bool) NewsletterService::config()->get('checkout_enabled')) {
            return;
        }
        $fields = $form->Fields();
        if ($fields->dataFieldByName('SubscribeNewsletter')) {
            return;
        }

        $default = (bool) NewsletterService::config()->get('checkout_default_checked');
        $fields->push(
            CheckboxField::create(
                'SubscribeNewsletter',
                _t(
                    self::class . '.OptIn',
                    'Keep me up to date with news and offers by email'
                ),
                $default
            )
        );
    }
}
