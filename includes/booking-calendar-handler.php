<?php
// --- HANDLERS BOOKING ---
add_action('wp_ajax_dsi_submit_reservation', 'dsi_submit_reservation');
add_action('wp_ajax_nopriv_dsi_submit_reservation', 'dsi_submit_reservation');

function dsi_submit_reservation() {
    global $wpdb;

    check_ajax_referer('dsi_calendar');
    $product_id = intval($_POST['product_id']);
    $unit_id = intval($_POST['unit_id']);
    $start_date = sanitize_text_field($_POST['start_date']);
    $end_date = sanitize_text_field($_POST['end_date']);
    $time_start = sanitize_text_field($_POST['time-start']);
    $time_end = sanitize_text_field($_POST['time-end']);
    $user_id = get_current_user_id();
    if (!$product_id || !$unit_id || !$start_date || !$end_date || !$time_start || !$time_end) {
        wp_send_json_error(['message' => 'Champs manquants.']);
    }

//$unique_hash = md5($user_id . '_' . $product_id . '_' . $unit_id . '_' . $start_date . '_' . $end_date . '_' . $time_start . '_' . $time_end);



    $result = dsi_enregistrer_reservation([
        'product_id' => $product_id,
        'unit_id'    => $unit_id,
        'user_id'    => $user_id,
        'start_date' => $start_date,
        'end_date'   => $end_date,
        'time_start' => $time_start,
        'time_end'   => $time_end,
        //'unique_hash' => $unique_hash
    ]);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success(['message' => $result['message']]);
}

