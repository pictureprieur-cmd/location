<?php
// Ajout des champs personnalisés produit

add_action('woocommerce_product_options_general_product_data', function () {
    woocommerce_wp_text_input([
        'id' => '_prix_demi_journee',
        'label' => 'Prix demi-journée (€)',
        'type' => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0']
    ]);

    woocommerce_wp_text_input([
        'id' => '_prix_journee',
        'label' => 'Prix journée (€)',
        'type' => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0']
    ]);

    woocommerce_wp_text_input([
        'id' => '_prix_weekend',
        'label' => 'Prix week-end (€)',
        'type' => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0']
    ]);

    woocommerce_wp_text_input([
        'id' => '_montant_caution',
        'label' => 'Montant de la caution (€)',
        'type' => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0']
    ]);

    woocommerce_wp_text_input([
        'id' => '_nombre_articles_location',
        'label' => 'Nombre d’unités louables',
        'type' => 'number',
        'custom_attributes' => ['step' => '1', 'min' => '1']
    ]);
//------------- Checkbox Forfait Transport ------------------
    woocommerce_wp_checkbox([
        'id' => '_forfait_transport',
        'label' => 'Forfait transport',
    ]);




$cat_id = get_option('dsi_related_product_category', '');

global $post;
$selected_products = (array) get_post_meta($post->ID, '_dsi_related_products', true);

// Vérifier si le produit actuel appartient à la catégorie $cat_id
$product_categories = wp_get_post_terms($post->ID, 'product_cat', array('fields' => 'ids'));
$is_in_category = in_array($cat_id, $product_categories);

// Requête
$args = array(
    'post_type'      => 'product',
    'posts_per_page' => -1,
    'tax_query'      => array(
        array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $cat_id,
        ),
    ),
);

$query = new WP_Query($args);

$output = '';

if ($query->have_posts() && !$is_in_category) {
    $output .= '<div class="options_group">';
    
    $output .= '<fieldset class="form-field dsi_related_products_field">';
    $output .= '<legend>Produits associés</legend>';
    $output .= '<ul class="wc-checkboxes" style="margin-left: 150px; padding-left: 0;">';

    while ($query->have_posts()) {
        $query->the_post();

        $product_id = get_the_ID();
        $product    = wc_get_product($product_id);
        $checked    = in_array($product_id, $selected_products) ? ' checked="checked"' : '';

        $output .= '<li>';
        $output .= '<label>';
        $output .= '<input type="checkbox" name="dsi_related_products[]" value="' . esc_attr($product_id) . '"' . $checked . ' /> ';
        $output .= esc_html(get_the_title());
        if ($product) {
            $output .= ' ' . $product->get_price_html();
        }
        $output .= '</label>';
        $output .= '</li>';
    }

    $output .= '</ul>';
    $output .= '</fieldset>';
    $output .= '</div>';

    wp_reset_postdata();
} 

echo $output;



});

add_action('woocommerce_process_product_meta', function ($post_id) {
    $fields = ['_prix_demi_journee', '_prix_journee', '_prix_weekend', '_montant_caution', '_nombre_articles_location'];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, wc_clean($_POST[$field]));
        }
    }
    // Sauvegarde de la checkbox (traitement spécial)
    $forfait_transport = isset($_POST['_forfait_transport']) ? 'yes' : 'no';
    update_post_meta($post_id, '_forfait_transport', $forfait_transport);

    // Sauvegarde des produits associés
    if (isset($_POST['dsi_related_products']) && is_array($_POST['dsi_related_products'])) {
        // On nettoie et on cast en int
        $products = array_map('intval', $_POST['dsi_related_products']);
        update_post_meta($post_id, '_dsi_related_products', $products);
    } else {
        // Rien coché : on supprime le meta
        delete_post_meta($post_id, '_dsi_related_products');
    }
});