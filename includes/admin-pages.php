<?php
// Fusion settings-page.php + returns-page.php
    require_once __DIR__ . '/class-dsi-reservations-list-table.php';

// --- MENU & PAGES ADMIN ---

add_action('admin_menu', 'dsi_location_setup_menu', 10);

function dsi_location_setup_menu() {

    add_menu_page(
        'DSI Location',
        'DSI Location',
        'manage_options',
        'dsi-location-returns',         // ← parent slug = réservation
        'dsi_location_returns_page',    // ← callback de Réservation
        '',                             // icône (facultatif)
                                        // position dans le menu
    );

    // On ajoute les autres sous‑menus
    add_submenu_page(
        'dsi-location-returns',
        'Réservation',                  // page title
        'Réservation',                  // menu title
        'manage_options',
        'dsi-location-returns',         // même slug
        'dsi_location_returns_page'     // même callback
    );
    add_submenu_page(
        'dsi-location-returns',
        'Maintenance',
        'Maintenance',
        'manage_woocommerce',
        'dsi-location-maintenance',
        'dsi_location_admin_maintenance_page'
    );
    add_submenu_page(
        'dsi-location-returns',
        'Réglages',
        'Réglages',
        'manage_options',
        'dsi-location-settings',
        'dsi_location_settings_page'
    );
}

// --- PAGE RÉGLAGES ---
function dsi_location_settings_page() {
    ?>
    <div class="wrap">
        <h1>Réglages</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('dsi_location_settings');
            do_settings_sections('dsi-location-settings');

            // Champs horaires personnalisés
            $hours = get_option('dsi_location_hours', [
                'matin_start' => '9',
                'matin_end' => '12',
                'aprem_start' => '14',
                'aprem_end' => '18',
            ]);
            // Récupération du lien image forfait de transport
            $transport_map = get_option('dsi_location_transpot_map', '');

            // Récupération de la catégorie sélectionnée
            $selected_category = get_option('dsi_related_product_category', '');

            // Récupération des catégories WooCommerce
            $product_categories = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC'
            ]);
            ?>
            <h2>Horaires de réservation</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="dsi_location_hours_matin_start">Horaire début de matinée</label></th>
                    <td><input type="text" id="dsi_location_hours_matin_start" name="dsi_location_hours[matin_start]" value="<?= esc_attr($hours['matin_start']) ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="dsi_location_hours_matin_end">Horaire fin de matinée</label></th>
                    <td><input type="text" id="dsi_location_hours_matin_end" name="dsi_location_hours[matin_end]" value="<?= esc_attr($hours['matin_end']) ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="dsi_location_hours_aprem_start">Horaire début d'après-midi</label></th>
                    <td><input type="text" id="dsi_location_hours_aprem_start" name="dsi_location_hours[aprem_start]" value="<?= esc_attr($hours['aprem_start']) ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="dsi_location_hours_aprem_end">Horaire fin d'après-midi</label></th>
                    <td><input type="text" id="dsi_location_hours_aprem_end" name="dsi_location_hours[aprem_end]" value="<?= esc_attr($hours['aprem_end']) ?>" required></td>
                </tr>
            </table>

            <h2>Forfait de transport</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="dsi_location_transpot_map">Lien image forfait de transport</label></th>
                    <td><input type="url" id="dsi_location_transpot_map" name="dsi_location_transpot_map" value="<?= esc_attr($transport_map) ?>" class="regular-text"></td>
                </tr>
            </table>

            <h2>Produits associés</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="dsi_related_product_category">Catégorie de produits associés</label></th>
                    <td>
                        <select id="dsi_related_product_category" name="dsi_related_product_category">
                            <option value="">-- Aucune catégorie --</option>
                            <?php foreach ($product_categories as $category): ?>
                                <?php if ($category->name === 'Non classé') continue; ?>
                                <option value="<?= esc_attr($category->term_id) ?>" <?php selected($selected_category, $category->term_id); ?>>
                                    <?= esc_html($category->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            
            <?php
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', function () {
    register_setting('dsi_location_settings', 'dsi_location_cancellation_days');
    register_setting('dsi_location_settings', 'dsi_location_hours');
    register_setting('dsi_location_settings', 'dsi_location_transpot_map', [
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => ''
    ]);
    register_setting('dsi_location_settings', 'dsi_related_product_category', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => ''
    ]);
    add_settings_section('dsi_location_main_section', 'Délais d\'annulation par catégorie', null, 'dsi-location-settings');
    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    foreach ($categories as $cat) {
        if ($cat->name === 'Non classé')
            continue;
        add_settings_field("cat_{$cat->term_id}", $cat->name, function () use ($cat) {
            $options = get_option('dsi_location_cancellation_days', []);
            $value = $options[$cat->term_id] ?? '';
            echo "<input type='number' name='dsi_location_cancellation_days[{$cat->term_id}]' value='" . esc_attr($value) . "' min='0' /> jours";
        }, 'dsi-location-settings', 'dsi_location_main_section');
    }
});