function dsi_enregistrer_reservation($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $start_hour = match ($data['time_start']) {
        'matin' => dsi_get_location_hours()['matin_start'],
        'midi' => dsi_get_location_hours()['aprem_start'],
        'soir' => dsi_get_location_hours()['aprem_start'],
        default => dsi_get_location_hours()['matin_start']
    };
    $end_hour = match ($data['time_end']) {
        'midi' => dsi_get_location_hours()['matin_end'],
        'soir' => dsi_get_location_hours()['aprem_end'],
        default => dsi_get_location_hours()['aprem_end']
    };
    $product_id = intval($data['product_id']);
    $unit_id    = intval($data['unit_id']);
    $user_id    = intval($data['user_id']);
    $start_date = sanitize_text_field($data['start_date']);
    $end_date   = sanitize_text_field($data['end_date']);
    $unique_hash = $data['unique_hash'];





    $montant_total = 0;

    $price_day = floatval(get_post_meta($product_id, '_prix_journee', true));
    $price_half = floatval(get_post_meta($product_id, '_prix_demi_journee', true));
    $price_weekend = floatval(get_post_meta($product_id, '_prix_weekend', true));

    log_error('--------------------------------------------------------------------------');
    log_error('Price day : ' . $price_day);
    log_error('Price half : ' . $price_half);
    log_error('Proce weekend : ' . $price_weekend);
    
    
    try {
        $montant_total = dsi_calculate_reservation_total($start_date, intval($start_hour), $end_date, intval($end_hour), $price_day, $price_half, $price_weekend);
    } catch (\Throwable $th) {
        log_error('Error calcul montant total : ' . $th->getMessage());
        $montant_total = 0;
    }
    
    log_error('Montant total : ' . $montant_total);

    $returned = isset($data['returned']) ? intval($data['returned']) : 0;
    $start_ts = strtotime($start_date);
    $end_ts   = strtotime($end_date);
    $maintenance_table = $wpdb->prefix . 'dsi_location_maintenance';
    $maintenance_query = $wpdb->prepare("
        SELECT * FROM $maintenance_table
        WHERE product_id = %d
        AND unit_id = %d
        AND start_date <= %s
        AND end_date >= %s
        AND completed = 0
    ", $product_id, $unit_id, $end_date, $start_date);
    $maintenances = $wpdb->get_results($maintenance_query);
    if (!empty($maintenances)) {
        foreach ($maintenances as $m) {
            $m_start = strtotime($m->start_date);
            $m_end   = strtotime($m->end_date);
            for ($ts = $start_ts; $ts <= $end_ts; $ts += 86400) {
                if ($ts >= $m_start && $ts <= $m_end) {
                    return new WP_Error('maintenance_conflict', "Le créneau est déjà réservé ou non disponible.");
                }
            }
        }
    }
    for ($ts = $start_ts; $ts <= $end_ts; $ts += 86400) {
        $current_day = date('Y-m-d', $ts);
        $query = $wpdb->prepare("
            SELECT * FROM $table
            WHERE product_id = %d
            AND unit_id = %d
            AND start_date <= %s AND end_date >= %s
            AND returned = 0
            AND waiting_validation IN (0,1)
        ", $product_id, $unit_id, $current_day, $current_day);
        $conflicts = $wpdb->get_results($query);
        foreach ($conflicts as $res) {

            $res_start_hour = intval($res->start_hour);
            $res_end_hour   = intval($res->end_hour);
            if ($res_start_hour > $res_end_hour) {
                if ($current_day == $res->start_date) {
                    $res_end_hour = 18;
                } elseif ($current_day == $res->end_date) {
                    $res_start_hour = 9;
                } else {
                    $res_start_hour = 9;
                    $res_end_hour = 18;
                }
            }

            $overlap = !($end_hour <= $res_start_hour || $start_hour >= $res_end_hour);
            if ($overlap) {
                return new WP_Error('conflict_detected', "Le créneau est déjà réservé ou non disponible.");
            }
        }
    }

    $unique_hash = md5($user_id . '_' . $product_id . '_' . $unit_id . '_' . $start_date . '_' . $end_date . '_' . $start_hour . '_' . $end_hour);
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE unique_hash = %s", $unique_hash));

    if ($exists) {
        return new WP_Error('duplicate_reservation', 'Réservation déjà enregistrée.');
    }
    $inserted = $wpdb->insert(
        $table,
        [
            'product_id' => $product_id,
            'unit_id'    => $unit_id,
            'user_id'    => $user_id,
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'expected_date' => $end_date,
            'start_hour' => $start_hour,
            'end_hour'   => $end_hour,
            'returned'   => 0,
            'waiting_validation' => 1,
            'unique_hash' => $unique_hash,
            'total_price' => $montant_total,
            'cart_item_id' => ''
        ],
        ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%f', '%s']
    );

    if (!$inserted) {
        return new WP_Error('insert_failed', 'Erreur lors de l\'enregistrement.');
    }
    return ['message' => 'Réservation enregistrée avec succès.'];
}

// --- HANDLERS CALENDAR ---
function dsi_location_get_calendars($product_id) {
    $nombre_unites = (int) get_post_meta($product_id, '_nombre_articles_location', true);
    $calendars = [];
    for ($i = 1; $i <= $nombre_unites; $i++) {
        $calendars[] = [
            'unit_id' => $i,
            'reservations' => dsi_location_get_reservations_for_unit($product_id, $i)
        ];
    }
    return $calendars;
}

function dsi_location_get_reservations_for_unit($product_id, $unit_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $result = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table
        WHERE product_id = %d AND unit_id = %d AND returned = 0 AND waiting_validation IN (0,1)
    ", $product_id, $unit_id), ARRAY_A);
    return $result;
}

add_action('wp_ajax_dsi_get_calendar_events', 'dsi_get_calendar_events');
add_action('wp_ajax_nopriv_dsi_get_calendar_events', 'dsi_get_calendar_events');

function dsi_get_calendar_events() {
    check_ajax_referer('dsi_calendar');
    $product_id = intval($_POST['product_id']);
    $unit_id = intval($_POST['unit_id']);
    $start = sanitize_text_field($_POST['start']);
    $end = sanitize_text_field($_POST['end']);
    $user_id = get_current_user_id();
    if($start > $end){ $fin = $end; $end = $start; $start = $fin; }
    $reservations = dsi_location_get_reservations_for_unit($product_id, $unit_id);
    $maintenances = dsi_location_get_maintenance_periods($product_id, $unit_id);
    $events = [];
    
    // Réservations validées
    foreach ($reservations as $r) {
        $start_date = new DateTime($r['start_date']);
        $end_date = new DateTime($r['end_date']);
        $current = clone $start_date;
        
        while ($current <= $end_date) {
            $classe = [];
            $date_str = $current->format('Y-m-d');
            
            if ($current == $start_date && $current == $end_date) {
                // Même jour - début et fin
                if ($r['start_hour'] == dsi_get_location_hours()['matin_start'] && $r['end_hour'] == dsi_get_location_hours()['matin_end']) {
                    $classe = ['demi', 'matin'];
                } elseif ($r['start_hour'] == dsi_get_location_hours()['aprem_start'] && $r['end_hour'] == dsi_get_location_hours()['aprem_end']) {
                    $classe = ['demi', 'aprem'];
                } elseif ($r['start_hour'] == dsi_get_location_hours()['matin_start'] && $r['end_hour'] == dsi_get_location_hours()['aprem_end']) {
                    $classe = ['jour'];
                } elseif ($r['start_hour'] == dsi_get_location_hours()['matin_end'] && $r['end_hour'] == dsi_get_location_hours()['aprem_end']) {
                    // Début midi (12h) jusqu'au soir (18h) => demi-journée aprem
                    $classe = ['demi', 'aprem'];
                }
            } elseif ($current == $start_date) {
                // Premier jour seulement
                if ($r['start_hour'] == dsi_get_location_hours()['aprem_start']) {
                    $classe = ['demi', 'aprem'];
                } elseif ($r['start_hour'] == dsi_get_location_hours()['matin_start']) {
                    $classe = ['jour'];
                } elseif ($r['start_hour'] == dsi_get_location_hours()['matin_end']) {
                    // Début midi => demi-journée aprem
                    $classe = ['demi', 'aprem'];
                }
            } elseif ($current == $end_date) {
                // Dernier jour seulement
                if ($r['end_hour'] == dsi_get_location_hours()['matin_end']) {
                    $classe = ['demi', 'matin'];
                } elseif ($r['end_hour'] == dsi_get_location_hours()['aprem_end']) {
                    $classe = ['jour'];
                }
            } else {
                $classe = ['jour'];
            }

            $events[] = [
                'start' => $date_str,
                'end' => (new DateTime($date_str))->modify('+1 day')->format('Y-m-d'),
                'display' => 'background',
                'color' => ($user_id == $r['user_id']) ? '#00a812' : '#fdbaba',
                'className' => $classe,
                'title' => '',
                'pending' => false
            ];
            $current->modify('+1 day');
        }
    }
    
    // Maintenances
    foreach ($maintenances as $m) {
        $events[] = [
            'start' => $m['start_date'],
            'end' => (new DateTime($m['end_date']))->modify('+1 day')->format('Y-m-d'),
            'display' => 'background',
            'color' => '#77f1f1ff',
            'className' => ['maintenance'],
            'title' => 'Maintenance',
            'pending' => false
        ];
    }
    wp_send_json_success($events);
}

add_action('wp_ajax_dsi_get_user_reservations', 'dsi_get_user_reservations');
add_action('wp_ajax_nopriv_dsi_get_user_reservations', 'dsi_get_user_reservations');

function dsi_get_user_reservations() {
    check_ajax_referer('dsi_calendar');
    $product_id = intval($_POST['product_id']);
    $unit_id = intval($_POST['unit_id']);
    $user_id = get_current_user_id();
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT id, start_date, end_date, start_hour, end_hour
        FROM $table
        WHERE product_id = %d AND unit_id = %d AND user_id = %d AND returned = 0
        ORDER BY start_date ASC
    ", $product_id, $unit_id, $user_id), ARRAY_A);
    foreach ($results as &$r) {
        $r['start_date_fr'] = function_exists('dsi_format_date_fr') ? dsi_format_date_fr($r['start_date']) : $r['start_date'];
        $r['end_date_fr'] = function_exists('dsi_format_date_fr') ? dsi_format_date_fr($r['end_date']) : $r['end_date'];
        $r['time_label'] = dsi_format_time_label($r['start_hour'], $r['end_hour']);
    }
    wp_send_json_success($results);
}

function dsi_format_time_label($start, $end) {
    $hours = dsi_get_location_hours();
    
    if ($start == $hours['matin_start'] && $end == $hours['matin_end']) return 'Matin';//9-12
    if ($start == $hours['aprem_start'] && $end == $hours['aprem_end']) return 'Après-midi';//14-18
    if ($start == $hours['matin_end'] && $end == $hours['aprem_end']) return 'Après-midi';//12-18 (retrait midi -> retour soir)
    if ($start == $hours['matin_start'] && $end == $hours['aprem_end']) return 'Journée';//9-18
    return "$start h - $end h";
}

add_action('wp_ajax_dsi_cancel_reservation', 'dsi_cancel_reservation');
add_action('wp_ajax_nopriv_dsi_cancel_reservation', 'dsi_cancel_reservation');

function dsi_cancel_reservation() {
    check_ajax_referer('dsi_calendar');
    $reservation_id = intval($_POST['reservation_id']);
    $user_id = get_current_user_id();
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    // Récupération de la réservation (pour obtenir la clé panier)
    $reservation = $wpdb->get_row($wpdb->prepare(
        "SELECT id, cart_item_id FROM {$table} WHERE id = %d AND user_id = %d",
        $reservation_id,
        $user_id
    ));

    // Tentative de suppression de l'item dans le panier si présent
    if ($reservation && !empty($reservation->cart_item_id) && function_exists('WC')) {
        $cart = WC()->cart;
        if ($cart) {
            // Supprime l'item; si absent, ignore
            $removed = $cart->remove_cart_item($reservation->cart_item_id);
            if (method_exists($cart, 'set_session')) {
                $cart->set_session();
            }
        }
    }

    // Suppression de la réservation en base
    $deleted = $wpdb->delete(
        $table,
        ['id' => $reservation_id, 'user_id' => $user_id],
        ['%d', '%d']
    );
    if ($deleted !== false) {
        wp_send_json_success(['message' => 'Réservation supprimée.']);
    } else {
        wp_send_json_error(['message' => 'Erreur lors de la suppression.']);
    }
} 