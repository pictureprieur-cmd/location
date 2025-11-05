<?php
// Ajout du formulaire de réservation en bas de la fiche produit
add_action('dsi_tarifs_reservation', 'dsi_afficher_tarifs_reservation', 21);
function dsi_afficher_tarifs_reservation() {
    global $post;

    if ($post->post_type !== 'product') return;

    $post_id = $post->ID;
    $product = wc_get_product( $post_id );
	
    $prix_demi_journee = get_post_meta($post_id, '_prix_demi_journee', true);
    $prix_journee = get_post_meta($post_id, '_prix_journee', true);
    $prix_weekend = get_post_meta($post_id, '_prix_weekend', true);
    $montant_caution = get_post_meta($post_id, '_montant_caution', true);
    $product_cat = $product->get_category_ids();

	echo '<h3 id="dsi-tarifs-title">Tarifs de la location :</h3>';
    echo '<div id="dsi-tarifs">';
?>
    <table class="dsi-tarifs-table">
        <tr class="dsi-tarifs-headers">
            <?php if($prix_demi_journee > 0){ ?>
                <td>1/2 jour</td>
            <?php } ?>

            <?php if($prix_journee > 0){ ?>
                <td>Journée</td>
            <?php } ?>

            <?php if($prix_weekend > 0){ ?>
                <td>Week-end</td>
            <?php } ?>
        </tr>
        <tr class="dsi-tarifs-prices">
            <?php if($prix_demi_journee > 0){ ?>
                <td><?= wc_price($prix_demi_journee) ?></td>
            <?php } ?>

            <?php if($prix_journee > 0){ ?>
                <td><?= wc_price($prix_journee) ?></td>
            <?php } ?>

            <?php if($prix_weekend > 0){ ?>
                <td><?= wc_price($prix_weekend) ?></td>
            <?php } ?>
        </tr>        
    </table>
<?php
/*
	if($prix_demi_journee > 0)
		echo '<p>Demi-journée : <b>' . wc_price($prix_demi_journee) . '</b></p>';
	if($prix_journee > 0)
		echo '<p>Journée : <b>' . wc_price($prix_journee) . '</b></p>';
	if($prix_weekend > 0)
		echo '<p>Weekend : <b>' . wc_price($prix_weekend) . '</b></p>';
	echo '</div><div>';
*/
    echo '<div class="dsi-tarifs-info">';
    if($product->get_price() > 0){
        echo '<p>Empreinte de CB : <span style="font-weight: bold; color: #dd1c1a;">' . wc_price($product->get_price()) . '</span></p>';
    }

	if($montant_caution > 0)
		echo '<p>Caution : ' . wc_price($montant_caution) . '</p>';
    if($product_cat){
        $terms = get_the_terms($post_id, 'product_cat');
        foreach ( $terms as $term ) {
            $cat_name = $term->name;
            $options = get_option('dsi_location_cancellation_days', []);
            //echo '<p>Catégorie : <b>' . $cat_name . '</b></p>';
            echo '<p>Délais d\'annulation : ' . $options[$term->term_id] . ' jours</p>';
            echo '<input type="hidden" class="delai-annulation" value="' . $options[$term->term_id] . '">';
        }
    }
	echo '<input id="tarifs-loc" type="hidden" data-demi="'.$prix_demi_journee.'" data-jour="'.$prix_journee.'" data-we="'.$prix_weekend.'">';
    echo '</div>';

    echo '</div>';
}

add_action('dsi_after_first_row', 'dsi_afficher_formulaire_reservation', 21);
function dsi_afficher_formulaire_reservation() {
    /*
    global $post;
    if ($post->post_type !== 'product') return;
    $product_id = $post->ID;
    $nb_units = get_post_meta($product_id, '_nombre_articles_location', true);
    $nb_units = $nb_units ? intval($nb_units) : 1;
    $today = date('Y-m-d');
    include dirname(__DIR__) . '/views/reservation-form.php';
    */

    if ( ! is_product() ) return;                  // au lieu d'utiliser $post
    $product_id = get_the_ID();                    // au lieu de $post->ID
    $nb_units = (int) get_post_meta($product_id, '_nombre_articles_location', true);
    $nb_units = $nb_units > 0 ? $nb_units : 1;
    $today = date('Y-m-d');
    include dirname(__DIR__) . '/views/reservation-form.php';

}

