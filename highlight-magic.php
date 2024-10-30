<?php
/*
Plugin Name: Highlight Magic
Plugin URI: https://wppluginsforyou.com
Description: This plugin will let logged in users to highlight textx in various colors .They can also delete or edit the color highlights.
Version: 1.0
Author: pkthakur
Author URI: https://isaas.in
License: GPL v2 or later
Text Domain: highlight-magic
Domain Path: /languages
*/

// Enqueue scripts and styles
function highlighter_enqueue_scripts() {
    if (is_user_logged_in()) {
        wp_enqueue_script('highlighter-script', plugin_dir_url(__FILE__) . 'js/highlighter.js', array('jquery'), '1.0', true);
        wp_enqueue_style('highlighter-style', plugin_dir_url(__FILE__) . 'css/style.css', array(), '1.0');
        // Localize variables for AJAX
        wp_localize_script('highlighter-script', 'highlighter_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id'  => get_the_ID(),
            'nonce'    => wp_create_nonce('highlighter_ajax_nonce'),
        ));
    }
}
add_action('wp_enqueue_scripts', 'highlighter_enqueue_scripts');

// Add the color dot and remove icon to the page
function highlighter_add_color_dot() {
    if (is_user_logged_in()) {
        $alignment = get_option('highlighter_alignment', 'left-middle');
        echo '<div id="highlighter-container" class="highlighter-' . esc_attr($alignment) . '">
                <div id="highlighter-dot" title="Change Highlight Color"></div>
                <div id="highlighter-remove-icon" title="Remove Highlight">
                    <img src="' . esc_url(plugin_dir_url(__FILE__) . 'images/remove-icon.png') . '" alt="Remove Highlight" width="20" height="20">
                </div>
              </div>';
        echo '<div id="highlighter-color-picker" class="highlighter-' . esc_attr($alignment) . '">
                <div class="color-option" data-color="#FFFF00" style="background-color: #FFFF00;"></div>
                <div class="color-option" data-color="#FFA500" style="background-color: #FFA500;"></div>
                <div class="color-option" data-color="#00FF00" style="background-color: #00FF00;"></div>
                <div class="color-option" data-color="#00FFFF" style="background-color: #00FFFF;"></div>
                <div class="color-option" data-color="#FF00FF" style="background-color: #FF00FF;"></div>
                <div class="color-option" data-color="#FFC0CB" style="background-color: #FFC0CB;"></div>
              </div>';
    }
}
add_action('wp_footer', 'highlighter_add_color_dot');


// Add admin menu
function highlighter_add_admin_menu() {
    add_menu_page(
        'Highlight Magic Settings', // Page title
        'Highlight Magic',          // Menu title
        'manage_options',           // Capability
        'highlight-magic',          // Menu slug
        'highlighter_settings_page',// Function to display page content
        'dashicons-edit',           // Dashicon class for pencil icon
        80                          // Position in the menu
    );
}

add_action('admin_menu', 'highlighter_add_admin_menu');

// Settings page content
function highlighter_settings_page() {
    if ( isset( $_POST['highlighter_settings_nonce'] ) ) {
        // Unslash and sanitize the nonce
        $nonce = sanitize_text_field( wp_unslash( $_POST['highlighter_settings_nonce'] ) );

        if ( wp_verify_nonce( $nonce, 'highlighter_save_settings' ) ) {
            if ( isset( $_POST['highlighter_alignment'] ) ) {
                // Unslash and sanitize the alignment value
                $alignment = sanitize_text_field( wp_unslash( $_POST['highlighter_alignment'] ) );
                update_option( 'highlighter_alignment', $alignment );
                echo '<div class="updated"><p>Settings saved.</p></div>';
            }
        } else {
            echo '<div class="error"><p>Nonce verification failed. Settings not saved.</p></div>';
        }
    }

    $alignment = get_option( 'highlighter_alignment', 'left-middle' );
    ?>
    <div class="wrap">
        <h1>HighLight Magic Settings</h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'highlighter_save_settings', 'highlighter_settings_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="highlighter_alignment">Dot Alignment</label></th>
                    <td>
                        <select name="highlighter_alignment" id="highlighter_alignment">
                            <option value="top-middle" <?php selected( $alignment, 'top-middle' ); ?>>Top Middle</option>
                            <option value="right-middle" <?php selected( $alignment, 'right-middle' ); ?>>Right Middle</option>
                            <option value="left-middle" <?php selected( $alignment, 'left-middle' ); ?>>Left Middle</option>
                            <option value="bottom-middle" <?php selected( $alignment, 'bottom-middle' ); ?>>Bottom Middle</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Wrap post content in a div with a specific ID
function highlighter_wrap_content($content) {
    if (is_user_logged_in() && is_singular()) {
        return '<div id="highlighter-content">' . $content . '</div>';
    } else {
        return $content;
    }
}
add_filter('the_content', 'highlighter_wrap_content');

// Handle saving highlights via AJAX
function highlighter_save_highlight() {
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        wp_die();
    }

    if (!check_ajax_referer('highlighter_ajax_nonce', 'nonce', false)) {
        wp_send_json_error('Nonce verification failed');
        wp_die();
    }

    if (!isset($_POST['post_id'], $_POST['start_offset'], $_POST['end_offset'], $_POST['color'])) {
        wp_send_json_error('Missing required data');
        wp_die();
    }

    $user_id = get_current_user_id();
    $post_id = intval($_POST['post_id']);
    $start_offset = intval($_POST['start_offset']);
    $end_offset = intval($_POST['end_offset']);
    $color = sanitize_text_field(wp_unslash($_POST['color']));

    $highlights = get_user_meta($user_id, 'highlighter_highlights_' . $post_id, true);
    if (!is_array($highlights)) {
        $highlights = array();
    }

    $highlights[] = array(
        'start_offset' => $start_offset,
        'end_offset' => $end_offset,
        'color' => $color,
    );

    update_user_meta($user_id, 'highlighter_highlights_' . $post_id, $highlights);

    wp_send_json_success();
    wp_die();
}
add_action('wp_ajax_save_highlight', 'highlighter_save_highlight');

