<?php 
namespace LoeCoder\Plugin\GTM_Purchase_DataLayer\Frontend;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class for creating purchase event on dataLayer
 */
class DataLayer {
    /**
     * Order id
     *
     * @var number
     */
    protected $order_id;

    /**
     * Meta key for check is event fired
     *
     * @var string
     */
    protected static $is_tracked_meta_key = 'is_fired_gtm_purchase_event';

    /**
     * Class constructor
     *
     * @param number $order_id
     */
    public function __construct($order_id) {
        if(is_numeric($order_id)) {
            $this->order_id = $order_id;
            $this->insert_data_layer();
        }
    }

    /**
     * Check weather event already fired or not 
     *
     * @param number $order_id
     * @return boolean
     */
    public function is_fired_event($order_id) {
        $is_tracked = !empty(get_post_meta($order_id, self::$is_tracked_meta_key, true));
        return $is_tracked;
    }

    /**
     * Push GTM dataLayer for purchase event
     *
     * @return void
     */
    public function insert_data_layer() {
        $is_tracked = $this->is_fired_event($this->order_id);
        if($is_tracked) return;

        add_action('wp_footer', function() {
            $order = wc_get_order( $this->order_id );
            if(!($order instanceof \WC_Order)) return;

            $order = wc_get_order( $this->order_id );
            $data_layer = $this->get_purchase_datalayer($order);
        ?> 
            <script>
                window.dataLayer = window.dataLayer || [];
                dataLayer.push(<?php echo wp_json_encode($data_layer); ?>);
            </script>
        <?php 
            update_post_meta($this->order_id, self::$is_tracked_meta_key, true);
        });
        
    }


    /**
     * Get formatted dataLayer data for purchase event
     *
     * @param Object $order
     * @return array of datalayer
     */
    public function get_purchase_datalayer( $order ) {

        $data_layer = array();
    
        if ( $order instanceof \WC_Order ) {
    
            $is_exclude_tax = (bool) $this->get_setting('exclude_tax');
            $is_exclude_shipping = (bool) $this->get_setting('exclude_shipping');

            $new_customer = \Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore::is_returning_customer($order) === false;
            $value = (float) $order->get_total();
            $shipping_cost = (float) $order->get_shipping_total();
            $tax = (float) $order->get_total_tax();
            
            if($is_exclude_tax) {
                $value -= $tax;
            }
            if($is_exclude_shipping) {
                $value -= $shipping_cost;
            }

            $currency = $order->get_currency();
            $order_number =  $order->get_order_number();
            $coupon = implode( ', ', $order->get_coupon_codes());
            $items = $this->get_items( $order );


            $data_layer['event'] = apply_filters('gtm_purchase_event_name', 'purchase');
            $data_layer['ecommerce'] = array(
                'transaction_id' => $order_number,
                'new_customer' => $new_customer,
                'value' => $value,
                'currency' => $currency,
                'tax' => $tax,
                'shipping' => $shipping_cost,
                'coupon' => $coupon,
                'items' => $items
            );
            $data_layer['customer'] = $this->get_custom_info($order);
        }
        return apply_filters( 'thankyou_purchase_datalayer', $data_layer );
    }

    /**
     * Get cart items   
     *
     * @param Object $order
     * @return array of cart items
     */
    public function get_items($order) {
        $items = $order->get_items();
        $order_items = array();

        foreach ($items as  $item) {

            $product = $item->get_product();
            $product_type  = $product->get_type();
            $product_id = $item->get_product_id();
            $item_id = $product->get_id();
            $product_name = $product->get_title();
            $product_sku = $product->get_sku();
            $inc_tax       = ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) );
            $product_regular_price = round( (float) wc_get_price_to_display( $product ), 2 );
            $product_price = round( (float) $order->get_item_total( $item, $inc_tax ), 2 );
            $discount = ($product_regular_price - $product_price);
            $product_brand = $this->get_brand($product_id);
            $product_categories = wp_get_post_terms($product_id, 'product_cat', array("fields" => "names"));
            $quantity = (int) $item->get_quantity();
            $categories = array_slice($product_categories, 0, 5);