// Endpoint AJAX pour vérifier la disponibilité d'une réservation AVANT ajout au panier
add_action('wp_ajax_dsi_check_reservation_availability', 'dsi_check_reservation_availability');
add_action('wp_ajax_nopriv_dsi_check_reservation_availability', 'dsi_check_reservation_availability');
function dsi_check_reservation_availability() {

    // Ce endpoint vérifie la disponibilité d'une réservation sans l'enregistrer
    if (!isset($_POST['product_id'], $_POST['unit_id'], $_POST['start_date'], $_POST['end_date'], $_POST['time_start'], $_POST['time_end'])) {

        wp_send_json_error(['message' => 'Champs manquants.']);
    }

    $data = [
        'product_id' => intval($_POST['product_id']),
        'unit_id'    => intval($_POST['unit_id']),
        'user_id'    => get_current_user_id(),
        'start_date' => sanitize_text_field($_POST['start_date']),
        'end_date'   => sanitize_text_field($_POST['end_date']),
        'time_start' => sanitize_text_field($_POST['time_start']),
        'time_end'   => sanitize_text_field($_POST['time_end']),
        'returned'   => 0
    ];
    // Réutilise la logique de conflit de booking-handler.php sans insérer en base
    if (!function_exists('dsi_enregistrer_reservation')) {
        require_once __DIR__ . '/booking-handler.php';
    }

   $result = dsi_enregistrer_reservation($data);
   // Si conflit ou erreur, on renvoie un WP_Error
   if (is_wp_error($result)) {
       wp_send_json_error([
           'message' => $result->get_error_message()
       ]);
   }
   // Ok, on peut ajouter en base et renvoyer le feu vert
   wp_send_json_success(['message' => 'Créneau disponible.']);

}


// À la toute fin du fichier, ajouter ce script pour exposer l'URL de base à JS
add_action('wp_enqueue_scripts', function() {
    if (is_product()) {
        wp_enqueue_script('dsi-front', plugin_dir_url(__DIR__) . 'js/dsi-front.js', ['jquery'], null, true);
        wp_localize_script('dsi-front', 'DSI_Calendar', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dsi_calendar')
        ]);
    }
});


// Shortcode: [tarif-info]
function dsi_tarif_info($atts) {
    $atts = shortcode_atts([
        'id' => 0,
    ], $atts, 'tarif-info');

    $product_id = absint($atts['id']);

    if (!$product_id) {
        global $post;
        if ($post instanceof WP_Post && $post->post_type === 'product') {
            $product_id = (int) $post->ID;
        } else {
            return ''; // hors page produit et sans id ⇒ rien
        }
    }

    $product = wc_get_product($product_id);
    if (!$product || 'product' !== get_post_type($product_id)) {
        return '';
    }

    // Méta ⇒ float pour comparer proprement
    $prix_demi_journee = (float) get_post_meta($product_id, '_prix_demi_journee', true);
    $prix_journee      = (float) get_post_meta($product_id, '_prix_journee', true);
    $prix_weekend      = (float) get_post_meta($product_id, '_prix_weekend', true);

    // Si aucun prix, on sort
    if ($prix_demi_journee <= 0 && $prix_journee <= 0 && $prix_weekend <= 0) {
        return '';
    }

    // Construire la table
    ob_start();
    ?>
    <table class="dsi-tarifs-grid">
        <tr>
            <td colspan="3"><?= $product->get_name() ?></td>
        </tr>
        <tr>
            <?php if ($prix_demi_journee > 0) : ?>
                <td>1/2&nbsp;journée</td>
            <?php endif; ?>

            <?php if ($prix_journee > 0) : ?>
                <td>Journée</td>
            <?php endif; ?>

            <?php if ($prix_weekend > 0) : ?>
                <td>Weekend</td>
            <?php endif; ?>
        </tr>
        <tr>
            <?php if ($prix_demi_journee > 0) : ?>
                <td><?php echo wc_price($prix_demi_journee); ?></td>
            <?php endif; ?>

            <?php if ($prix_journee > 0) : ?>
                <td><?php echo wc_price($prix_journee); ?></td>
            <?php endif; ?>

            <?php if ($prix_weekend > 0) : ?>
                <td><?php echo wc_price($prix_weekend); ?></td>
            <?php endif; ?>
        </tr>
    </table>
    <?php
    return ob_get_clean();
}
add_shortcode('tarif-info', 'dsi_tarif_info');

