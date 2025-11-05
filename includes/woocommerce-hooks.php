<?php
// Hooks WooCommerce pour DSI Location

// Hook : Validation de commande, mise à jour de la réservation en attente
//remove_action('woocommerce_checkout_order_processed', 'dsi_location_save_reservation_on_order', 20);
add_action('woocommerce_new_order', 'dsi_location_save_reservation_on_order', 20, 1);
function dsi_location_save_reservation_on_order($order_id) {
global $wpdb;
$order = wc_get_order($order_id);
    $table = $wpdb->prefix . 'dsi_location_reservations';
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $unique_hash = '';
        $meta = $item->get_meta('dsi_location');
        if (!empty($meta['is_dsi_reservation'])) {

            $user_id = $order->get_user_id();
            $unit_id = intval($meta['unit_id'] ?? 0);
            $start_date = sanitize_text_field($meta['start_date'] ?? '');
            $end_date = sanitize_text_field($meta['end_date'] ?? $start_date);
            $expected_date = sanitize_text_field($meta['expected_date'] ?? $end_date);
            $time_start = $meta['time-start'] ?? 'jour';
            $time_end = $meta['time-end'] ?? 'jour';
            // Conversion horaires
            $start_hour = ($time_start === 'matin') ? dsi_get_location_hours()['matin_start'] : (($time_start === 'midi') ? dsi_get_location_hours()['aprem_start'] : (($time_start === 'soir') ? dsi_get_location_hours()['aprem_start'] : dsi_get_location_hours()['matin_start']));
            $end_hour = ($time_end === 'midi') ? dsi_get_location_hours()['matin_end'] : (($time_end === 'soir') ? dsi_get_location_hours()['aprem_end'] : dsi_get_location_hours()['aprem_end']);

            // Calcul du montant total de la réservation
            $price_day = floatval(get_post_meta($product_id, '_prix_journee', true));
            $price_half = floatval(get_post_meta($product_id, '_prix_demi_journee', true));
            $price_weekend = floatval(get_post_meta($product_id, '_prix_weekend', true));
            try {
                $montant_total = dsi_calculate_reservation_total($start_date, intval($start_hour), $end_date, intval($end_hour), $price_day, $price_half, $price_weekend);
            } catch (\Throwable $th) {
                $montant_total = 0;
            }

            // Utilisation d'un identifiant unique pour fiabiliser la mise à jour
            $unique_hash = md5($user_id . '_' . $product_id . '_' . $unit_id . '_' . $start_date . '_' . $end_date . '_' . $start_hour . '_' . $end_hour);

            if (!empty($meta['unique_hash'])) {
                $unique_hash = sanitize_text_field($meta['unique_hash']);

                $sql = $wpdb->prepare(
                    "UPDATE {$table} SET order_id = %d WHERE unique_hash = %s",
                    $order_id,
                    $unique_hash
                );

                $result = $wpdb->query($sql); // Renvoie le nombre de lignes affectées ou false en cas d'erreur

                if ($result === false) {
                    log_error("ERREUR UPDATE SQL : " . $wpdb->last_error);
                } elseif ($result === 0) {
                    log_error("UPDATE exécutée, mais 0 ligne mise à jour (vérifier la valeur du hash)");
                } else {
                    log_error("UPDATE réussie : $result ligne(s) mise(s) à jour");
                }
            }
            // Mise à jour de la réservation en attente pour la passer à validée (et définir le total)
            $updated = $wpdb->update($table,
                ['waiting_validation' => 0, 'expected_date' => $expected_date, 'total_price' => $montant_total, 'order_id' => $order_id],
                ['unique_hash' => $unique_hash],
                ['%d', '%s', '%f', '%d'],
                ['%s']
            );
            // Si aucune réservation en attente n'a été trouvée, on insère (cas rare)
            if (!$updated) {
                $wpdb->insert($table, [
                    'product_id' => $product_id,
                    'unit_id' => $unit_id,
                    'user_id' => $user_id,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'expected_date' => $expected_date,
                    'start_hour' => $start_hour,
                    'end_hour' => $end_hour,
                    'returned' => 0,
                    'waiting_validation' => 0,
                    'unique_hash' => $unique_hash,
                    'total_price' => $montant_total,
                    'order_id' => $order_id,
                    'cart_item_id' => ''
                ], [
                    '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%f', '%d', '%s'
                ]);
                // Supprimer la réservation en attente si elle existe encore (doublon)
                $wpdb->delete($table, [
                    'unique_hash' => $unique_hash,
                    'waiting_validation' => 1
                ]);
            }
        }
    }
}