// Handle retrieving highlights via AJAX
function highlighter_get_highlights() {
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        wp_die();
    }

    if (!check_ajax_referer('highlighter_ajax_nonce', 'nonce', false)) {
        wp_send_json_error('Nonce verification failed');
        wp_die();
    }

    if (!isset($_POST['post_id'])) {
        wp_send_json_error('Post ID not provided');
        wp_die();
    }

    $user_id = get_current_user_id();
    $post_id = intval($_POST['post_id']);

    $highlights = get_user_meta($user_id, 'highlighter_highlights_' . $post_id, true);
    if (!$highlights) {
        $highlights = array();
    }

    wp_send_json_success($highlights);
    wp_die();
}
add_action('wp_ajax_get_highlights', 'highlighter_get_highlights');

// Handle updating highlights via AJAX
function highlighter_update_highlight() {
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        wp_die();
    }

    if (!check_ajax_referer('highlighter_ajax_nonce', 'nonce', false)) {
        wp_send_json_error('Nonce verification failed');
        wp_die();
    }

    if (!isset($_POST['post_id'], $_POST['start_offset'], $_POST['end_offset'], $_POST['color'])) {
        wp_send_json_error('Missing required data');
        wp_die();
    }

    $user_id = get_current_user_id();
    $post_id = intval($_POST['post_id']);
    $start_offset = intval($_POST['start_offset']);
    $end_offset = intval($_POST['end_offset']);
    $color = sanitize_text_field(wp_unslash($_POST['color']));

    // Get existing highlights
    $highlights = get_user_meta($user_id, 'highlighter_highlights_' . $post_id, true);
    if (!is_array($highlights)) {
        wp_send_json_error('No highlights found');
        wp_die();
    }

    // Find the highlight and update its color
    $found = false;
    foreach ($highlights as &$highlight) {
        if ($highlight['start_offset'] == $start_offset && $highlight['end_offset'] == $end_offset) {
            $highlight['color'] = $color;
            $found = true;
            break;
        }
    }

    if ($found) {
        update_user_meta($user_id, 'highlighter_highlights_' . $post_id, $highlights);
        wp_send_json_success();
    } else {
        wp_send_json_error('Highlight not found');
    }
    wp_die();
}
add_action('wp_ajax_update_highlight', 'highlighter_update_highlight');

// Handle deleting highlights via AJAX
function highlighter_delete_highlight() {
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        wp_die();
    }

    if (!check_ajax_referer('highlighter_ajax_nonce', 'nonce', false)) {
        wp_send_json_error('Nonce verification failed');
        wp_die();
    }

    if (!isset($_POST['post_id'], $_POST['start_offset'], $_POST['end_offset'])) {
        wp_send_json_error('Missing required data');
        wp_die();
    }

    $user_id = get_current_user_id();
    $post_id = intval($_POST['post_id']);
    $start_offset = intval($_POST['start_offset']);
    $end_offset = intval($_POST['end_offset']);

    // Get existing highlights
    $highlights = get_user_meta($user_id, 'highlighter_highlights_' . $post_id, true);
    if (!is_array($highlights)) {
        wp_send_json_error('No highlights found');
        wp_die();
    }

    // Find and remove the highlight
    $found = false;
    foreach ($highlights as $key => $highlight) {
        if ($highlight['start_offset'] == $start_offset && $highlight['end_offset'] == $end_offset) {
            unset($highlights[$key]);
            $found = true;
            break;
        }
    }

    if ($found) {
        // Reindex the array to prevent issues
        $highlights = array_values($highlights);
        update_user_meta($user_id, 'highlighter_highlights_' . $post_id, $highlights);
        wp_send_json_success();
    } else {
        wp_send_json_error('Highlight not found');
    }
    wp_die();
}
add_action('wp_ajax_delete_highlight', 'highlighter_delete_highlight');