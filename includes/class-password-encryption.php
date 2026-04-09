<?php
/**
 * Password Encryption for Registration Forms
 *
 * Encrypts passwords client-side before submission to avoid plaintext transmission.
 * Uses AES-256-GCM encryption with a server-generated key.
 *
 * @package Family_Travel_Tracker
 * @since 2.2.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FTT_Password_Encryption {
    
    const KEY_OPTION = 'ftt_password_encryption_key';
    
    /**
     * Initialize
     */
    public static function init() {
        // AJAX endpoint to get encryption key (nonce-protected)
        add_action('wp_ajax_nopriv_ftt_get_encryption_key', array(__CLASS__, 'ajax_get_encryption_key'));
        add_action('wp_ajax_ftt_get_encryption_key', array(__CLASS__, 'ajax_get_encryption_key'));
        
        // Ensure key exists on init (in case plugin was updated)
        add_action('init', array(__CLASS__, 'ensure_encryption_key'), 1);
    }
    
    /**
     * Ensure encryption key exists
     */
    public static function ensure_encryption_key() {
        $key = get_option(self::KEY_OPTION);
        
        if (empty($key)) {
            // Generate a cryptographically secure random key (32 bytes = 256 bits)
            $key = bin2hex(random_bytes(32));
            update_option(self::KEY_OPTION, $key, false); // Don't autoload
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FTT: Generated new password encryption key');
            }
        }
        
        return $key;
    }
    
    /**
     * Get encryption key (via AJAX, nonce-protected)
     */
    public static function ajax_get_encryption_key() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ftt_password_encryption')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $key = self::ensure_encryption_key();
        
        wp_send_json_success(array(
            'key' => $key
        ));
    }
    
    /**
     * Decrypt password on server side
     *
     * @param string $encrypted_data Base64-encoded encrypted data (iv:ciphertext:tag)
     * @return string|false Decrypted password or false on failure
     */
    public static function decrypt_password($encrypted_data) {
        if (empty($encrypted_data)) {
            error_log('FTT: decrypt_password called with empty data');
            return false;
        }
        
        $key = self::ensure_encryption_key();
        if (empty($key)) {
            error_log('FTT: Encryption key not found or could not be generated');
            return false;
        }
        
        // Decode from base64
        $decoded = base64_decode($encrypted_data);
        if ($decoded === false) {
            error_log('FTT: Failed to base64 decode encrypted password');
            return false;
        }
        
        // Split into iv:ciphertext:tag
        $parts = explode(':', $decoded);
        if (count($parts) !== 3) {
            error_log('FTT: Invalid encrypted password format - expected 3 parts, got ' . count($parts));
            return false;
        }
        
        list($iv_base64, $ciphertext_base64, $tag_base64) = $parts;
        
        // Decode each part from base64
        $iv = base64_decode($iv_base64);
        $ciphertext = base64_decode($ciphertext_base64);
        $tag = base64_decode($tag_base64);
        
        if ($iv === false || $ciphertext === false || $tag === false) {
            error_log('FTT: Failed to decode encryption components');
            return false;
        }
        
        // Convert key from hex to binary
        $key_binary = hex2bin($key);
        if ($key_binary === false) {
            error_log('FTT: Failed to convert hex key to binary');
            return false;
        }
        
        // Decrypt using AES-256-GCM
        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key_binary,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($decrypted === false) {
            error_log('FTT: openssl_decrypt failed - ' . openssl_error_string());
            return false;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FTT: Successfully decrypted password (length: ' . strlen($decrypted) . ')');
        }
        
        return $decrypted;
    }
    
    /**
     * Get encryption nonce for forms
     */
    public static function get_encryption_nonce() {
        return wp_create_nonce('ftt_password_encryption');
    }
}

FTT_Password_Encryption::init();
