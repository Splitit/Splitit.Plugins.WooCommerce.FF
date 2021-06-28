<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/*
 * Plugin Name: Splitit - WooCommerce plugin
 * Plugin URI: https://www.google.com
 * Description: Plugin available to WooCommerce users that would allow adding Splitit as a payment method at checkout.
 * Author: IWD
 * Author URI: https://www.iwdagency.com/
 * Version: 1.0.1
 *
 */

/*
 * Global plugin_id
 */
global $plguin_id;
$plguin_id = 'splitit';

/*
 * Check that WooCommerce is active
 */
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'split_add_gateway_class');
function split_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Splitit_Gateway';
    return $gateways;
}

/*
 * Configure from plugin page
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'splitit_gateway_plugin_links');
function splitit_gateway_plugin_links($links)
{
    global $plguin_id;
    $plugin_links = [
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $plguin_id) . '">' . __('Configure', 'wc-splitit') . '</a>'
    ];

    return array_merge($plugin_links, $links);
}

/*
 * Include DB files for creating tables in DB
 */
require_once 'db/create_log_table.php';
require_once 'db/create_transactions_tracking_table.php';
require_once 'db/create_order_data_with_ipn.php';

/*
 * Add DB tables when activating the plugin
 */
register_activation_hook(__FILE__, 'create_log_table');
register_activation_hook(__FILE__, 'create_transactions_tracking_table');
register_activation_hook(__FILE__, 'create_order_data_with_ipn');

/*
 * Redirect to the settings page after plugin activate
 */
function cyb_activation_redirect($plugin)
{
    if ($plugin == plugin_basename(__FILE__)) {
        global $plguin_id;
        exit(wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $plguin_id)));
    }
}

add_action('activated_plugin', 'cyb_activation_redirect');


//Send email when error occurs
set_error_handler('errorHandler');
set_exception_handler('exceptionHandler');

/**
 *
 * @param type $errorNumber
 * @param type $errorString
 * @param type $errorFile
 * @param type $errorLine
 * @param type $errorContext
 */
function errorHandler($errorNumber, $errorString, $errorFile, $errorLine, $errorContext) {

    $emailMessage = '<h2>Error Reporting on :- </h2>[' . date("Y-m-d h:i:s", time()) . ']';
    $emailMessage .= "<h2>Error Number :- </h2>".print_r($errorNumber, true).'';
    $emailMessage .= "<h2>Error String :- </h2>".print_r($errorString, true).'';
    $emailMessage .= "<h2>Error File :- </h2>".print_r($errorFile, true).'';
    $emailMessage .= "<h2>Error Line :- </h2>".print_r($errorLine, true).'';
    $emailMessage .= "<h2>Error Context :- </h2>".createTable($errorContext);

    send_error_email($emailMessage);
}

/**
 * @param $array
 * @return string
 */
function createTable($array){
    if(is_array($array) && count($array)>0){
        $errorContent = "<table border = 1><tr><td>";
        foreach ($array as $key => $val) {
            $errorContent .= $key . "</td><td>";
            if(is_array($val) && count($val)>0){
                $errorContent .= createTable(json_decode(json_encode($val),true)) ;
            }else{
                $errorContent .= print_r($val, true) ;
            }
        }
        $errorContent .= "</td></tr></table>";
        return $errorContent;
    }
    return '';
}

/**
 * @param $exception
 */
function exceptionHandler($exception) {

    $emailMessage = '<h2>Error Reporting on :- </h2>[' . date("Y-m-d h:i:s", time()) . ']';
    $emailMessage .= "<h2>Error Number :- </h2>".print_r($exception->getCode(), true).'';
    $emailMessage .= "<h2>Error String :- </h2>".print_r($exception->getMessage(), true).'';
    $emailMessage .= "<h2>Error File :- </h2>".print_r($exception->getFile(), true).'';
    $emailMessage .= "<h2>Error Line :- </h2>".print_r($exception->getLine(), true).'';
    $emailMessage .= "<h2>Error Context :- </h2>".$exception->getTraceAsString();

    send_error_email($emailMessage);
}

/**
 * @param $emailMessage
 */
function send_error_email($emailMessage) {
    $emailAddress = 'maksymvasylchuk@gmail.com, natalyt@iwdagency.com';
    $emailSubject = 'Error on SplitIt WC - ' . $_SERVER['SERVER_NAME'];
    $headers = "MIME-Version: 1.0" . "rn";
    $headers .= "Content-type:text/html;charset=UTF-8" . "rn";
    mail($emailAddress, $emailSubject, $emailMessage, $headers); // you may use SMTP, default php mail service OR other email sending process
}


