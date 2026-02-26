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

// If user is already logged in, redirect to dashboard
if (is_user_logged_in()) {
    wp_redirect(home_url('/sc-dashboard/'));
    exit;
}

// Get redirect destination
$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : home_url('/sc-dashboard/');

$login_url = wp_login_url($redirect_to);
$register_url = home_url('/sc-register/');
$lost_password_url = wp_lostpassword_url($redirect_to);

// Check for login errors/messages
$login_errors = array();
$login_message = '';

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

<div class="srt-login-wrapper">
    <div class="srt-login-container">
        <div class="srt-login-header">
            <h1>Schedule Login</h1>
            <p>Sign in to access your schedule and flight tracking</p>
        </div>
        
        <?php if (!empty($login_message)): ?>
            <div class="srt-login-message srt-success">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html($login_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($login_errors)): ?>
            <div class="srt-login-message srt-error">
                <span class="dashicons dashicons-warning"></span>
                <?php foreach ($login_errors as $error): ?>
                    <p><?php echo esc_html($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form name="loginform" id="srt-loginform" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>" method="post">
            <div class="srt-form-field">
                <label for="user_login">
                    <span class="dashicons dashicons-admin-users"></span>
                    Username or Email
                </label>
                <input type="text" name="log" id="user_login" class="srt-input" value="" size="20" autocapitalize="off" required />
            </div>
            
            <div class="srt-form-field">
                <label for="user_pass">
                    <span class="dashicons dashicons-lock"></span>
                    Password
                </label>
                <input type="password" name="pwd" id="user_pass" class="srt-input" value="" size="20" required />
            </div>
            
            <div class="srt-form-field srt-remember-me">
                <label>
                    <input name="rememberme" type="checkbox" id="rememberme" value="forever" />
                    Remember Me
                </label>
            </div>
            
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
            <input type="hidden" name="testcookie" value="1" />
            
            <div class="srt-form-actions">
                <button type="submit" name="wp-submit" id="wp-submit" class="srt-btn srt-btn-primary">
                    <span class="dashicons dashicons-unlock"></span>
                    Log In
                </button>
            </div>
        </form>
        
        <div class="srt-login-links">
            <a href="<?php echo esc_url($lost_password_url); ?>" class="srt-link">
                <span class="dashicons dashicons-sos"></span>
                Forgot Password?
            </a>
            <span class="srt-separator">|</span>
            <a href="<?php echo esc_url($register_url); ?>" class="srt-link">
                <span class="dashicons dashicons-admin-users"></span>
                Create Account
            </a>
        </div>
    </div>
</div>

<style>
.srt-login-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 60vh;
    padding: 40px 20px;
}

.srt-login-container {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    padding: 40px;
    max-width: 450px;
    width: 100%;
}

.srt-login-header {
    text-align: center;
    margin-bottom: 30px;
}

.srt-login-header h1 {
    color: #0066cc;
    font-size: 28px;
    margin: 0 0 10px 0;
    font-weight: 600;
}

.srt-login-header p {
    color: #666;
    font-size: 14px;
    margin: 0;
}

.srt-login-message {
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.srt-login-message .dashicons {
    flex-shrink: 0;
    margin-top: 2px;
}

.srt-login-message.srt-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.srt-login-message.srt-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.srt-login-message p {
    margin: 0;
}

#srt-loginform .srt-form-field {
    margin-bottom: 20px;
}

#srt-loginform label {
    display: block;
    font-weight: 500;
    color: #333;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

#srt-loginform label .dashicons {
    color: #0066cc;
    font-size: 18px;
    width: 18px;
    height: 18px;
}

#srt-loginform .srt-input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 15px;
    transition: all 0.2s;
}

#srt-loginform .srt-input:focus {
    outline: none;
    border-color: #0066cc;
    box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
}

.srt-remember-me label {
    font-weight: normal;
    font-size: 14px;
    color: #666;
    cursor: pointer;
}

.srt-remember-me input[type="checkbox"] {
    margin-right: 6px;
}

.srt-form-actions {
    margin-top: 25px;
}

.srt-btn {
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

.srt-btn-primary {
    background: #0066cc;
    color: #fff;
}

.srt-btn-primary:hover {
    background: #0052a3;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,102,204,0.3);
}

.srt-btn .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.srt-login-links {
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid #e0e0e0;
    text-align: center;
    font-size: 14px;
}

.srt-login-links .srt-link {
    color: #0066cc;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: color 0.2s;
}

.srt-login-links .srt-link:hover {
    color: #0052a3;
    text-decoration: underline;
}

.srt-login-links .srt-link .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.srt-separator {
    color: #ccc;
    margin: 0 10px;
}

@media (max-width: 600px) {
    .srt-login-container {
        padding: 30px 20px;
    }
    
    .srt-login-header h1 {
        font-size: 24px;
    }
}
</style>
