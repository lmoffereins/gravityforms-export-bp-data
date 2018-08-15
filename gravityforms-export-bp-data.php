<?php

/**
 * The Gravity Forms Export BP Data Plugin
 * 
 * @package Gravity Forms Export BP Data
 * @subpackage Main
 */

/**
 * Plugin Name:       Gravity Forms Export BP Data
 * Description:       Add BuddyPress user data to Gravityforms entry exports
 * Plugin URI:        https://github.com/lmoffereins/gravityforms-export-bp-data/
 * Version:           1.0.1
 * Author:            Laurens Offereins
 * Author URI:        https://github.com/lmoffereins/
 * Text Domain:       gravityforms-export-bp-data
 * Domain Path:       /languages/
 * GitHub Plugin URI: lmoffereins/gravityforms-export-bp-data
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GravityForms_Export_BP_Data' ) ) :
/**
 * The main plugin class
 *
 * @since 1.0.0
 */
final class GravityForms_Export_BP_Data {

	/**
	 * Setup and return the singleton pattern
	 *
	 * @since 1.0.0
	 *
	 * @return The single GravityForms_Export_BP_Data
	 */
	public static function instance() {

		// Store instance locally
		static $instance = null;

		if ( null === $instance ) {
			$instance = new GravityForms_Export_BP_Data;
			$instance->setup_globals();
			$instance->setup_actions();
		}

		return $instance;
	}

	/**
	 * Prevent the plugin class from being loaded more than once
	 *
	 * @since 1.0.0
	 */
	private function __construct() { /* Nothing to do */ }

	/** Private methods *************************************************/

	/**
	 * Define class globals
	 *
	 * @since 1.0.0
	 */
	private function setup_globals() {

		/** Versions **********************************************************/

		$this->version    = '1.0.1';

		/** Paths *************************************************************/

		// Setup some base path and URL information
		$this->file       = __FILE__;
		$this->basename   = plugin_basename( $this->file );
		$this->plugin_dir = plugin_dir_path( $this->file );
		$this->plugin_url = plugin_dir_url ( $this->file );

		// Languages
		$this->lang_dir   = trailingslashit( $this->plugin_dir . 'languages' );

		/** Misc **************************************************************/

		$this->extend     = new stdClass();
		$this->domain     = 'gravityforms-export-bp-data';
	}

	/**
	 * Setup default actions and filters
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {

		// Load textdomain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 20 );

		// Add export entry fields
		add_filter( 'gform_export_fields', array( $this, 'entry_export_fields' ) );

		// Provide export field data
		add_filter( 'gform_export_field_value', array( $this, 'export_field_value' ), 10, 4 );
	}

	/** Public methods **************************************************/

	/**
	 * Load the translation file for current language. Checks the languages
	 * folder inside the plugin first, and then the default WordPress
	 * languages folder.
	 *
	 * Note that custom translation files inside the plugin folder will be
	 * removed on plugin updates. If you're creating custom translation
	 * files, please use the global language folder.
	 *
	 * @since 1.0.0
	 *
	 * @uses apply_filters() Calls 'plugin_locale' with {@link get_locale()} value
	 */
	public function load_textdomain() {

		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/gravityforms-export-bp-data/' . $mofile;

		// Look in global /wp-content/languages/gravityforms-export-bp-data folder
		load_textdomain( $this->domain, $mofile_global );

		// Look in local /wp-content/plugins/gravityforms-export-bp-data/languages/ folder
		load_textdomain( $this->domain, $mofile_local );

		// Look in global /wp-content/languages/plugins/
		load_plugin_textdomain( $this->domain );
	}

