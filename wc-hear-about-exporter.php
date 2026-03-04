<?php
/**
 * Plugin Name: Hear About Us Exporter
 * Description: Preview and export "How Did You Hear About Us?" data as JSON.
 * Version: 1.0.0
 * Author: Md Laju Miah
 */

if (!defined('ABSPATH')) exit; // security

// Add Admin Menu
add_action('admin_menu', 'hae_add_admin_menu');
function hae_add_admin_menu() {
    add_menu_page(
        'Hear About Export',
        'Hear About Export',
        'manage_options',
        'hear-about-export',
        'hae_admin_page',
        'dashicons-download',
        26
    );
}

// Admin Page UI: preview table + JSON export
function hae_admin_page() {
    global $wpdb;

    echo '<div class="wrap">';
    echo '<h1>Preview "How Did You Hear About Us?" Data</h1>';

        // --- Summary Header ---
    // Total number of orders with the meta key
    $total_orders = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}postmeta
        WHERE meta_key = %s
    ", 'How Did You Hear About Us?'));

    $preview_limit = 20; // number of rows in preview

    echo '<div style="display:flex; gap:40px; margin-bottom:20px; align-items:center;">';

    echo '<div><strong>Total orders:</strong> ' . esc_html($total_orders) . '</div>';
    echo '<div><strong>Showing latest:</strong> ' . esc_html($preview_limit) . ' orders</div>';
    echo '<div><strong>Last updated:</strong> ' . esc_html(date('Y-m-d H:i:s')) . '</div>';

    echo '</div>';

    // Table header
    echo '<table class="widefat fixed striped" style="margin-top:20px;">';
    echo '<thead>
            <tr>
                <th>Order ID</th>
                <th>Email</th>
                <th>How Did You Hear About Us?</th>
            </tr>
          </thead>';
    echo '<tbody>';

    // Fetch first 20 orders with this meta key
    $order_ids = $wpdb->get_col("
        SELECT post_id 
        FROM {$wpdb->prefix}postmeta 
        WHERE meta_key = 'How Did You Hear About Us?'
        ORDER BY post_id DESC
        LIMIT 20
    ");

    if (empty($order_ids)) {
        echo '<tr><td colspan="3">No data found.</td></tr>';
    } else {
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;

            $email = $order->get_billing_email();
            $hear_about = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = %d AND meta_key = %s",
                $order_id,
                'How Did You Hear About Us?'
            ));

            echo "<tr>";
            echo "<td>" . esc_html($order_id) . "</td>";
            echo "<td>" . esc_html($email) . "</td>";
            echo "<td>" . esc_html($hear_about) . "</td>";
            echo "</tr>";
        }
    }

    echo '</tbody></table>';

    // JSON Export Button
    echo '<br>';
    echo '<a href="' . admin_url('admin-post.php?action=wc_hear_export_json') . '" class="button button-primary">
            Export All as JSON
          </a>';

    echo '</div>';
}

// Handle JSON Export
add_action('admin_post_wc_hear_export_json', 'wc_hear_export_json');
function wc_hear_export_json() {
    if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized');

    global $wpdb;

    $order_ids = $wpdb->get_col($wpdb->prepare("
        SELECT post_id
        FROM {$wpdb->prefix}postmeta
        WHERE meta_key = %s
    ", 'How Did You Hear About Us?'));

    $data = [];
    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) continue;

        $email = $order->get_billing_email();
        $hear_about = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = %d AND meta_key = %s",
            $order_id,
            'How Did You Hear About Us?'
        ));

        if (empty($hear_about)) continue;

        $data[] = [
            'order_id' => $order_id,
            'email' => $email,
            'hear_about' => $hear_about,
        ];
    }

    // Clear output buffer to prevent blank JSON issues
    if (ob_get_length()) ob_end_clean();

    // JSON headers for download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="hear-about-orders.json"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo wp_json_encode($data, JSON_PRETTY_PRINT);
    exit;
}
