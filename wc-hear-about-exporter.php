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
add_action( 'admin_post_hae_export_csv', 'hae_export_csv' );

function hae_export_csv() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized user' );
    }

    header( 'Content-Type: text/csv' );
    header( 'Content-Disposition: attachment; filename="hear-about-data.csv"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    $output = fopen( 'php://output', 'w' );

    // Column headers
    fputcsv( $output, array( 'Email', 'How did you hear about us?' ) );

    $users = get_users();

    foreach ( $users as $user ) {

        $hear_about = get_user_meta( $user->ID, 'how_did_you_hear', true );

        if ( ! empty( $hear_about ) ) {
            fputcsv( $output, array(
                $user->user_email,
                $hear_about
            ));
        }
    }

    fclose( $output );
    exit;
}
