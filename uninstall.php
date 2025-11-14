<?php

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// List of option keys to remove on uninstall
$option_keys = array(
    'fast_currency_select_allowed_currencies',
    'fast_currency_select_debug_enabled',
);

foreach ( $option_keys as $key ) {
    // Remove option from the current site
    delete_option( $key );

    // Remove network-wide option if this was stored as a site option
    if ( function_exists( 'is_multisite' ) && is_multisite() ) {
        delete_site_option( $key );
    }
}

// If you add custom DB tables, postmeta, or transient keys in the future, remove them here.

// Example: deleting transients (if any were used):
// delete_transient( 'fast_currency_select_some_transient' );

