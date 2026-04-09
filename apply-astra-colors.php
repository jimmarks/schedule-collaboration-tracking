<?php
/**
 * Apply Family Travel Tracker Colors to Astra Theme
 * 
 * Upload this file to your WordPress root directory and visit:
 * https://yoursite.com/apply-astra-colors.php
 * 
 * After running once, DELETE THIS FILE for security.
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('You must be logged in as an administrator to run this script.');
}

// Family Travel Tracker Brand Colors
$colors = array(
    'theme-color' => '#6A3E8E',              // Primary Plum
    'link-color' => '#6A3E8E',               // Link Plum
    'text-color' => '#333333',               // Body text
    'theme-color-hover' => '#5B347A',        // Primary hover
    'link-h-color' => '#5B347A',             // Link hover
    'heading-base-color' => '#6A3E8E',       // Headings Plum
    'button-color' => '#FFFFFF',             // Button text
    'button-h-color' => '#FFFFFF',           // Button text hover
    'button-bg-color' => '#F05A5A',          // Button Coral
    'button-bg-h-color' => '#E84E4E',        // Button hover
    'secondary-button-color' => '#FFFFFF',   // Secondary button text
    'secondary-button-h-color' => '#FFFFFF', // Secondary button text hover
    'secondary-button-bg-color' => '#6A3E8E',// Secondary button Plum
    'secondary-button-bg-h-color' => '#5B347A', // Secondary button hover
    'border-color' => '#E9E3F2',             // Border light purple
);

echo "<h1>Applying Family Travel Tracker Colors to Astra Theme...</h1>";
echo "<ul>";

foreach ($colors as $setting => $value) {
    set_theme_mod($setting, $value);
    echo "<li><strong>{$setting}:</strong> {$value} ✓</li>";
}

echo "</ul>";
echo "<h2>✓ All colors applied successfully!</h2>";
echo "<p><strong>IMPORTANT:</strong> Delete this file (apply-astra-colors.php) from your server now.</p>";
echo "<p>Visit your site to see the new colors: <a href='" . home_url() . "'>" . home_url() . "</a></p>";