// --- PAGE MAINTENANCE ---
function dsi_location_admin_maintenance_page() {
    global $wpdb;

    // 1) Mise à jour existante
    if ( isset($_POST['dsi_update_maintenance'])
    && check_admin_referer('dsi_calendar','dsi_calendar_nonce') ) {

        $id      = intval($_POST['maintenance-id']);
        $product = intval($_POST['product_id']);
        $unit    = intval($_POST['unit_id']);
        $start   = sanitize_text_field($_POST['start_date']);
        $end     = sanitize_text_field($_POST['end_date']);
        $note    = sanitize_textarea_field($_POST['note']);

        // ➤ 1.a) Vérification de conflit avec les réservations existantes
        $conflict = dsi_location_is_in_reservation( $product, $unit, $start, $end );
        if ( is_wp_error( $conflict ) ) {
            echo '<div class="notice notice-error"><p>'
            . esc_html( $conflict->get_error_message() )
            . '</p></div>';
        } else {
            // ➤ 1.b) Mise à jour en base
            $updated = $wpdb->update(
                "{$wpdb->prefix}dsi_location_maintenance",
                [
                    'product_id' => $product,
                    'unit_id'    => $unit,
                    'start_date' => $start,
                    'end_date'   => $end,
                    'note'       => $note,
                ],
                [ 'id' => $id ],
                [ '%d','%d','%s','%s','%s' ],
                [ '%d' ]
            );
            if ( false === $updated ) {
                echo '<div class="notice notice-error"><p>Erreur lors de la mise à jour.</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>Maintenance mise à jour avec succès.</p></div>';
            }
        }
    }


    // 2) Insertion d'une nouvelle maintenance
    if ( isset($_POST['dsi_add_maintenance'])
    && check_admin_referer('dsi_calendar','dsi_calendar_nonce') ) {

        $product = intval($_POST['product_id']);
        $unit    = intval($_POST['unit_id']);
        $start   = sanitize_text_field($_POST['start_date']);
        $end     = sanitize_text_field($_POST['end_date']);
        $note    = sanitize_textarea_field($_POST['note']);

        // ➤ 2.a) Vérification de conflit avec les réservations existantes
        $conflict = dsi_location_is_in_reservation( $product, $unit, $start, $end );
        if ( is_wp_error( $conflict ) ) {
            echo '<div class="notice notice-error"><p>'
            . esc_html( $conflict->get_error_message() )
            . '</p></div>';
        } else {
            // ➤ 2.b) Insertion en base
            $result = $wpdb->insert(
                "{$wpdb->prefix}dsi_location_maintenance",
                [
                    'product_id' => $product,
                    'unit_id'    => $unit,
                    'start_date' => $start,
                    'end_date'   => $end,
                    'note'       => $note,
                    'completed'  => 0
                ],
                [ '%d','%d','%s','%s','%s','%d' ]
            );
            if ( false === $result ) {
                echo '<div class="notice notice-error"><p>Erreur lors de l\'ajout de la maintenance.</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>Maintenance ajoutée avec succès.</p></div>';
            }
        }
    }

    // 3) Récupération des données pour la vue
    // Produits (à adapter selon votre CPT)
    $products = get_posts([
        'post_type'      => 'product',   // ou votre CPT
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC'
    ]);

    // Date du jour au format YYYY-MM-DD pour min="" sur les <input type="date">
    $today = date('Y-m-d');

    // Périodes de maintenance existantes
    $maintenances = $wpdb->get_results(
        "SELECT * 
         FROM {$wpdb->prefix}dsi_location_maintenance 
         ORDER BY start_date DESC"
    );

    // 4) Inclusion de la vue (où est votre form + tableau)
    //    Ajustez le chemin au besoin
    include plugin_dir_path(__FILE__) . '../views/admin-maintenance.php';
}



