<?php
/**
 * WPCasa
 *
 * @package           WPCasaNinjaForms
 * @author            WPSight
 * @copyright         2024 Kybernetik Services GmbH
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WPCasa Ninja Forms
 * Plugin URI:        https://wpcasa.com/downloads/wpcasa-ninja-forms
 * Description:       Add support for Ninja Forms plugin (v3.0.34.1 or higher) to attach property details to the contact email sent from WPCasa listing pages.
 * Version:           2.0.0
 * Requires at least: 4.0
 * Requires Plugins:  wpcasa, ninja-forms
 * Requires PHP:      5.6
 * Author:            WPSight
 * Author URI:        https://wpcasa.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcasa-ninja-forms
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * WPSight_Ninja_Forms class
 */
class WPSight_Ninja_Forms {

	/**
	 * Variable to contain WPSight_Ninja_Forms_Admin class
	 */
	public $admin;

	/**
	 * Constructor
	 */
	public function __construct() {

		// Define constants
		
		if ( ! defined( 'WPSIGHT_NAME' ) )
			define( 'WPSIGHT_NAME', 'WPCasa' );

		if ( ! defined( 'WPSIGHT_DOMAIN' ) )
			define( 'WPSIGHT_DOMAIN', 'wpcasa' );

		define( 'WPSIGHT_NINJA_FORMS_VERSION', '2.0.0' );
		define( 'WPSIGHT_NINJA_FORMS_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'WPSIGHT_NINJA_FORMS_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

		if ( is_admin() ){
			include( WPSIGHT_NINJA_FORMS_PLUGIN_DIR . '/includes/admin/class-wpsight-ninja-forms-admin.php' );
			$this->admin = new WPSight_Ninja_Forms_Admin();
		}

		// Actions		
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
		add_action( 'template_redirect', array( $this, 'listing_form_display' ) );
		add_action( 'ninja_forms_display_pre_init', array( $this, 'listing_form_agent' ) );
		
		// Remove Actions
		remove_action('init', 'ninja_forms_register_display_req_items');

	}

	/**
	 *	init()
	 *
	 *  Initialize the plugin when WPCasa is loaded
	 *
	 *  @param  object  $wpsight
	 *	@uses	do_action_ref_array()
	 *  @return object
	 *
	 *	@since 1.0.0
	 */
	public static function init( $wpsight ) {
		if ( ! isset( $wpsight->ninja_forms ) ) 
			$wpsight->ninja_forms = new self();

		do_action_ref_array( 'wpsight_init_ninja_forms', array( &$wpsight ) );

		return $wpsight->ninja_forms;
	}

	/**
	 *	load_plugin_textdomain()
	 *	
	 *	Set up localization for this plugin
	 *	loading the text domain.
	 *	
	 *	@uses	load_plugin_textdomain()
	 *	
	 *	@since 1.0.0
	 */

	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'wpcasa-ninja-forms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	
	/**
	 *	frontend_scripts()
	 *	
	 *	Register and enqueue scripts and css.
	 *	
	 *	@uses	wp_enqueue_style()
	 *	@uses	wpsight_get_option()
	 *	
	 *	@since 1.0.0
	 */
	public function frontend_scripts() {
		
		// Script debugging?
		$suffix = SCRIPT_DEBUG ? '' : '.min';
		
		if( is_singular( wpsight_post_type() ) && wpsight_get_option( 'ninja_listing_form_css' ) )
			wp_enqueue_style( 'wpsight-ninja-forms', WPSIGHT_NINJA_FORMS_PLUGIN_URL . '/assets/css/wpsight-ninja-forms' . $suffix . '.css', '', WPSIGHT_NINJA_FORMS_VERSION );

	}
	
	/**
	 *	listing_form_display()
	 *
	 *  Gets display option and current page.
	 *	Then fires corresponding action hook
	 *	to display the form on the listing page.
	 *
	 *	@uses	wpsight_post_type()
	 *	@uses	wpsight_get_option()
	 *	@uses	add_action()
	 *
	 *	@since 1.0.0
	 */
	public function listing_form_display() {
		
		if( is_singular( wpsight_post_type() ) && wpsight_get_option( 'ninja_listing_form_display' ) )
			add_action( wpsight_get_option( 'ninja_listing_form_display' ), array( $this, 'listing_form' ) );
		
	}
	
	/**
	 *	listing_form()
	 *
	 *  Displays a form when there is one
	 *	selected on the settings page.
	 *
	 *	@uses	wpsight_get_option()
	 *	@uses	ninja_forms_display_form()
	 *
	 *	@since 1.0.0
	 */
	public function listing_form() {
		
		if( wpsight_get_option( 'ninja_listing_form_id' ) )
			echo do_shortcode( '[ninja_form id=' . absint( wpsight_get_option( 'ninja_listing_form_id' ) ) . ']' );
		
	}
	
	/**
	 *	listing_form_agent()
	 *
	 *  Pre-populates the hidden field with the
	 *	listing agent email that is selected
	 *	on the settings page.
	 *
	 *	@uses	wpsight_get_option()
	 *	@uses	$ninja_forms_loading->update_field_value()
	 *	@uses	get_the_author_meta()
	 *	@uses	antispambot()
	 *
	 *	@since 1.0.0
	 */
	public function listing_form_agent( $form_id ) {		
		global $ninja_forms_loading;
		
		$_form_id	= absint( wpsight_get_option( 'ninja_listing_form_id' ) );
		$_field_id	= absint( wpsight_get_option( 'ninja_listing_field_id' ) );
		
		if( $_form_id && $_field_id && $_form_id == $form_id )
			$ninja_forms_loading->update_field_value( $_field_id, antispambot( get_the_author_meta( 'email' ) ) );
		
	}

	/**
	 *	activation()
	 *	
	 *	Callback for register_activation_hook
	 *	to create a default favorites page with
	 *	the [wpsight_dashboard] shortcode and
	 *	to create some default options to be
	 *	used by this plugin.
	 *	
	 *	@uses	plugin_dir_path()
	 *	@uses	untrailingslashit()
	 *	@uses	file_get_contents()
	 *	@uses	wpsight_get_option()
	 *	@uses	ninja_forms_import_form()
	 *	@uses	Ninja_Forms()->form()
	 *	@uses	wpsight_add_option()
	 *	
	 *	@since 1.0.0
	 */
	public static function activation() {

		// Get starter form
		$file = file_get_contents( untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/wpcasa-ninja-forms-starter.nff' );
		
		// create new form
		$form_id = ! wpsight_get_option( 'ninja_listing_form_id' ) ? Ninja_Forms()->form()->import_form( $file ) : false;
		
		$form_field_id = false;
		
		if( $form_id ) {
			
			foreach( Ninja_Forms()->form( absint( $form_id ) )->get_fields() as $key => $field ) {				
				if( '_hidden' == $field->get_type() && $field->get_label() == '_listing_agent' )
					$form_field_id = $field->get_id();
			}
			
		}

		// Add some default options

		$options = array(
			'ninja_listing_form_id'		=> $form_id,
			'ninja_listing_field_id'	=> $form_field_id,
			'ninja_listing_form_css'	=> '1',
			'ninja_listing_form_display'=> 'wpsight_listing_single_after'
		);

		foreach( $options as $option => $value ) {

			if( wpsight_get_option( $option ) )
				continue;

			wpsight_add_option( $option, $value );

		}

	}

	public static function ninja_form_activation_redirect( $plugin ) {
		if ( plugin_basename( __FILE__ ) == $plugin ) {
			$_form_id	= wpsight_get_option( 'ninja_listing_form_id' );
			$url = 'admin.php?page=ninja-forms&form_id=' . $_form_id;
			exit( esc_url( wp_redirect( $url ) ) );
		}
	}

	public static function deactivation() {
		wpsight_delete_option( 'ninja_listing_form_id' );
	}
	
}

/**
 *	Check if Ninja Forms plugin is active
 *	and activate our add-on if yes.
 *
 *	@since 1.0.1
 */
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

if( is_plugin_active( 'ninja-forms/ninja-forms.php' ) ) {

	$ninja_form_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/ninja-forms/ninja-forms.php' );
	if ( isset( $ninja_form_plugin_data ) ) {
		$ninja_form_version = $ninja_form_plugin_data['Version'];

		if ( $ninja_form_version >= '3.0.34.1' ) {

			// Run activation hook
			register_activation_hook( __FILE__, array( 'WPSight_Ninja_Forms', 'activation' ) );

			add_action( 'activated_plugin', array( 'WPSight_Ninja_Forms', 'ninja_form_activation_redirect' ) );

			// Run deactivation hook
			register_deactivation_hook( __FILE__, array( 'WPSight_Ninja_Forms', 'deactivation' ) );
				
			// Initialize plugin on wpsight_init
			add_action( 'wpsight_init', array( 'WPSight_Ninja_Forms', 'init' ) );
		} else {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			add_action('admin_head', 'wpsight_nf_hide_activation_notice');
			add_action('admin_notices', 'wpsight_nf_required_plugin_notice');
		}
	}

} else {
	deactivate_plugins( plugin_basename( __FILE__ ) );
	add_action('admin_head', 'wpsight_nf_hide_activation_notice');
	add_action('admin_notices', 'wpsight_nf_required_plugin_notice');
}

function wpsight_nf_hide_activation_notice() {
	?>
	<style>
		#message.updated.notice {
			display: none;
		}
	</style>
	<?php
}
function wpsight_nf_required_plugin_notice() {
	?>
	<div class="error notice is-dismissible"><p><?php echo wp_kses( sprintf( '%1$s <a href="https://wordpress.org/plugins/ninja-forms/" target="_blank">Ninja Forms</a> %2$s 3.0.34.1', __( 'WPCasa Ninja Forms', 'wpcasa-ninja-forms' ), __( 'greater than ', 'wpcasa-ninja-forms' ) ), array( 'a' => array( 'href' => array(), 'target' => array() ) ) ); ?></p></div>
	<?php
}