            $item_data = array(
                'id' => (string) $item_id,
                'item_id' => (string) $item_id,
                'item_name' => $product_name,
                'sku' => $product_sku,
                'price' => $product_price,
                'quantity' => $quantity,
                'item_brand' => $product_brand
            );

            if($discount > 0) {
                $item_data['discount'] = $discount;
            }

            if ( 'variation' === $product_type ) {
                $item_data['item_variant'] = implode( ',', $product->get_variation_attributes());
            }

            if(isset($categories[0])) {
                $item_data['item_category'] = $categories[0];
            }
            if(isset($categories[1])) {
                $item_data['item_category2'] = $categories[1];
            }
            if(isset($categories[2])) {
                $item_data['item_category3'] = $categories[2];
            }
            if(isset($categories[3])) {
                $item_data['item_category4'] = $categories[3];
            }
            if(isset($categories[4])) {
                $item_data['item_category5'] = $categories[4];
            }

            $order_items[] = $item_data;
        }

        return $order_items;
    }

    /**
     * Get product brand name by product_id
     *
     * @param number $product_id
     * @return string of product brand name
     */
    public function get_brand($product_id) {
        $brand_taxonomies = array(
            'product_brand',
            'yith_product_brand',
            'ultimate_brand',
            'wc_brands_pro',
            'branding_brand',
            'brands_for_woocommerce',
            'wc_brands',
            'pa_brand',
            'perfect_brand'
        );
    
        $brand_name = '';
    
        foreach ($brand_taxonomies as $taxonomy) {
            $brand_terms = wp_get_post_terms($product_id, $taxonomy, array("fields" => "names"));
            if ($brand_terms && !is_wp_error($brand_terms)) {
                $brand_name = implode(', ',$brand_terms);
                break; 
            }
        }
        return $brand_name;
    }


    /**
     * Get custom information 
     *
     * @param Object $order
     * @return array of customer
     */
    public function get_custom_info($order) {
        $customer = array(
            'billing'  => array(
              'first_name' => esc_js( $order->get_billing_first_name() ),
              'last_name'  => esc_js( $order->get_billing_last_name() ),
              'company'    => esc_js( $order->get_billing_company() ),
              'address_1'  => esc_js( $order->get_billing_address_1() ),
              'address_2'  => esc_js( $order->get_billing_address_2() ),
              'city'       => esc_js( $order->get_billing_city() ),
              'state'      => esc_js( $order->get_billing_state() ),
              'postcode'   => esc_js( $order->get_billing_postcode() ),
              'country'    => esc_js( $order->get_billing_country() ),
              'email'      => esc_js( $order->get_billing_email() ),
              'emailhash'  => esc_js( hash( 'sha256', $order->get_billing_email() ) ),
              'phone'      => esc_js( $order->get_billing_phone() ),
            ),
  
            'shipping' => array(
              'first_name' => esc_js( $order->get_shipping_first_name() ),
              'last_name'  => esc_js( $order->get_shipping_last_name() ),
              'company'    => esc_js( $order->get_shipping_company() ),
              'address_1'  => esc_js( $order->get_shipping_address_1() ),
              'address_2'  => esc_js( $order->get_shipping_address_2() ),
              'city'       => esc_js( $order->get_shipping_city() ),
              'state'      => esc_js( $order->get_shipping_state() ),
              'postcode'   => esc_js( $order->get_shipping_postcode() ),
              'country'    => esc_js( $order->get_shipping_country() ),
            )
        );

        return $customer;
    }
    

    /**
     * Get Setting value
     *
     * @param string $key
     * @return string|bool|false value of setting field
     */
    public function get_setting($key) {
        $settings = get_option('purchase_datalayer_settings');
        $value = (!empty($settings) && isset($settings[$key])) ? esc_attr($settings[$key]) : false;

        return $value;
    }
    
}
