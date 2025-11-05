<?php
// Vue : Formulaire de réservation, modale et calendrier pour chaque unité
// Variables attendues : $product_id, $nb_units, $today
?>
<div style="clear: both"></div>
<div id="dsi-unit-calendars">
<?php     
    $hours = dsi_get_location_hours();
    $mor_start = intval($hours['matin_start']);
    $mor_end   = intval($hours['matin_end']);
    $aft_start = intval($hours['aprem_start']);
    $aft_end   = intval($hours['aprem_end']);

    for ($i = 1; $i <= $nb_units; $i++) : ?>
    <div class="dsi-reservation-block" data-unit-id="<?= $i ?>">
        <div class="calendar-head">
            <h4>Disponibilités - Unité #<?= $i ?></h4>
            <button class="btn-view-resa" type="button" data-unit-id="<?= $i ?>">Voir mes réservations</button>
        </div>
        <div class="calendar-row">
            <div class="calendar-col">
                <form id="form-calendar-<?= $i ?>" class="dsi-reservation-form" method="post">

                    <div class="box-resa">
                        <input type="hidden" name="product_id" value="<?= esc_attr($product_id) ?>">
                        <input type="hidden" name="unit_id" value="<?= $i ?>">
                        
                        <!-- Date de début -->
                        <div class="box-input">
                            <label>Retrait : </label>
                            <input type="date" class="select-date start_date" name="start_date" min="<?= $today ?>">
                            <div class="radio-btn start-radio-btn">
                                <input type="radio" id="matin-debut-<?= $i ?>" class="matin-debut" name="time-start" value="matin" checked="checked"/><label for="matin-debut-<?= $i ?>">Matin (<?= $mor_start ?>h)</label>
                                <input type="radio" id="soir-debut-<?= $i ?>" class="soir-debut" name="time-start" value="soir" /><label for="soir-debut-<?= $i ?>">Après midi (<?= $mor_end ?>h)</label>
                            </div>
                        </div>

                        <!-- Date de fin -->
                        <div class="box-input end-date-block">
                            <label>Retour : </label>
                            <input type="date" class="select-date end_date" name="end_date" min="<?= $today ?>">
                            <div class="radio-btn end-radio-btn">
                                <input type="radio" id="midi-fin-<?= $i ?>" class="midi-fin" name="time-end" value="midi" checked="checked"/><label for="midi-fin-<?= $i ?>">Midi (<?= $aft_start ?>h)</label>
                                <input type="radio" id="soir-fin-<?= $i ?>" class="soir-fin" name="time-end" value="soir" /><label for="soir-fin-<?= $i ?>">Soir (<?= $aft_end ?>h)</label>
                            </div>
                        </div>
                    </div>
                    <div class="message"></div>
                    <button class="btn-resa" type="submit">Réserver</button>
                </form>
            </div>
            <!-- Modal des réservations -->
            <div class="dsi-reservation-modal" style="display:none;">
                <div class="dsi-reservation-modal-content">
                    <span class="dsi-close-modal">&times;</span>
                    <h3>Mes réservations</h3>
                    <div class="dsi-reservation-table-wrapper">
                        <table class="widefat striped user-reservations-table">
                            <thead>
                                <tr>
                                    <th>Du</th>
                                    <th>Heure de retrait</th>
                                    <th>Au</th>
                                    <th>Heure de retour</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody class="reservations-list">
                                <tr><td colspan="4">Chargement...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Fin modal des réservations -->

            <!-- Modal click sur le calendrier -->
            <div class="dsi-click-calendar-modal">
              <div class="dsi-click-calendar-modal-content">
                <span class="dsi-close-calendar-modal">&times;</span>
                <h3>Saisie réservation</h3>
                <div class="dsi-click-calendar-table-wrapper">
                  <p>Veuillez saisir les informations de réservation dans le formulaire de gauche</p>
                </div>
              </div>
            </div>
            <!-- Fin modal click sur le calendrier -->
            
            <!-- Section calendrier -->
            <div class="dsi-calendar" data-product-id="<?= esc_attr($product_id) ?>" data-unit-id="<?= $i ?>"></div>
            <!-- Fin section calendrier -->
        </div>
    </div>
