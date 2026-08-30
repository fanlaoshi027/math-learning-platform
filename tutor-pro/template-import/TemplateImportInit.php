<?php
/**
 * Init class
 *
 * @package TutorPro\TemplateImporter
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.6.0
 */

namespace TutorPro\TemplateImport;

/**
 * Class TemplateImportInit
 */
final class TemplateImportInit {

	/**
	 * Register hooks
	 *
	 * @since 3.6.0
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Init packages
	 *
	 * @since 3.6.0
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'tutor_admin_menu', array( $this, 'register_admin_menu' ) );
		new TemplateImporter();
	}

	/**
	 * Register admin menu.
	 *
	 * @since 3.7.0
	 * @since 3.8.0 param $menu added.
	 *
	 * @param array $menu menu.
	 *
	 * @return array
	 */
	public function register_admin_menu( $menu ) {
		$menu['group_two']['tutor-themes'] = array(
			'parent_slug' => 'tutor',
			'page_title'  => __( 'Themes', 'tutor-pro' ),
			'menu_title'  => __( 'Themes', 'tutor-pro' ),
			'capability'  => 'manage_tutor_instructor',
			'menu_slug'   => 'tutor-themes',
			'callback'    => array( $this, 'tutor_themes' ),
		);

		return $menu;
	}

	/**
	 * Tutor template view
	 *
	 * @since 3.6.0
	 */
	public function tutor_themes() {
		include tutor_pro()->path . 'templates/template-import/templates.php';
	}
}
