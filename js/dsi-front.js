// Fichier fusionné : dsi-front.js
// Gère le calendrier, la réservation, la modale et les contrôles de dates côté utilisateur

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.dsi-calendar').forEach(function (calendarEl) {
        const parent = calendarEl.closest('.dsi-reservation-block');
        const productId = calendarEl.dataset.productId;
        const unitId = calendarEl.dataset.unitId;
        const form = parent.querySelector('.dsi-reservation-form');
        //const message = form.find('.message');

const $form = jQuery(form);
const $message = $form.find('.message');


        let isSubmitting = false;

        // Initialisation du calendrier
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            selectable: false,
            selectOverlap: false,
            firstDay: 1,
            locale: 'fr',
            contentHeight: '300px',
            selectAllow: function (info) {
                return (info.start >= getDateWithoutTime(new Date()));
            },
            buttonText: {
                today: 'Aujourd\'hui'
            },
            events: function (info, successCallback, failureCallback) {
                fetch(DSI_Calendar.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'dsi_get_calendar_events',
                        product_id: productId,
                        unit_id: unitId,
                        start: info.startStr,
                        end: info.endStr,
                        _ajax_nonce: DSI_Calendar.nonce
                    })
                })
                .then(res => res.json().catch(() => null))
                .then(data => {
                    if (data && data.success) {
                        successCallback(data.data);
                    } else {
                        failureCallback((data && data.data && data.data.message) || 'Erreur serveur');
                    }
                })
                .catch(err => {
                    failureCallback('Erreur serveur');
                    console.error('Calendar AJAX error:', err);
                });
            },
            select: function (info) {
                form.style.display = 'block';
                form.querySelector('input[name="unit_id"]').value = unitId;
                form.querySelector('input[name="start_date"]').value = info.startStr;
                form.querySelector('input[name="end_date"]').value = new Date(info.end).toISOString().split('T')[0];
            },
            eventColor: '#d9534f',
            eventTextColor: 'white'
        });

        calendar.render();
        calendarEl._calendar = calendar;

        // Gestion de l'envoi du formulaire (vérification + soumission)
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (isSubmitting) return;
            isSubmitting = true;
            const formData = new FormData(form);
            const product_id = formData.get('product_id');
            const unit_id = formData.get('unit_id');
            const start_date = formData.get('start_date');
            const end_date = formData.get('end_date');
            const time_start = formData.get('time-start');
            const time_end = formData.get('time-end');
            
            // Validation des champs obligatoires
            if (!product_id || !unit_id || !start_date || !end_date || !time_start || !time_end) {
                //alert('Merci de remplir tous les champs obligatoires : date de début, date de fin, heure de retrait et heure de retour.');
                $message.text('Merci de remplir tous les champs obligatoires : date de début, date de fin, heure de retrait et heure de retour.').addClass('notice notice-error');
                isSubmitting = false;
                return;
            }
            
            // Validation des dates
            if (end_date < start_date) {
                //alert('La date de fin ne peut pas être antérieure à la date de début.');
                $message.text('La date de fin ne peut pas être antérieure à la date de début.').addClass('notice notice-error');
                isSubmitting = false;
                return;
            }
            
            // Validation des heures pour la même journée
            if (start_date === end_date) {
                if (time_start === 'soir' && time_end === 'midi') {
                    //alert('Impossible : retrait le soir et retour le midi le même jour.');
                    $message.text('Impossible : retrait le soir et retour le midi le même jour.').addClass('notice notice-error');
                    isSubmitting = false;
                    return;
                }
                if (time_start === 'midi' && time_end === 'midi') {
                    //alert('Impossible : retrait et retour au midi le même jour.');
                    $message.text('Impossible : retrait et retour au midi le même jour.').addClass('notice notice-error');
                    isSubmitting = false;
                    return;
                }
            }
            const btn = form.querySelector('.btn-resa');
            if (btn) btn.disabled = true;
            // Vérification disponibilité via AJAX
            const params = new URLSearchParams();
            params.append('action', 'dsi_check_reservation_availability');
            params.append('product_id', product_id);
            params.append('unit_id', unit_id);
            params.append('start_date', start_date);
            params.append('end_date', end_date);
            params.append('time_start', time_start);
            params.append('time_end', time_end);
            params.append('_ajax_nonce', DSI_Calendar.nonce);
            fetch(DSI_Calendar.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(function(response) {
                console.log(JSON.stringify(response))
                if (response.success) {
                    // Ajout au panier WooCommerce via AJAX natif
                    if (typeof wc_add_to_cart_params !== 'undefined') {
                        jQuery.post({
                            url: wc_add_to_cart_params.ajax_url,
                            data: {
                                action: 'woocommerce_add_to_cart',
                                product_id: product_id,
                                quantity: 1,
                                unit_id: unit_id,
                                start_date: start_date,
                                end_date: end_date,
                                'time-start': time_start,
                                'time-end': time_end
                            },
                            success: function(response) {
                                // Affichage message succès
                                const messageDiv = form.querySelector('.message');
                                if (messageDiv) {
                                    messageDiv.className = 'message notice notice-success';
                                    messageDiv.innerText = 'Réservation ajoutée au panier !!!';
                                }
                                // Rafraîchir le mini-panier
                                if (typeof jQuery !== 'undefined' && jQuery('body').hasClass('woocommerce')) {
                                    //jQuery(document.body).trigger('added_to_cart');
                                }
                                // Autres actions (calendrier, reset, etc.)
                                calendar.refetchEvents();
                                form.reset();
                                if (btn) btn.disabled = false;
                                isSubmitting = false;
                                jQuery(document.body).trigger('wc_fragment_refresh');
                            },
                            error: function(xhr, status, error) {
                                const messageDiv = form.querySelector('.message');
                                if (messageDiv) {
                                    messageDiv.className = 'message notice notice-error';
                                    messageDiv.innerText = 'Erreur lors de l\'ajout au panier.';
                                }
                                if (btn) btn.disabled = false;
                                isSubmitting = false;
                            }
                        });
                    } else {
                        // Fallback : message d'erreur
                        const messageDiv = form.querySelector('.message');
                        if (messageDiv) {
                            messageDiv.className = 'message notice notice-error';
                            messageDiv.innerText = 'Erreur : WooCommerce AJAX non disponible.';
                        }
                    }
                } else {
                    const messageDiv = form.querySelector('.message');
                    if (messageDiv) {
                        messageDiv.className = 'message notice notice-error';
                        messageDiv.innerText = response.data && response.data.message ? response.data.message : 'Créneau non disponible.';
                    } else {
                        //alert(response.data && response.data.message ? response.data.message : 'Créneau non disponible.');
                        $message.text(response.data && response.data.message ? response.data.message : 'Créneau non disponible.').addClass('notice notice-error');
                    }
                    if (btn) btn.disabled = false;
                }
                isSubmitting = false;
            })
            .catch(function(err) {
                const messageDiv = form.querySelector('.message');
                if (messageDiv) {
                    messageDiv.className = 'message notice notice-error';
                    messageDiv.innerText = 'Erreur lors de la vérification de disponibilité.';
                } else {
                    //alert('Erreur lors de la vérification de disponibilité.');
                    $message.text('Erreur lors de la vérification de disponibilité.').addClass('notice notice-error');
                }
                if (btn) btn.disabled = false;
                isSubmitting = false;
            });
        });
    });




    function getDateWithoutTime(dt) {
        dt.setHours(0, 0, 0, 0);
        return dt;
    }

    // Contrôles de dates et UI
    jQuery(function($) {

        $('.start_date, .end_date').on('change', function () {
            const form = $(this).closest('form');
            const startDateInput = form.find('.start_date');
            const endDateInput = form.find('.end_date');
            const startDateVal = startDateInput.val();
            if (startDateVal) {
                const startDate = new Date(startDateVal);
                //startDate.setDate(startDate.getDate() + 1);
                const minDate = startDate.toISOString().split('T')[0];
                endDateInput.attr('min', minDate);
/*
                if (endDateInput.val() && endDateInput.val() < minDate) {
                    endDateInput.val('');
                    form.find('.message').addClass('notice notice-info').text('La date de début doit être inférieure à la date de fin !');
                }
*/
            }
            form.find('.btn-resa').attr('disabled', false);
        });

/*
        $('.multidate').on('change', function(){
            const form = $(this).closest('form');
            const endDate = form.find('.end_date');
            if ($(this).is(':checked')) {
                form.find('.end-date-block').css('display', 'block');
            }else{
                form.find('.end-date-block').css('display', 'none');
                form.find(endDate).val('');
            }
        });
*/
        // Modale réservations utilisateur
        $('.btn-view-resa').on('click', function () {
            const unitId = $(this).data('unit-id');
            const form = $('#form-calendar-' + unitId);
            const parent = form.closest('.dsi-reservation-block');
            const modal = parent.find('.dsi-reservation-modal');
            const productId = form.find('input[name="product_id"]').val();
            modal.data('product-id', productId);
            modal.data('unit-id', unitId);
            const tableBody = modal.find('.reservations-list');
            tableBody.html('<tr><td colspan="4">Chargement...</td></tr>');
            $.post(DSI_Calendar.ajax_url, {
                action: 'dsi_get_user_reservations',
                product_id: productId,
                unit_id: unitId,
                _ajax_nonce: DSI_Calendar.nonce
            }, function(response) {
                if (response.success) {
                    const reservations = response.data;
                    if (reservations.length === 0) {
                        tableBody.html('<tr><td colspan="5">Aucune réservation.</td></tr>');
                        return;
                    }
                    tableBody.empty();
                    reservations.forEach(res => {
                        var delai = $('.delai-annulation').val();
                        var retard = checkTooLate(res.start_date, delai);
                        var today = new Date();
                        var debutResa = new Date(res.start_date);
                        if(debutResa >= today){
                            const row = `
                                <tr data-id="${res.id}">
                                    <td>${res.start_date_fr || toDate(res.start_date)}</td>
                                    <td>${res.start_hour}</td>
                                    <td>${res.end_date_fr || toDate(res.end_date)}</td>
                                    <td>${res.end_hour}</td>
                                    <td>
                                        <button type="button" class="button btn-confirmer validate-cancel-reservation" style="display: none;">Confirmer</button>
                                        <button class="button btn-annuler cancel-reservation">Annuler</button>
                                        ${retard}
                                    </td>
                                </tr>`;
                            tableBody.append(row);
                        }
                    });
                } else {
                    tableBody.html('<tr><td colspan="4">Erreur de chargement</td></tr>');
                }
            });
            modal.show();
        });

        $(document).on('click', '.dsi-close-modal', function () {
            $(this).closest('.dsi-reservation-modal').hide();
        });

        $(document).on('click', '.validate-cancel-reservation', function () {
            const row = $(this).closest('tr');
            const modal = row.closest('.dsi-reservation-modal');
            const resId = row.data('id');
            //if (!confirm('Annuler cette réservation ?')) return;
            $.post(DSI_Calendar.ajax_url, {
                action: 'dsi_cancel_reservation',
                reservation_id: resId,
                _ajax_nonce: DSI_Calendar.nonce
            }, function(response) {
                if (response.success) {
                    row.remove();
                    const productId = modal.data('product-id');
                    const unitId = modal.data('unit-id');
                    const block = $(`.dsi-reservation-block[data-unit-id="${unitId}"]`);
                    const calendarEl = block.find('.dsi-calendar').get(0);
                    if (calendarEl && calendarEl._calendar) {
                        calendarEl._calendar.refetchEvents();
                    } else {
                        const fcInstance = FullCalendar.getCalendar(calendarEl);
                        if (fcInstance) fcInstance.refetchEvents();
                    }
                } else {
                    const unitId = modal.data('unit-id');
                    const $msg = jQuery(`.dsi-reservation-block[data-unit-id="${unitId}"]`).find('.message');
                    $msg.text('Erreur : ' + (response.data?.message || 'Impossible d\'annuler.'))
                        .addClass('notice notice-error')
                        .show();
                }
            });
        });

        $(document).on('click', '.dsi-reservation-modal', function (e) {
            if (e.target === e.currentTarget) {
                $(this).fadeOut(200);
            }
        });

        $(document).on('keydown', function(e) {
            if (e.key === "Escape") {
                $('.dsi-reservation-modal').fadeOut(500);
            }
        });

        $(document).on('click', '.cancel-reservation', function () {
            $(this).css('display', 'none');
            $(this).closest('td').find('.validate-cancel-reservation').removeAttr('style');
        });

        //$('input[name=start_date], input[name=end_date]').attr('min', formatDate(addDayToDate(new Date(), $('.delai-annulation').val())))

        function checkTooLate(date, day) {
            var today = new Date();
            var dateDebutResa = new Date(date);
            dateDebutResa.setDate(dateDebutResa.getDate() - day);
            var retour = '</br><span style="color:red">Le délai d\'annulation est dépassé, le montant de la réservation ne peut étre rendu.</span>'
            return (dateDebutResa < today) ? retour : '';
        }
/*
        $('.btn-resa').on('click', () => {
            $('.end-date-block').css('display', 'none');
        })
*/

        function formatDate(date) {
            var d = new Date(date),
                month = '' + (d.getMonth() + 1),
                day = '' + d.getDate(),
                year = d.getFullYear();

            if (month.length < 2) 
                month = '0' + month;
            if (day.length < 2) 
                day = '0' + day;

            return [year, month, day].join('-');
        }


        function addDayToDate(date, day){
            var day = parseInt(day)
            date.setDate(date.getDate() + day + 1);
            return date
        }

            // Rafraîchir FullCalendar après suppression d'un article du panier
            function dsiRefreshAllCalendars() {
                document.querySelectorAll('.dsi-calendar').forEach(function (calendarEl) {
                    if (!calendarEl) return;
                    var instance = calendarEl._calendar || (typeof FullCalendar !== 'undefined' ? FullCalendar.getCalendar(calendarEl) : null);
                    if (instance && typeof instance.refetchEvents === 'function') {
                        instance.refetchEvents();
                    }
                });
            }

            // WooCommerce (mini-panier/page panier) : événements fiables après suppression
            jQuery(document.body).on('removed_from_cart wc_fragments_refreshed updated_wc_div', dsiRefreshAllCalendars);

            // Fallback : sur le clic direct (certains thèmes custom)
            jQuery(document).on('click', '.remove_from_cart_button', function () {
                setTimeout(dsiRefreshAllCalendars, 150);
            });

		}); // End jQuery
});

// Ajoute la fonction toDate si elle n'existe pas déjà
function toDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('fr-FR');
} 