/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'split_init_gateway_class');
function split_init_gateway_class()
{


    /*
     * Include additional classes
     */
    require_once 'classes/log.php';
    require_once 'classes/api.php';
    require_once 'classes/settings.php';
    require_once 'classes/checkout.php';
    require_once 'classes/traits/upstream-messaging-trait.php';

    /**
     * Class WC_Splitit_Gateway
     */
    class WC_Splitit_Gateway extends WC_Payment_Gateway
    {

        use UpstreamMessagingTrait;

        /**
         * @var null
         */
        public static $instance = null;

        const DEFAULT_INSTALMENT_PLAN = null;

        /**
         * @return WC_Splitit_Gateway|null
         */
        public static function get_instance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * WC_Splitit_Gateway constructor.
         */
        public function __construct()
        {
            global $plguin_id;
            $this->id = $plguin_id; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->title = 'Splitit';
            $this->method_title = 'Splitit';
            $this->method_description = ''; // will be displayed on the options page

            $this->pay_button_id = 'splitit-btn-pay';
            $this->order_button_text = 'Place order';

            // gateways can support subscriptions, refunds, saved payment methods
            $this->supports = [
                'products',
                'refunds',
            ];

            // Method with all the options fields
            $this->init_form_fields();

            // After init_settings() is called, you can get the settings and load them into variables, e.g:
            $this->init_settings();

            // Turn these settings into variables we can use
            foreach ($this->settings as $setting_key => $value) {
                $this->$setting_key = $value;
            }

            // This action hook changed order status
            add_action('woocommerce_thankyou', [$this, 'woocommerce_payment_change_order_status']);

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);


            // TODO Place to add ajax scripts for the front end
            $this->init_ajax_frontend_hook();
        }

        /**
         * Initiate frontend AJAX hooks
         */
        public function init_ajax_frontend_hook()
        {
            add_action('wp_ajax_calculate_new_installment_price_cart_page', [$this, 'calculate_new_installment_price_cart_page']);
            add_action('wp_ajax_nopriv_calculate_new_installment_price_cart_page', [$this, 'calculate_new_installment_price_cart_page']);
            add_action('wp_ajax_calculate_new_installment_price_product_page', [$this, 'calculate_new_installment_price_product_page']);
            add_action('wp_ajax_nopriv_calculate_new_installment_price_product_page', [$this, 'calculate_new_installment_price_product_page']);

            add_action('wp_ajax_flex_field_initiate_method', [$this, 'flex_field_initiate_method']);
            add_action('wp_ajax_nopriv_flex_field_initiate_method', [$this, 'flex_field_initiate_method']);

            add_action('wp_ajax_checkout_validate', [$this, 'checkout_validate']);
            add_action('wp_ajax_nopriv_checkout_validate', [$this, 'checkout_validate']);

            add_action('wp_ajax_order_pay_validate', [$this, 'order_pay_validate']);
            add_action('wp_ajax_nopriv_order_pay_validate', [$this, 'order_pay_validate']);
        }

        /**
         * Plugin options
         */
        public function init_form_fields()
        {
            $this->init_settings();

            $installments = $this->settings['splitit_inst_conf']['ic_from'] ?? null;

            $this->form_fields = Settings::get_fields($installments);
        }

        /**
         * Custom payment form
         */
        public function payment_fields()
        {
            if (!is_ajax()) {
                return;
            }

            $sandbox = true;

            if ($this->splitit_environment == 'sandbox') {
                $sandbox = true;
            } else if ($this->splitit_environment == 'production') {
                $sandbox = false;
            }

            if ($this->method_description) {
                echo wpautop(wp_kses_post($this->method_description));
            }

            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

            // Add this action hook if you want your custom payment gateway to support it
            do_action('woocommerce_splitit_form_start', $this->id);

            global $wp;
            $order_id = $wp->query_vars['order-pay'] ?? null;

            $flex_fields_form = file_get_contents(__DIR__ . '/template/flex-field-index.php');

            $tmp = str_replace("<order_id>", $order_id, $flex_fields_form);
            $tmp2 = str_replace("<debug>", $sandbox, $tmp);
            $result = str_replace("<culture>", str_replace("_", "-", get_locale()), $tmp2);


            echo $result;


            do_action('woocommerce_splitit_form_end', $this->id);

            echo '<div class="clear"></div></fieldset>';

            $this->payment_scripts();
        }


        public function payment_scripts()
        {
            wp_register_script('checkout_js', plugins_url('assets/js/checkout.js', __FILE__));
            wp_enqueue_script('checkout_js');
        }

        /**
         * Method that process payment for the order
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id)
        {
            global $woocommerce;

            if (!is_ssl()) {
                wc_add_notice('Please ensure your site supports SSL connection.', 'error');
                return;
            }

            // we need it to get any order detailes
            $order = wc_get_order($order_id);

            if (isset($_POST['flex_field_ipn']) && isset($_POST['flex_field_num_of_inst'])) {

                if (Log::check_exist_order_by_ipn($_POST['flex_field_ipn'])) {
                    wc_add_notice(sprintf('Sorry, your session has expired. Order already exist. <a href="%s" class="wc-backward">Return to homepage</a>', home_url()), 'error');
                    return;
                }

                $data = [
                    'user_id' => get_current_user_id(),
                    'order_id' => $order_id,
                    'installment_plan_number' => $_POST['flex_field_ipn'],
                    'number_of_installments' => $_POST['flex_field_num_of_inst'],
                    'processing' => 'woocommerce'
                ];
                //Add record to transaction table
                Log::transaction_log($data);

                // #17.06.2021  Postponed order updates after verifyPayment methods
                $api = new API($this->settings, self::DEFAULT_INSTALMENT_PLAN);
//                $api->update($order_id, $_POST['flex_field_ipn']);

                try {
                    $verifyData = $api->verifyPayment($_POST['flex_field_ipn']);
                    if ($verifyData->getResponseHeader()->getSucceeded()) {
                        $order_total_amount = $order->get_total();
                        if ($verifyData->getIsPaid() && $verifyData->getOriginalAmountPaid() == $order_total_amount) {
                            Log::update_transaction_log(['installment_plan_number' => $_POST['flex_field_ipn']]);
                        } else {
                            $api->cancel($_POST['flex_field_ipn'], 'NoRefunds');

                            if (Log::check_exist_order_by_ipn($_POST['flex_field_ipn'])) {
                                Log::update_transaction_log(['installment_plan_number' => $_POST['flex_field_ipn']]);
                            }

                            wc_add_notice('Your order has not been paid, please try again.', 'error');
                            return;

                        }
                    } else {
                        $message = 'Spltiti->verifyPaymentAPI() Returned an failed in process_payment()';
                        $data = [
                            'user_id' => $order->user_id ?? null,
                            'method' => 'process_payment() Splitit',
                            'message' => $message
                        ];
                        Log::save_log_info($data, $message, 'error');
                        if (Log::check_exist_order_by_ipn($_POST['flex_field_ipn'])) {
                            Log::update_transaction_log(['installment_plan_number' => $_POST['flex_field_ipn']]);
                        }
                    }
                    $api->update($order_id, $_POST['flex_field_ipn']);
                } catch (Exception $e) {
                    $message = $e->getMessage();
                    $data = [
                        'user_id' => $order->user_id ?? null,
                        'method' => 'process_payment() Splitit',
                        'message' => $message
                    ];
                    Log::save_log_info($data, $message, 'error');
                }


                $message = 'Customer placed order with Splitit';
                $data = [
                    'user_id' => get_current_user_id(),
                    'method' => 'process_payment() Splitit',
                    'message' => $message
                ];
                Log::save_log_info($data, $message, 'info');

                return [
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                ];
            } else {
                wc_add_notice('Sorry, there was no payment received! Please try to order again.', 'error');

                return;
            }

            wc_add_notice('Something went wrong, please try to place an order again.', 'error');

            return;
        }

        /**
         * Method that process payment refund for the order
         * @param int $order_id
         * @param null $amount
         * @param string $reason
         * @return bool|WP_Error
         */
        public function process_refund($order_id, $amount = null, $reason = '')
        {
            try {
                $order = wc_get_order($order_id);
                if ($order->get_payment_method() == 'splitit') {
                    if (in_array($order->get_status(), ['processing', 'completed'])) {
                        if ($splitit_info = Log::get_splitit_info_by_order_id($order_id)) {
                            $api = new API($this->settings, $splitit_info->number_of_installments);
                            if ($api->refund($amount, $order->get_currency(), $splitit_info)) {
                                if ($order->get_total() == $order->get_total_refunded()) {
                                    $order->update_status('refunded');
                                }
                                return true;
                            }
                        } else {
                            throw new Exception('Refund order to Splitit is failed, no order information in db for ship to Splitit');
                        }
                    } else {
                        throw new Exception('Invalid order status for refund');
                    }
                }

                return true;
            } catch (Exception $e) {
                $message = $e->getMessage();
                $data = [
                    'user_id' => get_current_user_id(),
                    'method' => 'process_refund() Splitit',
                    'message' => $message
                ];
                Log::save_log_info($data, $message, 'error');

                return new WP_Error('error', $message);
            }
        }

        /**
         * Method that change order status
         * @param $order_id
         */
        public function woocommerce_payment_change_order_status($order_id)
        {
            if (!$order_id) {
                return;
            }
            $order = wc_get_order($order_id);
            if ($order->get_payment_method() === 'splitit') {
                if (!$this->settings['splitit_auto_capture']) {
                    $order->update_status('pending');
                } else {
                    $order->update_status('processing');
                }
            }
        }

        /**
         * Initiate admin styles and scripts on the settings page
         */
        public function init_admin_styles_and_scripts()
        {
            global $plguin_id;
            add_action('woocommerce_order_status_changed', [$this, 'processing_change_status']);
            add_action('woocommerce_order_status_cancelled', [$this, 'process_cancelled']);

            //# 18.06.2021 reworked the launch start installation by clicking on the SHIP button
//            add_action('woocommerce_order_status_completed', [$this, 'process_start_installments']);

            add_action('wp_ajax_check_api_credentials', [$this, 'check_api_credentials']);
            add_action('woocommerce_order_item_add_action_buttons', [$this, 'add_ship_button_to_admin_order_page']);
            add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'splitit_add_installment_plan_number_data']);
            add_action('wp_ajax_start_installment_method', [$this, 'start_installment_method']);

            Settings::get_admin_scripts_and_styles($plguin_id);
        }

        /**
         * Call start Installment API method
         */
        public function start_installment_method()
        {
            if (isset($_POST)) {
                $_POST = stripslashes_deep($_POST);
                if (isset($_POST['order_id'])) {
                    return wp_send_json_success($this->process_start_installments($_POST['order_id']));
                }
            }
        }

        /**
         * @param $order
         */
        public function add_ship_button_to_admin_order_page($order)
        {
            if ($order->has_status('completed') || $order->has_status('processing') || $order->has_status('refunded')) {
                return;
            }
            echo "<button id='start_installment_button' data-order_id='{$order->get_id()}' class='button'>SHIP</button>";
        }

        /**
         * Adds installment_plan_number value to order edit page
         *
         * @param $order
         */
        public function splitit_add_installment_plan_number_data($order)
        {
            $order_info = Log::get_splitit_info_by_order_id($order->get_id());
            if (isset($order_info) && !empty($order_info)) {
                echo '<p><strong>' . 'Installment plan number' . ':</strong> ' . $order_info->installment_plan_number . '</p>';
                echo '<p><strong>' . 'Number of installments' . ':</strong> ' . $order_info->number_of_installments . '</p>';
            }
        }

        /**
         * Return IPN and Number of installment for 'Thank you' page
         * @param $thank_you_title
         * @param $order
         * @return string
         */
        public function splitit_add_installment_plan_number_data_thank_you_title($thank_you_title, $order)
        {
            $order_info = Log::get_splitit_info_by_order_id($order->get_id());
            if (isset($order_info) && !empty($order_info)) {
                $thank_you_title = '<p><strong>' . 'Installment plan number' . ':</strong> ' . $order_info->installment_plan_number . '</p> <p><strong>' . 'Number of installments' . ':</strong> ' . $order_info->number_of_installments . '</p>';
            }

            return $thank_you_title;

        }


        /**
         * Method checks if the hook has arrived and auto_capture is on and changes the order status
         *
         * @param $order_id
         */
        public function processing_change_status($order_id)
        {
            if ($this->settings['splitit_auto_capture'] && $order_info = Log::get_splitit_info_by_order_id($order_id)) {
                if (!$order_info->plan_create_succeed) {
                    $order = wc_get_order($order_id);
                    $order->update_status('failed');
                    $message = 'Order = ' . $order_id . ' status changed to failed. Since the hook didn\'t come and auto_capture is on';
                    $data = [
                        'user_id' => get_current_user_id(),
                        'method' => 'processing_change_status() Splitit',
                        'message' => $message
                    ];
                    Log::save_log_info($data, $message);
                }
            }
        }

        /**
         * Initiate AJAX URL that is used in all AJAX calls
         */
        public function init_client_styles_and_scripts()
        {
            add_action('wp_footer', [$this, 'include_footer_script_and_style_front']);
        }

        /**
         * Method that inserts the script in the footer
         */
        public function include_footer_script_and_style_front()
        {
            ?>
            <script>
                ajaxurl = '<?php echo admin_url('admin-ajax.php') ?>';
            </script>
            <?php
        }

        /**
         * Method for check API credentials on the admin settings page
         */
        public function check_api_credentials()
        {
            if (!$this->settings['splitit_api_key'] || !$this->settings['splitit_api_username'] || !$this->settings['splitit_api_password']) {
                $message = "Please enter the credentials and save settings";
            } else {
                $api = new API($this->settings, self::DEFAULT_INSTALMENT_PLAN);
                $session = $api->login(true);
                $message = '';
                if (!isset($session['error'])) {
                    $message .= 'Successfully login! API available!';
                } else {
                    $message .= $session['error']['message'];
                }
            }
            echo $message;
            wp_die();
        }

        /**
         * Method that cancels payment for the order
         * @param $order_id
         * @return bool|void
         */
        public function process_cancelled($order_id)
        {
            try {
                $order = wc_get_order($order_id);
                if ($order->get_payment_method() == 'splitit') {
                    if ($splitit_info = Log::get_splitit_info_by_order_id($order_id)) {
                        $api = new API($this->settings, $splitit_info->number_of_installments);
                        $ipn_info = $api->get_ipn_info($splitit_info->installment_plan_number);

                        if (!empty($ipn_info) && ($ipn_info->getInstallmentPlanStatus()->getCode() == 'PendingMerchantShipmentNotice' || $ipn_info->getInstallmentPlanStatus()->getCode() == 'InProgress')) {
                            $refund_under_cancelation = $ipn_info->getOriginalAmount() == $ipn_info->getOutstandingAmount() ? 'NoRefunds' : 'OnlyIfAFullRefundIsPossible';

                            if ($api->cancel($splitit_info->installment_plan_number, $refund_under_cancelation)) {
                                $order->update_status('cancelled');
                            } else {
                                Settings::update_order_status_to_old($order);
                                throw new Exception('Cancel order failed due to the order being processed already');
                            }

                        } else {
                            Settings::update_order_status_to_old($order);
                            throw new Exception('Cancel order failed due to the order being processed already');
                        }
                    } else {
                        throw new Exception('Cancel order to Splitit is failed, no order information in db for ship to Splitit');
                    }
                }
                return true;
            } catch (Exception $e) {
                $message = $e->getMessage();
                $data = [
                    'user_id' => get_current_user_id(),
                    'method' => 'cancel() Splitit',
                    'message' => $message
                ];
                Log::save_log_info($data, $message, 'error');

                setcookie("splitit", $message, time() + 30);
            }
        }

        /**
         * Method that call the StartInstallment for order and ipn
         * @param $order_id
         * @return void
         */
        public function process_start_installments($order_id)
        {
            try {
                $order = wc_get_order($order_id);
                if ($order->get_payment_method() == 'splitit') {
                    if ($splitit_info = Log::get_splitit_info_by_order_id($order_id)) {
                        $api = new API($this->settings, $splitit_info->number_of_installments);

                        if (!$this->settings['splitit_auto_capture']) {
                            if ($api->start_installments($splitit_info->installment_plan_number)) {
                                $order->update_status('processing');
                                return 'Start installments order to Splitit is success';
                            }
                        } else {
                            return 'Start installments order to Splitit is failed, auto_capture should be off';
                        }

                    } else {
                        return 'Start installments order to Splitit is failed, no order information in db for ship to Splitit';
                    }
                }
            } catch (Exception $e) {
                $message = $e->getMessage();
                $data = [
                    'user_id' => get_current_user_id(),
                    'method' => 'process_start_installments() Splitit',
                    'message' => $message
                ];
                Log::save_log_info($data, $message, 'error');

                setcookie("splitit", $message, time() + 30);

                return $message;
            }
        }

        /**
         * Method for settings form in the admin panel
         * @param array $form_fields
         * @param bool $echo
         * @return string
         */
        public function generate_settings_html($form_fields = [], $echo = true)
        {
            if (empty($form_fields)) {
                $form_fields = $this->get_form_fields();
            }

            $html = '';
            foreach ($form_fields as $k => $v) {
                switch ($k) {
                    case 'splitit_settings_3d':
                    case 'splitit_auto_capture':
                        $html .= $this->generate_custom_checkbox_html($k, $v);
                        break;
                    case 'splitit_inst_conf':
                        $html .= $this->generate_instalments_grid($v, $k);
                        break;
                    default:
                        $type = $this->get_field_type($v);

                        if (method_exists($this, 'generate_' . $type . '_html')) {
                            $html .= $this->{'generate_' . $type . '_html'}($k, $v);
                        } else {
                            $html .= $this->generate_text_html($k, $v, '', '');
                        }
                        break;
                }
            }
            if ($echo) {
                echo $html;
            } else {
                return $html;
            }
        }

        /**
         * Method for custom checkbox on the settings page
         * @param $key
         * @param $data
         * @return false|string
         */
        public function generate_custom_checkbox_html($key, $data)
        {
            $field_key = $this->get_field_key($key);
            $defaults = [
                'title' => '',
                'label' => '',
                'disabled' => false,
                'class' => '',
                'css' => '',
                'type' => 'text',
                'desc_tip' => false,
                'description' => '',
                'custom_attributes' => [],
            ];

            $data = wp_parse_args($data, $defaults);

            if (!$data['label']) {
                $data['label'] = $data['title'];
            }

            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?><?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.
                        ?></label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span>
                        </legend>
                        <label for="<?php echo esc_attr($field_key); ?>" class="switch">
                            <input <?php disabled($data['disabled'], true); ?>
                                    class="<?php echo esc_attr($data['class']); ?>" type="checkbox"
                                    name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>"
                                    style="<?php echo esc_attr($data['css']); ?>"
                                    value="1" <?php checked($this->get_option($key), '1'); ?> <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok.
                            ?> />
                            <div class="slider round">
                                <span class="on">ON</span>
                                <span class="off">OFF</span>
                            </div>
                        </label><br/>
                        <?php echo $this->get_description_html($data); // WPCS: XSS ok.
                        ?>
                    </fieldset>
                </td>
            </tr>
            <?php

            return ob_get_clean();
        }

        /**
         * Method for Installment grid on the settings page
         * @param $v
         * @param $k
         * @return string
         */
        public function generate_instalments_grid($v, $k)
        {
            $html = '<tr valign="top" class="custom_settings" id="main_ic_container">
                            <th class="heading">Number of Installments</th>
                            <td>
                            <table>
                            <p class="help_text_heading help_text_size">You can define several installments per each amount range. Do not overlap amount ranges. See examples:</p>
                            <p class="help_text_bold help_text_size">Bad configuration:</p>
                            <p class="help_text_size">100-500 | 2,3,4</p>
                            <p class="help_text_last help_text_size">300-700 | 4,7,8</p>
                            <p class="help_text_bold help_text_size">Good configuration:</p>
                            <p class="help_text_size">100-500 | 2,3,4</p>
                            <p class="help_text_last help_text_size">501-700 | 5,6,7</p>
                            </table>
                                <table id="ic_container">
                                    <tr>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>No. of installments</th>
                                        <th>Action</th>
                                    </tr>
                                ';
            foreach ($v as $k1 => $v1) {
                $i = 0;

                if (count((array)$v1) == 4) {
                    foreach ((array)$v1 as $k2 => $v2) {
                        if ($i == 0) {
                            $html .= '<tr class="ic_tr" id="ic_tr_' . $k1 . '">';
                        }

                        if ($k2 == 'ic_action') {
                            $html .= $this->generate_custom_text_field_in_grid($k1, $k2, $v2, true);
                        } else {
                            $html .= $this->generate_custom_text_field_in_grid($k1, $k2, $v2);
                        }

                        if ($i == 3) {
                            $html .= '</tr>';
                        }
                        $i++;
                    }
                }
            }
            $html .= '<tr><td colspan="4"><button class="btn btn-default" type="button" id="add_instalment">Add</button></td></tr>
                        </table>
                            </td>
                            </tr>';

            return $html;
        }

        /**
         * Method for custom text field on the settings page
         * @param $order
         * @param $key
         * @param $data
         * @param false $with_label
         * @return false|string
         */
        public function generate_custom_text_field_in_grid($order, $key, $data, $with_label = false)
        {
            $field_key = $this->get_field_key($key);
            $defaults = [
                'title' => '',
                'disabled' => false,
                'class' => '',
                'css' => '',
                'placeholder' => '',
                'type' => 'text',
                'desc_tip' => false,
                'description' => '',
                'custom_attributes' => [],
            ];

            $data = wp_parse_args($data, $defaults);

            ob_start();
            $text = $this->get_option($key);

            if (isset($this->settings['splitit_inst_conf'][$key][$order])) {
                $txtValue = $this->settings['splitit_inst_conf'][$key][$order];
            } else if (isset($text) && !empty($text)) {
                $txtValue = $text;
            } else {
                $txtValue = $data['default'] ?? '';
            }

            ?>
            <?php if ($with_label): ?>
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?><?php echo $this->get_tooltip_html($data); // WPCS: XSS ok. ?></label>
            </th>
        <?php else: ?>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>"
                           type="<?php echo esc_attr($data['type']); ?>" name="<?php echo esc_attr($field_key); ?>[]"
                           id="<?php echo esc_attr($field_key) . '_' . $order; ?>"
                           style="<?php echo esc_attr($data['css']); ?>"
                           value="<?php echo $txtValue; ?>"
                           placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok.
                    ?> />
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok.
                    ?>
                </fieldset>
            </td>
        <?php endif; ?>
            <?php

            return ob_get_clean();
        }

        /**
         * Method allows changing saving of the Installment grid form
         * @return bool
         */
        public function process_admin_options()
        {
            $this->init_settings();

            $post_data = $this->get_post_data();

            foreach ($this->get_form_fields() as $key => $field) {
                if ('title' !== $this->get_field_type($field)) {
                    try {
                        if ($key == 'splitit_inst_conf') {
                            if (isset($post_data['woocommerce_splitit_ic_from']) && isset($post_data['woocommerce_splitit_ic_to']) && isset($post_data['woocommerce_splitit_ic_installment'])) {
                                $newArr = [
                                    'ic_from' => $post_data['woocommerce_splitit_ic_from'],
                                    'ic_to' => $post_data['woocommerce_splitit_ic_to'],
                                    'ic_installment' => $post_data['woocommerce_splitit_ic_installment'],
                                ];
                                $this->settings[$key] = $newArr;
                            } else {
                                $this->settings[$key] = [];
                            }
                        } else {
                            $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                        }
                    } catch (Exception $e) {
                        $this->add_error($e->getMessage());
                    }
                }
            }

            return update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings));
        }

        /**
         * Method that checks SSL connection
         */
        public function do_ssl_check()
        {
            if ($this->enabled == "yes" && !is_ssl()) {
                echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
            }
        }

        /**
         * Method for initiate flex fields styles and scripts
         */
        public function init_flex_fields_styles_and_scripts()
        {
            function add_flex_field_sandbox_scripts()
            {
                wp_register_style('flex_field_css', 'https://flex-fields.sandbox.splitit.com/css/splitit.flex-fields.min.css');
                wp_enqueue_style('flex_field_css');
                wp_register_script('flex_field_js', 'https://flexfields.sandbox.splitit.com/v2.0/splitit.flex-fields.sdk.js', null, null, true);
                wp_enqueue_script('flex_field_js');
            }

            function add_flex_field_production_scripts()
            {
                wp_register_style('flex_field_css', 'https://flex-fields.production.splitit.com/css/splitit.flex-fields.min.css');
                wp_enqueue_style('flex_field_css');
                wp_register_script('flex_field_js', 'https://flexfields.production.splitit.com/v2.0/splitit.flex-fields.sdk.js', null, null, true);
                wp_enqueue_script('flex_field_js');
            }

            if ($this->splitit_environment == 'sandbox') {
                add_action('wp_enqueue_scripts', 'add_flex_field_sandbox_scripts');
            } else if ($this->splitit_environment == 'production') {
                add_action('wp_enqueue_scripts', 'add_flex_field_production_scripts');
            }
        }

        /**
         * Method that calls a method to insert custom flex field CSS from the settings page
         */
        public function init_custom_flex_fields_styles()
        {
            add_action('wp_head', [$this, 'flex_fields_custom_styles'], 100);
        }

        /**
         * Method for initiate Upstream messaging styles and scripts
         */
        public function init_upstream_messaging_styles_and_scripts()
        {
            add_action('wp_footer', [$this, 'upstream_messaging_script']);
            add_action('wp_head', [$this, 'upstream_messaging_script']);
            add_action('wp_head', [$this, 'upstream_messaging_custom_styles'], 100);
            add_action('woocommerce_before_shop_loop', [$this, 'upstream_messaging_script']);
            add_action('woocommerce_single_product_summary', [$this, 'upstream_messaging_script']);
            add_action('woocommerce_before_cart_totals', [$this, 'upstream_messaging_script']);
            add_action('woocommerce_before_checkout_form', [$this, 'upstream_messaging_script']);
        }

        /**
         * Method for insert in the footer custom flex field css
         */
        public function flex_fields_custom_styles()
        {
            if (is_checkout()) {
                echo "<style>" . strip_tags($this->settings['splitit_flex_fields_css']) . "</style>";
            }
        }


        /**
         * Method for asynchronous processing PlanCreatedSucceeded
         */
        public function splitit_payment_success_async()
        {
            try {
                $ipn = isset($_GET['InstallmentPlanNumber']) ? wc_clean($_GET['InstallmentPlanNumber']) : false;
                $order_info = Log::get_order_info_by_ipn($ipn);
                // Processing an order created through the admin panel
                if (!$order_info) {
                    $order_by_transaction = Log::select_from_transaction_log_by_ipn($ipn);
                    $order = wc_get_order($order_by_transaction->order_id);
                    $order_total_amount = $order->get_total();
                } else {
                    $order_total_amount = $order_info->set_total;
                }
                $api = new api($this->settings);

                $verifyData = $api->verifyPayment($ipn);
                if ($verifyData->getResponseHeader()->getSucceeded()) {
                    if ($verifyData->getIsPaid() && $verifyData->getOriginalAmountPaid() == $order_total_amount) {
                        if (!Log::check_exist_order_by_ipn($ipn)) {
                            $checkout = new checkout();
                            $order_id = $checkout->create_checkout($order_info);

                            $ipn_info = $api->get_ipn_info($ipn);
                            $data = [
                                'user_id' => $order_info->user_id,
                                'order_id' => $order_id,
                                'installment_plan_number' => $ipn,
                                'number_of_installments' => $ipn_info->getNumberOfInstallments(),
                                'processing' => 'splitit_hook',
                                'plan_create_succeed' => 1,
                            ];
                            //Add record to transaction table
                            Log::transaction_log($data);

                            $message = 'ASYNC Hook placed order with Splitit';
                            $data = [
                                'user_id' => $order_info->user_id,
                                'method' => 'splitit_payment_success_async() Splitit',
                                'message' => $message
                            ];
                            Log::save_log_info($data, $message);

                            if (!$this->settings['splitit_auto_capture']) {
                                $api->start_installments($ipn);

                                $message = 'ASYNC Hook call start installmentAPI';
                                $data = [
                                    'user_id' => $order_info->user_id,
                                    'method' => 'splitit_payment_success_async() Splitit',
                                    'message' => $message
                                ];
                                Log::save_log_info($data, $message);
                            }
                        } else {
                            Log::update_transaction_log(['installment_plan_number' => $ipn]);
                        }
                    } else {
                        $api->cancel($ipn, 'NoRefunds');
                        $order_id_by_ipn = Log::get_order_id_by_ipn($ipn);
                        $order_id_in_method = $order_id ?? $order_by_transaction->order_id;
                        $order = wc_get_order($order_id_by_ipn->order_id ?? $order_id_in_method);
                        $order->update_status('cancelled');
                        if (Log::check_exist_order_by_ipn($ipn)) {
                            Log::update_transaction_log(['installment_plan_number' => $ipn]);
                        }
                    }
                    $order_id_by_ipn = Log::get_order_id_by_ipn($ipn);
                    $order_id_in_method = $order_id ?? $order_by_transaction->order_id;
                    $api->update($order_id_by_ipn->order_id ?? $order_id_in_method, $ipn);
                } else {
                    $message = 'Spltiti->verifyPaymentAPI() Returned an failed';
                    $data = [
                        'user_id' => $order_info->user_id ? $order_info->user_id : null,
                        'method' => 'splitit_payment_success_async() Splitit',
                        'message' => $message
                    ];
                    Log::save_log_info($data, $message, 'error');
                    if (Log::check_exist_order_by_ipn($ipn)) {
                        Log::update_transaction_log(['installment_plan_number' => $ipn]);
                    }
                }
            } catch (Exception $e) {
                $message = $e->getMessage();
                $data = [
                    'user_id' => $order_info->user_id ? $order_info->user_id : null,
                    'method' => 'splitit_payment_success_async() Splitit',
                    'message' => $message
                ];
                Log::save_log_info($data, $message, 'error');
            }
        }

        /**
         * Method for initiate custom WC API hooks
         */
        public function init_custom_api_hooks()
        {
            add_action('woocommerce_api_splitit_payment_success_async', [$this, 'splitit_payment_success_async']);
        }

        /**
         * Method for Initiate Flex Fields (get token)
         */
        public function flex_field_initiate_method()
        {
            $total = $this->get_order_total();

            $api = new API($this->settings, self::DEFAULT_INSTALMENT_PLAN);

            $installments = $this->get_array_of_installments($total);

            if (isset($_POST)) {
                foreach ($_POST as $key => $value) {
                    $data[$key] = $value;
                }
            }

            if (isset($data['order_id']) && !empty($data['order_id'])) {
                $order = wc_get_order($data['order_id']);

                $order_data = $order->get_data();

                if ($total == 0) {
                    $total = $order->get_total();
                }

                if (isset($order_data['billing'])) {
                    $data['billingAddress']['AddressLine'] = $order_data['billing']['address_1'];
                    $data['billingAddress']['AddressLine2'] = $order_data['billing']['address_2'];
                    $data['billingAddress']['City'] = $order_data['billing']['city'];
                    $data['billingAddress']['State'] = $order_data['billing']['state'];
                    $data['billingAddress']['Country'] = $order_data['billing']['country'];
                    $data['billingAddress']['Zip'] = $order_data['billing']['postcode'];

                    $data['consumerData']['FullName'] = $order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name'];
                    $data['consumerData']['Email'] = $order_data['billing']['email'];
                    $data['consumerData']['PhoneNumber'] = $order_data['billing']['phone'];
                    $data['consumerData']['CultureName'] = str_replace("_", "-", get_locale());
                }
            }

            $data['amount'] = $total;
            $data['currency_code'] = get_woocommerce_currency();
            $data['installments'] = $installments;
            $data['culture'] = str_replace("_", "-", get_locale());


            echo $api->initiate($data);
            wp_die();
        }

        /**
         * Validation for order pay
         */
        public function order_pay_validate()
        {
            if (isset($_POST)) {
                $_POST = stripslashes_deep($_POST);
                $errors = [];
                $all_fields = $_POST['fields'];
                if (isset($all_fields['terms-field']) && $all_fields['terms-field'] && !isset($all_fields['terms'])) {
                    $errors[] = '<li>' . __('You must accept our Terms &amp; Conditions.', 'woocommerce') . '</li>';
                }

                if (!is_ssl()) {
                    $errors[] = '<li>Please ensure your site supports SSL connection.</li>';
                }

                if (is_array($errors) && count($errors)) {
                    $errors = array_unique($errors);
                    $response = [
                        'result' => 'failure',
                        'messages' => implode('', $errors),
                    ];
                } else {
                    $response = [
                        'result' => 'success',
                    ];
                }
            } else {
                $response = [
                    'result' => 'failure',
                    'messages' => 'No data has been sent from form',
                ];
            }
            wp_send_json($response);
        }

        /**
         * Method for custom checkout validation
         */
        public function checkout_validate()
        {
            if (isset($_POST)) {
                $_POST = stripslashes_deep($_POST);
                $errors = [];
                $countries = new WC_Countries();
                $billlingFields = $countries->get_address_fields($countries->get_base_country(), 'billing_');
                $shippingFields = $countries->get_address_fields($countries->get_base_country(), 'shipping_');

                $wc_fields = array_merge($billlingFields, $shippingFields);

                $all_fields = $_POST['fields'];

                if (!is_user_logged_in() && isset($all_fields['createaccount']) && isset($all_fields['billing_email']) && $all_fields['billing_email'] != "") {

                    if (email_exists($all_fields['billing_email_field'])) {
                        $errors[] = '<li>' . __('An account is already registered with your email address. Please login.', 'woocommerce') . '</li>';
                    }
                }

                if (isset($all_fields['terms-field']) && $all_fields['terms-field'] && !isset($all_fields['terms'])) {
                    $errors[] = '<li>' . __('You must accept our Terms &amp; Conditions.', 'woocommerce') . '</li>';
                }


                //For check shipping
                if (WC()->cart->needs_shipping()) {
                    $shipping_country = WC()->customer->get_shipping_country();

                    if (empty($shipping_country)) {
                        $errors[] = __('Please enter an address to continue.', 'woocommerce');
                    } elseif (!in_array(WC()->customer->get_shipping_country(), array_keys(WC()->countries->get_shipping_countries()), true)) {
                        $errors[] = sprintf(__('Unfortunately <strong>we do not ship %s</strong>. Please enter an alternative shipping address.', 'woocommerce'), WC()->countries->shipping_to_prefix() . ' ' . WC()->customer->get_shipping_country());
                    } else {
                        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
                        if (!$chosen_shipping_methods[0]) {
                            $errors[] = __('No shipping method has been selected. Please double check your address, or contact us if you need any help.', 'woocommerce');
                        }
                        foreach (WC()->shipping()->get_packages() as $i => $package) {
                            if (!isset($chosen_shipping_methods[$i], $package['rates'][$chosen_shipping_methods[$i]])) {
                                $errors[] = __('No shipping method has been selected. Please double check your address, or contact us if you need any help.', 'woocommerce');
                            }
                        }
                    }
                }

                $ship_to_different_address = (isset($all_fields['ship_to_different_address']) && $all_fields['ship_to_different_address']) ? true : false;

                if (!$ship_to_different_address) {
                    $all_fields['shipping_first_name'] = $all_fields['billing_first_name'];
                    $all_fields['shipping_last_name'] = $all_fields['billing_last_name'];
                    $all_fields['shipping_company'] = $all_fields['billing_company'];
                    $all_fields['shipping_country'] = $all_fields['billing_country'];
                    $all_fields['shipping_address_1'] = $all_fields['billing_address_1'];
                    $all_fields['shipping_address_2'] = $all_fields['billing_address_2'];
                    $all_fields['shipping_city'] = $all_fields['billing_city'];
                    $all_fields['shipping_state'] = $all_fields['billing_state'];
                    $all_fields['shipping_postcode'] = $all_fields['billing_postcode'];
                }

                foreach ($all_fields as $key => $value) {
                    switch ($key) {
                        case 'billing_postcode':
                            if (!WC_Validation::is_postcode($value, $all_fields['billing_country'])):
                                $errors[] = '<li><strong>' . __('Billing', 'woocommerce') . ' ' . $wc_fields[$key]['label'] . '</strong> ' . __('is not valid.', 'woocommerce') . '</li>';
                            endif;
                            break;

                        case 'shipping_postcode':
                            if (!WC_Validation::is_postcode($value, $all_fields['shipping_country'])):
                                $errors[] = '<li><strong>' . __('Shipping', 'woocommerce') . ' ' . $wc_fields[$key]['label'] . '</strong> ' . __('is not valid.', 'woocommerce') . '</li>';
                            endif;
                            break;

                        case 'billing_phone':
                        case 'shipping_phone':
                            if (!WC_Validation::is_phone($value)) {
                                $errors[] = '<li><strong>' . $wc_fields[$key]['label'] . '</strong> ' . __('is not a valid phone number.', 'woocommerce') . '</li>';
                            }
                            if (strlen($value) < 5 || strlen($value) > 14) {
                                $errors[] = '<li><strong>' . $wc_fields[$key]['label'] . '</strong> ' . __('should be greater than 5 and less than 14 digits', 'woocommerce') . '</li>';
                            }
                            break;

                        case 'billing_email':
                        case 'shipping_email':
                            if (!is_email($value)) {
                                $errors[] = '<li><strong>' . $wc_fields[$key]['label'] . '</strong> ' . __('is not a valid email address.', 'woocommerce') . '</li>';
                            }
                            break;

                        case 'billing_address_1':
                        case 'shipping_address_1':
                            if (!isset($value) || strlen(trim($value)) <= 0) {
                                $errors[] = '<li><strong>' . $wc_fields[$key]['label'] . '</strong> ' . __('is a required field.', 'woocommerce') . '</li>';
                            }
                            break;

                        case 'billing_state':
                        case 'shipping_state':
                            $valid_states = WC()->countries->get_states(WC()->customer->get_billing_country());
                            if (!empty($valid_states) && is_array($valid_states) && sizeof($valid_states) > 0) {
                                if (!in_array($value, array_keys($valid_states))) {
                                    $errors[] = '<li><strong>' . $wc_fields[$key]['label'] . '</strong> ' . __('is not valid. Please enter one of the following:', 'woocommerce') . ' ' . implode(', ', $valid_states) . '</li>';
                                }
                            }
                            break;

                        case 'billing_first_name':
                        case 'billing_last_name':
                        case 'shipping_first_name':
                        case 'shipping_last_name':
                        case 'billing_city':
                        case 'shipping_city':
                        case 'billing_country':
                        case 'shipping_country':
                            if (empty($value) || strlen(trim($value)) <= 0) {
                                $errors[] = '<li><strong>' . $wc_fields[$key]['label'] . '</strong> ' . __('is a required field.', 'woocommerce') . '</li>';
                            }
                            break;
                    }
                }

                if (isset($all_fields['billing_email'])) {
                    WC()->cart->check_customer_coupons(['billing_email' => $all_fields['billing_email']]);
                    $notices = wc_get_notices();
                    if (isset($notices['error']) && !empty($notices['error'])) {
                        foreach ($notices['error'] as $noticeErr) {
                            $errors[] = '<li>' . __($noticeErr, 'woocommerce') . '</li>';
                        }
                    }
                }

                if (!is_ssl()) {
                    $errors[] = '<li>Please ensure your site supports SSL connection.</li>';
                }

                if (is_array($errors) && count($errors)) {
                    $errors = array_unique($errors);
                    $response = [
                        'result' => 'failure',
                        'messages' => implode('', $errors),
                    ];
                } else {
                    $response = [
                        'result' => 'success',
                    ];
                    $this->add_order_data_to_db($_POST);
                }
            } else {
                $response = [
                    'result' => 'failure',
                    'messages' => 'No data has been sent from form',
                ];
            }
            wp_send_json($response);
        }

        /**
         * Method for adding full data about the order
         * @param $data
         */
        public function add_order_data_to_db($data)
        {
            if (isset($data)) {
                global $woocommerce;
                $fetch_session_item = WC()->session->get('chosen_shipping_methods');
                $shipping_method_cost = WC()->cart->shipping_total;
                if (!empty($fetch_session_item)) {
                    $explode_items = explode(":", $fetch_session_item[0]);
                    $shipping_method_id = $explode_items[0];
                } else {
                    $shipping_method_id = "";
                }
                $shipping_method_title = "";
                $coupon_code = "";
                $coupon_amount = "";
                $applied_coupon_array = $woocommerce->cart->get_applied_coupons();
                if (!empty($applied_coupon_array)) {
                    $discount_array = $woocommerce->cart->coupon_discount_amounts;
                    foreach ($discount_array as $key => $value) {
                        $coupon_code = $key;
                        $coupon_amount = wc_format_decimal(number_format($discount_array[$key], 2));
                    }
                }

                $set_shipping_total = WC()->cart->shipping_total;
                $set_discount_total = WC()->cart->get_cart_discount_total();
                $set_discount_tax = WC()->cart->get_cart_discount_tax_total();
                $set_cart_tax = WC()->cart->tax_total;
                $set_shipping_tax = WC()->cart->shipping_tax_total;
                $set_total = WC()->cart->total;
                $wc_cart = json_encode(WC()->cart);

                $get_packages = json_encode(WC()->shipping->get_packages());
                $chosen_shipping_methods_data = json_encode(WC()->session->get('chosen_shipping_methods'));

                $total_tax_amount = "";
                $total_taxes_array = WC()->cart->get_taxes();
                if (!empty($total_taxes_array)) {
                    $total_tax_amount = array_sum($total_taxes_array);
                    $total_tax_amount = wc_format_decimal(number_format($total_tax_amount, 2));
                }

                $insert_data = [
                    'ipn' => $data['ipn'],
                    'user_id' => get_current_user_id(),
                    'cart_items' => json_encode(WC()->cart->get_cart()),
                    'shipping_method_cost' => $shipping_method_cost,
                    'shipping_method_title' => $shipping_method_title,
                    'shipping_method_id' => $shipping_method_id,
                    'coupon_amount' => $coupon_amount,
                    'coupon_code' => $coupon_code,
                    'tax_amount' => $total_tax_amount,
                    'user_data' => $data['fields'],
                    'set_shipping_total' => $set_shipping_total,
                    'set_discount_total' => $set_discount_total,
                    'set_discount_tax' => $set_discount_tax,
                    'set_cart_tax' => $set_cart_tax,
                    'set_shipping_tax' => $set_shipping_tax,
                    'set_total' => $set_total,
                    'wc_cart' => $wc_cart,
                    'get_packages' => $get_packages,
                    'chosen_shipping_methods_data' => $chosen_shipping_methods_data,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                Log::add_order_data($insert_data);
            }
        }

        /**
         * Output of the admin notices
         */
        public function admin_notices()
        {
            if (!empty($_COOKIE['splitit'])) {
                $message = $_COOKIE['splitit'];
                setcookie("splitit", '', time() - 30);
                echo '<div class="notice notice-error is-dismissible"><p>' . $message . '</p></div>';
            }
        }

        /**
         * Method initiate admin ssl notice
         */
        public function init_admin_notice()
        {
            add_action('admin_notices', [$this, 'admin_notices']);
            add_action('admin_notices', [$this, 'do_ssl_check']);
        }


        /**
         * Disable SplitIt Based on Cart Total - WooCommerce
         */
        public function init_disable_of_the_payment()
        {
            add_filter('woocommerce_available_payment_gateways', [$this, 'disable_splitit']);
        }

        /**
         * @param $available_gateways
         * @return mixed
         */
        public function disable_splitit($available_gateways)
        {
            $price = WC()->cart->total;
            $installments = $this->get_array_of_installments($price);
            global $plguin_id;
            if (!isset($installments) || empty($installments)) {
                unset($available_gateways[$plguin_id]);
            }
            return $available_gateways;
        }


        /**
         * Add IPN and Number of installment to the 'Thank you' page
         */
        public function init_ipn_to_the_thank_you_page()
        {
            add_filter('woocommerce_thankyou_order_received_text', [$this, 'splitit_add_installment_plan_number_data_thank_you_title'], 10, 2);
        }

    }

    /**
     * Singleton
     * @return WC_Splitit_Gateway|null
     */
    function SplitIt()
    {
        return WC_Splitit_Gateway::get_instance();
    }

    SplitIt();
    Splitit()->init_custom_api_hooks();

    if (is_admin()) {
        SplitIt()->init_admin_styles_and_scripts();
        SplitIt()->init_admin_notice();
    } else {
        SplitIt()->init_flex_fields_styles_and_scripts();
        SplitIt()->init_upstream_messaging_styles_and_scripts();
        SplitIt()->init_custom_flex_fields_styles();
        SplitIt()->init_footer_credit_cards();
        SplitIt()->init_home_page_banner();
        SplitIt()->init_shop_page();
        SplitIt()->init_product_page();
        SplitIt()->init_cart_page();
        SplitIt()->init_checkout_page();
        SplitIt()->init_client_styles_and_scripts();
        SplitIt()->init_disable_of_the_payment();
        SplitIt()->init_ipn_to_the_thank_you_page();
    }

    /**
     * Adding custom payment button to the checkout page
     */
    add_filter('woocommerce_order_button_html', 'custom_order_button_html');
    function custom_order_button_html($button)
    {
        // HERE you make changes (Replacing the code of the button):
        $button = '<button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" onclick="performPayment(this)" value="Place order" data-value="Place order"></button>';

        // Return the modified/filtered content
        return $button;
    }

    /**
     * Outputting the hidden field in checkout page
     */
    add_action('woocommerce_after_order_notes', 'add_custom_checkout_hidden_field');
    function add_custom_checkout_hidden_field($checkout)
    {
        // Output the hidden field
        echo '<div id="flex_field_hidden_checkout_field">
                <input type="hidden" class="input-hidden" name="flex_field_ipn" id="flex_field_ipn" value="">
                <input type="hidden" class="input-hidden" name="flex_field_num_of_inst" id="flex_field_num_of_inst" value="">
            </div>';
    }

    add_action('woocommerce_order_status_changed', 'grab_order_old_status', 10, 4);
    function grab_order_old_status($order_id, $status_from, $status_to, $order)
    {
        if ($order->get_meta('_old_status')) {
            // Grab order status before it's updated
            update_post_meta($order_id, '_old_status', $status_from);
        } else {
            // Starting status in Woocommerce (empty history)
            update_post_meta($order_id, '_old_status', 'processing');
        }
    }


    add_action('wp_head', 'custom_checkout_script');

    function custom_checkout_script()
    {
        if (is_checkout() == true) {
            echo '<script>var flexFieldsInstance; localStorage.removeItem("ipn"); </script>';
        }
    }

}

