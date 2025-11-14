# Fast Currency Select

Fast Currency Select is a small WordPress plugin that adds a quick currency selector for WooCommerce in the admin area.
It lets administrators quickly switch and test store currencies without visiting the main WooCommerce settings page.

## Quick overview

- Adds a small, fast currency selector to the admin toolbar for easy testing.
- Stores a list of allowed currencies in `fast_currency_select_allowed_currencies`.
- Includes an optional debug logger integrating with the WooCommerce logger or the WP debug log.

## Dependencies

- WooCommerce (recommended: 10.3.5 — plugin developed and tested on this WooCommerce release).

### PHP and compatibility

- Requires PHP: 7.4 or later (see `Requires PHP` in `fast-currency-select.php`).
- Plugin development & testing environment: PHP 8.3.27.
- Other PHP versions and other WooCommerce versions may be untested — please open an issue if you see compatibility problems on a different PHP/WooCommerce pair.

If you'd like the plugin to formally require a higher minimum PHP version, update `Requires PHP` in `fast-currency-select.php` and record the change in `CHANGELOG.md`.

This plugin depends on WooCommerce data structures and functions to provide currency lists and conversions.

Note: There is no `Requires at least` (minimum WordPress version) header in this plugin; WordPress plugin headers do not include a `Requires Plugins` field and this plugin instead performs a runtime check and shows an admin notice if WooCommerce is not active.

## Installation

1. Install and activate WooCommerce (if it's not already installed).
2. Install and activate Fast Currency Select (`fast-currency-select`).
3. Visit **Settings → Fast Currency Select** to configure which currencies are available in the selector.

Tip: the plugin performs a runtime check for WooCommerce and will show an admin notice when dependencies are missing.

## Behavior when WooCommerce is not active

- The plugin shows an admin notice requesting WooCommerce be installed/activated.
- Settings remain available under **Settings → Fast Currency Select**, but some behaviors are disabled until WooCommerce is available.

## Usage

- Configure allowed currencies via the plugin settings page. These are stored in the `fast_currency_select_allowed_currencies` option.
- The plugin outputs a compact currency switcher in the admin for easy, on-the-fly testing.

Developer notes:
- If you want the plugin to refuse activation when WooCommerce is missing, add a check in `register_activation_hook()` and call `wp_die()` if dependencies are not met.

## Filters & Hooks

- `fast_currency_select_wc_currencies` — Filter the mapping of WooCommerce currencies passed to the admin script.
- `fast_currency_select_logger_sensitive_keys` — Filter to extend the list of keys that should be redacted from debug logs (used by the logger).

These filters allow the plugin to be customized by other plugins or themes.

## Debug logging

This plugin includes an opt-in debug logging feature for diagnosing issues in production without leaving verbose logging turned on permanently.

- Toggle logging under **Settings → Fast Currency Select: "Enable debug logging"**.
- When enabled, debug entries will go to:
	- the WooCommerce logger if available (WooCommerce → Status → Logs), or
	- the PHP error logs / WP debug log if WooCommerce is not present.
- Debug logging will also be enabled automatically when `WP_DEBUG` is set to `true`.

Security & usage:
- The logger redacts keys like `nonce`, `token`, `password`, `card_number`, and similar.
- The list can be extended with the `fast_currency_select_logger_sensitive_keys` filter.
- Only enable debug logging temporarily in production environments.

## Uninstall / Cleanup

When the plugin is removed via the WordPress admin, `uninstall.php` removes plugin options to keep the site tidy.

- Removes the `fast_currency_select_allowed_currencies` option. When network-activated, it also removes the site option.

If you add transients, custom DB tables, or other persistent storage, extend `uninstall.php` to remove them when the plugin is deleted.

### Test uninstall locally

1. Activate the plugin in a WordPress development instance.
2. Update allowed currencies via **Settings → Fast Currency Select**.
3. From **Plugins**, select *Delete* on the plugin. WordPress runs the uninstall routine.
4. Confirm the option was removed:

```bash
wp option get fast_currency_select_allowed_currencies || echo "Option removed"
```

If the plugin was network-activated, check the site option:

```bash
wp site option get fast_currency_select_allowed_currencies || echo "Network option removed"
```

## Translations (i18n)

Translations live in the `languages/` folder. The plugin ships with a Slovenian translation example:

- `languages/fast-currency-select-sl_SI.po` and the compiled `.mo` file.

Regenerate the `.pot` file with WP-CLI (requires the WP-CLI i18n package):

```bash
wp i18n make-pot . languages/fast-currency-select.pot
```

To test translations locally:
1. Add a .mo file to `languages/` matching your site language.
2. Change Site Language under **Settings → General**.
3. Load plugin screens to confirm translations are shown.

## Contributing

Contributions are welcome — submit issues or PRs on GitHub. Please:

- Open issues for bugs and feature requests.
- Add tests or clear reproduction steps for bugs.
- Keep changes small and focused; include docs for new features.

Formatting & tools:
- Use the WordPress Coding Standards and phpcs locally if possible.
- Add a short description to your PR explaining the problem and how your change resolves it.
## Testing & developer tips

- `fast_currency_select_allowed_currencies` option stores the saved selections.
- Use the filter `fast_currency_select_wc_currencies` to modify the currency list passed to the admin script.
- Read the logger in `includes/class-fcs-logger.php` for debug behavior and extend redaction with the `fast_currency_select_logger_sensitive_keys` filter.

Uninstall test (WP-CLI):

```bash
wp option get fast_currency_select_allowed_currencies || echo "Option removed"
wp site option get fast_currency_select_allowed_currencies || echo "Network option removed"
```

Files of interest:

- `fast-currency-select.php` — main plugin file
- `includes/class-fast-currency-select.php` — core features
- `includes/class-fcs-logger.php` — debug logging

If you add database tables or persistent data, ensure `uninstall.php` is updated.
## License

This repository should include a `LICENSE` file. WordPress plugins are typically compatible with GPL v2 or later.

If you plan to publish on WordPress.org, use a GPL-compatible license.

## Changelog

- See `CHANGELOG.md` (create one) for a history of important changes and releases.

---

Thank you for using Fast Currency Select! File issues and PRs at the repository for anything that could make this plugin more useful for developers and site admins.
