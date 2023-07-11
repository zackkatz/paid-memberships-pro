<?php
/**
 *  On the admin side check if lifters streamline feature is enabled and react accordingly.
 */
function enable_streamlined_feature() {
	$is_lifter_streamnlined_enabled = get_option( 'pmpro_toggle_lifter_streamline_setup' ) == 'true';
	if($is_lifter_streamnlined_enabled ) {
		$lifter_streamline =  plugins_url() . '/paid-memberships-pro/css/lifter-streamline.css';
		wp_register_style( 'pmpro_lifter', $lifter_streamline, [], PMPRO_VERSION, 'screen' );
		wp_enqueue_style( 'pmpro_lifter' );
		include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
	} else {
		wp_dequeue_style( 'pmpro_lifter' );
	}
}
add_action( 'admin_init','enable_streamlined_feature' );

/**
 * On LifterLMS plugin activation, activate streamline feature and install PMPro Courses plugin.
 */
function lifter_plugin_activation() {	
	include_once( PMPRO_DIR . '/classes/' . 'class-quiet-plugin-installer.php' ) ;
	$slug = 'pmpro-courses';
	$installer = new Quiet_Skin(compact('title', 'url', 'nonce', 'plugin', 'api') );
	$installer->download_install_and_activate($slug);	
}
register_activation_hook( 'lifterlms/lifterlms.php', 'lifter_plugin_activation' );

/**
 * Override student dashboard links if streamline feature is enabled.
 */
function pmpro_lifter_override_dashboard_tabs( $tabs ) {	
	// Only override if streamlined is enabled.
	if ( ! get_option( 'pmpro_toggle_lifter_streamline_setup' ) ) {
		return $template;
	}
	
	// Override the My Memberships tab.
	if ( isset( $tabs ) && isset( $tabs['view-memberships'] ) ) {
		$tabs['view-memberships']['url'] = pmpro_url( 'account' );
	}

	// Override the My Orders tab.
	if ( isset( $tabs ) && isset( $tabs['orders'] ) ) {
		$tabs['orders']['url'] = pmpro_url( 'invoice' );
	}

	return $tabs;
}
add_filter( 'llms_get_student_dashboard_tabs', 'pmpro_lifter_override_dashboard_tabs' );

/**
 * Insert our option into the intro step of the LifterLMS wizard.
 */
function pmpro_lifter_intro_html( $html, $wizard ) {
	// Get any previous streamline option. Default to true.
	$streamline = get_option( 'pmpro_toggle_lifter_streamline_setup', null );	
	if ( $streamline === null ) {
		$streamline = 1;
	}
	
	// Save output buffer.
	ob_start();
	?>
	<hr />
	<p><?php esc_html_e( 'Since you already have Paid Memberships Pro installed, you can enable a "streamlined" version of LifterLMS that will let PMPro handle all checkouts, memberships, restrictions, and user fields.', 'paid-memberships-pro' ) ?></p>

	<label for="lifter-streamline">
		<input type="checkbox" name="lifter-streamline" id="lifter-streamline" <?php checked( (int)$streamline, 1 ); ?>>
		<?php esc_html_e( 'Enable streamlined version of LifterLMS', 'paid-memberships-pro' ) ?>
	</label>
	<script>
		jQuery(document).ready(function(){
			function pmpro_lifter_add_streamline_to_url() {
				let $checkbox = jQuery('#lifter-streamline');
				let $link = jQuery('.llms-setup-actions a.llms-button-primary');

				//If the checkbox is checked, add streamline to the url
				if ($checkbox.is(':checked')) {
					$link.attr('href', $link.attr('href') + '&pmpro_toggle_lifter_streamline_setup=true');
				} else {
					$link.attr('href', $link.attr('href').replace('&pmpro_toggle_lifter_streamline_setup=true', ''));
				}
			}
			
			// Update the URL when the checkbox is changed.
			jQuery('#lifter-streamline').on('change', function(){
				pmpro_lifter_add_streamline_to_url();
			});

			// Run on load too.
			pmpro_lifter_add_streamline_to_url();
		});
	</script>
	<?php
	// Add the content buffer to the $html string.
	$html .= ob_get_clean();
	
	return $html;
}
add_filter( 'llms_setup_wizard_intro_html', 'pmpro_lifter_intro_html', 10, 2 );

/**
 * Hook into Page Setup step to save the streamline option.
 */
function pmpro_lifter_save_streamline_option( $wizard ) {
	// Bail if we're in the LifterLMS wizard.
	if ( empty( $_REQUEST['page'] ) || $_REQUEST['page'] !== 'llms-setup' ) {
		return;
	}

	// Bail if we're not on the pages step.
	if ( empty( $_REQUEST['step'] ) || $_REQUEST['step'] !== 'pages' ) {
		return;
	}

	// Bail if the current user doesn't have permission to manage LifterLMS.
	if ( ! current_user_can( 'manage_lifterlms' ) ) {
		return;
	}

	// Get the streamline value.
	if ( ! empty( $_REQUEST['pmpro_toggle_lifter_streamline_setup'] ) ) {
		$streamline = 1;
	} else {
		$streamline = 0;
	}
	
	update_option( 'pmpro_toggle_lifter_streamline_setup', $streamline );
}
add_action( 'admin_init', 'pmpro_lifter_save_streamline_option' );

/**
 * If the streamline option is enabled, don't create some pages.
 */
function pmpro_lifter_install_create_pages( $pages ) {
	// Bail if streamline is not enabled.
	if ( get_option( 'pmpro_toggle_lifter_streamline_setup' ) ) {
		return $pages;
	}
	
	// Loop through and remove the membership catalog and checkout pages.
	$new_pages = array();
	foreach ( $pages as $page ) {
		if ( $page['slug'] == 'memberships' || $page['slug'] == 'purchase' ) {
			continue;
		}
		
		$new_pages[] = $page;
	}

	return $new_pages;
}
add_filter( 'llms_install_create_pages', 'pmpro_lifter_install_create_pages' );