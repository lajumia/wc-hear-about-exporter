<?php
/**
 * Plugin Name: Hear About Us Exporter
 * Description: Export "How did you hear about us?" data with email to Excel.
 * Version: 1.0.0
 * Author: Md Laju Miah
 * Author URI: https://profiles.wordpress.org/devlaju/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add Admin Menu
 */
add_action( 'admin_menu', 'hae_add_admin_menu' );

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

/**
 * Admin Page UI
 */
function hae_admin_page() {

    global $wpdb;

    echo '<div class="wrap">';
    echo '<h1>Export "How Did You Hear About Us?" Data</h1>';

    if ( ! function_exists( 'wc_get_orders' ) ) {
        echo '<p>WooCommerce not loaded.</p></div>';
        return;
    }

    echo '<table class="widefat fixed striped" style="margin-top:20px;">';
    echo '<thead>
            <tr>
                <th>Order ID</th>
                <th>Email</th>
                <th>How Did You Hear About Us?</th>
            </tr>
          </thead>';
    echo '<tbody>';

    // Get all order IDs with this meta
    $order_ids = $wpdb->get_col("
        SELECT post_id 
        FROM {$wpdb->prefix}postmeta 
        WHERE meta_key = 'How Did You Hear About Us?'
        LIMIT 20
    ");

    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
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

    echo '</tbody></table>';

    echo '<br>';

    // Export button
    echo '<a href="' . admin_url('admin-post.php?action=wc_hear_export_xlsx') . '" 
              class="button button-primary">
              Export as XLSX
          </a>';

    echo '</div>';
}

/**
 * Handle Export
 */
add_action( 'admin_post_wc_hear_export_xlsx', 'wc_hear_export_xlsx' );

function wc_hear_export_xlsx() {

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Unauthorized' );
    }

    if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
        wp_die( 'PhpSpreadsheet not installed.' );
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Headers
    $sheet->setCellValue('A1', 'Order ID');
    $sheet->setCellValue('B1', 'Email');
    $sheet->setCellValue('C1', 'Hear About');

    $orders = wc_get_orders( array(
        'limit'  => -1,
        'status' => array( 'wc-completed', 'wc-processing' ),
    ) );

    $row = 2;

    foreach ( $orders as $order ) {

        $hear_about = $order->get_meta( 'hear_about' );

        if ( ! empty( $hear_about ) ) {

            $sheet->setCellValue( 'A' . $row, $order->get_id() );
            $sheet->setCellValue( 'B' . $row, $order->get_billing_email() );
            $sheet->setCellValue( 'C' . $row, $hear_about );

            $row++;
        }
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="wc-hear-about-orders.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
