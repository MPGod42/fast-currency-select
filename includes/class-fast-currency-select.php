<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Fast_Currency_Select {

    /** Plugin version */
    const VERSION = '1.0.0';

    /**
     * Get the allowed currencies from settings.
     * Defaults to an initial set that mirrors previous hardcoding.
     *
     * @return array
     */
    private function get_allowed_currencies() {
        // Avoid calling WC helpers if WooCommerce is not active — use empty default
        // which will cause the admin to configure allowed currencies after WC is installed.
        $defaults = function_exists( 'get_woocommerce_currency' ) ? array( get_woocommerce_currency() ) : array();

        $saved = get_option( 'fast_currency_select_allowed_currencies', $defaults );

        if ( ! is_array( $saved ) ) {
            return $defaults;
        }

        return array_values( array_unique( array_map( 'strtoupper', $saved ) ) );
    }

    public function __construct() {
        // Declare HPOS compatibility early
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compat' ) );

        // Set currency when new order is created via admin link
        add_action( 'woocommerce_before_order_object_save', array( $this, 'set_currency_on_new_order' ), 10, 1 );
    }

    public function declare_hpos_compat() {
        if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
            // Use the main plugin file constant to indicate HPOS compatibility. __FILE__ here refers to
            // the include file, which would make WooCommerce think the include file itself is the
            // plugin file. We want the main plugin root file to be registered as compatible.
            $plugin_file = defined( 'FCS_PLUGIN_FILE' ) ? FCS_PLUGIN_FILE : __FILE__;
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                $plugin_file,
                true
            );
        }
    }

    public function set_currency_on_new_order( $order ) {

        if ( empty( $_GET['currency'] ) ) {
            FCS_Logger::debug( 'No currency provided in GET; skipping set_currency_on_new_order' );
            return;
        }

        $currency = strtoupper( sanitize_text_field( wp_unslash( $_GET['currency'] ) ) );

        // Require a valid nonce for the 'set currency' action to harden against CSRF.
        // We use wp_verify_nonce instead of check_admin_referer so we can return gracefully
        // during the order save flow if the nonce is missing/invalid.
        if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'fcs_set_currency' ) ) {
            // Do not log raw nonce values. Log only presence to avoid exposing tokens in logs.
            FCS_Logger::warning( 'Invalid or missing nonce for set_currency_on_new_order', array( 'nonce_present' => ! empty( $_GET['_wpnonce'] ) ) );
            return;
        }

        $allowed = $this->get_allowed_currencies();

        if ( empty( $allowed ) || ! in_array( $currency, $allowed, true ) ) {
            FCS_Logger::debug( 'Currency not allowed or empty; skipping', array( 'currency' => $currency, 'allowed' => $allowed ) );
            return;
        }

        if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        if ( $order->get_status() !== 'auto-draft' ) {
            return;
        }

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            return;
        }

        if ( ! function_exists( 'get_current_screen' ) ) {
            return;
        }

        $screen = get_current_screen();
        $valid_screens = array( 'shop_order', 'woocommerce_page_wc-orders' );
        if ( ! $screen || ! in_array( $screen->id, $valid_screens, true ) ) {
            return;
        }

        if ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], array( 'add', 'new' ), true ) ) {
            return;
        }

        $order->set_currency( $currency );
        FCS_Logger::info( 'Order currency set via FCS', array( 'order_id' => $order->get_id(), 'currency' => $currency ) );
    }

}
