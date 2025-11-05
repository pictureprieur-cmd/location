<?php
// Création de la table à l'activation du plugin
register_activation_hook(__FILE__, 'dsi_location_create_table');

function dsi_location_create_table() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'dsi_location_reservations';
    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id BIGINT(20) NOT NULL,
        unit_id INT NOT NULL,
        user_id BIGINT(20) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        expected_date DATE NOT NULL COMMENT 'Date de retour prévue',
        start_hour TINYINT(2) DEFAULT NULL COMMENT '9 ou 14',
        end_hour TINYINT(2) DEFAULT NULL COMMENT '12 ou 18',
        returned TINYINT(1) DEFAULT 0 COMMENT '0 = en cours, 1 = retourné, 2 = en retard',
        waiting_validation TINYINT(1) DEFAULT 1 COMMENT '1 = en attente de commande, 0 = validé',
        unique_hash VARCHAR(64) DEFAULT NULL,
        total_price DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Montant total calculé à la réservation',
        taken TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = oui, 0 = non',
        order_id BIGINT(20) UNSIGNED DEFAULT NULL,
        cart_item_id VARCHAR(64) DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $table_name = $wpdb->prefix . 'dsi_location_maintenance';
    $sql.= "CREATE TABLE $table_name (
		id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		product_id BIGINT(20) NOT NULL,
		unit_id INT NOT NULL,
		start_date DATE NOT NULL,
		end_date DATE NOT NULL,
        note LONGTEXT DEFAULT NULL,
        completed TINYINT(1) DEFAULT 0
	) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function write_log( $data ) {
    if ( true === WP_DEBUG ) {
        if ( is_array( $data ) || is_object( $data ) ) {
            log_error( print_r( $data, true ) );
        } else {
            log_error( $data );
        }
    }
}