add_action('admin_init', function () {
    if (isset($_POST['dsi_mark_maintenance_done'])) {
        global $wpdb;
        $maintenance_id = intval($_POST['dsi_mark_maintenance_done']);
        $table = $wpdb->prefix . 'dsi_location_maintenance';
        $wpdb->update($table, ['completed' => 1], ['id' => $maintenance_id]);
        $type = 'success';
        $message = 'Maintenance marquée comme terminée.';
        include dirname(__DIR__) . '/views/partials/notice.php';
        wp_redirect(admin_url('admin.php?page=dsi-location-maintenance'));
        exit;
    }
});

// --- PAGE RETOURS ---
function dsi_location_returns_page() {

    global $wpdb;
    $per_page = 20;
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page - 1) * $per_page;
    $product_filter = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    $where = '';
    $params = [];
    if ($product_filter) {
        $where = 'WHERE r.product_id = %d';
        $params[] = $product_filter;
    }
    if ($where) {
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}dsi_location_reservations r $where",
            ...$params
        ));
    } else {
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dsi_location_reservations r");
    }
    $params_with_limits = $params;
    $params_with_limits[] = $per_page;
    $params_with_limits[] = $offset;
    $results = $where
        ? $wpdb->get_results($wpdb->prepare("
            SELECT r.*, p.post_title
            FROM {$wpdb->prefix}dsi_location_reservations r
            JOIN {$wpdb->prefix}posts p ON r.product_id = p.ID
            $where
            ORDER BY r.end_date DESC
            LIMIT %d OFFSET %d
        ", ...$params_with_limits))
        : $wpdb->get_results($wpdb->prepare("
            SELECT r.*, p.post_title
            FROM {$wpdb->prefix}dsi_location_reservations r
            JOIN {$wpdb->prefix}posts p ON r.product_id = p.ID
            ORDER BY r.end_date DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
    $product_list = $wpdb->get_results("
        SELECT DISTINCT r.product_id, p.post_title
        FROM {$wpdb->prefix}dsi_location_reservations r
        JOIN {$wpdb->prefix}posts p ON r.product_id = p.ID
        ORDER BY p.post_title ASC
    ");
    include dirname(__DIR__) . '/views/admin-returns.php';
}

add_action('admin_init', function () {
    if (isset($_POST['dsi_mark_returned'])) {
        global $wpdb;
        $id = intval($_POST['dsi_mark_returned']);
        $table = $wpdb->prefix . "dsi_location_reservations";
        $wpdb->update($table, ['returned' => 1], ['id' => $id]);
        $type = 'success';
        $message = 'Produit marqué comme retourné.';
        include dirname(__DIR__) . '/views/partials/notice.php';
        wp_redirect(admin_url('admin.php?page=dsi-location-returns'));
        exit;
    }
});

add_action('wp_ajax_dsi_admin_add_maintenance', 'dsi_admin_add_maintenance');
function dsi_admin_add_maintenance() {
    check_ajax_referer('dsi_calendar');
    $product_id = intval($_POST['product_id'] ?? 0);
    $unit_id = intval($_POST['unit_id'] ?? 0);
    $start_date = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date = sanitize_text_field($_POST['end_date'] ?? '');
    $note = ($_POST['note'] ?? '');
    if (!$product_id || !$unit_id || !$start_date || !$end_date || !$note) {
        wp_send_json_error(['message' => 'Champs manquants.']);
    }
    if (function_exists('dsi_location_is_in_maintenance') && dsi_location_is_in_maintenance($product_id, $unit_id, $start_date, $end_date, $note)) {
        wp_send_json_error(['message' => 'Chevauchement avec une maintenance existante !']);
    }
    $result = dsi_location_set_maintenance($product_id, $unit_id, $start_date, $end_date, $note);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success(['message' => 'Période de maintenance ajoutée.']);
}

// Action admin pour annuler une réservation depuis la liste (supprime aussi l'item du panier)
add_action('admin_post_dsi_admin_cancel_reservation', function() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('Interdit');
    }
    $id = intval( $_GET['reservation'] ?? 0 );
    if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'dsi_admin_cancel_reservation_' . $id ) ) {
        wp_die('Nonce invalide');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $row = $wpdb->get_row( $wpdb->prepare("SELECT cart_item_id, user_id FROM {$table} WHERE id = %d", $id) );

    // Tentative de suppression de l'item du panier si c'est le panier du même utilisateur et si session accessible
    if ( $row && ! empty( $row->cart_item_id ) && function_exists('WC') ) {
        $cart = WC()->cart;
        if ( $cart ) {
            $cart->remove_cart_item( $row->cart_item_id );
            if ( method_exists($cart, 'set_session') ) {
                $cart->set_session();
            }
        }
    }

    // Si la session n'est pas accessible ici, marquer la clé pour suppression au prochain chargement du panier du client
    if ( $row && ! empty( $row->cart_item_id ) ) {
        $cart_item_key = $row->cart_item_id;
        $keys = get_user_meta( $row->user_id, 'dsi_cart_pending_removals', true );
        if ( ! is_array( $keys ) ) $keys = [];
        if ( ! in_array( $cart_item_key, $keys, true ) ) {
            $keys[] = $cart_item_key;
            update_user_meta( $row->user_id, 'dsi_cart_pending_removals', $keys );
        }
    }

    // Suppression en base
    $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

    wp_redirect( wp_get_referer() );
    exit;
});

// --- HELPERS ---
function dsi_render_admin_pagination($total_items, $per_page, $current_page, $product_filter = 0) {
    $total_pages = ceil($total_items / $per_page);
    if ($total_pages <= 1) return;
        $base_url = admin_url('admin.php?page=dsi-location-returns');
    if ($product_filter) {
        $base_url = add_query_arg('product_id', $product_filter, $base_url);
    }
    $prev_page = max(1, $current_page - 1);
    $next_page = min($total_pages, $current_page + 1);
    echo '<div class="tablenav-pages">';
    echo '<span class="displaying-num">' . esc_html($total_items) . ' élément' . ($total_items > 1 ? 's' : '') . '</span>';
    echo '<span class="pagination-links">';
    if ($current_page > 1) {
        echo '<a class="first-page button" href="' . esc_url(add_query_arg('paged', 1, $base_url)) . '"><span class="screen-reader-text">Première page</span><span aria-hidden="true">&laquo;</span></a>';
    } else {
        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
    }
    if ($current_page > 1) {
        echo '<a class="prev-page button" href="' . esc_url(add_query_arg('paged', $prev_page, $base_url)) . '"><span class="screen-reader-text">Page précédente</span><span aria-hidden="true">&lsaquo;</span></a>';
    } else {
        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
    }
    echo '<span class="paging-input">';
    echo '<label for="current-page-selector" class="screen-reader-text">Page actuelle</label>';
    echo '<input class="current-page" id="current-page-selector" type="text" name="paged" value="' . esc_attr($current_page) . '" size="1" aria-describedby="table-paging">';
    echo '<span class="tablenav-paging-text"> sur <span class="total-pages">' . esc_html($total_pages) . '</span></span>';
    echo '</span>';
    if ($current_page < $total_pages) {
        echo '<a class="next-page button" href="' . esc_url(add_query_arg('paged', $next_page, $base_url)) . '"><span class="screen-reader-text">Page suivante</span><span aria-hidden="true">&rsaquo;</span></a>';
    } else {
        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
    }
    if ($current_page < $total_pages) {
        echo '<a class="last-page button" href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '"><span class="screen-reader-text">Dernière page</span><span aria-hidden="true">&raquo;</span></a>';
    } else {
        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
    }
    echo '</span>';
    echo '</div>';
} 



add_action( 'admin_post_dsi_mark_taken', function() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('Interdit');
    }
    $id = intval( $_GET['reservation'] ?? 0 );
    if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', "dsi_mark_taken_{$id}" ) ) {
        wp_die('Nonce invalide');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $wpdb->update( $table, [ 'taken' => 1 ], [ 'id' => $id ] );

    wp_redirect( wp_get_referer() );
    exit;
});

