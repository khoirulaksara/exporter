<?php
/*
Plugin Name: Exporter
Plugin URI: https://github.com/khoirulaksara/exporter
Description: A plugin to dynamically export and import Posts, Pages, Custom Post Types (CPT) and Custom Fields in CSV format.
Version: 1.1
Author: Khoirul Aksara
Author URI: https://github.com/khoirulaksara
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function cpt_custom_cfe_csv_add_menu() {
    add_submenu_page(
        'options-general.php',
        'Exporter Export/Import CSV',
        'Exporter',
        'manage_options',
        'cpt-custom-fields-import-export-csv',
        'cpt_custom_cfe_csv_export_import_page'
    );
}
add_action( 'admin_menu', 'cpt_custom_cfe_csv_add_menu' );

function cpt_custom_cfe_csv_export_import_page() {
    ?>
    <div class="wrap">
        <h1>Exporter (Export/Import CSV)</h1>
        <form method="post" action="">
            <h2>Export to CSV</h2>
            <label for="export_post_type">Select Post Type to export:</label>
            <select name="export_post_type" id="export_post_type">
                <?php
                $post_types = get_post_types( array( 'public' => true ), 'objects' );
                foreach ( $post_types as $post_type ) {
                    echo '<option value="' . $post_type->name . '">' . $post_type->label . '</option>';
                }
                ?>
            </select>
            <input type="submit" name="export_cpt" value="Export to CSV" class="button-primary" />
        </form>
        <form method="post" enctype="multipart/form-data">
            <h2>Import from CSV</h2>
            <label for="import_post_type">Select Post Type to import:</label>
            <select name="import_post_type" id="import_post_type">
                <?php
                foreach ( $post_types as $post_type ) {
                    echo '<option value="' . $post_type->name . '">' . $post_type->label . '</option>';
                }
                ?>
            </select>
            <label for="import_file">Select CSV file to import:</label>
            <input type="file" name="import_file" id="import_file" />
            <input type="submit" name="import_cpt" value="Import from CSV" class="button-primary" />
        </form>
    </div>
    <?php

    if ( isset( $_POST['export_cpt'] ) && isset( $_POST['export_post_type'] ) ) {
        $post_type = sanitize_text_field( $_POST['export_post_type'] );
        cpt_custom_cfe_csv_export_cpt( $post_type );
    }

    if ( isset( $_POST['import_cpt'] ) && isset( $_FILES['import_file'] ) && isset( $_POST['import_post_type'] ) ) {
        $post_type = sanitize_text_field( $_POST['import_post_type'] );
        cpt_custom_cfe_csv_import_cpt( $post_type );
    }
}

function cpt_custom_cfe_csv_export_cpt( $post_type ) {
    $args = array(
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    );

    $posts = get_posts( $args );
    
    $csv_filename = $post_type . '_cpt_export_' . time() . '.csv';
    $csv_file = fopen( plugin_dir_path( __FILE__ ) . $csv_filename, 'w' );

    fputcsv( $csv_file, array( 'Post Title', 'Description', 'Post Date', 'Author', 'Custom Fields' ) );

    foreach ( $posts as $post ) {
        $custom_fields = get_post_meta( $post->ID );
        $custom_fields_str = '';
        
        foreach ( $custom_fields as $key => $value ) {
            $custom_fields_str .= $key . ': ' . implode( ', ', $value ) . '; ';
        }

        fputcsv( $csv_file, array(
            $post->post_title,
            $post->post_content,
            $post->post_date,
            get_the_author_meta( 'user_login', $post->post_author ),
            $custom_fields_str
        ));
    }

    fclose( $csv_file );

    echo '<div class="updated"><p>Export completed! <a href="' . plugin_dir_url( __FILE__ ) . $csv_filename . '" download>Download CSV file</a></p></div>';
}

function cpt_custom_cfe_csv_import_cpt( $post_type ) {
    if ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
        return;
    }

    $csv_file = fopen( $_FILES['import_file']['tmp_name'], 'r' );
    $header = fgetcsv( $csv_file );

    while ( ( $row = fgetcsv( $csv_file ) ) !== FALSE ) {
        $post_title = $row[0];
        $post_content = $row[1];
        $author_username = $row[3];

        $author_id = username_exists( $author_username );
        if ( ! $author_id ) {
            $author_id = 1;
        }

        $post_data = array(
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_status'  => 'publish',
            'post_type'    => $post_type,
            'post_date'    => current_time( 'mysql' ), 
            'post_date_gmt' => current_time( 'mysql', 1 ), 
            'post_author'  => $author_id,
        );

        $post_id = wp_insert_post( $post_data );

        if ( isset( $row[4] ) && ! empty( $row[4] ) ) {
            $custom_fields = explode( '; ', $row[4] );
            foreach ( $custom_fields as $field ) {
                $field_data = explode( ': ', $field );
                if ( count( $field_data ) == 2 ) {
                    update_post_meta( $post_id, $field_data[0], $field_data[1] );
                }
            }
        }
    }

    fclose( $csv_file );

    echo '<div class="updated"><p>Import completed! Posts with title and description have been created for ' . $post_type . '.</p></div>';
}

function custom_admin_footer_content() {
    echo '
        <p id="footer-left" class="alignleft">
            by <a href="https://serat.us" target="_blank">Khoirul Aksara</a>
        </p>
        <div class="clear"></div>
    ';
}
add_action( 'in_admin_footer', 'custom_admin_footer_content' );
?>
