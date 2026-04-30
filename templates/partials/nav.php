<?php
/**
 * Partial: Global Navigation Header
 *
 * Usage — include at the top of any FTT template:
 *   $ftt_page_title = __('Calendar', 'schedule-collaboration-tracking');
 *   include FTT_PLUGIN_DIR . 'templates/partials/nav.php';
 *
 * $ftt_page_title  (string, required) — page name shown in the header
 * $ftt_active_slug (string, optional) — nav key to highlight: 'calendar',
 *                  'event_list', 'groups', 'event_form', 'profile'
 *
 * @package Family_Travel_Tracker
 */

if (!defined('ABSPATH')) exit;

$_ftt_user      = wp_get_current_user();
$_ftt_logged_in = is_user_logged_in();
$_ftt_is_parent = $_ftt_logged_in && FTT_Roles::is_parent($_ftt_user->ID);
$_ftt_is_admin  = $_ftt_logged_in && current_user_can('manage_options');

// Resolve each URL once
$_ftt_urls = array(
    'dashboard'  => FTT_Pages::get_page_url('dashboard')  ?: home_url('/ftt-dashboard/'),
    'calendar'   => FTT_Pages::get_page_url('calendar')   ?: home_url('/ftt-calendar/'),
    'event_list' => FTT_Pages::get_page_url('event_list') ?: home_url('/ftt-events/'),
    'groups'     => FTT_Pages::get_page_url('groups')     ?: home_url('/ftt-groups/'),
    'event_form' => FTT_Pages::get_page_url('event_form') ?: home_url('/ftt-manage-events/'),
    'profile'    => FTT_Pages::get_page_url('profile')    ?: home_url('/ftt-profile/'),
);

// Use caller-provided slug or try to auto-detect from current URL
if (empty($ftt_active_slug)) {
    $ftt_active_slug = '';
    $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    foreach ($_ftt_urls as $key => $url) {
        if ($url && strpos($current_path, trim(parse_url($url, PHP_URL_PATH), '/')) !== false) {
            $ftt_active_slug = $key;
            break;
        }
    }
}
?>
<div class="ftt-main-nav">
    <div class="ftt-nav-logo">
        <a href="<?php echo esc_url($_ftt_urls['dashboard']); ?>" class="ftt-nav-home-link">
            <h1>✈️ <?php echo esc_html($ftt_page_title ?? __('Family Travel Tracker', 'schedule-collaboration-tracking')); ?></h1>
        </a>
    </div>

    <?php if ($_ftt_logged_in): ?>
    <nav class="ftt-nav-menu" aria-label="<?php esc_attr_e('Main navigation', 'schedule-collaboration-tracking'); ?>">

        <?php if ($ftt_active_slug !== 'dashboard'): ?>
        <a href="<?php echo esc_url($_ftt_urls['dashboard']); ?>"
           class="ftt-nav-link">
            <span class="dashicons dashicons-home"></span>
            <?php esc_html_e('Dashboard', 'schedule-collaboration-tracking'); ?>
        </a>
        <?php endif; ?>

        <?php if ($ftt_active_slug !== 'calendar'): ?>
        <a href="<?php echo esc_url($_ftt_urls['calendar']); ?>"
           class="ftt-nav-link">
            <span class="dashicons dashicons-calendar-alt"></span>
            <?php esc_html_e('Calendar', 'schedule-collaboration-tracking'); ?>
        </a>
        <?php endif; ?>

        <?php if ($ftt_active_slug !== 'event_list'): ?>
        <a href="<?php echo esc_url($_ftt_urls['event_list']); ?>"
           class="ftt-nav-link">
            <span class="dashicons dashicons-list-view"></span>
            <?php $_ftt_is_admin
                ? esc_html_e('All Events', 'schedule-collaboration-tracking')
                : esc_html_e('My Events', 'schedule-collaboration-tracking'); ?>
        </a>
        <?php endif; ?>

        <?php if (($_ftt_is_parent || $_ftt_is_admin) && $ftt_active_slug !== 'groups'): ?>
        <a href="<?php echo esc_url($_ftt_urls['groups']); ?>"
           class="ftt-nav-link">
            <span class="dashicons dashicons-groups"></span>
            <?php esc_html_e('Family Groups', 'schedule-collaboration-tracking'); ?>
        </a>
        <?php endif; ?>

        <?php if ($_ftt_is_admin && $ftt_active_slug !== 'event_form'): ?>
        <a href="<?php echo esc_url($_ftt_urls['event_form']); ?>"
           class="ftt-nav-link">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php esc_html_e('Manage Events', 'schedule-collaboration-tracking'); ?>
        </a>
        <?php endif; ?>

        <?php if ($ftt_active_slug !== 'profile'): ?>
        <a href="<?php echo esc_url($_ftt_urls['profile']); ?>"
           class="ftt-nav-link">
            <span class="dashicons dashicons-admin-users"></span>
            <?php esc_html_e('My Settings', 'schedule-collaboration-tracking'); ?>
        </a>
        <?php endif; ?>

    </nav>
    <?php endif; ?>
</div>
