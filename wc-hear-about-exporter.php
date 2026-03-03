<?php
/**
 * Plugin Name: Hear About Us Exporter
 * Description: Export "How did you hear about us?" data with email to Excel.
 * Version: 1.0
 * Author: Your Name
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
    ?>
    <div class="wrap">
        <h1>Export "How did you hear about us?" Data</h1>
        <p>Click the button below to download Excel file.</p>

        <a href="<?php echo admin_url( 'admin-post.php?action=hae_export_csv' ); ?>" 
           class="button button-primary">
            Download Excel File
        </a>
    </div>
    <?php
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