	/**
	 * Add BuddyPress data items to the list exportable entry data
	 *
	 * @since 1.0.0
	 * 
	 * @param array $form Form data
	 * @return array Form data
	 */
	public function entry_export_fields( $form ) {

		// Member Types (BP 2.2+)
		if ( function_exists( 'bp_get_member_types' ) ) {

			// Get all member types
			$types = bp_get_member_types( array(), 'objects' );

			// Define exportable field
			if ( ! empty( $types ) ) {
				$form['fields'][] = array( 
					'id'    => 'bp-member-types',
					'label' => __( 'Member Types', 'gravityforms-export-bp-data' )
				);
			}
		}

		// XProfile component
		if ( bp_is_active( 'xprofile' ) ) {

			// Get all field groups with their fields
			$xprofile = bp_xprofile_get_groups( array( 'fetch_fields' => true, 'hide_empty_groups' => true ) );

			// Define export fields
			foreach ( $xprofile as $field_group ) {
				foreach ( $field_group->fields as $field ) {

					// Add to exportable fields
					$form['fields'][] = array(
						'id'    => 'bp-xprofile.' . $field->id,
						/* translators: 1. Pofile field name, 2. Profile field group name */
						'label' => sprintf( __( 'Profile: %2$s/%1$s', 'gravityforms-export-bp-data' ), $field->name, $field_group->name )
					);
				}
			}
		}

		// Groups component
		if ( bp_is_active( 'groups' ) ) {

			// Get all groups
			$groups = groups_get_groups( array( 'show_hidden' => true, 'type' => 'alphabetical', 'populate_extras' => false ) );

			// Define exportable field
			if ( ! empty( $groups['groups'] ) ) {
				$form['fields'][] = array( 
					'id'    => 'bp-groups',
					'label' => __( 'User Groups', 'gravityforms-export-bp-data' )
				);
			}
		}

		return $form;
	}

	/**
	 * Modify the value for the given export field
	 *
	 * @since 1.0.0
	 * 
	 * @param mixed $value Export field value
	 * @param int $form_id Form ID
	 * @param string $field Export field name
	 * @param array $entry Entry data
	 * @return mixed Export field value
	 */
	public function export_field_value( $value, $form_id, $field, $entry ) {

		// Get entry's user
		$user_id = (int) $entry['created_by'];

		// Bail when the user does not exist
		if ( ! $user_id || ! get_user_by( 'id', $user_id ) )
			return $value;

		// Check the field
		switch ( $field ) {

			// Member Types
			case 'bp-member-types' :

				// Get the user's member types
				$types = bp_get_member_type( $user_id, false );

				// Get member type names
				if ( ! empty( $types ) ) {
					$names = array();
					foreach ( $types as $type ) {
						$type_object = bp_get_member_type_object( $type );

						// Get type label
						if ( ! empty( $type_object ) ) {
							$names[] = $type_object->labels['name'];
						}
					}

					// Turn array into comma-separated string
					if ( ! empty( $names ) ) {
						$value = implode( ', ', $names );
					}
				}

				break;

			// XProfile
			case 'bp-xprofile' === strtok( $field, '.' ) :

				// Get profile field id
				$field_id = substr( $field, strpos( $field, '.' ) + 1 );

				// Get the user's profile field value. Turn arrays into comma-separated strings
				$_value = xprofile_get_field_data( $field_id, $user_id, 'comma' );

				// Only assign value when found
				if ( false !== $_value ) {
					$value = $_value;
				}

				break;

			// Groups
			case 'bp-groups' :

				// Get the user's groups
				$groups = groups_get_groups( array(
					'user_id'         => $user_id,
					'show_hidden'     => true,
					'type'            => 'alphabetical',
					'populate_extras' => false
				) );

				// Turn array into comma-separated string
				if ( ! empty( $groups['groups'] ) ) {
					$value = implode( ', ', wp_list_pluck( $groups['groups'], 'name' ) );
				}

				break;
		}

		return $value;
	}
}

/**
 * Return single instance of this main plugin class
 *
 * @since 1.0.0
 * 
 * @return GravityForms_Export_BP_Data
 */
function gravityforms_export_bp_data() {
	return GravityForms_Export_BP_Data::instance();
}

// Initiate on 'bp_loaded' when plugins are loaded
add_action( 'bp_loaded', 'gravityforms_export_bp_data' );

endif; // class_exists
