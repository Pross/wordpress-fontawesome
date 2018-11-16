<?php

if ( ! class_exists( 'FontAwesome_Config_Controller' ) ) :

	/**
	 * Controller class for REST endpoint
	 */
	class FontAwesome_Config_Controller extends WP_REST_Controller {

		private $plugin_slug = null;

		protected $namespace = null;

		public function __construct( $plugin_slug, $namespace ) {
			$this->plugin_slug = $plugin_slug;
			$this->namespace   = $namespace;
		}

		public function register_routes() {
			$route_base = 'config';

			register_rest_route(
				$this->namespace,
				'/' . $route_base,
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'get_item' ),
						'permission_callback' => function() {
							return current_user_can( 'manage_options' ); },
						'args'                => array(),
					),
					array(
						'methods'             => 'PUT',
						'callback'            => array( $this, 'update_item' ),
						'permission_callback' => function() {
							return current_user_can( 'manage_options' ); },
						'args'                => array(),
					),
				)
			);
		}

		protected function build_item( $fa ) {
			return array(
				'adminClientInternal' => FontAwesome::ADMIN_USER_CLIENT_NAME_INTERNAL,
				'adminClientExternal' => FontAwesome::ADMIN_USER_CLIENT_NAME_EXTERNAL,
				'options'             => $fa->options(),
				'clientRequirements'  => $fa->requirements(),
				'conflicts'           => $fa->conflicts(),
				'currentLoadSpec'     => $fa->load_spec(),
				'unregisteredClients' => $fa->unregistered_clients(),
				'releases'            => array(
					'available'        => $fa->get_available_versions(),
					'latest_version'   => $fa->get_latest_version(),
					'latest_semver'    => $fa->get_latest_semver(),
					'previous_version' => $fa->get_previous_version(),
					'previous_semver'  => $fa->get_previous_semver(),
				),
			);
		}

		/**
		 * Get the config, a singleton resource
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Response
		 */
		public function get_item( $request ) {
			/*
			 * TODO: consider alteratives to using ini_set to ensure that display_errors is disabled.
			 * Without this, when a client plugin of Font Awesome throws an error (like our plugin-epsilon
			 * in this repo), instead of this REST controller returning an HTTP status of 500, indicating
			 * the server error, it sends back a status of 200, setting the data property in the response
			 * object equal to an HTML document that describes the error. This confuses the client.
			 * Ideally, we'd be able to detect which plugin results in such an error by catching it and then
			 * reporting to the client which plugin caused the error. But at a minimum, we need to make sure
			 * that we return 500 instead of 200 in these cases.
			 */
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_ini_set
			ini_set( 'display_errors', 0 );
			// If we don't add a reset() here, then the subsequent load() with rebuild
			// will end up adding the clients a second time.
			// We need to run load() with rebuild to make sure that all data is populated
			// for build_item().
			try {
				$fa = FontAwesome::reset();
				$fa->load(
					[
						'rebuild' => true,
						'save'    => false,
					]
				);
				$data = $this->build_item( $fa );

				return new WP_REST_Response( $data, 200 );
			} catch ( Exception $e ) {
				/*
				 * TODO: distinguish between problems that happen with the Font Awesome plugin versus those that happen in
				 * client plugins.
				 */
				return new WP_Error( 'cant-fetch', 'Whoops, there was a critical error trying to load Font Awesome.', array( 'status' => 500 ) );
			}
		}

		/**
		 * Update the singleton resource
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Response
		 */
		public function update_item( $request ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_ini_set
			ini_set( 'display_errors', 0 );

			$item = $this->prepare_item_for_database( $request );

			$current_options = get_option( FontAwesome::OPTIONS_KEY );

			if ( $item['options'] === $current_options || update_option( FontAwesome::OPTIONS_KEY, $item['options'] ) ) {
				// Because FontAwesome is a singleton, we need to reset it now that the
				// user options have changed. And running load() is what must happen
				// in order to fully populate the object with all of its data that will
				// be pulled together into a response object by build_item().
				try {
					$fa = FontAwesome::reset();
					$fa->load(
						[
							'rebuild' => true,
							'save'    => true,
						]
					);
					$return_data = $this->build_item( $fa );
					return new WP_REST_Response( $return_data, 200 );
				} catch ( Exception $e ) {
					return new WP_Error( 'cant-update', 'Whoops, the attempt to update options failed.', array( 'status' => 500 ) );
				}
			} else {
				return new WP_Error( 'cant-update', 'Whoops, we couldn\'t update those options.', array( 'status' => 500 ) );
			}
		}

		/**
		 * Prepare the item for and update operation
		 *
		 * @param WP_REST_Request $request Request object
		 * @return array $prepared_item
		 */
		protected function prepare_item_for_database( $request ) {
			$body = $request->get_json_params();
			return array_merge( array(), $body );
		}
	}

endif; // end class_exists.