<?php endfor; ?>
</div> 
<script>
jQuery(function($){
  const jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];

  $('.dsi-reservation-form').each(function(){
    const form = $(this);
    const message = form.find('.message');

    // 1) Gestion de la date de début
    form.find('.start_date').on('change', function(){
      const startDate = $(this).val();
      if (startDate) {
        // Mettre à jour la date minimale pour la date de fin
        const minEndDate = new Date(startDate);
        minEndDate.setDate(minEndDate.getDate() + 1);
        const minEndDateStr = minEndDate.toISOString().split('T')[0];
        form.find('.end_date').attr('min', minEndDateStr);
        
        // Si la date de fin est antérieure à la nouvelle date de début, la vider
        const endDate = form.find('.end_date').val();
        if (endDate && endDate < startDate) {
          form.find('.end_date').val('');
        }
      }
    });

    // 2) Gestion de la date de fin
    form.find('.end_date').on('change', function(){
      const endDate = $(this).val();
      const startDate = form.find('.start_date').val();
      
      if (endDate && startDate) {
        // Vérifier que la date de fin n'est pas antérieure à la date de début
        if (endDate < startDate) {
            message.text('La date de fin ne peut pas être antérieure à la date de début.').addClass('notice notice-error');
          //alert('La date de fin ne peut pas être antérieure à la date de début.');
          $(this).val('');
          return;
        }
        
        // Si même jour, vérifier la cohérence des heures
        if (endDate === startDate) {
          const startTime = form.find('input[name="time-start"]:checked').val();
          const endTime = form.find('input[name="time-end"]:checked').val();
          
          if (startTime === 'soir' && endTime === 'midi') {
            //alert('Impossible : retrait le soir et retour le midi le même jour.');
            message.text('Impossible : retrait le soir et retour le midi le même jour.').addClass('notice notice-error');
            form.find('input[name="time-end"][value="soir"]').prop('checked', true);
          }
          if (startTime === 'midi' && endTime === 'midi') {
            //alert('Impossible : retrait et retour au midi le même jour.');
            message.text('Impossible : retrait et retour au midi le même jour.').addClass('notice notice-error');
            form.find('input[name="time-end"][value="soir"]').prop('checked', true);
          }
        }
      }
    });

    // 3) Gestion des changements d'heures
    form.find('input[name="time-start"], input[name="time-end"]').on('change', function(){
      const startDate = form.find('.start_date').val();
      const endDate = form.find('.end_date').val();
      
      if (startDate && endDate && startDate === endDate) {
        const startTime = form.find('input[name="time-start"]:checked').val();
        const endTime = form.find('input[name="time-end"]:checked').val();
        
        // Validation pour la même journée
        if (startTime === 'soir' && endTime === 'midi') {
          //alert('Impossible : retrait le soir et retour le midi le même jour.');
          message.text('Impossible : retrait le soir et retour le midi le même jour.').addClass('notice notice-error');
          // Revenir à la sélection précédente
          $(this).prop('checked', false);
          return;
        }
        if (startTime === 'midi' && endTime === 'midi') {
          //alert('Impossible : retrait et retour au midi le même jour.');
          message.text('Impossible : retrait et retour au midi le même jour.').addClass('notice notice-error');
          // Revenir à la sélection précédente
          $(this).prop('checked', false);
          return;
        }
      }
    });

    // 4) Validation finale avant soumission
    form.on('submit', function(e) {
      const startDate = form.find('.start_date').val();
      const endDate = form.find('.end_date').val();
      const startTime = form.find('input[name="time-start"]:checked').val();
      const endTime = form.find('input[name="time-end"]:checked').val();
      const message = form.find('.message');
      
      if (!startDate || !endDate || !startTime || !endTime) {
        e.preventDefault();
        //alert('Veuillez remplir tous les champs : date de début, date de fin, heure de retrait et heure de retour.');
        message.text('Veuillez remplir tous les champs : date de début, date de fin, heure de retrait et heure de retour.').addClass('notice notice-error');
        return false;
      }
      
      // Validation des dates
      if (endDate < startDate) {
        e.preventDefault();
        //alert('La date de fin ne peut pas être antérieure à la date de début.');
        message.text('La date de fin ne peut pas être antérieure à la date de début.').addClass('notice notice-error');
        return false;
      }
      
      // Validation des heures pour la même journée
      if (startDate === endDate) {
        if (startTime === 'soir' && endTime === 'midi') {
          e.preventDefault();
          //alert('Impossible : retrait le soir et retour le midi le même jour.');
          message.text('Impossible : retrait le soir et retour le midi le même jour.').addClass('notice notice-error');
          return false;
        }
        if (startTime === 'midi' && endTime === 'midi') {
          e.preventDefault();
          //alert('Impossible : retrait et retour au midi le même jour.');
          message.text('Impossible : retrait et retour au midi le même jour.').addClass('notice notice-error');
          return false;
        }
      }
    });
  });

  // Ouvrir la modale au clic sur .dsi-calendar
  $('.fc-daygrid').on('click', function() {
    $('.dsi-click-calendar-modal').fadeIn(300);
  });
  
  // Fermer la modale au clic sur le bouton de fermeture
  $('.dsi-close-calendar-modal').on('click', function() {
    $('.dsi-click-calendar-modal').fadeOut(300);
  });
  
  // Fermer la modale au clic en dehors du contenu
  $('.dsi-click-calendar-modal').on('click', function(e) {
    if ($(e.target).hasClass('dsi-click-calendar-modal')) {
      $(this).fadeOut(300);
    }
  });


});
</script>