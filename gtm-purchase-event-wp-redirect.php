<?php 
 /**
 * Plugin Name: GTM Purchase for WooCommerce Thank You
 * Plugin URI: https://leocoder.com
 * Description: GTM purchase event fix for WooCommerce custom thank you page.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Md Hasanuzzaman
 * Author URI: https://leocoder.com/
 * Text Domain: gtm-purchase-wc-thankyou
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 8.2.1
 * License: GPLv3 or later License
 * URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

use LoeCoder\Plugin\GTM_Purchase_DataLayer\Admin\Settings;
use LoeCoder\Plugin\GTM_Purchase_DataLayer\Frontend\DataLayer;

 if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists(Settings::class)) {
	require_once plugin_dir_path(__FILE__) . 'includes/Admin/Settings.php';
}

 if (!class_exists(DataLayer::class)) {
	require_once plugin_dir_path(__FILE__) . 'includes/Frontend/DataLayer.php';
 }

 final class GTM_Purchase_Event_Woo_Thanks {
    /**
	 * Instance of class
	 *
	 * @var instance
	 */
	static protected $instance;

	/**
	* Redirect Page URL
	*
	* @since 1.0.0
	* @var string
	*/
	protected $redirect_page_url;

	/**
	 * @since 1.0.0
	 * Class constructor, initialize all action
	 */
    private function __construct() {
        register_activation_hook(__FILE__, array($this, 'on_activation'));
		register_deactivation_hook(__FILE__, array($this, 'on_deactivation'));
		$this->redirect_page_url = $this->get_setting('thank_you_page_url');
		add_action('plugins_loaded', array($this, 'on_plugins_loaded'));
    }

	/**
	 * Action after plugin activate
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function on_activation() {
		$install_time = get_option('gtm_purchase_wc_thankyou');
		if( !$install_time ) {
			update_option('gtm_purchase_wc_thankyou', time());
		}
        update_option('gtm_purchase_wc_thankyou_version', $this->plugin_version());
	}

	/**
	 * Action after plugin deactivate
	 *
	 * @since	1.0.0
	 * @return void
	 */
	public function on_deactivation() {
		//do nothing for now
	}

	/**
	 * Action after plugins loaded
	 *
	 * @since	1.0.0
	 * @return void
	 */
	public function on_plugins_loaded() {
		$this->load_textdomain();

		if (!$this->has_satisfied_dependencies()) {
			add_action('admin_notices', array($this, 'render_dependencies_notice'));
			return;
		}

		if(is_admin()) {
			new Settings($this);
			add_action('admin_enqueue_scripts', array($this, 'setting_style'));
			$this->plugin_action_links();
		}else {
			if($this->redirect_page_url) {
				add_action('woocommerce_thankyou', array($this, 'redirect_page'), -1000);
			}

			if($this->get_order_id()) {
				new DataLayer($this->get_order_id());
			}
			
		}
	}

	/**
	 * Load Localization files
	 *
	 * @since      1.0.0
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain('gtm-purchase-wc-thankyou', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	/**
	 * Enqueue style for setting page
	 * 
	 * @since	1.0.0
	 * @return void
	 */
	public function setting_style() {
        $screen = $this->get_current_screen();
		$version = $this->script_version();	

        if ($screen->id === 'toplevel_page_purchase-datalayer-settings') {
            wp_enqueue_style('gtm-purchase-wc-thankyou-settings', $this->get_url('assets/css/settings.css'), array(), $version);
        }
    }

	/**
	 * Get Order Id from query parameter
	 * 
	 * @since	1.0.0
	 * @return false|number 
	 */
	public function get_order_id() {
		if (isset($_GET['order_id']) && !empty($_GET['order_id']) && is_numeric($_GET['order_id'])) {
            $order_id = $_GET['order_id'];
			return (int) $order_id;
        }
		return false;
	}

	/**
	 * Redirect page to thank you page
	 *
	 * @since	1.0.0
	 * @return void
	 */
	public function redirect_page($order_id) {
		$url = $this->redirect_page_url;
		if(filter_var($url, FILTER_VALIDATE_URL)) {
			$url = explode('?', $url)[0];
			$url .= '?order_id='.$order_id;
			wp_safe_redirect($url);
			exit;
		}
	}

	/**
	 * Get plugin version
	 *
	 * @since	1.0.0
	 * @return string current version of plugin
	 */
	public function plugin_version() {
		$plugin_data = get_file_data(__FILE__, array(
			'Version' => 'Version',
		));

		return $plugin_data['Version'];
	}

	/**
	 * Get class instance
	 * 
	 * @since	1.0.0
	 * @return instance of plugin base class
	 */
	public static function init($__FILE__) {
		if (is_null(self::$instance)) {
			self::$instance = new self($__FILE__);
		}
		return self::$instance;
	}

	/**
	 * Get script url of plugin file
	 *
	 * @since	1.0.0
	 * @return	URL link of file.
	 * @param	string File name with folder path.
	 */
	public function get_url($file = '') {
		return plugin_dir_url(__FILE__) . $file;
	}

	/**
     * Get the current screen object
     *
     * @since 1.00
     * @global WP_Screen $current_screen WordPress current screen object.
     * @return WP_Screen|null Current screen object or null when screen not defined.
     */
    function get_current_screen() {
        global $current_screen;
        if ( ! isset( $current_screen ) ) {
            return null;
        }
        return $current_screen;
    }

	/**
	 * Get enqueue script version
	 *
	 * @since	1.0.0
	 * @return string version of script base on development or production server
	 */
	public function script_version() {
		if (WP_DEBUG) {
			return time();
		}
		return $this->plugin_version();
	}


    /**
     * Get Setting value
     *
	 * @since	1.0.0
     * @param string $key
     * @return string|bool|false value of setting field
     */
    public function get_setting($key) {
        $settings = get_option('purchase_datalayer_settings');
        $value = (!empty($settings) && isset($settings[$key])) ? esc_attr($settings[$key]) : false;

        return $value;
    }

	/**
	 * Returns true if all dependencies for the plugin are loaded
	 *
	 * @since      1.0.0
	 * @return bool
	 */
	protected function has_satisfied_dependencies() {
		$dependency_errors = $this->get_dependency_errors();
		return 0 === count($dependency_errors);
	}

	/**
	 * Get an array of dependency error messages
	 *
	 * @since      1.0.0
	 * @return array all dependency error message.
	 */
	protected function get_dependency_errors() {
		$errors = array();
		$wordpress_version = get_bloginfo('version');
		$minimum_wordpress_version = $this->get_min_wp();
		$minimum_woocommerce_version = $this->get_min_wc();
		$minium_php_version = $this->get_min_php();

		$wordpress_minimum_met = version_compare($wordpress_version, $minimum_wordpress_version, '>=');
		$woocommerce_minimum_met = class_exists('WooCommerce') && version_compare(WC_VERSION, $minimum_woocommerce_version, '>=');
		$php_minimum_met = version_compare(phpversion(), $minium_php_version, '>=');

		if (!$woocommerce_minimum_met) {

			$errors[] = sprintf(
				/* translators: 1. link of plugin, 2. plugin version. */
				__('Purchase Event for WooCommerce Thank You plugin requires <a href="%1$s" target="_blank">WooCommerce</a> %2$s or greater to be installed and active.', 'gtm-purchase-wc-thankyou'),
				'https://wordpress.org/plugins/woocommerce/',
				$minimum_woocommerce_version
			);
		}

		if (!$wordpress_minimum_met) {
			$errors[] = sprintf(
				/* translators: 1. link of wordpress 2. version of WordPress. */
				__('Purchase Event for WooCommerce Thank You plugin requires <a href="%1$s" target="_blank">WordPress</a> %2$s or greater to be installed and active.', 'gtm-purchase-wc-thankyou'),
				'https://wordpress.org/',
				$minimum_wordpress_version
			);
		}

		if (!$php_minimum_met) {
			$errors[] = sprintf(
				/* translators: 1. version of php */
				__('Purchase Event for WooCommerce Thank You plugin requires <strong>php version %s</strong> or greater. Please update php version.', 'gtm-purchase-wc-thankyou'),
				$minium_php_version
			);
		}
		return $errors;
	}

	/**
	 * Notify about plugin dependency
	 *
	 * @since	1.0.0
	 * @return void
	 */
	public function render_dependencies_notice() {
		$message = $this->get_dependency_errors();
		printf('<div class="error"><p>%s</p></div>', implode(' ', $message));
	}

	/**
	 * Get required min php version
	 *
	 * @since	1.0.0
	 * @return string min require php version
	 */
	public function get_min_php() {
		$file_info = get_file_data(__FILE__, array(
			'min_php' => 'Requires PHP',
		));
		return $file_info['min_php'];
	}

	/**
	 * Get require WooCommerce version
	 *
	 * @since	1.0.0
	 * @return string min require WooCommerce version
	 */
	public function get_min_wc() {
		$file_info = get_file_data(__FILE__, array(
			'min_wc' => 'WC requires at least',
		));
		return $file_info['min_wc'];
	}

	/**
	 * Get require WordPress version
	 *
	 * @since	1.0.0
	 * @return string min require WordPress version
	 */
	public function get_min_wp() {
		$file_info = get_file_data(__FILE__, array(
			'min_wc' => 'Requires at least',
		));
		return $file_info['min_wc'];
	}

	/**
	 * Add plugin action link
	 * @since	1.0.0
	 */
	public function plugin_action_links() {
		add_action('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
			$link_before = array(
				'settings' => '<a href="' . esc_url(get_admin_url(null, 'admin.php?page=purchase-datalayer-settings')) . '">' . __('Settings', 'gtm-purchase-wc-thankyou') . '</a>',
			);

			return array_merge($link_before, $links);
		});
	}
 }


 /**
 * Plugin execution
 * @since    1.0.0
 */
function gtm_purchase_wc_thankyou() {
    return \GTM_Purchase_Event_Woo_Thanks::init(__FILE__);
}
gtm_purchase_wc_thankyou();