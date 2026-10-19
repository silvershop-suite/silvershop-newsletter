# silvershop-suite/newsletter

Newsletter signup, marketing consent, double opt-in and queued ESP (email-service-provider) synchronisation
for SilverStripe 6 / SilverShop.

Every shop gets a **working local newsletter list out of the box** — a footer/sidebar signup form, a checkout
opt-in checkbox, GDPR-friendly double opt-in, one-click unsubscribe, an explicit timestamped marketing-consent
record, a subscribers admin and report — with **zero ESP configured**. Adding an ESP is purely additive: install
a thin adapter module (e.g. [`silvershop-suite/newsletter-campaignmonitor`](https://github.com/RVXD/silvershop-newsletter-campaignmonitor)),
set it as the active provider, and every subscriber change is pushed to the ESP as a retryable, auditable
background job. The ESP owns the campaigns; this module owns capture, consent and sync.

## Requirements

- `silverstripe/framework` ^6, `silverstripe/cms` ^6, `silverstripe/siteconfig` ^6, `silverstripe/admin` ^3,
  `silverstripe/reports` ^6
- `symbiote/silverstripe-queuedjobs` ^6.2 — **hard requirement**. ESP subscriber sync is the module's named
  purpose: it runs as a retryable `QueuedJob` visible in **QueuedJobAdmin** (next run, history, failures, retries).
- PHP 8.3 / 8.4

`silvershop/core` is a **soft** dependency (suggested, not required). The subscriber model, signup form, consent,
double opt-in, unsubscribe, the admin/report and ESP sync all work on any SilverStripe 6 site. The checkout opt-in
checkbox, the account "Email preferences" section and the order/ecommerce hook points only light up when
`silvershop/core` is installed (attached behind `class_exists()` guards in `_config.php`).

Other soft dependencies, all guarded: `silvershop-suite/store-profile` (sender identity), `silverstripe/spamprotection`
(form protection), `tractorcow/silverstripe-fluent` (per-locale lists).

## Installation

```bash
composer require silvershop-suite/newsletter
```

Then run `dev/build?flush=1`.

> **queuedjobs needs one OS cron entry** to process the queue. Add (as your web user):
> ```
> */1 * * * * /path/to/php /path/to/vendor/symbiote/silverstripe-queuedjobs/cli-script.php dev/tasks/ProcessJobQueueTask
> ```
> After that, all shop scheduling — including newsletter ESP sync — is visible in the CMS under *Jobs*.

## What you get

### The local list (no ESP needed)

- **Signup form** — `$NewsletterSignupForm` is available in any template (via a `TemplateGlobalProvider`), so a
  theme can render it in the footer/sidebar with:
  ```html
  <% include SilverShop/Newsletter/NewsletterSignup %>
  ```
  Override `templates/SilverShop/Newsletter/Includes/NewsletterSignup.ss` in your theme to restyle it.
  The form is protected by `silverstripe/spamprotection` when installed, with a built-in honeypot fallback.
- **Checkout opt-in** — when `silvershop/core` is present, a *"Keep me up to date"* checkbox is added to the
  single-page checkout OrderForm via the composable `updateOrderForm` extend hook (it **stacks** with
  vat-compliance and any other checkout-touching module — no `SinglePageCheckoutComponentConfig` Injector
  override). Default **off** (GDPR). The checkbox is read back on `onPlaceOrder`.
- **Double opt-in** — guests are emailed a tokenised confirm link (`newsletter/confirm/<token>`) and only count as
  subscribed once they click it. Logged-in members are auto-confirmed (configurable).
- **Unsubscribe** — a tokenised one-click link (`newsletter/unsubscribe/<token>`), no login required.
- **Account section** — `account/newsletter` (with core) lets a member toggle their subscription.
- **Subscribers admin** — manage/search/import/export subscribers under *Subscribers* in the CMS.
- **Subscribers report** — a filterable report (status / source) with consent timestamps for GDPR record-keeping.

### Marketing consent is here

`NewsletterSubscription` is the canonical marketing-consent source of truth for the shop — explicit `Consent` flag
+ `ConsentAt` timestamp + `Source` + `Locale`. Sibling modules (abandoned-cart, stock-alerts) can gate their
marketing emails on it:

```php
use SilverShop\Newsletter\Service\NewsletterConsent;

if (!\SilverStripe\Core\Manifest\ModuleLoader::inst()->getManifest()->getModule('silvershop-suite/newsletter')
    || NewsletterConsent::has($email)) {
    // send marketing email
}
```

## ESP providers

The core ships a `NewsletterProvider` interface + a config-driven `NewsletterProviderRegistry` + a default
**local (no-op) provider**. An ESP adapter is a thin module that implements `NewsletterProvider` (subscribe /
update / unsubscribe / syncAll, capability flags, and webhook verify/parse) and registers itself:

```yaml
SilverShop\Newsletter\Provider\NewsletterProviderRegistry:
  active: campaignmonitor
  providers:
    campaignmonitor: '%$SilverShop\Newsletter\CampaignMonitor\CampaignMonitorProvider'
```

With a remote provider active and configured:

- subscribe / confirm / unsubscribe enqueue a **`NewsletterSyncJob`** (retryable, in QueuedJobAdmin);
- `newsletter/webhook/<provider>` receives ESP webhooks (unsubscribe / bounce / cleaned) and maps them back onto
  the local status, so the local list never drifts from the ESP;
- `sake tasks:NewsletterResync --run` performs a full CLI resync of every subscribed record (dry-run without
  `--run`).

> Because this module already does double opt-in, configure the ESP list as **single opt-in** — don't make the ESP
> send its own confirmation email on top of ours.

## Configuration

```yaml
SilverShop\Newsletter\Service\NewsletterService:
  double_opt_in: true          # guests must confirm by email
  member_auto_confirm: true    # logged-in members skip the confirm email
  checkout_enabled: true       # show the checkout opt-in checkbox (needs silvershop/core)
  checkout_default_checked: false
  from_email: ''               # defaults to store-profile / SiteConfig ContactEmail / admin email
```

## Development

```bash
composer install
composer run lint    # phpcs (PSR-12)
composer run stan    # phpstan (cambis/silverstan, level 5)
composer run test    # phpunit
```

The three core-integration extensions (`NewsletterCheckoutExtension`, `NewsletterOrderExtension`,
`NewsletterAccountExtension`) reference `silvershop/core` and are therefore excluded from the standalone phpstan
run (core is a soft dependency, absent from the core-less analysis); they are exercised on a live shop.

## License

BSD-3-Clause.

## Credits

Built and maintained by **Remy Vaartjes** (<remy@xd.nl>) — part of the **SilverShop-Suite**.
