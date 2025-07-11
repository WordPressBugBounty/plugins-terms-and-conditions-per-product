<?php
/**
 * Class for logging terms acceptance .
 *
 * @package Terms_Conditions_Accept_Log
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If class exists, then don't execute this.
if ( ! class_exists( 'TACPP4_Terms_Conditions_Accept_Log' ) ) {

	/**
	 * Class for Terms_Conditions_Accept_Log.
	 */
	class TACPP4_Terms_Conditions_Accept_Log {

		/**
		 * Constructor for class.
		 */
		public function __construct() {
			$this->load_hooks();
		}

		public function load_hooks() {
			// Add the fields to the "product category" taxonomy
			add_action( 'woocommerce_checkout_order_processed',
				array( $this, 'save_terms_acceptance' ), 10, 3 );

			add_action( 'add_meta_boxes', array(
				$this,
				'terms_acceptance_information_meta_box'
			) );
		}

		private function get_product_terms( $input_id = 0 ) {
			$terms = array();

			if ( $input_id && strstr( $input_id, 'tacpp-term-' ) ) {
				$info       = str_replace( 'tacpp-term-', '', $input_id );
				$info_array = explode( '-', $info );

				if ( is_array( $info_array ) && count( $info_array ) >= 3 ) {
					$cart_item = array(
						'product_id'   => $info_array[0],
						'variation_id' => $info_array[1],
						'term_id'      => $info_array[2]
					);

					$terms = TACPP4_Terms_Conditions_Per_Product::get_custom_terms( $cart_item );
				}
			}


			return $terms;
		}

		/**
		 * Save user accepted terms after checkout is processed
		 */
		public function save_terms_acceptance( $order_id, $posted_data, $order ) {
			if ( empty( $_REQUEST ) || ! is_array( $_REQUEST ) ) {
				return;
			}

			foreach ( $_REQUEST as $key => $value ) {
				if ( strstr( $key, 'tacpp-term-' ) ) {
					$product_terms = (array) $this->get_product_terms( $key );

					// Insert the terms to log
					$this->insert_terms_to_log(
						$order_id,
						get_current_user_id(),
						$product_terms
					);
				}
			}
		}

		/**
		 * Insert the accepted terms to log table
		 *
		 * @param       $order_id
		 * @param       $user_id
		 * @param array $product_terms
		 */
		private function insert_terms_to_log( $order_id, $user_id, array $product_terms ) {
			global $wpdb;
			$table_name = $wpdb->prefix . TACPP4_ACCEPT_LOG_TABLE_NAME;

			$order_id = absint( $order_id );
			$user_id  = absint( $user_id );

			// Bailout if empty data
			if ( empty( $order_id ) || empty( $product_terms ) ) {
				return;
			}

			foreach ( $product_terms as $data ) {

				if ( ! isset( $data['type'] ) || ! isset( $data['url'] ) || ! isset( $data['text'] ) ) {
					continue;
				}

				$insert_array = array(
					'user_id'       => $user_id,
					'order_id'      => $order_id,
					'type'          => $data['type'],
					'terms_url'     => $data['url'],
					'terms_text'    => $data['text'],
					'product_id'    => $data['product_id'],
					'variation_id'  => $data['variation_id'],
					'term_id'       => $data['term_id'],
					'date_recorded' => current_time( 'mysql', 1 )
				);

				$result = $wpdb->insert(
					$table_name,
					$insert_array
				);
			}
		}

		/**
		 * Display the Acceptance information meta box
		 */
		public function terms_acceptance_information_meta_box() {

			// Bail out if the log is disabled or not premium
			if ( ! $this->acceptance_log_enabled() ) {
				return;
			}

			add_meta_box(
				'acceptance_log',
				'<span class="dashicons dashicons-media-document"></span> User Accepted Terms',
				array( $this, 'display_acceptance_information' ),
				'shop_order',
				'normal',
				'default'
			);
			add_meta_box(
				'acceptance_logs',
				'<span class="dashicons dashicons-media-document"></span> User Accepted Terms',
				array( $this, 'display_acceptance_information' ),
				'woocommerce_page_wc-orders',
				'normal',
				'default'
			);

		}

		/**
		 * Add acceptance information to admin edit order page
		 * if applicable
		 */
		public function display_acceptance_information( $post ) {

			// Bail out if the log is disabled or not premium
			if ( ! $this->acceptance_log_enabled() ) {
				return;
			}

			if ( is_a( $post, 'Automattic\WooCommerce\Admin\Overrides\Order' ) ) {
				$order_id = $post->get_id();
			} else {
				if ( empty( $post ) || ! isset( $post->ID ) || empty( $post->ID ) ) {
					return;
				}

				$order_id = absint( $post->ID );
			}

			if ( empty( $order_id ) ) {
				return;
			}

			$accepted_items = self::get_accepted_items( $order_id );

			if ( ! empty( $accepted_items ) ) {
				?>
				<div class="acceptance-terms-wrapper">
					<table id="user-accepted-terms">
						<thead>
						<tr>
							<th class="center"><?php esc_html_e( 'S/N', 'terms-and-conditions-per-product' ); ?></th>
							<th><?php esc_html_e( 'Terms Type', 'terms-and-conditions-per-product' ); ?></th>
							<th><?php esc_html_e( 'Terms Text & Link', 'terms-and-conditions-per-product' ); ?></th>

						</tr>
						</thead>
						<tbody>
						<?php
						foreach ( $accepted_items as $key => $item ) {

							$type_link = self::get_type_link( $item );

							// Get type text
							$type = '';
							if ( $item['type'] == 'product' ) {
								$type = __( 'Product',
									'terms-and-conditions-per-product' );
							} else if ( $item['type'] == 'product_cat' ) {
								$type = __( 'Category',
									'terms-and-conditions-per-product' );
							} else if ( $item['type'] == 'product_tag' ) {
								$type = __( 'Tag',
									'terms-and-conditions-per-product' );
							}
							?>
							<tr>
								<td class="center"><?php echo (int) $key + 1; ?>.</td>
								<td>
									<?php echo $type; ?>:
									<?php if ( isset( $type_link['url'] ) ) { ?>
									<a href="<?php echo esc_url( $type_link['url'] ); ?>" title="<?php
									printf(
									/* translators: %s: The title of the product/term */
										__( 'View %s',
											'terms-and-conditions-per-product' ),
										$type_link['title']
									); ?>">
										<?php } ?>
										<?php echo $type_link['title']; ?>
										<?php if ( $type_link ) { ?>
									</a>
								<?php } ?>
								</td>
								<td class="url">
									<a href="<?php echo $item['terms_url']; ?>">
										<?php echo $item['terms_text']; ?></a>
								</td>
								<td></td>


							</tr>
							<?php
						}
						?>
						</tbody>
					</table>
				</div>
				<?php
			} else {
				?>
				<p><?php _e( 'No user accepted terms were found.', 'terms-and-conditions-per-product' ); ?></p>
				<?php
			}
		}

		/**
		 * Get the accepted items from the database
		 *
		 * @param $order_id
		 *
		 * @return array $accepted_items
		 */
		public static function get_accepted_items( $order_id ) {
			global $wpdb;
			$accepted_items = array();

			$log_table_name = $wpdb->prefix . TACPP4_ACCEPT_LOG_TABLE_NAME;

			$query   = $wpdb->prepare( "
                SELECT SQL_CALC_FOUND_ROWS
                   *
                   FROM
                        $log_table_name 
                    WHERE
                        order_id = %d
                    ORDER BY
                        id ASC
                    ", $order_id );
			$results = $wpdb->get_results( $query, "ARRAY_A" );

			if ( ! empty( $results ) ) {
				$accepted_items = $results;
			}

			/**
			 * Filter the accepted order items
			 */
			return apply_filters( 'tacpp_accept_log_get_accepted_items',
				$accepted_items, $order_id );
		}

		/**
		 * Get the log record type link
		 *
		 * @param Array $log_record
		 *
		 * @return Array $type_link
		 */
		public static function get_type_link( $log_record ): array {

			// Bail out if Type is not set
			if ( ! isset( $log_record['type'] ) || empty( $log_record['type'] ) ) {
				return array();
			}

			$type = esc_html( $log_record['type'] );
			if ( empty( $type ) ) {
				return array();
			}

			$type_link = array();

			// Check if the log is about a product/variation
			if ( $type == 'product' ) {
				$product_id = ( isset( $log_record['product_id'] ) ) ? absint( $log_record['product_id'] ) : false;

				if ( $product_id > 0 ) {
					$type_link['url']   = admin_url( 'post.php?post=' . $product_id . '&action=edit' );
					$type_link['title'] = get_the_title( $product_id );
				}
			} // Check if the log is about a product category or tag
			else if ( $type == 'product_cat' || $type == 'product_tag' ) {
				$term_id = ( isset( $log_record['term_id'] ) ) ? absint( $log_record['term_id'] ) : false;

				if ( $term_id > 0 ) {
					$type_link['url']   = get_edit_term_link( $term_id, $type );
					$type_link['title'] = '';

					$term = get_term( $term_id );

					if ( ! is_wp_error( $term ) && $term ) {
						$type_link['title'] = $term->name;
					}
				}
			}

			return apply_filters( 'tacpp_get_type_link', $type_link, $log_record );
		}

		/**
		 * Check if Acceptance log is enabled
		 *
		 * @return bool
		 */
		private function acceptance_log_enabled() {
			// Init return value
			$return = false;

			// Get Settings
			$settings = get_option( TACPP4_Terms_Conditions_Settings::$tacpp_option_name );

			if ( isset( $settings['log_acceptance'] ) &&
			     $settings['log_acceptance'] === 1 &&
			     tacppp_fs()->is_paying_or_trial() ) {
				$return = true;
			}

			return $return;
		}
	}

	new TACPP4_Terms_Conditions_Accept_Log();
}
