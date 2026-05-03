<?php
/**
 * Child Color Management
 *
 * Assigns and manages unique colors for each child to visually differentiate
 * them on the calendar. Colors auto-assign from a 10-color palette and cycle
 * when families have more than 10 children.
 *
 * @package FamilyTravelTracker
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTT_Child_Colors {
    
    /**
     * 10-color palette designed for visibility and differentiation
     * Colors are web-safe, accessible, and distinct from each other
     */
    private static $color_palette = [
        ['hex' => '#FF6B6B', 'name' => 'Red', 'text' => '#FFFFFF'],
        ['hex' => '#4ECDC4', 'name' => 'Teal', 'text' => '#000000'],
        ['hex' => '#FFD93D', 'name' => 'Yellow', 'text' => '#000000'],
        ['hex' => '#6BCF7F', 'name' => 'Green', 'text' => '#000000'],
        ['hex' => '#A78BFA', 'name' => 'Purple', 'text' => '#FFFFFF'],
        ['hex' => '#FB923C', 'name' => 'Orange', 'text' => '#000000'],
        ['hex' => '#60A5FA', 'name' => 'Blue', 'text' => '#000000'],
        ['hex' => '#F472B6', 'name' => 'Pink', 'text' => '#000000'],
        ['hex' => '#34D399', 'name' => 'Mint', 'text' => '#000000'],
        ['hex' => '#A855F7', 'name' => 'Violet', 'text' => '#FFFFFF'],
    ];
    
    /**
     * Get the full color palette
     *
     * @return array Array of color definitions
     */
    public static function get_palette() {
        return apply_filters('ftt_color_palette', self::$color_palette);
    }
    
    /**
     * Auto-assign a color to a child when they're added
     * Cycles through palette based on parent's existing children count
     *
     * @param int $child_id The child user ID
     * @param int $parent_id The parent user ID adding this child
     * @return array Color data ['hex', 'name', 'text'] or false on failure
     */
    public static function assign_color($child_id, $parent_id) {
        // Get all children for this parent
        $children = FTT_Family_Groups::get_user_children($parent_id);
        
        // Find the index of this new child
        $child_index = 0;
        foreach ($children as $i => $child) {
            // $children is an array of user IDs (ints), not WP_User objects
            $child_id_val = is_object($child) ? $child->ID : (int) $child;
            if ($child_id_val == $child_id) {
                $child_index = $i;
                break;
            }
        }
        
        // Get palette and cycle through it
        $palette = self::get_palette();
        $color_index = $child_index % count($palette);
        $color = $palette[$color_index];
        
        // Save to child's user meta
        update_user_meta($child_id, 'ftt_assigned_color', $color['hex']);
        update_user_meta($child_id, 'ftt_color_name', $color['name']);
        
        do_action('ftt_color_assigned', $child_id, $color, $parent_id);
        
        return $color;
    }
    
    /**
     * Get a child's assigned color
     *
     * @param int $child_id The child user ID
     * @return array|false Color data or false if no color assigned
     */
    public static function get_child_color($child_id) {
        // Check new format first (ftt_assigned_color)
        $hex = get_user_meta($child_id, 'ftt_assigned_color', true);
        $name = get_user_meta($child_id, 'ftt_color_name', true);
        
        // Fallback to legacy format (child_color) for backwards compatibility
        if (!$hex) {
            $hex = get_user_meta($child_id, 'child_color', true);
            if ($hex) {
                // Migrate to new format
                $palette = self::get_palette();
                $color_name = 'Custom';
                foreach ($palette as $color) {
                    if (strtolower($color['hex']) === strtolower($hex)) {
                        $color_name = $color['name'];
                        break;
                    }
                }
                // Save in new format
                update_user_meta($child_id, 'ftt_assigned_color', $hex);
                update_user_meta($child_id, 'ftt_color_name', $color_name);
                $name = $color_name;
            }
        }
        
        if (!$hex) {
            return false;
        }
        
        // Find text color from palette
        $palette = self::get_palette();
        $text_color = '#FFFFFF';
        foreach ($palette as $color) {
            if ($color['hex'] === $hex) {
                $text_color = $color['text'];
                break;
            }
        }
        
        return [
            'hex' => $hex,
            'name' => $name,
            'text' => $text_color,
        ];
    }
    
    /**
     * Update a child's color (manual override)
     * Allows parents to customize if auto-assignment isn't ideal
     *
     * @param int $child_id The child user ID
     * @param string $hex_color The new hex color (e.g., '#FF6B6B')
     * @return bool Success
     */
    public static function update_color($child_id, $hex_color) {
        // Validate hex format
        if (!preg_match('/^#[A-Fa-f0-9]{6}$/', $hex_color)) {
            return false;
        }
        
        // Find color name from palette, or use "Custom"
        $palette = self::get_palette();
        $color_name = 'Custom';
        foreach ($palette as $color) {
            if (strtolower($color['hex']) === strtolower($hex_color)) {
                $color_name = $color['name'];
                break;
            }
        }
        
        update_user_meta($child_id, 'ftt_assigned_color', strtoupper($hex_color));
        update_user_meta($child_id, 'ftt_color_name', $color_name);
        
        do_action('ftt_color_updated', $child_id, $hex_color);
        
        return true;
    }
    
    /**
     * Get all children with their colors for a parent
     * Used for filter sidebar and legend
     *
     * @param int $parent_id The parent user ID
     * @return array Array of child data with colors
     */
    public static function get_children_with_colors($parent_id) {
        $children_ids = FTT_Family_Groups::get_user_children($parent_id);
        $result = [];
        
        foreach ($children_ids as $child_id) {
            $child = get_user_by('id', $child_id);
            if (!$child) {
                continue;
            }
            
            $color = self::get_child_color($child_id);
            
            // Auto-assign if missing
            if (!$color) {
                $color = self::assign_color($child_id, $parent_id);
            }
            
            $result[] = [
                'id' => $child_id,
                'name' => $child->display_name,
                'email' => $child->user_email,
                'color' => $color,
            ];
        }
        
        return $result;
    }
    
    /**
     * Check if a color is available (not used by siblings)
     * Useful for manual color selection UI
     *
     * @param int $parent_id The parent ID
     * @param string $hex_color The color to check
     * @param int $exclude_child_id Optional child ID to exclude from check
     * @return bool True if available
     */
    public static function is_color_available($parent_id, $hex_color, $exclude_child_id = 0) {
        $children = self::get_children_with_colors($parent_id);
        
        foreach ($children as $child) {
            if ($child['id'] == $exclude_child_id) {
                continue;
            }
            
            if (strtolower($child['color']['hex']) === strtolower($hex_color)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get CSS for child colors
     * Generates dynamic CSS to style events
     *
     * @param int $parent_id Optional parent ID for specific children
     * @return string CSS rules
     */
    public static function get_color_css($parent_id = 0) {
        $css = "/* Family Travel Tracker - Child Colors */\n";
        
        if ($parent_id) {
            $children = self::get_children_with_colors($parent_id);
            
            foreach ($children as $child) {
                $css .= sprintf(
                    ".fc-event.child-%d { background-color: %s; border-color: %s; color: %s; }\n",
                    $child['id'],
                    $child['color']['hex'],
                    $child['color']['hex'],
                    $child['color']['text']
                );
            }
        } else {
            // Generate for all palette colors
            $palette = self::get_palette();
            foreach ($palette as $i => $color) {
                $css .= sprintf(
                    ".fc-event.color-%d { background-color: %s; border-color: %s; color: %s; }\n",
                    $i,
                    $color['hex'],
                    $color['hex'],
                    $color['text']
                );
            }
        }
        
        return $css;
    }
    
    /**
     * Add child color to event data
     * Used by REST API and iCal export
     *
     * @param array $event_data The event data array
     * @param int $child_id The child user ID
     * @return array Modified event data
     */
    public static function add_color_to_event($event_data, $child_id) {
        $color = self::get_child_color($child_id);
        
        if ($color) {
            $event_data['color'] = $color['hex'];
            $event_data['textColor'] = $color['text'];
            $event_data['className'] = 'child-' . $child_id;
        }
        
        return $event_data;
    }
    
    /**
     * Admin settings: Customize color palette
     * Allows admin to override default colors
     *
     * @return void
     */
    public static function render_settings_section() {
        $custom_palette = get_option('ftt_custom_color_palette', []);
        $palette = !empty($custom_palette) ? $custom_palette : self::$color_palette;
        
        echo '<div class="ftt-color-palette-settings">';
        echo '<h3>Child Color Palette</h3>';
        echo '<p>Customize the colors used for children on calendars. Colors auto-assign in order.</p>';
        echo '<table class="form-table">';
        
        foreach ($palette as $i => $color) {
            echo '<tr>';
            echo '<th scope="row">Color ' . ($i + 1) . '</th>';
            echo '<td>';
            echo '<input type="color" name="ftt_color_' . $i . '_hex" value="' . esc_attr($color['hex']) . '" />';
            echo ' <input type="text" name="ftt_color_' . $i . '_name" value="' . esc_attr($color['name']) . '" placeholder="Color Name" />';
            echo ' <span class="color-preview" style="display: inline-block; width: 40px; height: 20px; background: ' . esc_attr($color['hex']) . '; margin-left: 10px; vertical-align: middle;"></span>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
    }
    
    /**
     * Save custom color palette from settings
     *
     * @return void
     */
    public static function save_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $palette = [];
        for ($i = 0; $i < 10; $i++) {
            if (isset($_POST['ftt_color_' . $i . '_hex'])) {
                $palette[] = [
                    'hex' => sanitize_text_field($_POST['ftt_color_' . $i . '_hex']),
                    'name' => sanitize_text_field($_POST['ftt_color_' . $i . '_name']),
                    'text' => self::calculate_text_color($_POST['ftt_color_' . $i . '_hex']),
                ];
            }
        }
        
        if (!empty($palette)) {
            update_option('ftt_custom_color_palette', $palette);
        }
    }
    
    /**
     * Calculate best text color (black or white) for a background
     * Uses relative luminance formula
     *
     * @param string $hex_color Background color
     * @return string '#000000' or '#FFFFFF'
     */
    private static function calculate_text_color($hex_color) {
        $hex = str_replace('#', '', $hex_color);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Calculate relative luminance
        $luminance = ($r * 0.299 + $g * 0.587 + $b * 0.114) / 255;
        
        return $luminance > 0.5 ? '#000000' : '#FFFFFF';
    }
}
