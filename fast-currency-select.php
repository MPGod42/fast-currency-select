<?php
/**
 * Plugin Name: Fast Currency Select
 * Plugin URI: https://github.com
 * Description: Quick currency selector for WooCommerce admin
 * Version: 1.0.0
 * Author: MPGod42
 * Author URI: https://github.com/MPGod42
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fast-currency-select
 * Domain Path: /languages
 * Requires PHP: 7.4
 * WC requires at least: 7.1
 * WC tested up to: 10.3.5
 * PHP tested on: 8.3.27
 */

// Define plugin file constant (used for asset URLs in classes)
if ( ! defined( 'FCS_PLUGIN_FILE' ) ) {
    define( 'FCS_PLUGIN_FILE', __FILE__ );
}

// Text domain constant used for all translation calls in the plugin
if ( ! defined( 'FCS_TEXT_DOMAIN' ) ) {
    define( 'FCS_TEXT_DOMAIN', 'fast-currency-select' );
}

// Central plugin slug to use for menu and links
if ( ! defined( 'FCS_PLUGIN_SLUG' ) ) {
    define( 'FCS_PLUGIN_SLUG', 'fast-currency-select' );
}

// Logger source tag (used to identify plugin logs). Kept separate from text domain.
if ( ! defined( 'FCS_LOG_SOURCE' ) ) {
    define( 'FCS_LOG_SOURCE', 'fast-currency-select' );
}

/**
 * Load plugin text domain for translations.
 *
 * This ensures translation files placed in the plugin `languages/` directory
 * or the global WordPress languages directory are loaded for translations.
 */
function fcs_load_textdomain() {
    load_plugin_textdomain( FCS_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'plugins_loaded', 'fcs_load_textdomain' );

// Load classes
require_once plugin_dir_path( __FILE__ ) . 'includes/class-fast-currency-select.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-fcs-logger.php';

// Only load admin specific code on admin pages — this avoids unnecessarily
// loading admin assets and admin-only hooks on the front-end or during
// non-admin requests.
if ( is_admin() ) {
    require_once plugin_dir_path( __FILE__ ) . 'admin/class-fast-currency-select-admin.php';
}

// Instantiate the plugin
$fast_currency_select = new Fast_Currency_Select();
// Only instantiate admin class when in admin context so we don't call admin-only
// code on the front end.
if ( is_admin() ) {
    $fast_currency_select_admin = new Fast_Currency_Select_Admin( $fast_currency_select );
}

// Activation / deactivation hooks (no-op for now)
register_activation_hook( __FILE__, function() {
    // Fail activation if WooCommerce is not active. This avoids a partially
    // configured plugin where critical WC functions are not available.
    if ( ! class_exists( 'WooCommerce' ) && ! function_exists( 'WC' ) ) {
        // If we are in a context where plugins can be deactivated, do so.
        if ( function_exists( 'deactivate_plugins' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
        }

        // Provide a friendly message with next steps for the admin.
        $install_url = admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' );
        $plugins_url = admin_url( 'plugins.php' );

        wp_die( sprintf( /* translators: %1$s: install URL, %2$s: plugins URL */
            __( 'Fast Currency Select requires WooCommerce. Please install and activate WooCommerce first. <a href="%1$s">Install WooCommerce</a> or go to <a href="%2$s">Plugins</a>.', FCS_TEXT_DOMAIN ),
            esc_url( $install_url ),
            esc_url( $plugins_url )
        ) );
    }
    // Initialize defaults safely: if WooCommerce is not active yet, avoid
    // calling WooCommerce helpers directly.
    $default_currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
    $default = $default_currency ? array( $default_currency ) : array();

    if ( false === get_option( 'fast_currency_select_allowed_currencies', false ) ) {
        update_option( 'fast_currency_select_allowed_currencies', $default );
    }
    // Ensure debug option exists (default off)
    if ( false === get_option( 'fast_currency_select_debug_enabled', false ) ) {
        update_option( 'fast_currency_select_debug_enabled', false );
    }
} );

register_deactivation_hook( __FILE__, function() {
    // Placeholder for deactivation tasks
} );
