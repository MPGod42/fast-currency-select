<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Fast_Currency_Select_Admin {

    /** Reference to main plugin instance */
    private $plugin;

    public function __construct( $plugin ) {
        $this->plugin = $plugin;

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        // Handle AJAX saving of allowed currencies to avoid using a Save button
        add_action( 'wp_ajax_fcs_save_allowed_currencies', array( $this, 'ajax_save_allowed_currencies' ) );
        // Debug endpoint to check available currencies
        add_action( 'wp_ajax_fcs_debug_currencies', array( $this, 'ajax_debug_currencies' ) );
        // Add settings link to plugin list
        add_filter( 'plugin_action_links_' . plugin_basename( FCS_PLUGIN_FILE ), array( $this, 'add_plugin_action_links' ) );
    }

    /**
     * Return whether WooCommerce is active and usable.
     *
     * We prefer the class_exists check because it's safe on most contexts. On some
     * multisite setups `is_plugin_active()` can be used, but it isn't available on
     * all admin pages without including plugin.php.
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
    }

    public function enqueue_admin_assets( $hook ) {
        // Only load for admin pages where we might need the dropdown or on the options page
        if ( ! is_admin() ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        // Load assets on WooCommerce Orders admin screen and our options page
        $load_for = array( 'woocommerce_page_wc-orders', 'shop_order', 'toplevel_page_fast-currency-select', 'woocommerce_page_fast-currency-select' );

        if ( in_array( $screen->id, $load_for, true ) ) {

            // CSS
            wp_enqueue_style( 'fast-currency-select-admin', plugin_dir_url( FCS_PLUGIN_FILE ) . 'assets/css/admin.css', array(), Fast_Currency_Select::VERSION );
            wp_enqueue_style( 'dashicons' );

            // Dashicons not used for Add Currency button anymore; do not enqueue.

            // JS
            wp_enqueue_script( 'fast-currency-select-admin', plugin_dir_url( FCS_PLUGIN_FILE ) . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), Fast_Currency_Select::VERSION, true );

            // Localize script: full WooCommerce currencies list
            // Prefer modern WooCommerce API but fall back for older versions. Expose a filter for
            // plugins/themes to modify the full currencies mapping if required.
            // Fetch current WooCommerce currencies (empty array if WC isn't present). We deliberately
            // do not provide a hard-coded fallback here — if no currencies are available the JS
            // will show an empty select and the admin can add currencies from their own source.
            $wc_currencies = function_exists( 'get_woocommerce_currencies' ) ? get_woocommerce_currencies() : array();

            /**
             * Filter the full list of WooCommerce currencies exposed to the admin script.
             *
             * This allows third-party code to add, remove or rename currencies presented in
             * the plugin's dropdowns.
             *
             * @param array $wc_currencies Associative array of currency code => label.
             */
            $wc_currencies = apply_filters( 'fast_currency_select_wc_currencies', $wc_currencies );

            // Decode HTML entities in currency names to prevent double-encoding in JS
            foreach ( $wc_currencies as $code => $label ) {
                $wc_currencies[ $code ] = html_entity_decode( $label, ENT_QUOTES, 'UTF-8' );
            }

                // Use raw translation functions when passing strings to JS so characters are not
            // pre-escaped as HTML entities; JS will insert them using textContent which is safe.
            wp_localize_script( 'fast-currency-select-admin', 'FastCurrencySelectData', array(
                'wc_currencies'    => $wc_currencies,
                'allowed_currencies' => get_option( 'fast_currency_select_allowed_currencies', array( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '' ) ),
                'default_currency' => __( 'Default Currency', FCS_TEXT_DOMAIN ),
                'select_currency'  => __( 'Select currency', FCS_TEXT_DOMAIN ),
                'add_text'         => __( 'Add', FCS_TEXT_DOMAIN ),
                'remove_text'      => __( 'Remove', FCS_TEXT_DOMAIN ),
                'ajax_url'         => admin_url( 'admin-ajax.php' ),
                'save_nonce'       => wp_create_nonce( 'fcs_save_allowed_currencies' ),
                'set_currency_nonce' => wp_create_nonce( 'fcs_set_currency' ),
                'saving_text'      => __( 'Saving…', FCS_TEXT_DOMAIN ),
                'saved_text'       => __( 'Saved', FCS_TEXT_DOMAIN ),
                'debug_enabled'    => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || (bool) get_option( 'fast_currency_select_debug_enabled', false ),
                'debug_nonce'      => wp_create_nonce( 'fcs_debug_currencies' ),
            ) );
        }
    }

    /**
     * Add Settings link to the plugin action links in Plugins list.
     *
     * @param array $links Existing plugin action links.
     * @return array Modified plugin action links with Settings.
     */
    public function add_plugin_action_links( $links ) {
        $settings_url = $this->is_woocommerce_active() ? admin_url( 'admin.php?page=fast-currency-select' ) : admin_url( 'options-general.php?page=fast-currency-select' );
        $settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', FCS_TEXT_DOMAIN ) . '</a>';

        // Add the link to the beginning of the links array
        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * Register plugin settings and sanitization callbacks.
     */
    public function register_settings() {
        register_setting(
            'fast_currency_select_options',
            'fast_currency_select_allowed_currencies',
            array(
                'type' => 'array',
                'sanitize_callback' => array( $this, 'sanitize_allowed_currencies' ),
                'default' => array( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '' ),
            )
        );

        // Debug logging option — disabled by default
        register_setting(
            'fast_currency_select_options',
            'fast_currency_select_debug_enabled',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'boolval',
                'default' => false,
            )
        );
    }

    /**
     * AJAX handler to persist allowed currencies. Expects POST parameter 'currencies' as an array.
     */
    public function ajax_save_allowed_currencies() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'insufficient_permissions', 403 );
        }

        check_ajax_referer( 'fcs_save_allowed_currencies', 'nonce' );

        // Accept either the explicit 'currencies' param (used by our AJAX calls) or
        // the form field name used in the settings UI (`fast_currency_select_allowed_currencies[]`).
        // This makes the handler more robust in case other code submits the form input
        // directly to admin-ajax.php.
        if ( isset( $_POST['currencies'] ) ) {
            $currencies = (array) wp_unslash( $_POST['currencies'] );
        } elseif ( isset( $_POST['fast_currency_select_allowed_currencies'] ) ) {
            $currencies = (array) wp_unslash( $_POST['fast_currency_select_allowed_currencies'] );
        } else {
            $currencies = array();
        }

        // Sanitize input - ensure we uppercase all codes
        $currencies = array_map( 'strtoupper', array_map( 'sanitize_text_field', $currencies ) );

        FCS_Logger::debug( 'Received currencies', $currencies );

        $sanitized = $this->sanitize_allowed_currencies( $currencies );

        if ( empty( $sanitized ) && ! empty( $currencies ) ) {
            // We had input but nothing sanitized — maybe codes do not match WC currencies.
            $available = function_exists( 'get_woocommerce_currencies' ) ? array_keys( get_woocommerce_currencies() ) : array();
            FCS_Logger::debug( 'Sanitized to empty. Available', $available );
            wp_send_json_error( array(
                'message'   => __( 'No valid currencies provided or currencies not recognized by WooCommerce.', FCS_TEXT_DOMAIN ),
                'available' => $available,
                'payload'   => $currencies,
            ), 422 );
        }

        update_option( 'fast_currency_select_allowed_currencies', $sanitized );

        // Return current saved option back to the client for verification
        wp_send_json_success( array( 'saved' => $sanitized ) );
    }

    /**
     * Sanitization callback for allowed currencies setting.
     * Ensures all values are valid currency codes and uppercased.
     *
     * @param mixed $value
     * @return array
     */
    public function sanitize_allowed_currencies( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        // No fallback: only permit currencies provided by WooCommerce.
        // Allow case-insensitive matching against WooCommerce currencies. Some third-
        // party filters may alter key casing and we want to avoid rejecting otherwise
        // valid codes for that reason.
        $available = function_exists( 'get_woocommerce_currencies' ) ? array_keys( get_woocommerce_currencies() ) : array();
        $available_upper = array_map( 'strtoupper', $available );
        $out = array();

        FCS_Logger::debug( 'Sanitize: Input', $value );
        FCS_Logger::debug( 'Sanitize: Available (uppercased)', $available_upper );

        foreach ( $value as $v ) {
            $code = strtoupper( sanitize_text_field( $v ) );
            // Avoid embedding raw user input directly in the message string — pass it via
            // the context so it can be sanitised/masked by the logger.
            FCS_Logger::debug( 'Sanitize: Checking code', array(
                'code'   => $code,
                'result' => ( in_array( $code, $available_upper, true ) ? 'VALID' : 'INVALID' ),
            ) );
            if ( in_array( $code, $available_upper, true ) ) {
                $out[] = $code;
            }
        }

        // Deduplicate and reindex
        $out = array_values( array_unique( $out ) );

        FCS_Logger::debug( 'Sanitize: Output', $out );

        return $out;
    }

    public function add_admin_menu() {
        // Add submenu under WooCommerce; if WooCommerce isn't installed add an
        // options page under Settings so administrators can still configure the
        // plugin and read the dependency notice.
        if ( $this->is_woocommerce_active() ) {
            add_submenu_page(
                'woocommerce',
                __( 'Fast Currency Select', FCS_TEXT_DOMAIN ),
                __( 'Fast Currency Select', FCS_TEXT_DOMAIN ),
                'manage_woocommerce',
                FCS_PLUGIN_SLUG,
                array( $this, 'render_options_page' )
            );
        } else {
            // Provide a settings page under the 'Settings' menu for visibility.
            add_options_page(
                __( 'Fast Currency Select', FCS_TEXT_DOMAIN ),
                __( 'Fast Currency Select', FCS_TEXT_DOMAIN ),
                'manage_options',
                FCS_PLUGIN_SLUG,
                array( $this, 'render_options_page' )
            );
        }
    }

    public function render_options_page() {
        // Blank options page for now
        if ( $this->is_woocommerce_active() ) {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', FCS_TEXT_DOMAIN ) );
            }
        } else {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', FCS_TEXT_DOMAIN ) );
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Fast Currency Select', FCS_TEXT_DOMAIN ); ?> <a href="#" class="page-title-action fcs-add-currency" data-position="top"><?php esc_html_e( 'Add Currency', FCS_TEXT_DOMAIN ); ?></a></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'fast_currency_select_options' );
                do_settings_sections( 'fast_currency_select' );

                // Build currencies list: prefer WooCommerce API if available; do not provide a
                // hard-coded fallback so that the admin UI reflects what WooCommerce exposes.
                    $wc_currencies = function_exists( 'get_woocommerce_currencies' ) ? get_woocommerce_currencies() : array();

                // Decode HTML entities in currency names for display
                foreach ( $wc_currencies as $code => $label ) {
                    $wc_currencies[ $code ] = html_entity_decode( $label, ENT_QUOTES, 'UTF-8' );
                }

                $selected = get_option( 'fast_currency_select_allowed_currencies', array( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '' ) );

                // Build a WP List Table style listing resembling the Orders list.
                echo '<div class="fcs-currencies-wrap">';

                echo '<table class="wp-list-table widefat fixed striped fcs-currency-list">';
                // Rename 'Code' column to 'Currency' per UX request
                echo '<thead><tr><th class="manage-column column-enable" style="width:80px;">' . esc_html__( 'Enabled', FCS_TEXT_DOMAIN ) . '</th><th class="manage-column column-code">' . esc_html__( 'Currency', FCS_TEXT_DOMAIN ) . '</th><th class="manage-column column-name">' . esc_html__( 'Name', FCS_TEXT_DOMAIN ) . '</th><th class="manage-column column-actions">' . esc_html__( 'Actions', FCS_TEXT_DOMAIN ) . '</th><th class="manage-column column-order" style="width:40px;">' . esc_html__( 'Order', FCS_TEXT_DOMAIN ) . '</th></tr></thead>';
                echo '<tbody>';

                // (No inline top action row — single Add button is shown next to title.)

                // Show selected currencies first
                foreach ( $selected as $code ) {
                    if ( ! isset( $wc_currencies[ $code ] ) ) {
                        // May be a custom codes left; show them anyway
                        $wc_currencies[ $code ] = $code;
                    }
                    printf( '<tr data-code="%s"><td><label><input type="checkbox" name="fast_currency_select_allowed_currencies[]" value="%s" checked /></label></td><td class="column-code">%s</td><td class="column-name">%s</td><td class="column-actions"><a href="#" class="fcs-remove-currency" style="color:#a00;">' . esc_html__( 'Remove', FCS_TEXT_DOMAIN ) . '</a></td><td class="column-order"><span class="fcs-drag-handle dashicons dashicons-menu"></span></td></tr>', esc_attr( $code ), esc_attr( $code ), esc_html( $code ), esc_html( $wc_currencies[ $code ] ) );
                }

                echo '</tbody>';
                echo '</table>';

                // (No bottom action row — single Add button is shown next to title.)
                echo '</div>'; // .fcs-currencies-wrap

                // No more Save Changes; changes are saved immediately via AJAX.
                // Debug log option
                $debug_enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || (bool) get_option( 'fast_currency_select_debug_enabled', false );
                echo '<h2>' . esc_html__( 'Diagnostic', FCS_TEXT_DOMAIN ) . '</h2>';
                echo '<p>' . esc_html__( 'When enabled, the plugin writes diagnostic messages to the WooCommerce logger (if available) or to the PHP error log. This should only be used temporarily in production.', FCS_TEXT_DOMAIN ) . '</p>';
                printf( '<label><input type="checkbox" name="fast_currency_select_debug_enabled" value="1" %s/> %s</label>', checked( true, $debug_enabled, false ), esc_html__( 'Enable debug logging', FCS_TEXT_DOMAIN ) );
                submit_button( __( 'Save Settings', FCS_TEXT_DOMAIN ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX handler to return debug info about available currencies
     */
    public function ajax_debug_currencies() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'insufficient_permissions', 403 );
        }

        // Protect the debug endpoint from CSRF by requiring the 'fcs_debug_currencies' nonce.
        check_ajax_referer( 'fcs_debug_currencies', 'nonce' );

        $available = function_exists( 'get_woocommerce_currencies' ) ? get_woocommerce_currencies() : array();
        
        wp_send_json_success( array(
            'wc_currencies' => $available,
            'wc_currency_codes' => array_keys( $available ),
            'wc_currency_codes_upper' => array_map( 'strtoupper', array_keys( $available ) ),
            'saved_currencies' => get_option( 'fast_currency_select_allowed_currencies', array() ),
        ) );
    }

    /**
     * Show an admin notice when WooCommerce isn't active.
     */
    public function woocommerce_missing_notice() {
        if ( $this->is_woocommerce_active() ) {
            return;
        }

        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        $install_url = admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' );
        $activate_url = admin_url( 'plugins.php' );

        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . esc_html__( 'Fast Currency Select requires WooCommerce to function.', FCS_TEXT_DOMAIN ) . '</strong><br/>';
        echo wp_kses_post( esc_html__( 'Please install and activate WooCommerce to use the plugin. ', FCS_TEXT_DOMAIN ) );
        echo '<a href="' . esc_url( $install_url ) . '">' . esc_html__( 'Install WooCommerce', FCS_TEXT_DOMAIN ) . '</a> | <a href="' . esc_url( $activate_url ) . '">' . esc_html__( 'Manage plugins', FCS_TEXT_DOMAIN ) . '</a>';
        echo '</p></div>';
    }

}
