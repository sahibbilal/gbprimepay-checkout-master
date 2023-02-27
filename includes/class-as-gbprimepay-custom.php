<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class AS_Gbprimepay_CUSTOM {

    public function __construct()
    {
        add_action( 'wp_head', array( $this, 'wp_head_function' ), 99 );
        add_action( 'wp_footer', array( $this, 'wp_footer_function' ), 99 );
        add_action( 'wp_ajax_add_currency_converter_add', array( $this, 'add_currency_converter_add' ), 99 );
        add_action( 'wp_ajax_nopriv_add_currency_converter_add', array( $this, 'add_currency_converter_add' ), 99 );
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'woocommerce_product_options_general_product_data_function' ) );
        add_action( 'woocommerce_admin_process_product_object', array( $this, 'woocommerce_admin_process_product_object_function' ) );
        add_action( 'admin_init', array( $this, 'admin_init_function'), 10 );
        add_action( 'admin_menu', array( $this, 'admin_menu_function'), 10 );
        add_action( 'woocommerce_product_query', array( $this, 'woocommerce_product_query_function'), 99, 1 );
        add_filter( 'woocommerce_get_price_html', array( $this, 'woocommerce_get_price_html_function'), 99, 2 );
        add_filter( 'woocommerce_cart_item_price', array( $this, 'woocommerce_cart_item_price_function'), 99, 2 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'woocommerce_before_calculate_totals_function'), 99 );
        add_shortcode( 'WOOCSDROPDOWN', array( $this, 'woo_currency_converter') );
        add_filter( 'woocommerce_currency_symbol', array( $this, 'woocommerce_currency_symbol_function'), 99, 2 );
        add_filter( 'woocommerce_cart_total', array( $this, 'woocommerce_cart_total_function'), 99, 1 );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'woocommerce_checkout_create_order_function'), 99, 1 );
        add_action( 'woocommerce_thankyou', array( $this, 'woocommerce_thankyou_function'), 99);
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'woocommerce_checkout_update_order_meta_function'), 99, 2);
        add_filter( 'woocommerce_package_rates', array( $this, 'woocommerce_package_rates_funtion'), 99, 2);
//        add_action( 'woocommerce_order_status_pending', array( $this, 'woocommerce_thankyou_function'), 99);
        add_filter( 'cron_schedules', array( $this, 'cron_time_intervals'), 99);
        add_action( 'init', array( $this, 'init_function'), 10 );
        add_action( 'shedule_order_update', array( $this, 'update_order_pending_payment'), 99);
