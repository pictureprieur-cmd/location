<?php
    echo '<div class="wrap"><h1>Liste des réservations</h1>';
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="dsi-location-returns" />';
    $list_table = new DSI_Reservations_List_Table();
    $list_table->prepare_items();
    $list_table->display();
    echo '</form></div>';
    $today = date('Y-m-d');
    $hours = dsi_get_location_hours(); 
?>

<!-- Modale infos client -->
<div id="dsi-client-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3);">
  <div style="background:#fff; max-width:420px; margin:10vh auto; padding:2.5rem 2rem 2rem 2rem; border-radius:12px; position:relative; box-shadow:0 0 20px #0002; min-width:320px;">
    <button id="dsi-client-modal-close" style="position:absolute; top:10px; right:10px; background:none; border:none; font-size:2rem; cursor:pointer;">&times;</button>
    <h2 style="margin-bottom:2rem; font-size:1.4rem;">Informations client</h2>
    <div style="margin-bottom:1.2rem; font-size:1.1rem;"><b>Nom :</b> <span id="dsi-client-nom"></span></div>
    <div style="margin-bottom:1.2rem; font-size:1.1rem;"><b>Prénom :</b> <span id="dsi-client-prenom"></span></div>
    <div style="margin-bottom:1.2rem; font-size:1.1rem;"><b>Téléphone :</b> <span id="dsi-client-tel"></span></div>
    <div style="margin-bottom:1.2rem; font-size:1.1rem;"><b>Email :</b> <span id="dsi-client-email"></span></div>
  </div>
</div>

<?php $hours = dsi_get_location_hours(); ?>

<!-- MODALE DATE DE DÉBUT -->
<div id="dsi-start-modal" class="dsi-modal" style="display:none; align-items:center; justify-content:center;">
  <!-- overlay (fermeture au clic) -->
  <div class="dsi-modal-overlay"></div>

  <div class="dsi-modal-content" role="dialog" aria-modal="true">
    <!-- bouton croix -->
    <button type="button" class="dsi-modal-close" aria-label="<?php esc_attr_e( 'Fermer', 'dsi-location' ); ?>">&times;</button>

    <h2><?php _e( 'Date de retrait', 'dsi-location' ); ?></h2>

    <form id="form-start-date"
          method="post"
          action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
      <?php wp_nonce_field( 'dsi_update_start_date', 'dsi_start_nonce' ); ?>
      <input type="hidden" name="action" value="dsi_update_start_date">
      <input type="hidden" name="reservation_id" id="dsi-start-reservation-id" value="">

      <p>
        <label for="dsi-start-date"><?php _e( 'Date de retrait', 'dsi-location' ); ?></label><br>
        <input type="date"
               name="start_date"
               id="dsi-start-date"
               min="<?php //echo esc_attr( $today ); ?>"
               required>
      </p>

      <fieldset>
        <legend><?php _e( 'Heure de début', 'dsi-location' ); ?></legend> <!-- dsi_get_location_hours()['matin_start'] -->
        <p>
          <input type="radio"
                 name="start_hour"
                 id="start-hour-am"
                 value="<?php echo esc_attr( $hours['matin_start'] ); ?>"
                 required>
          <label for="start-hour-am"><?php _e( dsi_get_location_hours()['matin_start'], 'dsi-location' ); ?> h</label>
        </p>
        <p>
          <input type="radio"
            name="start_hour"
            id="start-hour-pm"
            value="<?php echo esc_attr( $hours['aprem_start'] ); ?>">
          <label for="start-hour-pm"><?php _e( dsi_get_location_hours()['aprem_start'], 'dsi-location' ); ?> h</label>
        </p>
      </fieldset>

      <p>
        <button type="submit" class="button button-primary">
          <?php _e( 'Valider', 'dsi-location' ); ?>
        </button>
      </p>
    </form>
  </div>
</div>