// Copier le meta dsi_location dans l'order item lors de la création de la commande (hook moderne)
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!empty($values['dsi_location'])) {
        $item->add_meta_data('dsi_location', $values['dsi_location'], true);
        // Ajoute un meta lisible pour l'affichage (admin + emails)
        $hours = dsi_get_location_hours();
        $start_date = $values['dsi_location']['start_date'] ?? '';
        $end_date   = $values['dsi_location']['end_date'] ?? '';
        $time_start = $values['dsi_location']['time-start'] ?? '';
        $time_end   = $values['dsi_location']['time-end'] ?? '';
        $unit_id    = $values['dsi_location']['unit_id'] ?? '';
        $product_id = $item->get_product_id();

        $start_hour = ($time_start === 'matin') ? $hours['matin_start'] : (($time_start === 'midi' || $time_start === 'soir') ? $hours['aprem_start'] : $hours['matin_start']);
        $end_hour   = ($time_end === 'midi') ? $hours['matin_end'] : (($time_end === 'soir') ? $hours['aprem_end'] : $hours['aprem_end']);

        $start_fr = function_exists('dsi_format_date_fr') ? dsi_format_date_fr($start_date) : $start_date;
        $end_fr   = function_exists('dsi_format_date_fr') ? dsi_format_date_fr($end_date) : $end_date;

        // Caution et total
        $deposit = floatval(get_post_meta($product_id, '_montant_caution', true));
        $price_day = floatval(get_post_meta($product_id, '_prix_journee', true));
        $price_half = floatval(get_post_meta($product_id, '_prix_demi_journee', true));
        $price_weekend = floatval(get_post_meta($product_id, '_prix_weekend', true));
        try {
            $total_loc = dsi_calculate_reservation_total($start_date, intval($start_hour), $end_date, intval($end_hour), $price_day, $price_half, $price_weekend);
        } catch (\Throwable $th) {
            $total_loc = 0;
        }

        $list_str = sprintf(
            'Unité %s<br>Du %s %sh au %s %sh<br>Caution: %s<br>Total location: %s',
            esc_html($unit_id),
            $start_fr,
            $start_hour,
            $end_fr,
            $end_hour,
            function_exists('wc_price') ? wc_price($deposit) : (number_format_i18n($deposit, 2) . '€'),
            function_exists('wc_price') ? wc_price($total_loc) : (number_format_i18n($total_loc, 2) . '€')
        );
        $item->add_meta_data('_dsi_location_list', $list_str, true);
    }
}, 10, 4);

