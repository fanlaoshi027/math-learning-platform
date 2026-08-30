<?php
/**
 * Template importer
 *
 * @package TutorPro\TemplateImport
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

namespace TutorPro\TemplateImport;

use Kirki\ExportImport\TemplateImport as KirkiTemplateImport;
use TutorPro\TemplateImport\TemplateImportHelper;
use TUTOR\Input;
use Tutor\Traits\JsonResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Template import handler class
 *
 * @since 4.0.0
 */
class TemplateImporter {
	use JsonResponse;

	/**
	 * Instance of template import helper.
	 *
	 * @var TemplateImportHelper
	 */
	public $template_helper_cls;

	/**
	 * Template dependency endpoint
	 *
	 * @var string
	 */
	public $template_import_dependency_api;

	/**
	 * Droip plugin path
	 *
	 * @var string
	 */
	const DROIP_PLUGIN_PATH = 'droip/droip.php';

	/**
	 * Kirki plugin path
	 *
	 * @var string
	 */
	const KIRKI_PLUGIN_PATH = 'kirki/kirki.php';

	/**
	 * Register default hooks and actions for WordPress
	 */
	public function __construct() {
		$this->template_helper_cls            = new TemplateImportHelper();
		$this->template_import_dependency_api = $this->template_helper_cls->make_url( 'template-import-dependencies' );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'wp_ajax_tutor_template_required_plugin_install', array( $this, 'tutor_template_required_plugin_install' ) );
		add_action( 'wp_ajax_import_tutor_template', array( $this, 'import_kirki_template' ) );
		add_action( 'wp_ajax_process_tutor_template', array( $this, 'process_kirki_template' ) );
		add_action( 'wp_ajax_tutor_template_import_list', array( $this, 'tutor_template_import_list' ) );
	}

	/**
	 * Enqueue scripts for admin
	 *
	 * @param string $page install plugin details.
	 *
	 * @since 4.0.0
	 *
	 * @return void
	 */
	public function admin_scripts( $page ) {
		if ( 'tutor-lms-pro_page_tutor-themes' === $page ) {
			wp_enqueue_script( 'tutor-template-import', tutor_pro()->url . 'assets/js/template-import.js', array( 'wp-i18n' ), TUTOR_PRO_VERSION, true );
		}
	}

	/**
	 * AJAX callback to install a plugin.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	public function tutor_template_required_plugin_install() {
		if ( current_user_can( 'manage_options' ) === false ) {
			return $this->json_response( __( 'Permission denied!', 'tutor-pro' ), array(), 400 );
		}
		tutor_utils()->check_nonce();
		$plugin_name = Input::post( 'plugin_name' );
		// check consent
		$consent = Input::post( 'consent', false, Input::TYPE_BOOL );
		if ( ! $consent ) {
			return $this->json_response( __( 'Please agree to the terms and conditions!', 'tutor-pro' ), array(), 400 );
		}
		try {
			if ( $this->is_droip_active() ) {
				return $this->json_response( __( 'Please deactivate droip!', 'tutor-pro' ), array(), 400 );
			}
			$required_plugins = $this->template_dependency();
			$plugin_info      = $required_plugins[ $plugin_name ] ?? array();
			if ( empty( $plugin_info ) ) {
				return $this->json_response( __( 'Required plugin info missing!', 'tutor-pro' ), array(), 400 );
			}
			$this->installing_plugin( $plugin_info );
		} catch ( \Throwable $th ) {
			return $this->json_response( __( 'Something went wrong!', 'tutor-pro' ), array(), 400 );
		}
	}

	/**
	 * Template dependency
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	public function template_dependency() {
		$dependent_plugins = array();
		$response          = wp_remote_get(
			$this->template_import_dependency_api,
			array(
				'headers' => array(
					'Secret-Key' => 't344d5d71sae7dcb546b8cf55e594808',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! empty( $data ) && 200 === $data['status'] ) {
			$dependent_plugins = $data['body_response'] ?? array();
		}

		return $dependent_plugins;
	}

	/**
	 * Installing plugin.
	 *
	 * @param array $plugin_info installed plugin details.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	public function installing_plugin( $plugin_info ) {
		try {
			if ( ! class_exists( 'WP_Upgrader' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}
			if ( 'plugin' === $plugin_info['type'] && ! empty( $plugin_info['src'] ) ) {
				if ( ! function_exists( 'plugins_api' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
				}

				$is_install_plugin = $this->is_plugin_installed( $plugin_info['path'] );
				if ( ! $is_install_plugin ) {
					$upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );

					$installed = $upgrader->install( $plugin_info['src'] );
					if ( is_wp_error( $installed ) ) {
						return $this->json_response( __( 'Plugin installation error!', 'tutor-pro' ), array(), 400 );
					}
				}

				$activate = activate_plugin( $plugin_info['path'], '', false, false );
				return $this->json_response( __( 'Plugin installed successfully!', 'tutor-pro' ) );
			} elseif ( 'theme' === $plugin_info['type'] && ! empty( $plugin_info['src'] ) ) {
				require_once ABSPATH . 'wp-admin/includes/theme-install.php';

				$is_theme_installed = wp_get_theme( $plugin_info['slug'] )->exists();

				if ( ! $is_theme_installed ) {
					$upgrader = new \Theme_Upgrader( new \WP_Ajax_Upgrader_Skin() );

					$installed = $upgrader->install( $plugin_info['src'] );
					if ( is_wp_error( $installed ) ) {
						return $this->json_response( __( 'Theme installation error!', 'tutor-pro' ), array(), 400 );
					}
				}
				switch_theme( $plugin_info['slug'] );
				if ( wp_get_theme()->get_stylesheet() !== $plugin_info['slug'] ) {
					return $this->json_response( __( 'Error: while activating theme!', 'tutor-pro' ), array(), 400 );
				}

				return $this->json_response( __( 'Theme installed and activated successfully.', 'tutor-pro' ) );
			} else {
				return $this->json_response( __( 'Plugin or theme nothing installed!', 'tutor-pro' ) );
			}
		} catch ( \Throwable $th ) {
			return $this->json_response( __( 'Something went wrong!', 'tutor-pro' ), array(), 400 );
		}
	}

	/**
	 * Import kirki template
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	public function import_kirki_template() {
		try {
			if ( current_user_can( 'manage_options' ) === false ) {
				return self::json_response( __( 'Permission denied!', 'tutor-pro' ), null, 400 );
			}
			tutor_utils()->check_nonce();
			$template_id           = Input::post( 'template_id' );
			$selected_mode         = Input::post( 'selected_mode' );
			$template_to_download  = $this->template_helper_cls->get_template_download_url( $template_id );
			$kirki_template_import = new KirkiTemplateImport();
			$is_import             = $kirki_template_import->import( $template_to_download, true, $selected_mode );
			if ( $is_import ) {
				return self::json_response( __( 'Content imported', 'tutor-pro' ), null, 200 );
			} else {
				return self::json_response( __( 'Content importing error!', 'tutor-pro' ), null, 400 );
			}
		} catch ( \Throwable $th ) {
			return self::json_response( __( 'Something went wrong!', 'tutor-pro' ), null, 400 );
		}
	}

	/**
	 * Process kirki template
	 *
	 * @since 4.0.0
	 *
	 * @return  array
	 */
	public function process_kirki_template() {
		$kirki_template_import = new KirkiTemplateImport();
		$is_process            = $kirki_template_import->process();
		return self::json_response( '', $is_process, 200 );
	}

	/**
	 * Check droip installed or not
	 *
	 * @since 4.0.0
	 *
	 * @return  bool
	 */
	public function is_droip_active() {
		return is_plugin_active( self::DROIP_PLUGIN_PATH ) ? true : false;
	}

	/**
	 * Check plugin is install or not
	 *
	 * @param   string $plugin_path plugin-slug.
	 *
	 * @since 4.0.0
	 *
	 * @return  bool
	 */
	private function is_plugin_installed( $plugin_path ) {
		$installed_plugins = get_plugins();
		foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
			if ( $plugin_path === $plugin_file ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get Template list.
	 *
	 * @since 4.0.0
	 */
	public function tutor_template_import_list() {
		ob_start();
		require_once tutor()->path . 'views/templates/templates-list.php';
		$contents = ob_get_clean();
		$this->json_response( __( 'Successfully fetched!', 'tutor-pro' ), $contents );
	}
}
