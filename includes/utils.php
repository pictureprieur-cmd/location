<?php
// Helpers utilitaires pour DSI Location

/**
 * Formatte une date au format français (d/m/Y)
 */
function dsi_format_date_fr($date) {
    if (!$date) return '';
    $dt = date_create($date);
    if (!$dt) $dt = DateTime::createFromFormat('m-d-y', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}

/**
 * Vérifie la présence de champs obligatoires dans un tableau (ex: $_POST)
 * @param array $fields Liste des champs attendus
 * @param array $data Tableau à vérifier (ex: $_POST)
 * @return array Liste des champs manquants (vide si tout est ok)
 */
function dsi_check_required_fields(array $fields, array $data) {
    $missing = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $missing[] = $field;
        }
    }
    return $missing;
} 

function log_error($data) {
    $log_file = DSI_PLUGIN_DIR . 'error_log.log';
    $timestamp = date("Y-m-d H:i:s");
    $message = "[$timestamp] $data\n";
    error_log($message, 3, $log_file);
}

/**
 * Récupère les horaires de réservation (SELECT)
 * @return array
 */
function dsi_get_location_hours() {
    $defaults = [
        'matin_start' => 9,  // 9h00 pour le retrait matin
        'matin_end' => 12,   // 12h00 pour le retour midi
        'aprem_start' => 14, // 14h00 pour le retrait soir
        'aprem_end' => 18,   // 18h00 pour le retour soir
    ];
    $hours = get_option('dsi_location_hours', $defaults);
    return is_array($hours) ? array_merge($defaults, $hours) : $defaults;
}

/**
 * Met à jour les horaires de réservation (UPDATE)
 * @param array $hours
 * @return bool
 */
function dsi_update_location_hours($hours) {
    return update_option('dsi_location_hours', $hours);
}

/**
 * Insère les horaires de réservation si non existant (INSERT)
 * @param array $hours
 * @return bool
 */
function dsi_insert_location_hours($hours) {
    if (get_option('dsi_location_hours', null) === null) {
        return add_option('dsi_location_hours', $hours);
    }
    return false;
}


//------------------------------------------------------------

/**
 * Calcule le prix total d'une réservation en tenant compte des week‑ends réels,
 * des journées complètes et des demi‑journées, sans fusion de créneaux
 * entre dates différentes.
 *
 * @param string $start_date     Date de début ("YYYY-MM-DD").
 * @param int    $start_hour     Heure de début (9 ou 14).
 * @param string $end_date       Date de fin ("YYYY-MM-DD").
 * @param int    $end_hour       Heure de fin (12 ou 18).
 * @param float  $day_price      Prix d'une journée entière.
 * @param float  $half_price     Prix d'une demi-journée.
 * @param float  $weekend_price  Prix d'un week-end complet (samedi+dimanche).
 * @return float Montant total.
 * @throws Exception Si un créneau non autorisé est détecté.
 */
function dsi_calculate_reservation_total(
    string $start_date,
    int    $start_hour,
    string $end_date,
    int    $end_hour,
    float  $day_price,
    float  $half_price,
    float  $weekend_price
): float {
    $hours = dsi_get_location_hours();
    // Normaliser en entiers pour comparaisons strictes
    $mor_start = intval($hours['matin_start']);
    $mor_end   = intval($hours['matin_end']);
    $aft_start = intval($hours['aprem_start']);
    $aft_end   = intval($hours['aprem_end']);

    // Normalisation défensive des heures reçues
    $start_hour = intval($start_hour);
    $end_hour   = intval($end_hour);

    // Autoriser un début à midi (12h) comme début d'après-midi
    if ($start_hour === $mor_end) {
        $start_hour = $aft_start;
    }

    $segments = [];

    // Même date
    if ($start_date === $end_date) {
        if ($start_hour === $mor_start && $end_hour === $mor_end) {
            // Matin seul (9h-12h)
            $segments[] = ['date' => $start_date, 'slot' => 'am'];
        } elseif ($start_hour === $aft_start && $end_hour === $aft_end) {
            // Après-midi seul (14h-18h)
            $segments[] = ['date' => $start_date, 'slot' => 'pm'];
        } elseif ($start_hour === $mor_end && $end_hour === $aft_end) {
            // Midi → Soir traité comme après-midi seul
            $segments[] = ['date' => $start_date, 'slot' => 'pm'];
        } elseif ($start_hour === $mor_start && $end_hour === $aft_end) {
            // Journée entière (9h-18h)
            $segments[] = ['date' => $start_date, 'slot' => 'am'];
            $segments[] = ['date' => $start_date, 'slot' => 'pm'];
        } else {
            throw new Exception("Créneau non autorisé: {$start_date} {$start_hour}-{$end_hour}");
        }
    } else {
        // Premier jour
        if ($start_hour === $mor_start) {
            $segments[] = ['date' => $start_date, 'slot' => 'am'];
            $segments[] = ['date' => $start_date, 'slot' => 'pm'];
        } elseif ($start_hour === $aft_start) {
            $segments[] = ['date' => $start_date, 'slot' => 'pm'];
        } else {
            throw new Exception("Début non autorisé: {$start_hour}");
        }
        // Jours complets intermédiaires
        $d = new DateTime($start_date);
        $end = new DateTime($end_date);
        $d->modify('+1 day');
        while ($d < $end) {
            $segments[] = ['date' => $d->format('Y-m-d'), 'slot' => 'am'];
            $segments[] = ['date' => $d->format('Y-m-d'), 'slot' => 'pm'];
            $d->modify('+1 day');
        }
        // Dernier jour
        if ($end_hour === $aft_end) {
            $segments[] = ['date' => $end_date, 'slot' => 'am'];
            $segments[] = ['date' => $end_date, 'slot' => 'pm'];
        } elseif ($end_hour === $mor_end) {
            $segments[] = ['date' => $end_date, 'slot' => 'am'];
        } else {
            throw new Exception("Fin non autorisée: {$end_hour}");
        }
    }

    // 2) Construction d'une map par date
    $map = [];
    foreach ($segments as $s) {
        $map[$s['date']][$s['slot']] = true;
    }

    // 3) Comptage par date
    $weekend_halves = 0;
    $weekday_full   = 0;
    $weekday_halves = 0;

    foreach ($map as $date => $slots) {
        $dt  = DateTime::createFromFormat('Y-m-d', $date);
        $dow = (int)$dt->format('N'); // 6=Samedi,7=Dimanche

        $count = count($slots);
        if ($dow >= 6) {
            $weekend_halves += $count; // chaque demi compte
        } else {
            if ($count === 2) {
                $weekday_full++;
            } elseif ($count === 1) {
                $weekday_halves++;
            }
        }
    }

    // 4) Calcul
    $blocks_weekend = intdiv($weekend_halves, 4);
    $rem_weekend    = $weekend_halves % 4;

    $total = 0.0;
    $total += $blocks_weekend * $weekend_price;
    $total += $rem_weekend * $half_price;
    $total += $weekday_full * $day_price;
    $total += $weekday_halves * $half_price;

    return $total;
}