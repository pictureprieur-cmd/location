<?php
// Vue : Page admin de gestion de la maintenance
// Variables attendues : $products, $today, $maintenances
?>
<div class="wrap">
    <h1>Maintenance des articles louables</h1>
    <h2>Ajouter une période de maintenance</h2>
    <div class="maintenace-form" style="display: flex">
        <form method="post" id="dsi-maintenance-form" style="width: 50%;flex-direction:row">
            <input id="maintenance-id" name="maintenance-id" type="hidden" value="" />
            <table class="form-table">

                <tr>
                    <td style="width:25%">
                        <label for="product_id">Produit</label>
                    </td>
                    <td style="width:25%">
                            <select name="product_id" required>
                                <option value="">-- Choisir un produit --</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= esc_attr($product->ID) ?>"><?= esc_html($product->post_title) ?></option>
                                <?php endforeach; ?>
                            </select>
                    </td>

                    <td style="width:25%">
                        <label for="unit_id">Unité (ex. #1, #2...)</label>
                    </td>
                    <td style="display:flex; width:25%">
                            <input type="number" name="unit_id" required min="1" value="1">
                            <button type="button" id="btn-load-calendar" class="button" style="display:none">Voir</button>
                    </td>
                </tr>
                <tr>
                    <td style="width:25%">
                        <label for="start_date">Date de début</label>
                    </td>
                    <td style="width:25%">
                        <input class="start_date" type="date" name="start_date" min="<?= $today ?>" required>
                    </td>
                    <td style="width:25%">
                        <label for="end_date">Date de fin</label>
                    </td>
                    <td style="width:25%">
                        <input class="end_date" type="date" name="end_date" min="<?= $today ?>" required>
                    </td>
                </tr>
            </table>
            <div style="padding-left:10px">
                <label for="note" style="display:block">Note:</label>

                <textarea id="note" name="note" rows="10" cols="70"></textarea>


            </div>
            <?php wp_nonce_field('dsi_calendar', 'dsi_calendar_nonce'); ?>
            <div class="message"></div>

            <div style="display:flex; flex-direction:row">
                <!-- Bouton “Ajouter” -->
                <?php submit_button(
                    'Ajouter la maintenance',
                    'primary',
                    'dsi_add_maintenance',
                    true,
                    ['id' => 'btn-add-maintenance']
                ); ?>
                <div style="margin-top: 20px;padding-top: 10px;" id="cancel">
                    <!-- Bouton “Mettre à jour” (caché par défaut) -->
                    <?php submit_button('Mettre à jour', 'secondary', 'dsi_update_maintenance', false, ['id' => 'btn-update-maintenance', 'style' => 'display:none;'] ); ?>

                    <!-- Bouton “Abbuler” mise à jour (caché par défaut) -->
                    <input type="reset" name="dsi_cancel_update_maintenance" id="dsi_cancel_update_maintenance" class="btn-cancel-form" value="Annuler">

                </div>
            </div>
        </form>
        <div class="dsi-reservation-block" style="width:50%">
            <div id="calendar" class="dsi-maintenance-calendar" style="margin-bottom:10px"></div>
        </div>
    </div>
    <hr>
    <h2>Liste des périodes de maintenance</h2>
    <div id="dsi-maintenance-list">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Produit</th>
                <th>Unité</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($maintenances as $m): ?>
                <?php
                    $action = !$m->completed ? '<form method="post" style="display:inline;"><input type="hidden" name="dsi_mark_maintenance_done" value="' . intval($m->id) . '"><button type="submit" class="button">Maintenance términée</button></form>' : '';
                    if($m->completed){
                        $icone = '✔️ Terminée';
                    }elseif($m->end_date < $today){
                        $icone = '❌ Non terminée';
                    }else{
                        $icone = '⏳ En cours';
                    }
                    $dateStart = date_create($m->start_date);
                    $startDate = date_format($dateStart, 'd/m/Y');
                    $dateEnd = date_create($m->end_date);
                    $endDate = date_format($dateEnd, 'd/m/Y');
                    $title = get_the_title($m->product_id);
                ?>
                <tr>
                    <td><?= esc_html($m->id); ?></td>
                    <td>
                        <span class="dsi-maintenance-title" style="cursor:pointer; color: #2271b1; text-decoration:underline;"><?= $title ? esc_html($title) : '(Produit supprimé)'; ?></span>
                        <input class="dsi-product-id" type="hidden" value="<?= $m->product_id; ?>">
                        <input class="dsi-product-unit" type="hidden" value="<?= esc_html($m->unit_id); ?>">

                        <input class="dsi-product-dateStart" type="hidden" value="<?= $m->start_date; ?>">
                        <input class="dsi-product-dateEnd" type="hidden" value="<?= $m->end_date; ?>">
                        <input class="dsi-product-note" type="hidden" value="<?= esc_html($m->note); ?>">
                        <input class="dsi-maintenance-id" type="hidden" id="maintenance-id" name="maintenance-id" value="<?= esc_attr($m->id); ?>">
                    </td>
                    <td>
                        #<span class="dsi-maintenance-unit-id"><?= esc_html($m->unit_id); ?></span>
                    </td>
                    <td><?= $startDate; ?></td>
                    <td><?= $endDate; ?></td>
                    <td><?= $icone ?></td>
                    <td><?= $action ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>


<script>
jQuery(document).ready(function($){
    $('#dsi-maintenance-form').on('submit', function(e){
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button[type="submit"]');
        var msg = form.find('.message');
        msg.html('');
        btn.prop('disabled', true);
        var data = {
            action: 'dsi_admin_add_maintenance',
            product_id: form.find('[name="product_id"]').val(),
            unit_id: form.find('[name="unit_id"]').val(),
            start_date: form.find('[name="start_date"]').val(),
            end_date: form.find('[name="end_date"]').val(),
            note: form.find('[name="note"]').val(),
            _ajax_nonce: form.find('[name="dsi_calendar_nonce"]').val()
        };
        $.post(ajaxurl, data, function(response){
            btn.prop('disabled', false);
            if(response.success){
                msg.html('<div class="notice notice-success">'+response.data.message+'</div>');
                // Rafraîchir la liste des maintenances
                $('#dsi-maintenance-list').load(window.location.href + ' #dsi-maintenance-list > *');
                // Rafraîchir le calendrier
                $('#btn-load-calendar').trigger('click');
                form[0].reset();
            }else{
                msg.html('<div class="notice notice-error">'+(response.data && response.data.message ? response.data.message : 'Erreur inconnue')+'</div>');
            }
        });
    });





 $('#dsi-maintenance-form').off('submit');

    $('.dsi-maintenance-title').on('click', function(){
        var $cell = $(this).closest('td');

        // on récupère tout depuis ce même <td>
        var product_id    = $cell.find('.dsi-product-id').val();
        var unit_id       = $cell.find('.dsi-product-unit').val();
        var startdate     = $cell.find('.dsi-product-dateStart').val();
        var enddate       = $cell.find('.dsi-product-dateEnd').val();
        var note          = $cell.find('.dsi-product-note').val();
        var maintenanceId = $cell.find('.dsi-maintenance-id').val();

        // on remplit le formulaire
        $('select[name="product_id"]').val(product_id).change();
        $('input[name="unit_id"]').val(unit_id);
        $('input[name="start_date"]').val(startdate);
        $('input[name="end_date"]').val(enddate);
        $('textarea[name="note"]').val(note);

        // le champ caché pour l’ID
        $('#maintenance-id').val(maintenanceId);

        // debug
        console.log('→ maintenance-id envoyé :', $('#maintenance-id').val());

    $('#btn-add-maintenance').hide();
    $('#btn-update-maintenance').show();
    $('#dsi_cancel_update_maintenance').show();

        // recharge ou affichage du form
        $('#btn-load-calendar').trigger('click');
    })

    $('#dsi_cancel_update_maintenance').on('click', ()  => {
        $('#btn-add-maintenance').show();
        $('#btn-update-maintenance').hide();
        $('#dsi_cancel_update_maintenance').hide();
        //$(this).closest('#dsi-maintenance-form"').find("input, textarea").val("");
    })

    $('select[name="product_id"], input[name="unit_id"]').on('change', () => {
        $('#btn-load-calendar').trigger('click');
    })

    // ********* tri tableau ************** //
    var $table = $('table.widefat.striped');
    var $tbody = $table.find('tbody');

    // Configuration : key = index de la colonne, value = type de données
    var sortConfig = {
        0: 'number', // ID
        1: 'string', // Produit
        3: 'date',   // Début
        4: 'date',   // Fin
        5: 'string'  // Statut
    };

    // État de chaque colonne (true = prochain tri ASC)
    var sortState = {};

    $.each(sortConfig, function(colIdx, colType){
        sortState[colIdx] = true;

        // Récupère le <th> correspondant
        var $th = $table.find('thead th').eq(colIdx);

        // Curseur et indicateur
        $th.css('cursor','pointer')
        .append(' <span class="sort-ind-'+colIdx+'">▲</span>');

        $th.on('click', function(){
        var asc = sortState[colIdx];
        var rows = $tbody.find('tr').get();

        rows.sort(function(a, b){
            var aText = $(a).children('td').eq(colIdx).text().trim();
            var bText = $(b).children('td').eq(colIdx).text().trim();
            var cmp = 0;

            if (colType === 'number') {
            cmp = (parseFloat(aText) || 0) - (parseFloat(bText) || 0);
            } else if (colType === 'string') {
            cmp = aText.localeCompare(bText);
            } else if (colType === 'date') {
            function toTs(str) {
                var p = str.split('/');
                return new Date(p[2], p[1]-1, p[0]).getTime();
            }
            cmp = toTs(aText) - toTs(bText);
            }

            return asc ? cmp : -cmp;
        });

        // Réinjecte les lignes triées
        $.each(rows, function(i, row){
            $tbody.append(row);
        });

        // Basculer l’état et la flèche
        sortState[colIdx] = !asc;
        $th.find('.sort-ind-'+colIdx)
            .text(sortState[colIdx] ? '▲' : '▼');
        });
    });





});
</script> 

<style>

 .btn-cancel-form{
    display:none;
    font-size: 13px;
    background: #f6f7f7 !important;
    border: solid 1px #3582c4 !important;
    color: #0a4b78;
    outline: 2px solid transparent;
    outline-offset: 0;
    width: auto;
    border-radius: 3px;
    box-sizing: border-box;
    padding-bottom: 6px !important;
    padding-left: 10px !important;
    padding-right: 10px !important;
    padding-top: 5px !important;
}

</style>