<!-- MODALE DATE DE FIN -->
<div id="dsi-end-modal" class="dsi-modal" style="display:none; align-items:center; justify-content:center;">
  <div class="dsi-modal-overlay"></div>

  <div class="dsi-modal-content" role="dialog" aria-modal="true">
    <button type="button" class="dsi-modal-close" aria-label="<?php esc_attr_e( 'Fermer', 'dsi-location' ); ?>">&times;</button>
    <h2><?php _e( 'Date de retour', 'dsi-location' ); ?></h2>

    <form id="form-end-date"
          method="post"
          action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
      <?php wp_nonce_field( 'dsi_update_end_date', 'dsi_end_nonce' ); ?>
      <input type="hidden" name="action" value="dsi_update_end_date">
      <input type="hidden" name="reservation_id" id="dsi-end-reservation-id" value="">

      <p>
        <label for="dsi-end-date"><?php _e( 'Date de retour', 'dsi-location' ); ?></label><br>
        <input type="date"
               name="end_date"
               id="dsi-end-date"
               min="<?php echo esc_attr( $today ); ?>"
               required>
      </p>

      <fieldset>
        <legend><?php _e( 'Heure de fin', 'dsi-location' ); ?></legend>
        <p>
          <input type="radio"
            name="end_hour"
            id="return-hour-am"
            value="<?php echo esc_attr( $hours['matin_end'] ); ?>"
            required>
          <label for="return-hour-am"><?php _e( dsi_get_location_hours()['matin_end'], 'dsi-location' ); ?> h</label>
        </p>
        <p>
          <input type="radio"
            name="end_hour"
            id="return-hour-pm"
            value="<?php echo esc_attr( $hours['aprem_end'] ); ?>">
          <label for="return-hour-pm"><?php _e( dsi_get_location_hours()['aprem_end'], 'dsi-location' ); ?> h</label>
        </p>
      </fieldset>

      <p>
        <button type="submit" class="button button-primary">
          <?php _e( 'Valider', 'dsi-location' ); ?>
        </button>
      </p>
    </form>
  </div>
</div>



<script>
    jQuery(document).ready(function($) {
        // Ouvre la modale client
        $('.dsi-client-modal-link').on('click', function(e) {
            e.preventDefault();
            $('#dsi-client-nom').text( $(this).data('nom') || '' );
            $('#dsi-client-prenom').text( $(this).data('prenom') || '' );
            $('#dsi-client-tel').text( $(this).data('tel') || '' );
            $('#dsi-client-email').text( $(this).data('email') || '' );
            $('#dsi-client-modal').show();
        });

        // Ferme la modale client via le bouton
        $('#dsi-client-modal-close').on('click', function() {
            $('#dsi-client-modal').hide();
        });

        // Ferme la modale client en cliquant sur le fond
        $('#dsi-client-modal').on('click', function(e) {
            if ( e.target === this ) {
                $(this).hide();
            }
        });

// Ouvre la modale de start_date (pré-remplie)
$('.dsi-open-start-modal').on('click', function () {
  var id   = $(this).data('reservation');
  var date = $(this).data('start-date') || '';
  var hour = parseInt($(this).data('start-hour'), 10);

  $('#dsi-start-reservation-id').val(id);
  $('#dsi-start-date').val(date);

  // évite un blocage si la date existante est < min
  var today = new Date().toISOString().slice(0,10);
  $('#dsi-start-date').attr('min', date && date < today ? date : today);

  $('input[name="start_hour"]').prop('checked', false);
  if (hour == <?php echo (int) $hours['matin_start']; ?>) {
    $('#start-hour-am').prop('checked', true);
  } else if (hour == <?php echo (int) $hours['aprem_start']; ?>) {
    $('#start-hour-pm').prop('checked', true);
  }

  $('#dsi-start-modal').css('display', 'flex');
});

$('.dsi-open-end-modal').on('click', function () {
  var id   = $(this).data('reservation');
  var date = $(this).data('end-date') || '';     // <-- FIN !
  var hour = parseInt($(this).data('end-hour'), 10);

  $('#dsi-end-reservation-id').val(id);
  $('#dsi-end-date').val(date);                  // <-- on met la date fin

  // coche le bon radio
  $('input[name="end_hour"]').prop('checked', false);
  if (hour == <?php echo (int)$hours['matin_end']; ?>) {
    $('#return-hour-am').prop('checked', true);
  } else if (hour == <?php echo (int)$hours['aprem_end']; ?>) {
    $('#return-hour-pm').prop('checked', true);
  }

  $('#dsi-end-modal').css('display','flex');
});




        // Ferme toutes les modales via le bouton de fermeture
        $('.dsi-modal-close').on('click', function() {
            $(this).closest('.dsi-modal').hide();
        });

        // Ferme toutes les modales en cliquant sur le fond
        $('.dsi-modal').on('click', function(e) {
            if ( e.target === this ) {
                $(this).hide();
            }
        });
    });
</script>

<style>
    .dsi-modal{
        position:fixed;
        top:0;
        left:0;
        width:100vw;
        height:100vh;
        background:rgba(0,0,0,0.3);
        display:flex;
        align-items:center;
        justify-content:center;
    }
    .dsi-modal-content{
        background:#fff;
        padding:2rem;
        border-radius:8px;
        position:relative;
        max-width:400px;
        width:90%;
    }
    .dsi-modal-close{
        position:absolute;
        top:0.5rem;
        right:0.5rem;
        background:none;
        border:none;
        font-size:1.5rem;
        cursor:pointer;
    }
</style>