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

    $preview_limit = 20;

    // Fetch data
    $total_orders = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}postmeta
        WHERE meta_key = %s
    ", 'How Did You Hear About Us?'));

    $order_ids = $wpdb->get_col($wpdb->prepare("
        SELECT post_id 
        FROM {$wpdb->prefix}postmeta 
        WHERE meta_key = %s
        ORDER BY post_id DESC
        LIMIT %d
    ", 'How Did You Hear About Us?', $preview_limit));

    // --- Admin CSS for modern SaaS look ---
    echo '<style>
        .hae-summary { display:flex; gap:20px; margin-bottom:30px; flex-wrap:wrap; }
        .hae-card { background:#fff; padding:20px 25px; border-radius:12px; flex:1; min-width:200px; text-align:center; box-shadow:0 4px 12px rgba(0,0,0,0.05); }
        .hae-card-title { color:#6b7280; font-size:14px; font-weight:500; margin-bottom:5px; }
        .hae-card-value { font-size:22px; font-weight:700; color:#111827; }
        .hae-table-container { overflow-x:auto; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.05); }
        .hae-table { width:100%; border-collapse:collapse; font-size:14px; min-width:600px; }
        .hae-table thead tr { background:linear-gradient(90deg,#4f46e5,#3b82f6); color:#fff; text-align:left; }
        .hae-table th, .hae-table td { padding:12px 15px; }
        .hae-table tbody tr:nth-child(even) { background:#f9fafb; }
        .hae-table tbody tr:nth-child(odd) { background:#ffffff; }
        .hae-table tbody tr:hover { background:#e0e7ff; }
        .hae-btn { display:inline-block; background:linear-gradient(90deg,#4f46e5,#3b82f6); color:#fff; font-weight:700; padding:12px 28px; border-radius:8px; text-decoration:none; box-shadow:0 4px 12px rgba(0,0,0,0.15); transition:0.3s; }
        .hae-btn:hover { background:linear-gradient(90deg,#4338ca,#2563eb); }
    </style>';

    echo '<div class="wrap" style="font-family:Arial, sans-serif; max-width:1200px; margin:0 auto;">';
    echo '<h1 style="margin-bottom:30px; font-size:30px; color:#1f2937;">Hear About Us Data Preview</h1>';

    // --- Summary ---
    echo '<div class="hae-summary">';
    echo '<div class="hae-card"><div class="hae-card-title">Total Orders</div><div class="hae-card-value">' . esc_html($total_orders) . '</div></div>';
    echo '<div class="hae-card"><div class="hae-card-title">Showing Latest</div><div class="hae-card-value">' . esc_html($preview_limit) . '</div></div>';
    echo '<div class="hae-card"><div class="hae-card-title">Last Updated</div><div class="hae-card-value">' . esc_html(date('Y-m-d H:i:s')) . '</div></div>';
    echo '</div>';

    // --- Table ---
    echo '<div class="hae-table-container">';
    echo '<table class="hae-table">';
    echo '<thead><tr><th>Order ID</th><th>Email</th><th>How Did You Hear About Us?</th></tr></thead>';
    echo '<tbody>';

    if (empty($order_ids)) {
        echo '<tr><td colspan="3" style="padding:12px; text-align:center;">No data found.</td></tr>';
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

            echo '<tr>';
            echo '<td>' . esc_html($order_id) . '</td>';
            echo '<td>' . esc_html($email) . '</td>';
            echo '<td>' . esc_html($hear_about) . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table></div>';

    // --- JSON Export Button ---
    echo '<div style="margin-top:25px;">';
    echo '<a href="' . admin_url('admin-post.php?action=wc_hear_export_json') . '" class="hae-btn">Export All as JSON</a>';
    echo '</div>';

    echo '</div>'; // wrap
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
