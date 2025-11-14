<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Simple logger for Fast Currency Select.
 *
 * Writes to WooCommerce logger if available, otherwise falls back to error_log().
 * Logging is only active when WP_DEBUG is true or when the plugin "Debug logging"
 * option is enabled in the admin settings.
 */
class FCS_Logger {

    const OPTION_KEY = 'fast_currency_select_debug_enabled';

    public static function enabled() {
        // Allow debug automatically if WP_DEBUG is on for developer convenience.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            return true;
        }

        $enabled = get_option( self::OPTION_KEY, false );
        return (bool) $enabled;
    }

    public static function debug( $message, $context = array() ) {
        self::log( 'debug', $message, $context );
    }

    public static function info( $message, $context = array() ) {
        self::log( 'info', $message, $context );
    }

    public static function warning( $message, $context = array() ) {
        self::log( 'warning', $message, $context );
    }

    public static function error( $message, $context = array() ) {
        self::log( 'error', $message, $context );
    }

    protected static function log( $level, $message, $context = array() ) {
        if ( ! self::enabled() ) {
            return;
        }

        // Apply sanitization to avoid leaking user-provided or sensitive values.
        // All arrays/objects and message contexts are filtered before being encoded
        // and written to logs.
        $message = self::sanitize_for_log( $message );
        if ( is_array( $message ) || is_object( $message ) ) {
            $message = wp_json_encode( $message );
        }
        if ( is_array( $context ) && ! empty( $context ) ) {
            $context = self::sanitize_for_log( $context );
            $message .= ' | ' . wp_json_encode( $context );
        }

        $source = defined( 'FCS_LOG_SOURCE' ) ? FCS_LOG_SOURCE : 'fast-currency-select';

        // If WooCommerce logger available, use it to keep logs unified when running WC
        if ( function_exists( 'wc_get_logger' ) ) {
            try {
                $logger = wc_get_logger();
                $logger->log( $level, sprintf( '[%s] %s', $source, $message ), array( 'source' => $source ) );
            } catch ( Exception $e ) {
                // Fall back to error_log if WC logger errors
                error_log( sprintf( 'FCS %s: %s', strtoupper( $level ), $message ) );
            }
            return;
        }

        // Fallback: use error_log so messages end up in server logs or WP_DEBUG_LOG if enabled.
        error_log( sprintf( 'FCS %s: %s', strtoupper( $level ), $message ) );
    }

    /**
     * Sanitize arbitrary data for safe logging: redact known secret keys, mask
     * likely credentials (tokens/nonces/credit-card numbers) and trim large values.
     *
     * @param mixed $data
     * @return mixed Sanitized data ready for json_encoding
     */
    public static function sanitize_for_log( $data ) {
        // If object, convert to array to simplify handling
        if ( is_object( $data ) ) {
            $data = (array) $data;
        }

        // Known sensitive keys that should always be redacted when present.
        $sensitive_keys = array(
            'password', 'pass', 'token', 'auth', 'nonce', 'session', 'secret',
            'card', 'cc', 'card_number', 'credit', 'cvv', 'cvc', 'number', 'ssn',
        );
        /**
         * Filter the list of sensitive keys which should be redacted from logs.
         * Allows integrating plugins to add their own sensitive keys.
         *
         * @param string[] $sensitive_keys
         */
        $sensitive_keys = (array) apply_filters( 'fast_currency_select_logger_sensitive_keys', $sensitive_keys );

        // Short-circuit for scalars
        if ( ! is_array( $data ) ) {
            return self::mask_scalar( $data, null, $sensitive_keys );
        }

        // If indexed list that looks like currency codes (3-letter uppercase alpha) we
        // return the list but limit to the first 50 for safety.
        if ( self::is_indexed_list( $data ) && self::looks_like_currency_codes( $data ) ) {
            $sample = array_slice( $data, 0, 50 );
            $result = array_values( $sample );
            if ( count( $data ) > 50 ) {
                $result[] = sprintf( '...+%d more', count( $data ) - 50 );
            }
            return $result;
        }

        $out = array();
        foreach ( $data as $k => $v ) {
            $lower_key = is_string( $k ) ? strtolower( $k ) : '';
            if ( in_array( $lower_key, $sensitive_keys, true ) ) {
                $out[ $k ] = '***REDACTED***';
                continue;
            }

            if ( is_array( $v ) || is_object( $v ) ) {
                $out[ $k ] = self::sanitize_for_log( $v );
                continue;
            }

            $out[ $k ] = self::mask_scalar( $v, $lower_key, $sensitive_keys );
        }

        return $out;
    }

    /**
     * Determine if array has sequential numeric indexes.
     */
    private static function is_indexed_list( array $arr ) {
        if ( empty( $arr ) ) {
            return true;
        }
        return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
    }

    /**
     * Check whether an indexed list looks like currency codes.
     */
    private static function looks_like_currency_codes( array $arr ) {
        foreach ( $arr as $v ) {
            if ( ! is_string( $v ) || ! preg_match( '/^[A-Z]{3}$/', $v ) ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Mask individual scalar values according to rules.
     */
    private static function mask_scalar( $value, $key = null, $sensitive_keys = array() ) {
        if ( is_string( $value ) ) {
            $trimmed = trim( $value );

            // Numeric-only strings that match card-like lengths should be redacted.
            $digits_only = preg_replace( '/[^0-9]/', '', $trimmed );
            if ( strlen( $digits_only ) >= 13 && strlen( $digits_only ) <= 19 ) {
                return '***REDACTED***';
            }

            // If the key contains 'token'/'nonce' etc, redact.
            if ( $key && preg_match( '/token|nonce|auth|secret|session|pass|cvv|cvc|ssn/i', $key ) ) {
                return '***REDACTED***';
            }

            // Very long strings may contain secrets; truncate to a safe length.
            $max = 128;
            if ( strlen( $trimmed ) > $max ) {
                return substr( $trimmed, 0, 64 ) . '...(' . ( strlen( $trimmed ) - 64 ) . ' bytes redacted)';
            }

            return $trimmed;
        }

        // Non-string scalars (int/float/bool) are generally safe; cast for JSON.
        if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
            return $value;
        }

        // Fallback for other types
        return '***REDACTED***';
    }
}
