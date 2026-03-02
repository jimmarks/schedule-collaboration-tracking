<?php
/**
 * Custom Login Form Template
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// If user is already logged in, show message or redirect based on context
if (is_user_logged_in()) {
    // Check if we're in a shortcode rendering context (ob_get_level > 1)
    // If so, return message instead of redirecting to avoid breaking page render
    if (ob_get_level() > 1) {
        ?>
        <div class="ftt-login-wrapper">
            <div class="ftt-login-container">
                <div class="ftt-login-message ftt-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <p><?php esc_html_e('You are already logged in.', 'schedule-collaboration-tracking'); ?></p>
                    <p><a href="<?php echo esc_url(home_url('/ftt-dashboard/')); ?>" class="ftt-btn ftt-btn-primary"><?php esc_html_e('Go to Dashboard', 'schedule-collaboration-tracking'); ?></a></p>
                </div>
            </div>
        </div>
        <?php
        return;
    } else {
        // Direct page access - safe to redirect
        wp_redirect(home_url('/ftt-dashboard/'));
        exit;
    }
}

// Get redirect destination
$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : home_url('/ftt-dashboard/');

$login_url = wp_login_url($redirect_to);
$register_url = home_url('/ftt-register/');
$lost_password_url = wp_lostpassword_url($redirect_to);

// Check for login errors/messages
$login_errors = array();
$login_message = '';

if (isset($_GET['access_denied']) && $_GET['access_denied'] === '1') {
    $login_errors[] = '<strong>Access Denied:</strong> Your account access has been restricted by an administrator. Please contact support at <a href="mailto:support@familytraveltracker.app">support@familytraveltracker.app</a> for assistance.';
}

if (isset($_GET['login']) && $_GET['login'] === 'failed') {
    $login_errors[] = 'Invalid username or password. Please try again.';
}

if (isset($_GET['login']) && $_GET['login'] === 'empty') {
    $login_errors[] = 'Please enter your username and password.';
}

if (isset($_GET['loggedout']) && $_GET['loggedout'] === 'true') {
    $login_message = 'You have been successfully logged out.';
}

if (isset($_GET['registered']) && $_GET['registered'] === 'true') {
    $login_message = 'Registration successful! Please log in.';
}

if (isset($_GET['password']) && $_GET['password'] === 'changed') {
    $login_message = 'Password changed successfully! Please log in with your new password.';
}

if (isset($_GET['checkemail']) && $_GET['checkemail'] === 'confirm') {
    $login_message = 'Check your email for the password reset link.';
}
?>

<div class="ftt-login-wrapper">
    <div class="ftt-login-container">
        <div class="ftt-login-header">
            <h1>Schedule Login</h1>
            <p>Sign in to access your schedule and flight tracking</p>
        </div>
        
        <?php if (!empty($login_message)): ?>
            <div class="ftt-login-message ftt-success">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html($login_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($login_errors)): ?>
            <div class="ftt-login-message ftt-error">
                <span class="dashicons dashicons-warning"></span>
                <?php foreach ($login_errors as $error): ?>
                    <p><?php echo esc_html($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form name="loginform" id="ftt-loginform" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>" method="post">
            <div class="ftt-form-field">
                <label for="user_login">
                    <span class="dashicons dashicons-admin-users"></span>
                    Username or Email
                </label>
                <input type="text" name="log" id="user_login" class="ftt-input" value="" size="20" autocapitalize="off" required />
            </div>
            
            <div class="ftt-form-field">
                <label for="user_pass">
                    <span class="dashicons dashicons-lock"></span>
                    Password
                </label>
                <input type="password" name="pwd" id="user_pass" class="ftt-input" value="" size="20" required />
            </div>
            
            <div class="ftt-form-field ftt-remember-me">
                <label>
                    <input name="rememberme" type="checkbox" id="rememberme" value="forever" />
                    Remember Me
                </label>
            </div>
            
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
            <input type="hidden" name="ftt_custom_login" value="1" />
            <input type="hidden" name="testcookie" value="1" />
            
            <?php
            // hCaptcha integration
            $settings = get_option('ftt_settings', array());
            $enable_hcaptcha = $settings['enable_hcaptcha'] ?? false;
            $hcaptcha_site_key = $settings['hcaptcha_site_key'] ?? '';
            
            if ($enable_hcaptcha && !empty($hcaptcha_site_key)) :
            ?>
            <!-- hCaptcha -->
            <div class="ftt-form-field">
                <div class="h-captcha" data-sitekey="<?php echo esc_attr($hcaptcha_site_key); ?>"></div>
            </div>
            <?php endif; ?>
            
            <div class="ftt-form-actions">
                <button type="submit" name="wp-submit" id="wp-submit" class="ftt-btn ftt-btn-primary">
                    <span class="dashicons dashicons-unlock"></span>
                    Log In
                </button>
            </div>
        </form>
        
        <div class="ftt-login-links">
            <a href="<?php echo esc_url($lost_password_url); ?>" class="ftt-link">
                <span class="dashicons dashicons-sos"></span>
                Forgot Password?
            </a>
            <span class="ftt-separator">|</span>
            <a href="<?php echo esc_url($register_url); ?>" class="ftt-link">
                <span class="dashicons dashicons-admin-users"></span>
                Create Account
            </a>
        </div>
    </div>
</div>

<style>
.ftt-login-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 60vh;
    padding: 40px 20px;
}

.ftt-login-container {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    padding: 40px;
    max-width: 450px;
    width: 100%;
}

.ftt-login-header {
    text-align: center;
    margin-bottom: 30px;
}

.ftt-login-header h1 {
    color: #0066cc;
    font-size: 28px;
    margin: 0 0 10px 0;
    font-weight: 600;
}

.ftt-login-header p {
    color: #666;
    font-size: 14px;
    margin: 0;
}

.ftt-login-message {
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.ftt-login-message .dashicons {
    flex-shrink: 0;
    margin-top: 2px;
}

.ftt-login-message.ftt-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.ftt-login-message.ftt-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.ftt-login-message p {
    margin: 0;
}

#ftt-loginform .ftt-form-field {
    margin-bottom: 20px;
}

#ftt-loginform label {
    display: block;
    font-weight: 500;
    color: #333;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

#ftt-loginform label .dashicons {
    color: #0066cc;
    font-size: 18px;
    width: 18px;
    height: 18px;
}

#ftt-loginform .ftt-input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 15px;
    transition: all 0.2s;
}

#ftt-loginform .ftt-input:focus {
    outline: none;
    border-color: #0066cc;
    box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
}

.ftt-remember-me label {
    font-weight: normal;
    font-size: 14px;
    color: #666;
    cursor: pointer;
}

.ftt-remember-me input[type="checkbox"] {
    margin-right: 6px;
}

.ftt-form-actions {
    margin-top: 25px;
}

.ftt-btn {
    width: 100%;
    padding: 14px 20px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.ftt-btn-primary {
    background: #0066cc;
    color: #fff;
}

.ftt-btn-primary:hover {
    background: #0052a3;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,102,204,0.3);
}

.ftt-btn .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.ftt-login-links {
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid #e0e0e0;
    text-align: center;
    font-size: 14px;
}

.ftt-login-links .ftt-link {
    color: #0066cc;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: color 0.2s;
}

.ftt-login-links .ftt-link:hover {
    color: #0052a3;
    text-decoration: underline;
}

.ftt-login-links .ftt-link .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.ftt-separator {
    color: #ccc;
    margin: 0 10px;
}

@media (max-width: 600px) {
    .ftt-login-container {
        padding: 30px 20px;
    }
    
    .ftt-login-header h1 {
        font-size: 24px;
    }
}
</style>
<?php
// Load hCaptcha script if enabled
$settings = get_option('ftt_settings', array());
$enable_hcaptcha = $settings['enable_hcaptcha'] ?? false;
$hcaptcha_site_key = $settings['hcaptcha_site_key'] ?? '';

if ($enable_hcaptcha && !empty($hcaptcha_site_key)) :
?>
<script src="https://js.hcaptcha.com/1/api.js" async defer></script>
<?php endif; ?>