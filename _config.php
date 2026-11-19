<?php

/**
 * silvershop/core is a SOFT dependency. The subscriber model, the footer signup form, double-opt-in,
 * unsubscribe, the subscribers admin/report and ESP sync all work on any SilverStripe 6 site. Only the shop
 * integrations — the checkout opt-in checkbox, the account "Email preferences" section and the order email /
 * ecommerce hook points — need silvershop/core, so they are attached here behind class_exists() guards.
 *
 * This mirrors silvershop-suite/vat-compliance's guarded _config.php and keeps the module installable (and testable)
 * without silvershop/core, while lighting up the shop surfaces when it is present.
 */

use SilverShop\Newsletter\Extension\NewsletterAccountExtension;
use SilverShop\Newsletter\Extension\NewsletterCheckoutExtension;
use SilverShop\Newsletter\Extension\NewsletterOrderExtension;

if (class_exists(\SilverShop\Page\CheckoutPageController::class)) {
    \SilverShop\Page\CheckoutPageController::add_extension(NewsletterCheckoutExtension::class);
}

if (class_exists(\SilverShop\Model\Order::class)) {
    \SilverShop\Model\Order::add_extension(NewsletterOrderExtension::class);
}

if (class_exists(\SilverShop\Page\AccountPageController::class)) {
    \SilverShop\Page\AccountPageController::add_extension(NewsletterAccountExtension::class);
}
