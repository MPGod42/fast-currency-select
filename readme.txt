=== Fast Currency Select ===
Contributors: MPGod42
Donate link: 
Tags: woocommerce, currency, switcher, admin, debug, logger, testing
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast Currency Select adds a tiny, fast currency selector to the WordPress admin so you can instantly switch the store currency for testing and debugging WooCommerce stores.

== Description ==

Fast Currency Select is a compact admin tool for WooCommerce store administrators and developers who need to switch site currencies quickly without navigating to the full WooCommerce settings page. It provides a quick selector in the admin toolbar, lets you control which currencies are available through plugin settings, and includes an optional debug logger that integrates with the WooCommerce logger or the WP debug log.

Features:
- Quick currency selector accessible in the admin area
- Stores a list of allowed currencies in the `fast_currency_select_allowed_currencies` option
- Optional debug logger with automatic redaction of sensitive keys
- Filter hooks to customize behavior (`fast_currency_select_wc_currencies`, `fast_currency_select_logger_sensitive_keys`)

== Installation ==
1. Make sure WooCommerce is installed and active.
2. Upload the `fast-currency-select` directory to the `/wp-content/plugins/` directory, or install via Git.
3. Activate the plugin through the ‘Plugins’ menu in WordPress.
4. Configure allowed currencies under **Settings → Fast Currency Select**.

Note: The plugin will show an admin notice if WooCommerce is not active; some features are disabled until WooCommerce is present.

== Screenshots ==
1. Admin toolbar currency selector (compact switcher)
2. Settings page showing allowed currencies and debug logging option

== Frequently Asked Questions ==

= Which currencies are listed in the selector? =
The available currencies come from WooCommerce but are filtered using the `fast_currency_select_wc_currencies` filter and reduced to the list saved in the plugin settings option `fast_currency_select_allowed_currencies`.

= Where does debug logging go? =
If WooCommerce is available, logs go to the WooCommerce logger (found under WooCommerce → Status → Logs). When WooCommerce is not present, logs default to the WP debug log / PHP error logs.

= How do I add more redacted keys for logging? =
Use the `fast_currency_select_logger_sensitive_keys` filter to extend the list of sensitive keys (e.g., `nonce`, `token`, `password`, `card_number`).

= What happens on uninstall? =
The uninstall routine removes `fast_currency_select_allowed_currencies` (also removes the site option when network-activated). Check `uninstall.php` for more details.

== Changelog ==

= 1.0.0 =
* Initial release — compact admin currency selector and settings page, optional logger

== Upgrade Notice ==

= 1.0.0 =
First release, no upgrade actions required.

== Additional Notes ==
- Developer / QA: This plugin was developed and tested with PHP 8.3.27 and WooCommerce 10.3.5 (recommended). It requires PHP 7.4 or later.
- If you want the plugin to refuse activation when WooCommerce is missing, add the check in `register_activation_hook()` and call `wp_die()` during activation.
- For contributions and bug reports, open issues or pull requests on the repository. Please include reproduction steps and, if possible, tests for new behavior.
