<?php
/**
 * Stripe Settings & Admin UI
 *
 * Provides WordPress admin interface for configuring Stripe integration,
 * including API keys, price IDs, trial settings, and connection testing.
 *
 * @package FamilyTravelTracker
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTT_Stripe_Settings {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_ftt_test_stripe_connection', [__CLASS__, 'ajax_test_connection']);
        add_action('wp_ajax_ftt_fetch_stripe_prices', [__CLASS__, 'ajax_fetch_prices']);
    }
    
    /**
     * Add settings page to admin menu
     */
    public static function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            __('Billing Settings', 'schedule-collaboration-tracking'),
            __('Billing', 'schedule-collaboration-tracking'),
            'manage_options',
            'ftt-billing-settings',
            [__CLASS__, 'render_settings_page']
        );
    }
    
    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting(
            'ftt_stripe_settings_group',
            'ftt_stripe_settings',
            [
                'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
            ]
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public static function enqueue_admin_scripts($hook) {
        // Load on the stand-alone billing settings page AND when embedded as a
        // tab inside the combined FTT Settings page.
        $is_standalone  = $hook === 'ftt_event_page_ftt-billing-settings';
        $is_embedded    = $hook === 'ftt_event_page_ftt-settings'
                          && isset( $_GET['tab'] )
                          && sanitize_key( $_GET['tab'] ) === 'billing-settings';

        if ( ! $is_standalone && ! $is_embedded ) {
            return;
        }
        
        wp_enqueue_style('ftt-billing-admin', FTT_PLUGIN_URL . 'assets/css/billing-admin.css', [], FTT_VERSION);
        wp_enqueue_script('ftt-billing-admin', FTT_PLUGIN_URL . 'assets/js/billing-admin.js', ['jquery'], FTT_VERSION, true);
        
        wp_localize_script('ftt-billing-admin', 'fttBilling', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ftt_billing_admin'),
        ]);
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $settings = get_option('ftt_stripe_settings', []);
        $mode = $settings['mode'] ?? 'test';
        
        // Check if Stripe library is installed
        $stripe_installed = file_exists(FTT_PLUGIN_DIR . 'lib/stripe-php/init.php');
        
        ?>
        <div class="wrap ftt-billing-settings">
            <h1><?php esc_html_e('Billing Settings', 'schedule-collaboration-tracking'); ?></h1>
            
            <?php settings_errors('ftt_stripe_settings'); ?>
            
            <?php if (!$stripe_installed) : ?>
                <div class="notice notice-warning">
                    <h3><?php esc_html_e('Stripe PHP Library Required', 'schedule-collaboration-tracking'); ?></h3>
                    <p>
                        <?php esc_html_e('The Stripe PHP library is not installed. You can configure your API keys here, but billing features will not work until the library is installed.', 'schedule-collaboration-tracking'); ?>
                    </p>
                    <p><strong><?php esc_html_e('Installation Options:', 'schedule-collaboration-tracking'); ?></strong></p>
                    <ol>
                        <li>
                            <strong><?php esc_html_e('Via Composer (Recommended):', 'schedule-collaboration-tracking'); ?></strong>
                            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">cd <?php echo esc_html(FTT_PLUGIN_DIR); ?>
composer require stripe/stripe-php</pre>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Manual Installation:', 'schedule-collaboration-tracking'); ?></strong>
                            <p>
                                <?php printf(
                                    esc_html__('Download from %s and extract to %s', 'schedule-collaboration-tracking'),
                                    '<a href="https://github.com/stripe/stripe-php/releases" target="_blank">GitHub</a>',
                                    '<code>' . esc_html(FTT_PLUGIN_DIR) . 'lib/stripe-php/</code>'
                                ); ?>
                            </p>
                        </li>
                    </ol>
                </div>
            <?php endif; ?>
            
            <div class="ftt-settings-header">
                <div class="ftt-mode-indicator ftt-mode-<?php echo esc_attr($mode); ?>">
                    <?php if ($mode === 'live') : ?>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('LIVE MODE', 'schedule-collaboration-tracking'); ?>
                    <?php else : ?>
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php esc_html_e('TEST MODE', 'schedule-collaboration-tracking'); ?>
                    <?php endif; ?>
                </div>
                
                <p class="description">
                    <?php esc_html_e('Configure Stripe integration for subscription billing. Use test mode during development.', 'schedule-collaboration-tracking'); ?>
                </p>
            </div>
            
            <form method="post" action="options.php" id="ftt-billing-form">
                <?php settings_fields('ftt_stripe_settings_group'); ?>
                
                <div class="ftt-settings-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#configuration" class="nav-tab nav-tab-active"><?php esc_html_e('Configuration', 'schedule-collaboration-tracking'); ?></a>
                        <a href="#prices" class="nav-tab"><?php esc_html_e('Pricing', 'schedule-collaboration-tracking'); ?></a>
                        <a href="#webhooks" class="nav-tab"><?php esc_html_e('Webhooks', 'schedule-collaboration-tracking'); ?></a>
                        <a href="#advanced" class="nav-tab"><?php esc_html_e('Advanced', 'schedule-collaboration-tracking'); ?></a>
                    </nav>
                    
                    <!-- Tab 1: Configuration -->
                    <div id="configuration" class="tab-content active">
                        <?php self::render_configuration_tab($settings); ?>
                    </div>
                    
                    <!-- Tab 2: Pricing -->
                    <div id="prices" class="tab-content">
                        <?php self::render_pricing_tab($settings); ?>
                    </div>
                    
                    <!-- Tab 3: Webhooks -->
                    <div id="webhooks" class="tab-content">
                        <?php self::render_webhooks_tab($settings); ?>
                    </div>
                    
                    <!-- Tab 4: Advanced -->
                    <div id="advanced" class="tab-content">
                        <?php self::render_advanced_tab($settings); ?>
                    </div>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render configuration tab
     */
    private static function render_configuration_tab($settings) {
        $mode = $settings['mode'] ?? 'test';
        $test_pk = $settings['test_publishable_key'] ?? '';
        $test_sk = $settings['test_secret_key'] ?? '';
        $live_pk = $settings['live_publishable_key'] ?? '';
        $live_sk = $settings['live_secret_key'] ?? '';
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ftt_mode"><?php esc_html_e('Operating Mode', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
                    <select name="ftt_stripe_settings[mode]" id="ftt_mode" class="regular-text">
                        <option value="test" <?php selected($mode, 'test'); ?>><?php esc_html_e('Test Mode', 'schedule-collaboration-tracking'); ?></option>
                        <option value="live" <?php selected($mode, 'live'); ?>><?php esc_html_e('Live Mode', 'schedule-collaboration-tracking'); ?></option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Use test mode for development. Switch to live mode only when ready for production.', 'schedule-collaboration-tracking'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e('Test Mode API Keys', 'schedule-collaboration-tracking'); ?></h2>
        <p class="description">
            <?php printf(
                esc_html__('Get your test API keys from %s', 'schedule-collaboration-tracking'),
                '<a href="https://dashboard.stripe.com/test/apikeys" target="_blank">Stripe Dashboard (Test Mode)</a>'
            ); ?>
        </p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ftt_test_pk"><?php esc_html_e('Test Publishable Key', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
                    <input type="text" name="ftt_stripe_settings[test_publishable_key]" id="ftt_test_pk" value="<?php echo esc_attr($test_pk); ?>" class="regular-text code" placeholder="pk_test_...">
                    <p class="description"><?php esc_html_e('Starts with pk_test_', 'schedule-collaboration-tracking'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ftt_test_sk"><?php esc_html_e('Test Secret Key', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
                    <input type="password" name="ftt_stripe_settings[test_secret_key]" id="ftt_test_sk" value="<?php echo esc_attr($test_sk); ?>" class="regular-text code" placeholder="sk_test_...">
                    <p class="description"><?php esc_html_e('Starts with sk_test_ - Keep this secret!', 'schedule-collaboration-tracking'); ?></p>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e('Live Mode API Keys', 'schedule-collaboration-tracking'); ?></h2>
        <p class="description">
            <?php printf(
                esc_html__('Get your live API keys from %s', 'schedule-collaboration-tracking'),
                '<a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard (Live Mode)</a>'
            ); ?>
        </p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ftt_live_pk"><?php esc_html_e('Live Publishable Key', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
                    <input type="text" name="ftt_stripe_settings[live_publishable_key]" id="ftt_live_pk" value="<?php echo esc_attr($live_pk); ?>" class="regular-text code" placeholder="pk_live_...">
                    <p class="description"><?php esc_html_e('Starts with pk_live_', 'schedule-collaboration-tracking'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ftt_live_sk"><?php esc_html_e('Live Secret Key', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
                    <input type="password" name="ftt_stripe_settings[live_secret_key]" id="ftt_live_sk" value="<?php echo esc_attr($live_sk); ?>" class="regular-text code" placeholder="sk_live_...">
                    <p class="description"><?php esc_html_e('Starts with sk_live_ - Keep this secret!', 'schedule-collaboration-tracking'); ?></p>
                </td>
            </tr>
        </table>
        
        <div class="ftt-test-connection">
            <button type="button" id="ftt-test-stripe-btn" class="button button-secondary">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php esc_html_e('Test Connection', 'schedule-collaboration-tracking'); ?>
            </button>
            <span class="spinner"></span>
            <div id="ftt-connection-result"></div>
        </div>
        <?php
    }
    
    /**
     * Render pricing tab
     */
    private static function render_pricing_tab($settings) {
        $price_base_monthly = $settings['price_base_monthly'] ?? '';
        $price_base_yearly = $settings['price_base_yearly'] ?? '';
        $price_addon_monthly = $settings['price_addon_monthly'] ?? '';
        $price_addon_yearly = $settings['price_addon_yearly'] ?? '';
        
        ?>
        <div class="ftt-pricing-help">
            <h3><?php esc_html_e('How to Get Price IDs', 'schedule-collaboration-tracking'); ?></h3>
            <ol>
                <li><?php esc_html_e('Go to Stripe Dashboard → Products', 'schedule-collaboration-tracking'); ?></li>
                <li><?php esc_html_e('Create these products:', 'schedule-collaboration-tracking'); ?>
                    <ul>
                        <li><strong><?php esc_html_e('Base Subscription', 'schedule-collaboration-tracking'); ?></strong>: <?php esc_html_e('$5.99/month and $59.90/year (includes 1 child)', 'schedule-collaboration-tracking'); ?></li>
                        <li><strong><?php esc_html_e('Additional Child', 'schedule-collaboration-tracking'); ?></strong>: <?php esc_html_e('$2.00/month and $20/year', 'schedule-collaboration-tracking'); ?></li>
                    </ul>
                </li>
                <li><?php esc_html_e('Click on each price to see the Price ID (starts with "price_")', 'schedule-collaboration-tracking'); ?></li>
                <li><?php esc_html_e('Copy the Price IDs into the fields below', 'schedule-collaboration-tracking'); ?></li>
            </ol>
            <p>
                <a href="https://dashboard.stripe.com/products" target="_blank" class="button button-secondary">
                    <?php esc_html_e('Open Stripe Products', 'schedule-collaboration-tracking'); ?>
                </a>
                <button type="button" id="ftt-fetch-prices-btn" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Auto-Fetch Prices', 'schedule-collaboration-tracking'); ?>
                </button>
            </p>
        </div>
        
        <table class="form-table">
            <tr>
                <th colspan="2"><h3><?php esc_html_e('Base Subscription Price IDs', 'schedule-collaboration-tracking'); ?></h3></th>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ftt_price_base_monthly"><?php esc_html_e('Monthly ($5.99/mo)', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
                    <input type="text" name="ftt_stripe_settings[price_base_monthly]" id="ftt_price_base_monthly" value="<?php echo esc_attr($price_base_monthly); ?>" class="regular-text code" placeholder="price_...">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ftt_price_base_yearly"><?php esc_html_e('Yearly ($59.90/yr)', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
                    <input type="text" name="ftt_stripe_settings[price_base_yearly]" id="ftt_price_base_yearly" value="<?php echo esc_attr($price_base_yearly); ?>" class="regular-text code" placeholder="price_...">
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h3><?php esc_html_e('Additional Child Price IDs', 'schedule-collaboration-tracking'); ?></h3></th>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ftt_price_addon_monthly"><?php esc_html_e('Monthly ($2/mo)', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
                    <input type="text" name="ftt_stripe_settings[price_addon_monthly]" id="ftt_price_addon_monthly" value="<?php echo esc_attr($price_addon_monthly); ?>" class="regular-text code" placeholder="price_...">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ftt_price_addon_yearly"><?php esc_html_e('Yearly ($20/yr)', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
                    <input type="text" name="ftt_stripe_settings[price_addon_yearly]" id="ftt_price_addon_yearly" value="<?php echo esc_attr($price_addon_yearly); ?>" class="regular-text code" placeholder="price_...">
                </td>
            </tr>
        </table>
        
        <div id="ftt-prices-result"></div>
        <?php
    }
    
    /**
     * Render webhooks tab
     */
    private static function render_webhooks_tab($settings) {
        $webhook_secret = $settings['webhook_secret'] ?? '';
        $webhook_url = rest_url('ftt/v1/stripe-webhook');
        
        ?>
        <div class="ftt-webhook-setup">
            <h3><?php esc_html_e('Webhook Configuration', 'schedule-collaboration-tracking'); ?></h3>
            <p><?php esc_html_e('Webhooks keep your WordPress site in sync with Stripe events (payments, cancellations, etc.).', 'schedule-collaboration-tracking'); ?></p>
            
            <div class="ftt-webhook-url">
                <label><strong><?php esc_html_e('Your Webhook URL:', 'schedule-collaboration-tracking'); ?></strong></label>
                <div class="ftt-url-display">
                    <code id="ftt-webhook-url-text"><?php echo esc_html($webhook_url); ?></code>
                    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('ftt-webhook-url-text').textContent)">
                        <?php esc_html_e('Copy', 'schedule-collaboration-tracking'); ?>
                    </button>
                </div>
            </div>
            
            <h4><?php esc_html_e('Setup Steps:', 'schedule-collaboration-tracking'); ?></h4>
            <ol>
                <li><?php printf(
                    esc_html__('Go to %s', 'schedule-collaboration-tracking'),
                    '<a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Dashboard → Webhooks</a>'
                ); ?></li>
                <li><?php esc_html_e('Click "Add endpoint"', 'schedule-collaboration-tracking'); ?></li>
                <li><?php esc_html_e('Paste the webhook URL above', 'schedule-collaboration-tracking'); ?></li>
                <li><?php esc_html_e('Select these events:', 'schedule-collaboration-tracking'); ?>
                    <ul>
                        <li><code>checkout.session.completed</code></li>
                        <li><code>customer.subscription.created</code></li>
                        <li><code>customer.subscription.updated</code></li>
                        <li><code>customer.subscription.deleted</code></li>
                        <li><code>customer.subscription.trial_will_end</code></li>
                        <li><code>invoice.payment_succeeded</code></li>
                        <li><code>invoice.payment_failed</code></li>
                    </ul>
                </li>
                <li><?php esc_html_e('Copy the "Signing secret" and paste it below', 'schedule-collaboration-tracking'); ?></li>
            </ol>
        </div>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ftt_webhook_secret"><?php esc_html_e('Webhook Signing Secret', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
                    <input type="password" name="ftt_stripe_settings[webhook_secret]" id="ftt_webhook_secret" value="<?php echo esc_attr($webhook_secret); ?>" class="regular-text code" placeholder="whsec_...">
                    <p class="description"><?php esc_html_e('Starts with whsec_ - Verifies webhook authenticity', 'schedule-collaboration-tracking'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render advanced tab
     */
    private static function render_advanced_tab($settings) {
        $trial_days = $settings['trial_days'] ?? 14;
        $grace_period_days = $settings['grace_period_days'] ?? 7;
        $max_parents_per_child = $settings['max_parents_per_child'] ?? 4;
        $app_domain = $settings['app_domain'] ?? home_url();
        
        ?>
        <h2><?php esc_html_e('Domain Configuration', 'schedule-collaboration-tracking'); ?></h2>
        <p class="description">
            <?php esc_html_e('Configure domain settings for dual-domain setup (www vs my subdomain).', 'schedule-collaboration-tracking'); ?>
        </p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ftt_app_domain"><?php esc_html_e('App Domain', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
                    <input type="url" name="ftt_stripe_settings[app_domain]" id="ftt_app_domain" value="<?php echo esc_attr($app_domain); ?>" class="regular-text" placeholder="https://my.familytraveltracker.app">
                    <p class="description">
                        <?php esc_html_e('The domain where the app is hosted (e.g., https://my.familytraveltracker.app). This is where users will be redirected after checkout.', 'schedule-collaboration-tracking'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e('Trial & Access Settings', 'schedule-collaboration-tracking'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ftt_trial_days"><?php esc_html_e('Free Trial Period (days)', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
              <input type="number" name="ftt_stripe_settings[trial_days]" id="ftt_trial_days" value="<?php echo esc_attr($trial_days); ?>" min="0" max="90" class="small-text">
                    <p class="description"><?php esc_html_e('Number of days users can try the service free. Default: 14 days', 'schedule-collaboration-tracking'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ftt_grace_period_days"><?php esc_html_e('Payment Failure Grace Period (days)', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
                    <input type="number" name="ftt_stripe_settings[grace_period_days]" id="ftt_grace_period_days" value="<?php echo esc_attr($grace_period_days); ?>" min="1" max="30" class="small-text">
                    <p class="description"><?php esc_html_e('Days to allow access after payment failure. Default: 7 days', 'schedule-collaboration-tracking'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ftt_max_parents"><?php esc_html_e('Max Parents per Child', 'schedule-collaboration-tracking'); ?></label>
                </th>
                <td>
                    <input type="number" name="ftt_stripe_settings[max_parents_per_child]" id="ftt_max_parents" value="<?php echo esc_attr($max_parents_per_child); ?>" min="1" max="10" class="small-text">
                    <p class="description"><?php esc_html_e('Maximum number of parent accounts that can link to one child. Default: 4', 'schedule-collaboration-tracking'); ?></p>
                </td>
            </tr>
        </table>
        
        <h3><?php esc_html_e('Child Color Palette', 'schedule-collaboration-tracking'); ?></h3>
        <p class="description"><?php esc_html_e('Colors used for differentiating children on calendars. Auto-assigned when children are added.', 'schedule-collaboration-tracking'); ?></p>
        
        <?php FTT_Child_Colors::render_settings_section(); ?>
        <?php
    }
    
    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        $sanitized = [];
        
        // Mode
        $sanitized['mode'] = in_array($input['mode'] ?? '', ['test', 'live']) ? $input['mode'] : 'test';
        
        // API Keys
        $sanitized['test_publishable_key'] = sanitize_text_field($input['test_publishable_key'] ?? '');
        $sanitized['test_secret_key'] = sanitize_text_field($input['test_secret_key'] ?? '');
        $sanitized['live_publishable_key'] = sanitize_text_field($input['live_publishable_key'] ?? '');
        $sanitized['live_secret_key'] = sanitize_text_field($input['live_secret_key'] ?? '');
        $sanitized['webhook_secret'] = sanitize_text_field($input['webhook_secret'] ?? '');
        
        // Price IDs
        $sanitized['price_base_monthly'] = sanitize_text_field($input['price_base_monthly'] ?? '');
        $sanitized['price_base_yearly'] = sanitize_text_field($input['price_base_yearly'] ?? '');
        $sanitized['price_addon_monthly'] = sanitize_text_field($input['price_addon_monthly'] ?? '');
        $sanitized['price_addon_yearly'] = sanitize_text_field($input['price_addon_yearly'] ?? '');
        
        // Advanced
        $sanitized['trial_days'] = absint($input['trial_days'] ?? 14);
        $sanitized['grace_period_days'] = absint($input['grace_period_days'] ?? 7);
        $sanitized['max_parents_per_child'] = absint($input['max_parents_per_child'] ?? 4);
        $sanitized['app_domain'] = esc_url_raw($input['app_domain'] ?? home_url());
        
        // Validate API keys format
        if (!empty($sanitized['test_publishable_key']) && !str_starts_with($sanitized['test_publishable_key'], 'pk_test_')) {
            add_settings_error('ftt_stripe_settings', 'invalid_test_pk', __('Test Publishable Key should start with pk_test_', 'schedule-collaboration-tracking'));
        }
        
        if (!empty($sanitized['test_secret_key']) && !str_starts_with($sanitized['test_secret_key'], 'sk_test_')) {
            add_settings_error('ftt_stripe_settings', 'invalid_test_sk', __('Test Secret Key should start with sk_test_', 'schedule-collaboration-tracking'));
        }
        
        if (!empty($sanitized['live_publishable_key']) && !str_starts_with($sanitized['live_publishable_key'], 'pk_live_')) {
            add_settings_error('ftt_stripe_settings', 'invalid_live_pk', __('Live Publishable Key should start with pk_live_', 'schedule-collaboration-tracking'));
        }
        
        if (!empty($sanitized['live_secret_key']) && !str_starts_with($sanitized['live_secret_key'], 'sk_live_')) {
            add_settings_error('ftt_stripe_settings', 'invalid_live_sk', __('Live Secret Key should start with sk_live_', 'schedule-collaboration-tracking'));
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX: Test Stripe connection
     */
    public static function ajax_test_connection() {
        check_ajax_referer('ftt_billing_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        // Check if Stripe library is installed
        if (!file_exists(FTT_PLUGIN_DIR . 'lib/stripe-php/init.php')) {
            wp_send_json_error(['message' => 'Stripe PHP library not installed. Please install it first.']);
        }
        
        require_once FTT_PLUGIN_DIR . 'lib/stripe-php/init.php';
        
        $settings = get_option('ftt_stripe_settings', []);
        $mode = $settings['mode'] ?? 'test';
        
        $secret_key = $mode === 'live' 
            ? ($settings['live_secret_key'] ?? '')
            : ($settings['test_secret_key'] ?? '');
        
        if (empty($secret_key)) {
            wp_send_json_error(['message' => 'No API key configured']);
        }
        
        try {
            \Stripe\Stripe::setApiKey($secret_key);
            $account = \Stripe\Account::retrieve();
            
            wp_send_json_success([
                'message' => 'Connection successful!',
                'account_name' => $account->business_profile->name ?? $account->email,
                'account_id' => $account->id,
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX: Fetch Stripe prices
     */
    public static function ajax_fetch_prices() {
        check_ajax_referer('ftt_billing_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        // Check if Stripe library is installed
        if (!file_exists(FTT_PLUGIN_DIR . 'lib/stripe-php/init.php')) {
            wp_send_json_error(['message' => 'Stripe PHP library not installed. Please install it first.']);
        }
        
        require_once FTT_PLUGIN_DIR . 'lib/stripe-php/init.php';
        
        $settings = get_option('ftt_stripe_settings', []);
        $mode = $settings['mode'] ?? 'test';
        
        $secret_key = $mode === 'live' 
            ? ($settings['live_secret_key'] ?? '')
            : ($settings['test_secret_key'] ?? '');
        
        if (empty($secret_key)) {
            wp_send_json_error(['message' => 'No API key configured']);
        }
        
        try {
            \Stripe\Stripe::setApiKey($secret_key);
            $prices = \Stripe\Price::all(['limit' => 100, 'active' => true]);
            
            $found_prices = [];
            
            foreach ($prices->data as $price) {
                $amount = $price->unit_amount / 100;
                $interval = $price->recurring->interval ?? 'one_time';
                
                // Match base subscription
                if ($amount == 5.99 && $interval === 'month') {
                    $found_prices['price_base_monthly'] = $price->id;
                } elseif ($amount == 59.90 && $interval === 'year') {
                    $found_prices['price_base_yearly'] = $price->id;
                }
                
                // Match add-ons
                elseif ($amount == 2.00 && $interval === 'month') {
                    $found_prices['price_addon_monthly'] = $price->id;
                } elseif ($amount == 20.00 && $interval === 'year') {
                    $found_prices['price_addon_yearly'] = $price->id;
                }
            }
            
            if (empty($found_prices)) {
                wp_send_json_error(['message' => 'No matching prices found. Please create products manually.']);
            }
            
            wp_send_json_success(['prices' => $found_prices]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}

// Initialize
FTT_Stripe_Settings::init();
