<?php
// Gestion des indisponibilités pour maintenance

// Ajouter une période d'indisponibilité
function dsi_location_set_maintenance($product_id, $unit_id, $start_date, $end_date, $note) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_maintenance';
    // Vérification de chevauchement avec maintenance
    if (function_exists('dsi_location_is_in_maintenance') && dsi_location_is_in_maintenance($product_id, $unit_id, $start_date, $end_date, $note)) {
        return new WP_Error('maintenance_conflict', 'Chevauchement avec une maintenance existante.');
    }
    // Vérification de chevauchement avec réservation
    if (function_exists('dsi_location_is_in_reservation') && dsi_location_is_in_reservation($product_id, $unit_id, $start_date, $end_date, $note)) {
        return new WP_Error('reservation_conflict', 'Chevauchement avec une réservation existante.');
    }
    $wpdb->insert($table, [
        'product_id'=> $product_id,
        'unit_id'   => $unit_id,
        'start_date'=> $start_date,
        'end_date'  => $end_date,
        'note'      => $note
    ]);
    return true;
}

// Récupérer les périodes d’indisponibilité pour une unité donnée
function dsi_location_get_maintenance_periods($product_id, $unit_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_maintenance';

    return $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table
        WHERE product_id = %d AND unit_id = %d AND completed = 0
    ", $product_id, $unit_id), ARRAY_A);
}

// Vérifie si une unité est en maintenance pendant une période donnée
function dsi_location_is_in_maintenance($product_id, $unit_id, $start_date, $end_date) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_maintenance';

    $conflit = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table
        WHERE product_id = %d AND unit_id = %d
        AND start_date <= %s AND end_date >= %s
        AND completed = 0
    ", $product_id, $unit_id, $end_date, $start_date));
    return $conflit > 0;
}

/**
 * Vérifie la disponibilité d'une unité pour réservation.
 *
 * @param int    $product_id   ID du produit.
 * @param int    $unit_id      ID de l'unité.
 * @param string $start_date   Date de début (YYYY-MM-DD).
 * @param string $end_date     Date de fin   (YYYY-MM-DD).
 * @param int    $exclude_id   (optionnel) ID de réservation à exclure (pour les mises à jour).
 *
 * @return true|WP_Error       true si pas de conflit, WP_Error sinon.
 */
function dsi_location_is_in_reservation( $product_id, $unit_id, $start_date, $end_date, $exclude_id = 0 ) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';

    // Construction de la requête
    $sql = "
        SELECT COUNT(*) 
        FROM {$table}
        WHERE product_id = %d
          AND unit_id    = %d
          AND start_date <= %s
          AND end_date   >= %s
          AND returned    = 0
          AND waiting_validation IN (0,1)
    ";
    $params = [ $product_id, $unit_id, $end_date, $start_date ];

    // Exclusion (pour mise à jour)
    if ( $exclude_id ) {
        $sql .= " AND id != %d";
        $params[] = $exclude_id;
    }

    $count = intval( $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) ) );

    if ( $count > 0 ) {
        return new WP_Error(
            'reservation_conflict',
            __( 'Impossible d’enregistrer : le matériel est déjà réservé sur cette période.', 'dsi-location' )
        );
    }

    return true;
}




add_action('wp_ajax_dsi_set_maintenance', 'dsi_set_maintenance');
function dsi_set_maintenance() {
    // Vérification du nonce
    check_ajax_referer('dsi_calendar','security');

    // Récupération et sanitization des données
    $product_id = intval( $_POST['product_id'] );
    $unit_id    = intval( $_POST['unit_id'] );
    $start_date = sanitize_text_field( $_POST['start_date'] );
    $end_date   = sanitize_text_field( $_POST['end_date'] );
    $note       = sanitize_textarea_field( $_POST['note'] );

    // 1) Conflit avec réservations existantes ?
    $conflict = dsi_location_is_in_reservation( $product_id, $unit_id, $start_date, $end_date );
    if ( is_wp_error( $conflict ) ) {
        wp_send_json_error([
            'message' => $conflict->get_error_message()
        ]);
    }

    // 2) Enregistrement de la maintenance
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_maintenance';
    $inserted = $wpdb->insert(
        $table,
        [
            'product_id' => $product_id,
            'unit_id'    => $unit_id,
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'note'       => $note,
            'completed'  => 0
        ],
        [ '%d','%d','%s','%s','%s','%d' ]
    );

    if ( false === $inserted ) {
        wp_send_json_error([
            'message' => 'Erreur lors de la création de la maintenance.'
        ]);
    }

    // 3) Succès
    wp_send_json_success([
        'message' => 'Produit mis en maintenance avec succès.'
    ]);
}