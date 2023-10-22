<?php 
namespace LoeCoder\Plugin\GTM_Purchase_DataLayer\Admin;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class for plugin setting page
 */
class Settings {
    /**
     * Settings value
     *
     * @var array with setting value from option
     */
    private $options;

    /**
     * Class constructor, initialize all setting options 
     */
    public function __construct() {
        $this->options = get_option('purchase_datalayer_settings');
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'init_settings'));
    }

    /**
     * Add admin menu page
     *
     * @return void
     */
    public function add_menu() {
        add_menu_page(
            'GTM Purchase',
            'GTM Purchase',
            'manage_options',
            'purchase-datalayer-settings',
            array($this, 'render_page'),
            'dashicons-chart-line',
            100
        );
    }

    /**
     * Render setting page
     *
     * @return void
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h2><?php _e('Settings', 'gtm-purchase-wc-thankyou'); ?></h2>
            <?php
             settings_errors();
            ?>
            <form method="post" action="options.php" id="wc-purchase-datalayer-settings">
                <?php
                settings_fields('purchase_datalayer_settings_group');
                do_settings_sections('purchase-datalayer-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Initialize all setting fields
     *
     * @return void
     */
    public function init_settings() {
        register_setting('purchase_datalayer_settings_group', 'purchase_datalayer_settings', array($this, 'validate_settings'));
        add_settings_section('purchase_datalayer_section', '', array($this, 'section_callback'), 'purchase-datalayer-settings');

        add_settings_field('thank_you_page_url', __('Thank You Page URL', 'gtm-purchase-wc-thankyou'), array($this, 'url_field_callback'), 'purchase-datalayer-settings', 'purchase_datalayer_section');
        add_settings_field('exclude_tax', __('Exclude Tax from Revenue', 'gtm-purchase-wc-thankyou'), array($this, 'exclude_tax_callback'), 'purchase-datalayer-settings', 'purchase_datalayer_section');
        add_settings_field('exclude_shipping', __('Exclude Shipping from Revenue', 'gtm-purchase-wc-thankyou'), array($this, 'exclude_shipping_callback'), 'purchase-datalayer-settings', 'purchase_datalayer_section');
    }

    /**
     * Setting section
     *
     * @return void
     */
    public function section_callback() {
        return false;
    }

    /**
     * Redirect url field render
     *
     * @return void
     */
    public function url_field_callback() {
        $value = isset($this->options['thank_you_page_url']) ? esc_attr($this->options['thank_you_page_url']) : '';
        echo '<input type="text" class="regular-text" name="purchase_datalayer_settings[thank_you_page_url]" value="' . $value . '" />';
    }

    /**
     * Exclude tax checkbox render
     *
     * @return void
     */
    public function exclude_tax_callback() {
        $value = isset($this->options['exclude_tax']) ? esc_attr($this->options['exclude_tax']) : '';
        echo '<input type="checkbox" name="purchase_datalayer_settings[exclude_tax]" value="1" ' . checked(1, $value, false) . ' />';
    }

    /**
     * Exclude shipping checkbox render
     *
     * @return void
     */
    public function exclude_shipping_callback() {
        $value = isset($this->options['exclude_shipping']) ? esc_attr($this->options['exclude_shipping']) : '';
        echo '<input type="checkbox" name="purchase_datalayer_settings[exclude_shipping]" value="1" ' . checked(1, $value, false) . ' />';
    }

    /**
     * Validate Setting fields
     *
     * @param array $input
     * @return void
     */
    public function validate_settings($input) {
        $output = array();

        if (isset($input['thank_you_page_url'])) {
            $output['thank_you_page_url'] = esc_url_raw($input['thank_you_page_url']);
        }

        if (isset($input['exclude_tax'])) {
            $output['exclude_tax'] = (bool) $input['exclude_tax'];
        }

        if (isset($input['exclude_shipping'])) {
            $output['exclude_shipping'] = (bool) $input['exclude_shipping'];
        }

        return $output;
    }
}