add_action( 'admin_post_dsi_mark_returned', function() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('Interdit');
    }
    $id = intval( $_GET['reservation'] ?? 0 );
    if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', "dsi_mark_returned_{$id}" ) ) {
        wp_die('Nonce invalide');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $wpdb->update( $table, [ 'returned' => 1 ], [ 'id' => $id ] );

    wp_redirect( wp_get_referer() );
    exit;
});


// Met à jour la start_date
add_action( 'admin_post_dsi_update_start_date', 'dsi_update_start_date_callback' );
function dsi_update_start_date_callback() {
    if ( ! current_user_can( 'manage_options' )
      || ! check_admin_referer( 'dsi_update_start_date', 'dsi_start_nonce' )
    ) {
        wp_die( __( 'Permission denied', 'dsi-location' ) );
    }

    $id         = absint( $_POST['reservation_id'] );
    $start_date = sanitize_text_field( $_POST['start_date'] );
    $start_hour = sanitize_text_field( $_POST['start_hour'] );

    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $updated = $wpdb->update(
        $table,
        [
            'start_date' => $start_date,
            'start_hour' => $start_hour,
            'taken'      => 1
        ],
        [ 'id' => $id ],
        [ '%s', '%s', '%d' ],
        [ '%d' ]
    );

    if ( false === $updated ) {
        wp_die( __( 'Erreur lors de la mise à jour de la date de retrait.', 'dsi-location' ) );
    }

    wp_safe_redirect( wp_get_referer() ?: admin_url() );
    exit;
}




// Met à jour la end_date
add_action( 'admin_post_dsi_update_end_date', 'dsi_update_end_date_callback' );
function dsi_update_end_date_callback() {
    if ( ! current_user_can( 'manage_options' )
      || ! check_admin_referer( 'dsi_update_end_date', 'dsi_end_nonce' )
    ) {
        wp_die( __( 'Permission denied', 'dsi-location' ) );
    }

    $id       = absint( $_POST['reservation_id'] );
    $end_date = sanitize_text_field( $_POST['end_date'] );
    $end_hour = sanitize_text_field( $_POST['end_hour'] );

    global $wpdb;
    $table = $wpdb->prefix . 'dsi_location_reservations';
    $updated = $wpdb->update(
        $table,
        [
            'end_date' => $end_date,
            'end_hour' => $end_hour,
            'returned' => 1,
        ],
        [ 'id' => $id ],
        [ '%s', '%s', '%d' ],
        [ '%d' ]
    );

    if ( false === $updated ) {
        wp_die( __( 'Erreur lors de la mise à jour de la date de retour.', 'dsi-location' ) );
    }

    wp_safe_redirect( wp_get_referer() ?: admin_url() );
    exit;
}

