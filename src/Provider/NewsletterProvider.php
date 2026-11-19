<?php

declare(strict_types=1);

namespace SilverShop\Newsletter\Provider;

use SilverShop\Newsletter\Model\NewsletterSubscription;
use SilverStripe\Control\HTTPRequest;

/**
 * The published seam between the newsletter core and an email-service-provider (ESP) adapter — the newsletter
 * analogue of silvershop-suite/search's {@see \SilverShop\Search\Backend\SearchBackend}. The core owns the subscriber
 * model, forms, consent and double-opt-in; a provider maps a subscriber change onto one ESP's list / contact API,
 * and maps that ESP's webhooks back onto a local {@see NewsletterSubscription} status.
 *
 * Adapters live in their own thin module (e.g. silvershop-suite/newsletter-campaignmonitor) so a shop only pulls the
 * one ESP client it uses. A provider is registered in config against a stable key and selected as the active provider;
 * it only takes effect once {@see isConfigured()} returns true, otherwise the core falls back to its local list.
 */
interface NewsletterProvider
{
    /**
     * Short, stable key used in config + SiteConfig selection + the webhook URL (e.g. 'campaignmonitor').
     */
    public function getKey(): string;

    /**
     * Human-readable name, e.g. 'Campaign Monitor'.
     */
    public function getName(): string;

    /**
     * Whether the provider has the credentials / list id it needs to talk to the ESP.
     */
    public function isConfigured(): bool;

    /**
     * Whether this provider talks to a remote ESP (true) or is the local no-op list (false). The core only
     * enqueues sync jobs / exposes the webhook for remote providers.
     */
    public function isRemote(): bool;

    public function supportsDoubleOptIn(): bool;

    public function supportsEcommerce(): bool;

    public function supportsMergeFields(): bool;

    public function supportsTags(): bool;

    public function supportsSegments(): bool;

    public function supportsWebhooks(): bool;

    /**
     * Add / upsert the subscriber on the ESP. Return true on success.
     */
    public function subscribe(NewsletterSubscription $subscription): bool;

    /**
     * Update an existing subscriber's merge fields / status on the ESP. Return true on success.
     */
    public function update(NewsletterSubscription $subscription): bool;

    /**
     * Unsubscribe the subscriber on the ESP. Return true on success.
     */
    public function unsubscribe(NewsletterSubscription $subscription): bool;

    /**
     * Push every locally-subscribed record to the ESP (full resync). Return the number synced.
     */
    public function syncAll(): int;

    /**
     * Verify an incoming webhook request is genuinely from the ESP (signature / shared secret / source).
     */
    public function verifyWebhook(HTTPRequest $request): bool;

    /**
     * Parse an incoming webhook into normalised events. Each event is
     * ['email' => string, 'status' => NewsletterSubscription status constant]; the core applies them to the
     * local list. Return an empty array when there is nothing to apply.
     *
     * @return array<int, array{email: string, status: string}>
     */
    public function parseWebhook(HTTPRequest $request): array;
}
