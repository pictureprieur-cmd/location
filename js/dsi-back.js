jQuery(document).ready(function($) {

    let calendarEl = document.getElementById('calendar');
    let calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'fr',
        height: 400,
        firstDay: 1,
        buttonText: {
            today: 'Aujourd\'hui'
        },
        events: [], // Vide au départ
    });

    calendar.render();

    $('#btn-load-calendar').on('click', function () {
        const productId = $('select[name="product_id"]').val();
        const unitId = $('input[name="unit_id"]').val();

        if (!productId || !unitId) {
            alert("Veuillez sélectionner un produit et une unité.");
            return;
        }

        calendar.removeAllEvents(); // Vide les anciens events

        calendar.setOption('events', function(info, successCallback, failureCallback) {
            $.ajax({
                url: DSI_Calendar.ajax_url,
                method: 'POST',
                data: {
                    action: 'dsi_get_calendar_events',
                    product_id: productId,
                    unit_id: unitId,
                    start: info.startStr,
                    end: info.endStr,
                    _ajax_nonce: DSI_Calendar.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const events = response.data.map(event => {
                            return event;
                        });
                        successCallback(events);
                    } else {
                        failureCallback(response.data?.message || 'Erreur');
                    }
                },
                error: function(err) {
                    console.error(err);
                    failureCallback('Erreur serveur');
                }
            });
        });

        calendar.refetchEvents();
    });


    $('.start_date, .end_date').on('change', function () {
        const form = $(this).closest('form');
        const startDateInput = form.find('.start_date');
        const endDateInput = form.find('.end_date');
        const startDateVal = startDateInput.val();

        // Si la date de début est bien remplie
        if (startDateVal) {
            const startDate = new Date(startDateVal);
            
            // Convertir au format AAAA-MM-JJ
            const minDate = startDate.toISOString().split('T')[0];

            // Appliquer comme min sur le champ de fin
            endDateInput.attr('min', minDate);
            
            // Réinitialiser si la date de fin est invalide
            if (endDateInput.val() && endDateInput.val() < minDate) {
                endDateInput.val('');
                form.find('.message').removeClass().addClass('message notice notice-info').text('La date de fin doit être postérieure à la date de début.');
            }
        }
    });



    // Soumission AJAX du formulaire d'ajout de maintenance

    $('form').on('submit', function(e) {
        const form = $(this);
        if (form.find('button[type=submit][name=dsi_add_maintenance]').length === 0) return; // Ne cible que le formulaire d'ajout
        e.preventDefault();
        const messageDiv = form.find('.message');
        messageDiv.removeClass().addClass('message').text('');
        const data = {
            action: 'dsi_admin_add_maintenance',
            product_id: form.find('select[name=product_id]').val(),
            unit_id: form.find('input[name=unit_id]').val(),
            start_date: form.find('input[name=start_date]').val(),
            end_date: form.find('input[name=end_date]').val(),
            note: form.find('input[name="note"]').val(),
            _ajax_nonce: DSI_Calendar.nonce
        };
        $.post(DSI_Calendar.ajax_url, data, function(response) {
            if (response.success) {
                messageDiv.removeClass().addClass('message notice notice-success').text(response.data.message);
                form[0].reset();
                // Rafraîchir le calendrier si besoin
                $('#btn-load-calendar').trigger('click');
            } else {
                messageDiv.removeClass().addClass('message notice notice-error').text(response.data && response.data.message ? response.data.message : 'Erreur.');
            }
        });
    });


}); 