//        add_action( 'init', array( $this, 'update_order_pending_payment'), 99);
        add_action( 'woocommerce_after_checkout_validation', array( $this, 'woocommerce_after_checkout_validation_function'), 99, 2);
    }
    public function cron_time_intervals($schedules){
        $schedules['minutes_10'] = array(
            'interval' => 5,
            'display' => 'Once 10 minutes'
        );
        return $schedules;
    }
    public function init_function() {
        if ( ! wp_next_scheduled( 'shedule_order_update' ) ) {
            wp_schedule_event( time(), 'minutes_10', 'shedule_order_update');
        }
    }
    public function update_order_pending_payment(){
        $all_orders = get_posts( array(
            'numberposts' => -1,
            'post_type'   => 'shop_order',
            'post_status' => array_keys( wc_get_order_statuses() )
        ) );
        foreach ($all_orders as $all_order) {
            $order_id = $all_order->ID;
            $order = wc_get_order($order_id);
            $status = get_post_meta($order_id, 'order_status', true);
            $currency = get_post_meta($order_id, '_order_currency', true);
            if(($currency == 'THB_USD' || empty($currency)) && empty($status)){
                update_post_meta($order_id, 'order_status', 200);
                $order->set_currency('THB');
                $order->save();
            }
            else if(($currency == 'THB_EUR' || empty($currency)) && empty($status)){
                update_post_meta($order_id, 'order_status', 200);
                $order->set_currency('THB');
                $order->save();
            }
        }

    }
    public function wp_head_function() {
        session_start();
        if (empty($_SESSION['currency']) || $_SESSION['currency'] == 'THB_USD'){
            $_SESSION['currency'] = 'USD';
        }
        else if ($_SESSION['currency'] == 'THB_EUR'){
            $_SESSION['currency'] = 'EUR';
        }
    }
    public function wp_footer_function() {
        global $woocommerce;
        $amount = wc_prices_include_tax() ? WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax() : WC()->cart->get_cart_contents_total();
        $amount =WC()->cart->get_total();
        ?>
        <script>
            jQuery(document).ready(function ($){
                $(document).on('change', '.woo-currency-switcher', function (e){
                    var currency = $(this).val();
                    console.log('testing ', currency);
                    jQuery.ajax({
                        type: "POST",
                        url: '<?php echo admin_url("admin-ajax.php")?>',
                        data: {
                            action: "add_currency_converter_add",
                            currency: currency,
                        },
                        dataType: "json",
                        cache: false,
                        success: function (res) {
                            window.location.reload();
                        }
                    });
                });
            })
        </script>
        <?php
        if ( is_checkout()) :
            ?>
            <script type="text/javascript">
                jQuery( function($){
                    $('form.checkout').on('change', 'input[name="payment_method"]', function(){
                        $(document.body).trigger('update_checkout');
                    });
                });
            </script>
            <?php
        endif;
    }
    public function add_currency_converter_add() {
        $val = $_POST['currency'];
        session_start();
        $_SESSION['currency'] = $val;
        echo 200;
        die();
    }
    public function woocommerce_product_options_general_product_data_function() {
        ?>
        <div class="product_custom_field">
            <?php
            woocommerce_wp_text_input( array(
                'id'          => 'thb_price',
                'type'          => 'number',
                'label'       => __('Thai Price:', 'woocommerce'),
                'placeholder' => 'Thai Price',
                'desc_tip'    => 'true' // <== Not needed as you don't use a description
            ) );
            woocommerce_wp_text_input( array(
                'id'          => 'euro_price',
                'type'          => 'number',
                'label'       => __('EURO Price:', 'woocommerce'),
                'placeholder' => 'EURO Price',
                'desc_tip'    => 'true' // <== Not needed as you don't use a description
            ) );
            ?>
        </div>
        <?php
    }
    public function woocommerce_admin_process_product_object_function($product) {
        if (isset($_POST['thb_price']))
            $product->update_meta_data('thb_price', sanitize_text_field($_POST['thb_price']));
        if (isset($_POST['euro_price']))
            $product->update_meta_data('euro_price', sanitize_text_field($_POST['euro_price']));
    }
    public function admin_init_function() {
        register_setting( 'conversion_rate', 'conversion_rate_options' );
        $this->conversion_rate_section();
        $this->conversion_rate_details();
    }
    public function conversion_rate_section(){
        add_settings_section(
            'conversion_rate_design_styling',
            __( 'Details', 'conversion_rate' ), 'conversion_rate_section_styling_data',
            'conversion_rate'
        );
    }
    public function conversion_rate_details(){
        add_settings_field(
            'conversion_rate_usd_price',
            __( 'USD Rate', 'conversion_rate' ),
            array( $this, 'conversion_rate_usd_price_cb'),
            'conversion_rate',
            'conversion_rate_design_styling',
            array(
                'label_for'         => 'conversion_rate_usd_price',
                'class'             => 'conversion_rate_row',
                'conversion_rate_custom_data' => 'custom',
            )
        );
        add_settings_field(
            'conversion_rate_euro_price',
            __( 'EURO Rate', 'conversion_rate' ),
            array( $this, 'conversion_rate_euro_price_cb'),
            'conversion_rate',
            'conversion_rate_design_styling',
            array(
                'label_for'         => 'conversion_rate_euro_price',
                'class'             => 'conversion_rate_row',
                'conversion_rate_custom_data' => 'custom',
            )
        );
    }
    public function conversion_rate_usd_price_cb( $args ) {
        $options = get_option( 'conversion_rate_options' );
        ?>
            <input id="<?php echo esc_attr( $args['label_for'] ); ?>"
                   name="conversion_rate_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
                   value="<?php echo $options[$args['label_for']]; ?>" type="number">
            <p class="description">
                <?php esc_html_e( 'Add Conversion Rate.', 'conversion_rate' ); ?>
            </p>
        <?php
    }
    public function conversion_rate_euro_price_cb( $args ) {
        $options = get_option( 'conversion_rate_options' );
        ?>
            <input id="<?php echo esc_attr( $args['label_for'] ); ?>"
                   name="conversion_rate_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
                   value="<?php echo $options[$args['label_for']]; ?>" type="number">
            <p class="description">
                <?php esc_html_e( 'Add Conversion Rate.', 'conversion_rate' ); ?>
            </p>
        <?php
    }
    public function admin_menu_function() {
        add_menu_page(
            'Currency Rate',
            'Currency Rate',
            'manage_options',
            'conversion_rate',
            array($this, 'sb_conversion_rate_html')
        );
    }
    public function sb_conversion_rate_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <div class="tab-content">
                <form action="options.php" method="post">
                    <?php
                    settings_fields( 'conversion_rate' );
                    do_settings_sections( 'conversion_rate' );
                    submit_button( 'Save Settings' );
                    ?>
                </form>
            </div>
        </div>
        <?php
    }
    public function woocommerce_product_query_function($query) {
        session_start();
        $session = isset($_SESSION['currency']) ? $_SESSION['currency'] : '';
        if(isset($session) && !empty($session)) {
            if ($session == 'THB') {
                $currency_key = 'thb_price';
            }
            else if ($session == 'EUR') {
                $currency_key = 'euro_price';
            }
            else{
                $currency_key = '_price';
            }
            $meta_query = $query->get( 'meta_query' );
            $meta_query[] = array(
                'key'       => $currency_key,
                'value'     => '',
                'compare'   => '!='
            );
            $query->set( 'meta_query', $meta_query );
        }
    }
    public function woocommerce_cart_item_price_function($price, $cart_item) {
        session_start();
        $product = $cart_item['data'];
        $session = isset($_SESSION['currency']) ? $_SESSION['currency'] : '';
        if(isset($session) && !empty($session)){
            $pricee = get_option('conversion_rate_options');
            if($session == 'THB'){
                $price_html = get_post_meta($product->get_id(), 'thb_price', true);
                return $price_html = wc_price( $price_html );
            }
//            else if($session == 'THB_USD'){
//                $price_html = get_post_meta($product->get_id(), '_price', true) * $pricee['conversion_rate_usd_price'];
//                return $price_html = wc_price( $price_html );
//            }
            else if($session == 'EUR'){
                $price_html = get_post_meta($product->get_id(), 'euro_price', true);
                return $price_html = wc_price( $price_html );
            }
            else{
                return $price;
            }
        }
        else{
            return $price;
        }
    }
    public function woocommerce_get_price_html_function($price_html) {
        session_start();
        global $product;
        $session = isset($_SESSION['currency']) ? $_SESSION['currency'] : '';
        if(isset($session) && !empty($session)){
            $pricee = get_option('conversion_rate_options');
            if($session == 'THB'){
                $price_html = get_post_meta($product->get_id(), 'thb_price', true);
                return $price_html = wc_price( $price_html );
            }
//            else if($session == 'THB_USD'){
//                $price_html = get_post_meta($product->get_id(), '_price', true) * $pricee['conversion_rate_usd_price'];
//                return $price_html = wc_price( $price_html );
//            }
            else if($session == 'EUR'){
                $price_html = get_post_meta($product->get_id(), 'euro_price', true);
                return $price_html = wc_price( $price_html );
            }
            else{
                return $price_html;
            }
        }
        else{
            return $price_html;
        }
    }
    public function woocommerce_before_calculate_totals_function($cart) {
        global $woocommerce;
        session_start();
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $session = isset($_SESSION['currency']) ? $_SESSION['currency'] : '';
            if(!empty($session)) {
                $product = $cart_item['data'];
                $pricee = get_option('conversion_rate_options');
                if ($session == 'THB') {
                    $price = get_post_meta($product->get_id(), 'thb_price', true);
                    $cart_item['data']->set_price($price);
                }
                else if ($session == 'THB_USD') {
                    $price = get_post_meta($product->get_id(), '_price', true) * $pricee['conversion_rate_usd_price'];
                    $cart_item['data']->set_price($price);
                }
                else if ($session == 'THB_EUR') {
                    $price = get_post_meta($product->get_id(), 'euro_price', true) * $pricee['conversion_rate_euro_price'];
                    $cart_item['data']->set_price($price);
                }
                else if ($session == 'EUR') {
                    $price = get_post_meta($product->get_id(), 'euro_price', true);
                    $cart_item['data']->set_price($price);
                }
            }
        }

    }
    public function woo_currency_converter() {
        session_start();
        $currency = '';
        $session = isset($_SESSION['currency']) ? $_SESSION['currency'] : '';
        if (isset($session) && !empty($session)){
            $currency = $session;
        }
        $html = '<form method="post" action="" class="woocommerce-currency-switcher-form">';
        $html .= '<select name="woocommerce-currency-switcher" data-width="100%" class="woo-currency-switcher">';
        if($currency == 'USD'){
            $html .= '<option value="USD" selected>USD, $</option>';
        }else{
            $html .= '<option value="USD">USD, $</option>';
        }
        if($currency == 'THB'){
            $html .= '<option value="THB" selected>THB, ฿</option>';
        }else{
            $html .= '<option value="THB">THB, ฿</option>';
        }
        if($currency == 'EUR'){
            $html .= '<option value="EUR" selected>EUR, €</option>';
        }else{
            $html .= '<option value="EUR">EUR, €</option>';
        }

        $html .= '</select></form>';
        return $html;
    }
    public function woocommerce_currency_symbol_function($currency_symbol, $currency) {
        session_start();
        if(is_admin()){
            return $currency_symbol;
        }
        if(is_account_page()){
            return $currency_symbol;
        }
        if (isset($_GET['pay_for_order']) && !empty($_GET['pay_for_order'])){
            return $currency_symbol;
        }
        if ( is_checkout() && !empty( is_wc_endpoint_url('order-received') ) ) {
            return $currency_symbol;
        }
        $session = isset($_SESSION['currency']) ? $_SESSION['currency'] : '';
        if($session == 'THB'){
            $currency_symbol = '฿';
        }
        else if($session == 'THB_USD'){
            $currency_symbol = '฿';
        }
        else if($session == 'THB_EUR'){
            $currency_symbol = '฿';
        }
        else if($session == 'EUR'){
            $currency_symbol = '€';
        }
        else{
            $currency_symbol = '$';
        }
        return $currency_symbol;
    }
    public function woocommerce_cart_total_function( $price ) {
        session_start();
        $payment_method = WC()->session->get( 'chosen_payment_method' );
        global $woocommerce;
        $amount = $woocommerce->cart->cart_contents_total+$woocommerce->cart->tax_total + WC()->cart->get_shipping_total();
        if ( 'gbprimepay_checkout' == $payment_method ) {
            $session = isset($_SESSION['currency']) ? $_SESSION['currency'] : '';
            $pricee = get_option('conversion_rate_options');
            if($session == 'USD'){
                $price2 = $amount * $pricee['conversion_rate_usd_price'];
                $afterPriceSymbol = '<span class="extra-cod-info"><small>(In THB price will be approximately '.$price2.')</small></span>';
                return $afterPriceSymbol.'<br />'.$price;
            }
            else if($session == 'EUR'){
                $price2 = $amount * $pricee['conversion_rate_euro_price'];
                $afterPriceSymbol = '<span class="extra-cod-info"><small>(In THB price will be approximately '.$price2.')</small></span>';
                return $afterPriceSymbol.'<br />'.$price;
            }
            else {
                return $price;
            }
        }
        else {
            return $price;
        }
    }
    public function woocommerce_checkout_create_order_function( $order ) {
        $payment_method = WC()->session->get( 'chosen_payment_method' );
        if ( 'gbprimepay_checkout' == $payment_method ) {
            session_start();
            $session = isset($_SESSION['currency']) ? $_SESSION['currency'] : '';
            if($session == 'THB_USD'){
                $order->set_currency('THB');
                update_post_meta($order->get_id(), '_order_currency', $session);
                $order->save();
                $_SESSION['currency'] = '';
            }
            else if($session == 'THB_EUR'){
                $order->set_currency('THB');
                update_post_meta($order->get_id(), '_order_currency', $session);
                $order->save();
                $_SESSION['currency'] = '';
            }
        }
    }
    public function woocommerce_thankyou_function( $order_id ) {
        $order = wc_get_order( $order_id );
        $payment_method = $order->get_payment_method();
        $success = get_post_meta($order_id, '_order_payment', true);
        if ( 'gbprimepay_checkout' == $payment_method && $success == 200) {
            $total = $order->get_total();
            $pricee = get_option('conversion_rate_options');
            session_start();
            $session = isset($_SESSION['currency']) ? $_SESSION['currency'] : '';
            if ($session == 'USD') {
                $new_total = $total / $pricee['conversion_rate_usd_price'];
            } else if ($session == 'EUR') {
                $new_total = $total / $pricee['conversion_rate_euro_price'];
            }
            else{
                $new_total = $total;
            }
            if (WC()->version < '3.0.0') {
                $order->set_total($new_total, 'cart_discount');
            } else {
                $order->set_total($new_total);
            }
            $order->calculate_totals();
            update_post_meta($order_id, '_order_payment', '');
            echo "<script type='text/javascript'>window.location=document.location.href;</script>";
        }
    }
    public function woocommerce_checkout_update_order_meta_function( $order_id, $posted ) {
        $order = wc_get_order( $order_id );
        $payment_method = $order->get_payment_method();
        $session = isset($_SESSION['currency']) ? $_SESSION['currency'] : '';
        if ( 'gbprimepay_checkout' == $payment_method ) {
            update_post_meta($order_id, '_order_payment', 200);
            update_post_meta($order_id, '_order_currency', $session);
        }
    }
    public function woocommerce_package_rates_funtion( $rates, $package ) {
        session_start();
        $session = isset($_SESSION['currency']) ? $_SESSION['currency'] : '';
        $pricee = get_option('conversion_rate_options');
        foreach( $rates as $rate_key => $rate ){
            if($session == 'THB' || $session == 'THB_USD'){
                $rates[$rate_key]->cost = $rates[$rate_key]->cost * $pricee['conversion_rate_usd_price'];
            }
            else if($session == 'THB_EUR'){
                $rates[$rate_key]->cost = $rates[$rate_key]->cost * $pricee['conversion_rate_euro_price'];
            }
//            else if($session == 'EUR'){
//                $rates[$rate_key]->cost = $rates[$rate_key]->cost + $pricee['conversion_rate_euro_price'];
//            }
//            else {
//                $rates[$rate_key]->cost = $rates[$rate_key]->cost + $pricee['conversion_rate_usd_price'];
//            }
        }
        return $rates;
    }
    public function woocommerce_after_checkout_validation_function($data,$errors){
        if( WC()->session->get( 'chosen_payment_method' ) == 'gbprimepay_checkout'){
            session_start();
            global $woocommerce;
            if($_SESSION['currency'] == 'USD'){
                $_SESSION['currency'] = 'THB_USD';
            }
            else if($_SESSION['currency'] == 'EUR'){
                $_SESSION['currency'] = 'THB_EUR';
            }
            $items = $woocommerce->cart->get_cart();
            $pricee = get_option('conversion_rate_options');
            foreach ( $items as $key => $value ) {
                $price = get_post_meta($value['product_id'], '_price', true) * $pricee['conversion_rate_usd_price'];
                $value['data']->set_price($price);
            }
            $woocommerce->cart->calculate_totals();
        }
    }
}
