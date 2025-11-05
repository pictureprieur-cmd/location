<?php
if ( ! class_exists( 'DSI_Reservations_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

    class DSI_Reservations_List_Table extends WP_List_Table {
        public function __construct() {
            parent::__construct([
                'singular' => 'réservation',
                'plural'   => 'réservations',
                'ajax'     => false,
            ]);
        }

        public function get_columns() {
            
            return [
                //'cb'                => '<input type="checkbox" />',
                'id'                => 'ID',
                'order_id'          => 'Commande',
                'product_id'        => 'Produit',
                'unit_id'           => 'Unité',
                'user_id'           => 'Utilisateur',
                'start_date'        => 'Date début',
                //'start_hour'        => 'Heure début',
                'end_date'          => 'Date fin',
                //'end_hour'          => 'Heure fin',
                'cancel'            => 'Annuler la réservation',
                'returned'          => 'Statut',
                'action'            => 'Action',
                //'expected_date'     => 'Date retour prévue',
                //'waiting_validation'=> 'En attente',
                //'total_price'       => 'Prix total',
            ];
        }

        public function column_default( $item, $column_name ) {
            return isset( $item[ $column_name ] )
                ? esc_html( $item[ $column_name ] )
                : '';
        }

        protected function column_order_id( $item ) {
            $order_id = isset( $item['order_id'] ) ? absint( $item['order_id'] ) : 0;
            if ( ! $order_id ) {
                return '—';
            }

            // Récupère la commande (pour avoir le numéro affiché correct)
            $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
            $display_number = $order ? $order->get_order_number() : $order_id; // gère les numéros séquentiels

            // Lien admin vers la commande
            $url = admin_url( sprintf( 'post.php?post=%d&action=edit', $order_id ) );

            // Ne faire un lien que si l’utilisateur a le droit
            if ( current_user_can( 'edit_shop_order', $order_id ) || current_user_can( 'edit_post', $order_id ) ) {
                return sprintf(
                    '<a href="%s" title="%s" target="_blank">#%s</a>',
                    esc_url( $url ),
                    esc_attr__( 'Voir la commande', 'dsi-location' ),
                    esc_html( $display_number )
                );
            }

            // Sinon, juste le numéro affiché
            return '#' . esc_html( $display_number );
        }


        protected function column_product_id( $item ) {
            $post = get_post( $item['product_id'] );
            if ( $post ) {
                return esc_html( $post->post_title );
            }
            return '–';
        }

		protected function column_user_id( $item ) {
			$user_id = isset($item['user_id']) ? intval($item['user_id']) : 0;
			$user = $user_id ? get_userdata( $user_id ) : false;

			if ( ! $user ) {
				return $user_id ? sprintf('— (ID %d)', $user_id) : '—';
			}

			$prenom = esc_html( get_user_meta( $user->ID, 'first_name', true ) );
			$nom    = esc_html( get_user_meta( $user->ID, 'last_name', true ) );
			$email  = esc_html( $user->user_email );
			$tel    = esc_html( get_user_meta( $user->ID, 'billing_phone', true ) );

			$client = trim( $prenom . ' ' . $nom );
			if ( $client === '' ) {
				$nick = get_user_meta( $user->ID, 'nickname', true );
				if ( ! $nick ) {
					$nick = $user->display_name ? $user->display_name : $user->user_login;
				}
				$client = esc_html( $nick );
			}

			return '<a href="#" class="dsi-client-modal-link row-client-link" data-nom="' . $nom . '"
						data-prenom="' . $prenom . '"
						data-email="' . $email . '"
						data-tel="' . $tel . '"
						style="text-decoration:underline; color:#2271b1; cursor:pointer;"
						>' . $client . '</a>';
		}

        protected function column_start_date( $item ){
            $start_date = $item['start_date'];
            $start_hour = $item['start_hour'];

            return dsi_format_date_fr($start_date) . ' - ' . $start_hour . 'h';
        }

        protected function column_end_date( $item ){
            $end_date = $item['end_date'];
            $end_hour = $item['end_hour'];

            return dsi_format_date_fr($end_date) . ' - ' . $end_hour . 'h';
        }

        protected function column_cancel( $item ){
            $id = intval($item['id']);
            $url = wp_nonce_url(
                admin_url('admin-post.php?action=dsi_admin_cancel_reservation&reservation=' . $id),
                'dsi_admin_cancel_reservation_' . $id
            );
            return '<a href="' . esc_url($url) . '" class="button button-small">Annuler</a>';
        }

		protected function column_returned( $item ) {
			$today_ts = current_time( 'timestamp' );

			// Heures par défaut si absentes
			$hours = function_exists( 'dsi_get_location_hours' ) ? dsi_get_location_hours() : [
				'matin_start' => 9,
				'matin_end'   => 12,
				'aprem_start' => 14,
				'aprem_end'   => 18,
			];

			$start_hour = isset( $item['start_hour'] ) && $item['start_hour'] !== '' ? intval( $item['start_hour'] ) : intval( $hours['matin_start'] );
			$end_hour   = isset( $item['end_hour'] ) && $item['end_hour'] !== '' ? intval( $item['end_hour'] )   : intval( $hours['aprem_end'] );

			// Concaténer date + heure pour une comparaison précise
			$start_ts = strtotime( $item['start_date'] . ' ' . sprintf( '%02d:00:00', $start_hour ) );
			$end_ts   = strtotime( $item['end_date']   . ' ' . sprintf( '%02d:00:00', $end_hour ) );

			$returned = intval( ! empty( $item['returned'] ) );
			$taken    = intval( ! empty( $item['taken'] ) );

			if ( $returned === 1 ) {
				$icon  = '✔️';
				$label = __( 'Retourné', 'dsi-location' );
			} elseif ( $taken === 1 && $today_ts <= $end_ts && $returned === 0 ) {
				$icon  = '✅';
				$label = __( 'En cours', 'dsi-location' );
			} elseif ( $today_ts > $end_ts && $returned === 0 ) {
				$icon  = '❌';
				$label = __( 'Non retourné', 'dsi-location' );
			} elseif ( $today_ts < $start_ts ) {
				$icon  = '⏳';
				$label = __( 'À venir', 'dsi-location' );
			} elseif ( $today_ts >= $start_ts && $taken === 0 ) {
				$icon  = '🕙';
				$label = __( 'En retard', 'dsi-location' );
			} else {
				$icon  = 'ℹ️';
				$label = __( 'Statut inconnu', 'dsi-location' );
			}

			return sprintf( '%s %s', esc_html( $icon ), esc_html( $label ) );
		}

        protected function column_action( $item ) {
            $id         = (int) $item['id'];
            $start_date = ! empty($item['start_date']) ? esc_attr($item['start_date']) : '';
            $start_hour = isset($item['start_hour']) ? (int) $item['start_hour'] : '';
            $end_date   = ! empty($item['end_date'])   ? esc_attr($item['end_date'])   : '';
            $end_hour   = isset($item['end_hour'])   ? (int) $item['end_hour']   : '';
        
            $btn_taken = '';
            if ( (int) $item['taken'] === 0 ) {
                $btn_taken = sprintf(
                    '<button type="button"
                             class="button button-small dsi-open-start-modal"
                             data-reservation="%d"
                             data-start-date="%s"
                             data-start-hour="%s">%s</button>',
                    $id, $start_date, $start_hour,
                    esc_html__( 'Retrait', 'dsi-location' )
                );
            }
        
            $btn_returned = '';
            if ( (int)$item['taken'] === 1 && (int)$item['returned'] === 0 ) {
            
                // sécurise le format pour l’input type="date"
                $end_date_raw = !empty($item['end_date']) ? $item['end_date'] : '';
                $end_date_val = $end_date_raw ? date('Y-m-d', strtotime($end_date_raw)) : '';
            
                $end_hour_val = isset($item['end_hour']) ? (int)$item['end_hour'] : '';
            
                $btn_returned = sprintf(
                    '<button type="button"
                        class="button button-small dsi-open-end-modal"
                        data-reservation="%d"
                        data-end-date="%s"
                        data-end-hour="%s">%s</button>',
                    (int) $item['id'],
                    esc_attr($end_date_val),   // ex: 2025-08-21
                    esc_attr($end_hour_val),   // ex: 11
                    esc_html__( 'Retour', 'dsi-location' )
                );
            }
            
        
            return $btn_taken . ' ' . $btn_returned;
        }
        

        protected function get_sortable_columns() {
            return [
                'id'            => ['id', false],
                'order_id'      => ['order_id', false],
                'product_id'    => ['product_id', false],
                'unit_id'       => ['unit_id', false],
                'start_date'       => ['start_date', false],
                'end_date'       => ['end_date', false],
            ];
        }

        public function prepare_items() {
            global $wpdb;
            $table        = $wpdb->prefix . 'dsi_location_reservations';
            $per_page     = 20;
            $current_page = $this->get_pagenum();
            $offset       = ( $current_page - 1 ) * $per_page;

            $where_sql = '';
            if ( ! empty( $_REQUEST['product_filter'] ) && intval( $_REQUEST['product_filter'] ) > 0 ) {
                $pid       = intval( $_REQUEST['product_filter'] );
                $where_sql = $wpdb->prepare( "WHERE product_id = %d", $pid );
            }

            $orderby = isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ), true )
                    ? esc_sql( $_REQUEST['orderby'] )
                    : 'id';
            $order   = ( isset( $_REQUEST['order'] ) && in_array( strtoupper( $_REQUEST['order'] ), ['ASC','DESC'], true ) )
                    ? $_REQUEST['order']
                    : 'DESC';

            $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table $where_sql" );

            $this->items = $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT * 
                    FROM $table
                    $where_sql
                    ORDER BY $orderby $order
                    LIMIT %d OFFSET %d
                    ",
                    $per_page,
                    $offset
                ),
                ARRAY_A
            );

            $this->set_pagination_args( [
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil( $total_items / $per_page ),
            ] );

            $this->_column_headers = [
                $this->get_columns(),
                [], // colonnes cachées
                $this->get_sortable_columns(),
            ];
        }

        protected function extra_tablenav( $which ) {
            if ( 'top' !== $which ) {
                return;
            }

            $current = isset( $_REQUEST['product_filter'] ) 
                ? intval( $_REQUEST['product_filter'] ) 
                : 0;

            $product_ids = wp_list_pluck( $this->items, 'product_id' );
            $product_ids = array_unique( array_map( 'intval', $product_ids ) );

            if ( empty( $product_ids ) ) {
                return;
            }

            echo '<div class="alignleft actions">';
            echo '<select name="product_filter">';
            echo '<option value="0">' . esc_html__( '— Tous les produits —', 'dsi-location' ) . '</option>';

            foreach ( $product_ids as $pid ) {
                $post = get_post( $pid );
                if ( ! $post ) {
                    continue;
                }
                printf(
                    '<option value="%1$d"%2$s>%3$s</option>',
                    $pid,
                    selected( $current, $pid, false ),
                    esc_html( $post->post_title )
                );
            }

            echo '</select>';
            submit_button( __( 'Filtrer', 'dsi-location' ), 'button', false, false );
            echo '</div>';
        }


/*
        protected function column_cb( $item ) {
            return sprintf(
                '<input type="checkbox" name="reservation_id[]" value="%s" />',
                $item['id']
            );
        }
*/
 

/*
        protected function get_bulk_actions() {
            return [
                'delete' => 'Supprimer',
            ];
        }
*/
    }
}
