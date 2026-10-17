<?php

declare(strict_types=1);

namespace SilverShop\Newsletter\Admin;

use SilverShop\Newsletter\Model\NewsletterSubscription;
use SilverStripe\Admin\ModelAdmin;

/**
 * CMS admin for the newsletter subscriber list: search, inspect, edit statuses, and import/export subscribers
 * (the Magento-style subscribers grid). The ModelAdmin is also the permission source the subscription's
 * canModerate() checks.
 */
class NewsletterAdmin extends ModelAdmin
{
    private static string $url_segment = 'newsletter-subscribers';

    private static string $menu_title = 'Subscribers';

    private static string $menu_icon_class = 'font-icon-block-users';

    private static int $menu_priority = 0;

    private static array $managed_models = [
        NewsletterSubscription::class,
    ];
}