// Après ajout au panier, enregistrer la cart_item_key dans la réservation liée
add_action('woocommerce_add_to_cart', function($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    if (empty($cart_item_data['dsi_location']['is_dsi_reservation'])) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';

    $unique_hash = isset($cart_item_data['dsi_location']['unique_hash']) ? sanitize_text_field($cart_item_data['dsi_location']['unique_hash']) : '';
    if (!$unique_hash) {
        return;
    }

    // Log de diagnostic temporaire
    if (function_exists('log_error')) {
        log_error('woocommerce_add_to_cart: cart_item_key=' . $cart_item_key . ' unique_hash=' . $unique_hash);
    }

    // Enregistre la clé de l'item du panier pour traçabilité
    $updated = $wpdb->update(
        $table,
        [ 'cart_item_id' => $cart_item_key ],
        [ 'unique_hash' => $unique_hash ],
        [ '%s' ],
        [ '%s' ]
    );

    if (function_exists('log_error')) {
        if ($updated === false) {
            log_error('woocommerce_add_to_cart: update error => ' . $wpdb->last_error);
        } else {
            log_error('woocommerce_add_to_cart: rows updated => ' . $updated);
        }
    }

    // Insère une version lisible dans l'item du panier (pour affichage dans le panier, y compris Blocks)
    if (function_exists('WC')) {
        $cart = WC()->cart;
        if ($cart && isset($cart->cart_contents[$cart_item_key])) {
            $hours = dsi_get_location_hours();
            $start_date = $cart_item_data['dsi_location']['start_date'] ?? '';
            $end_date   = $cart_item_data['dsi_location']['end_date'] ?? '';
            $time_start = $cart_item_data['dsi_location']['time-start'] ?? '';
            $time_end   = $cart_item_data['dsi_location']['time-end'] ?? '';
            $unit_id    = $cart_item_data['dsi_location']['unit_id'] ?? '';

            $start_hour = ($time_start === 'matin') ? $hours['matin_start'] : (($time_start === 'midi' || $time_start === 'soir') ? $hours['aprem_start'] : $hours['matin_start']);
            $end_hour   = ($time_end === 'midi') ? $hours['matin_end'] : (($time_end === 'soir') ? $hours['aprem_end'] : $hours['aprem_end']);
            $start_fr = function_exists('dsi_format_date_fr') ? dsi_format_date_fr($start_date) : $start_date;
            $end_fr   = function_exists('dsi_format_date_fr') ? dsi_format_date_fr($end_date) : $end_date;

            // Caution et total
            $deposit = floatval(get_post_meta($product_id, '_montant_caution', true));
            $price_day = floatval(get_post_meta($product_id, '_prix_journee', true));
            $price_half = floatval(get_post_meta($product_id, '_prix_demi_journee', true));
            $price_weekend = floatval(get_post_meta($product_id, '_prix_weekend', true));
            try {
                $total_loc = dsi_calculate_reservation_total($start_date, intval($start_hour), $end_date, intval($end_hour), $price_day, $price_half, $price_weekend);
            } catch (\Throwable $th) {
                $total_loc = 0;
            }

            $list_str = sprintf(
                'Unité %s - Du %s %sh au %s %sh - Caution: %s - Total location: %s',
                esc_html($unit_id),
                $start_fr,
                $start_hour,
                $end_fr,
                $end_hour,
                function_exists('wc_price') ? wc_price($deposit) : (number_format_i18n($deposit, 2) . '€'),
                function_exists('wc_price') ? wc_price($total_loc) : (number_format_i18n($total_loc, 2) . '€')
            );

            $cart->cart_contents[$cart_item_key]['dsi_location_list'] = $list_str;
            if (method_exists($cart, 'set_session')) {
                $cart->set_session();
            }
        }
    }
}, 10, 6);
// Affichage des infos DSI dans le panier (classique + Blocks via Store API)
add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (!empty($cart_item['dsi_location_list'])) {
        $item_data[] = [
            'key'   => __('Réservation', 'dsi-location'),
            'value' => wp_kses_post($cart_item['dsi_location_list']),
            'display' => wp_kses_post($cart_item['dsi_location_list']),
        ];
    }
    return $item_data;
}, 10, 2);

// Ne pas masquer le meta _dsi_location_list dans l'admin commande
add_filter('woocommerce_hidden_order_itemmeta', function($hidden) {
    $idx = array_search('_dsi_location_list', $hidden, true);
    if ($idx !== false) {
        unset($hidden[$idx]);
    }
    return $hidden;
});


// Lorsqu'un article est supprimé du panier (minipanier ou page panier),
// supprimer la réservation en attente correspondante dans la DB
add_action('woocommerce_remove_cart_item', function($cart_item_key, $cart) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $user_id = get_current_user_id();
    if (function_exists('log_error')) {
        log_error('woocommerce_remove_cart_item: key=' . $cart_item_key . ' user=' . $user_id);
    }
    $deleted = $wpdb->delete(
        $table,
        [
            'cart_item_id' => $cart_item_key,
            'user_id' => $user_id,
            'waiting_validation' => 1,
        ],
        ['%s', '%d', '%d']
    );
    if (function_exists('log_error')) {
        log_error('woocommerce_remove_cart_item: rows deleted => ' . ($deleted === false ? 'ERR ' . $wpdb->last_error : $deleted));
    }
}, 10, 2);

// Redondance de sécurité (certains thèmes/plugins déclenchent cet autre hook)
add_action('woocommerce_cart_item_removed', function($cart_item_key, $cart) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $user_id = get_current_user_id();
    if (function_exists('log_error')) {
        log_error('woocommerce_cart_item_removed: key=' . $cart_item_key . ' user=' . $user_id);
    }
    $deleted = $wpdb->delete(
        $table,
        [
            'cart_item_id' => $cart_item_key,
            'user_id' => $user_id,
            'waiting_validation' => 1,
        ],
        ['%s', '%d', '%d']
    );
    if (function_exists('log_error')) {
        log_error('woocommerce_cart_item_removed: rows deleted => ' . ($deleted === false ? 'ERR ' . $wpdb->last_error : $deleted));
    }
}, 10, 2);

