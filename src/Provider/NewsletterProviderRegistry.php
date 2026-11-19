<?php

declare(strict_types=1);

namespace SilverShop\Newsletter\Provider;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

/**
 * Registry of newsletter providers. Providers are declared in config (key => Injector service) so ESP adapters
 * plug in without code changes, and one is selected as active:
 *
 *   SilverShop\Newsletter\Provider\NewsletterProviderRegistry:
 *     active: campaignmonitor
 *     providers:
 *       campaignmonitor: '%$SilverShop\Newsletter\CampaignMonitor\CampaignMonitorProvider'
 *
 * The active provider only takes effect once it is configured; otherwise the module falls back to the local list.
 * This mirrors silvershop-suite/reviews' ReviewProviderRegistry and silvershop-suite/search's BackendRegistry.
 */
class NewsletterProviderRegistry
{
    use Configurable;
    use Injectable;

    /**
     * @var array<string, string> key => Injector service name / class
     */
    private static array $providers = [
        'local' => LocalProvider::class,
    ];

    /**
     * Key (getKey()) of the active provider. Defaults to the local no-op list.
     */
    private static string $active = 'local';

    /**
     * @return array<string, NewsletterProvider> keyed by provider key
     */
    public function all(): array
    {
        $providers = [];
        foreach ((array) self::config()->get('providers') as $service) {
            if (!$service) {
                continue;
            }
            /** @var NewsletterProvider $provider */
            $provider = Injector::inst()->get($service);
            $providers[$provider->getKey()] = $provider;
        }

        return $providers;
    }

    public function get(string $key): ?NewsletterProvider
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * The active, configured provider. Always returns something usable (falls back to the local list), so callers
     * never have to null-check.
     */
    public function active(): NewsletterProvider
    {
        $key = (string) self::config()->get('active');
        $provider = $key ? $this->get($key) : null;

        if ($provider && $provider->isConfigured()) {
            return $provider;
        }

        return $this->get('local') ?? LocalProvider::create();
    }

    /**
     * The active provider only when it is a configured remote ESP (so the core should enqueue sync jobs /
     * accept webhooks); null when the local list is in effect.
     */
    public function activeRemote(): ?NewsletterProvider
    {
        $provider = $this->active();

        return $provider->isRemote() ? $provider : null;
    }
}
