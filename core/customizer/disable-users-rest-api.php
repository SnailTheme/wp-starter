<?php
/**
 * Disable REST users endpoint Customizer feature.
 *
 * @package ST_WP_Core
 */

if ( ! function_exists( 'st_wp_core_customizer_register_disable_users_rest_api' ) ) {
	/**
	 * Register the Disable Users REST API Customizer control.
	 *
	 * @param WP_Customize_Manager $wp_customize The WP_Customize_Manager instance.
	 *
	 * @return void
	 */
	function st_wp_core_customizer_register_disable_users_rest_api( WP_Customize_Manager $wp_customize ): void {
		$wp_customize->add_setting(
			'st_wp_core_disable_users_rest_api',
			array(
				'default'   => false,
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			'st_wp_core_disable_users_rest_api_control',
			array(
				'label'    => __( 'Disable Users REST API', 'st-wp-starter' ),
				'section'  => 'st_wp_core_security',
				'settings' => 'st_wp_core_disable_users_rest_api',
				'type'     => 'checkbox',
				'priority' => 10,
			)
		);
	}
}

if ( ! function_exists( 'st_wp_core_disable_rest_users_get' ) ) {
	/**
	 * Disable WordPress REST API `users` Endpoint for GET requests.
	 *
	 * @param mixed           $result  Response to replace the requested version with.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 *
	 * @return mixed Original result or WP_Error if users endpoint.
	 */
	function st_wp_core_disable_rest_users_get( $result, $server, $request ) {
		$route = $request->get_route();

		if ( strpos( $route, '/wp/v2/users' ) === 0 ) {
			return new WP_Error(
				'rest_user_cannot_view',
				__( 'Sorry, you are not allowed to list users.', 'st-wp-starter' ),
				array( 'status' => 401 )
			);
		}

		return $result;
	}
}

if ( ! function_exists( 'st_wp_core_customizer_boot_disable_users_rest_api' ) ) {
	/**
	 * Boot Disable Users REST API runtime behavior.
	 *
	 * @return void
	 */
	function st_wp_core_customizer_boot_disable_users_rest_api(): void {
		if ( ! get_theme_mod( 'st_wp_core_disable_users_rest_api', false ) ) {
			return;
		}

		add_filter( 'rest_pre_dispatch', 'st_wp_core_disable_rest_users_get', 10, 3 );
	}
}