// Au chargement du panier côté client, supprimer les items marqués côté admin
add_action('woocommerce_cart_loaded_from_session', function($cart) {
    $user_id = get_current_user_id();
    if (!$user_id) return;
    $keys = get_user_meta($user_id, 'dsi_cart_pending_removals', true);
    if (!is_array($keys) || empty($keys)) return;

    $cart_items = $cart->get_cart();
    foreach ($keys as $key) {
        if (isset($cart_items[$key])) {
            $cart->remove_cart_item($key);
        }
    }
    if (method_exists($cart, 'set_session')) {
        $cart->set_session();
    }
    delete_user_meta($user_id, 'dsi_cart_pending_removals');

    if (function_exists('log_error')) {
        log_error('woocommerce_cart_loaded_from_session: removed keys => ' . json_encode($keys));
    }
}, 100, 1);

// Forcer tous les produits à être achetables pour la location
add_filter('woocommerce_is_purchasable', function($purchasable, $product) {
    return true;
}, 10, 2);

// Forcer le prix de la ligne panier selon la réservation DSI (calcul dynamique)
//add_action('woocommerce_before_calculate_totals', 'dsi_location_set_custom_price', 20, 1);
function dsi_location_set_custom_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $cart_item) {
        if (!empty($cart_item['dsi_location']['is_dsi_reservation'])) {
            $product_id = $cart_item['product_id'];
            $prix_demi_journee = floatval(get_post_meta($product_id, '_prix_demi_journee', true));
            $prix_journee = floatval(get_post_meta($product_id, '_prix_journee', true));
            $prix_weekend = floatval(get_post_meta($product_id, '_prix_weekend', true));

            $start_date = $cart_item['dsi_location']['start_date'] ?? '';
            $end_date   = $cart_item['dsi_location']['end_date'] ?? '';
            $time_start = $cart_item['dsi_location']['time-start'] ?? '';
            $time_end   = $cart_item['dsi_location']['time-end'] ?? '';

            // Si données incomplètes, fallback minimal: demi-journée si dispo, sinon journée, sinon weekend
            if (!$start_date || !$end_date || !$time_start || !$time_end) {
                if ($prix_demi_journee > 0) {
                    $cart_item['data']->set_price($prix_demi_journee);
                } elseif ($prix_journee > 0) {
                    $cart_item['data']->set_price($prix_journee);
                } elseif ($prix_weekend > 0) {
                    $cart_item['data']->set_price($prix_weekend);
                }
                continue;
            }

            // Conversion horaires cohérente avec le backend
            $hours = dsi_get_location_hours();
            $start_hour = ($time_start === 'matin') ? $hours['matin_start'] : (($time_start === 'midi' || $time_start === 'soir') ? $hours['aprem_start'] : $hours['matin_start']);
            $end_hour   = ($time_end === 'midi') ? $hours['matin_end'] : (($time_end === 'soir') ? $hours['aprem_end'] : $hours['aprem_end']);

            try {
                $total = dsi_calculate_reservation_total($start_date, intval($start_hour), $end_date, intval($end_hour), $prix_journee, $prix_demi_journee, $prix_weekend);
                if ($total > 0) {
                    $cart_item['data']->set_price($total);
                }
            } catch (\Throwable $th) {
                // En cas d'erreur de calcul, fallback mou
                if ($prix_demi_journee > 0) {
                    $cart_item['data']->set_price($prix_demi_journee);
                } elseif ($prix_journee > 0) {
                    $cart_item['data']->set_price($prix_journee);
                } elseif ($prix_weekend > 0) {
                    $cart_item['data']->set_price($prix_weekend);
                }
            }
        }
    }
}

add_action('woocommerce_admin_order_item_headers', 'dsi_afficher_bloc_reservation_separe');
function dsi_afficher_bloc_reservation_separe($order) {
    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT total_price FROM $table WHERE order_id = %d",
        $order->get_id()
    ));

    if (!empty($results)) {
        $total = 0;
        foreach ($results as $row) {
            $total += floatval($row->total_price);
        }
        echo '<div style="margin:0 0 20px 0;padding:10px;border:1px solid #ccc;background:#f9f9f9;font-size: 16px;">';
        echo '<strong>Montant total de la réservation :</strong> ' . wc_price($total);
        echo '</div>';
    }
}

