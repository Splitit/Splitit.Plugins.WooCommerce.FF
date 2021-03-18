<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Log
 */
class Log
{

    /**
     * @var string
     */
    protected static $file_log = 'splitit.log';

    /**
     * @var string
     */
    protected static $db_table_log = 'splitit_log';

    /**
     * @var string
     */
    protected static $db_table_transaction_log = 'splitit_transactions_log';

    /**
     * @var string
     */
    protected static $db_order_data = 'splitit_order_data_with_ipn';

    /**
     * Log into DB, file and WC
     * @param $data
     * @param $message
     * @param string $type
     * @param null $file
     */
    public static function save_log_info($data, $message, $type = '', $file = null)
    {
        Log::log_to_db($data);
        Log::log_to_file($file, $message);
        Log::wc_log($type, $message);
    }

    /**
     * Log to DB method
     * @param $data
     */
    public static function log_to_db($data)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$db_table_log;

        if (isset($data['user_id']) && $data['user_id'] == '0') {
            $data['user_id'] = null;
        }

        $wpdb->insert("$table_name", [
            "user_id" => $data['user_id'] ?? null,
            "method" => $data['method'] ?? null,
            "message" => $data['message'] ?? null,
            "date" => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log to file method
     * @param null $file
     * @param $errorMessage
     */
    public static function log_to_file($file = null, $errorMessage)
    {
        if (!isset($file)) {
            $file = self::$file_log;
        }

        $path = __DIR__ . '/../logs/';
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        $file = $path . $file;
        $fd = fopen($file, 'a') or die("Could not open file - " . $file);
        $message = date('Y-m-d H:i:s') . ' ' . $errorMessage . PHP_EOL;
        fwrite($fd, $message);
        fclose($fd);
    }

    /**
     * WooCommerce log
     * @param $type
     * @param $message
     * @param array $context
     */
    public static function wc_log($type, $message, $context = [])
    {
        $log = new WC_Logger();

        switch ($type) {
            case 'error':
                $log->error($message, $context);
                break;
            case 'warning':
                break;
            case 'notice':
                $log->notice($message, $context);
                break;
            case 'info':
                $log->info($message, $context);
                break;
            case 'critical':
                $log->critical($message, $context);
                break;
            case 'alert':
                $log->alert($message, $context);
                break;
            case 'emergency':
                $log->emergency($message, $context);
                break;
            default:
                $log->info($message, $context);
                break;
        }
    }

    /**
     * Method for adding data to transaction log
     * @param $data
     */
    public static function transaction_log($data)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$db_table_transaction_log;

        if (isset($data['user_id']) && $data['user_id'] == '0') {
            $data['user_id'] = null;
        }

        $wpdb->insert("$table_name", [
            "user_id" => $data['user_id'] ?? null,
            "order_id" => $data['order_id'] ?? null,
            "installment_plan_number" => $data['installment_plan_number'] ?? null,
            "number_of_installments" => $data['number_of_installments'] ?? null,
            "processing" => $data['processing'] ?? null,
            "plan_create_succeed" => $data['plan_create_succeed'] ?? 0,
            "date" => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Method for updating transaction record
     * @param $data
     */
    public static function update_transaction_log($data)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$db_table_transaction_log;
        $wpdb->update("$table_name", ['plan_create_succeed' => 1], ['installment_plan_number' => $data['installment_plan_number']]);
    }


    /**
     * Get record from transaction log by order ID
     * @param null $order_id
     * @param string $type
     * @return array|object|void|null
     */
    public static function select_from_transaction_log_by_order_id($order_id = null, $type = OBJECT)
    {
        $return_data = null;
        global $wpdb;
        $table_name = $wpdb->prefix . self::$db_table_transaction_log;

        if (isset($order_id)) {
            $return_data = $wpdb->get_row("SELECT *
	                              FROM $table_name
	                              WHERE order_id = '$order_id'
	                              ORDER BY order_id DESC LIMIT 0,1", $type);
        }

        return $return_data;
    }

    /**
     * Get record from transaction log by ipn
     * @param null $ipn
     * @param string $type
     * @return array|object|void|null
     */
    public static function select_from_transaction_log_by_ipn($ipn = null, $type = OBJECT)
    {
        $return_data = null;
        global $wpdb;
        $table_name = $wpdb->prefix . self::$db_table_transaction_log;

        if (isset($ipn)) {
            $return_data = $wpdb->get_row("SELECT *
	                              FROM $table_name
	                              WHERE number_of_installments = '$ipn'
	                              ORDER BY number_of_installments DESC LIMIT 0,1", $type);
        }

        return $return_data;
    }

    /**
     * Get info about transaction by order ID
     * @param $order_id
     * @return false|mixed
     */
    public static function get_splitit_info_by_order_id($order_id)
    {
        global $wpdb;

        $splitit_transaction_info = $wpdb->get_results("Select installment_plan_number, number_of_installments, plan_create_succeed FROM " . $wpdb->prefix . "splitit_transactions_log WHERE order_id=$order_id LIMIT 1");

        return !empty($splitit_transaction_info) ? $splitit_transaction_info[0] : false;
    }

    /**
     * Add data about order
     * @param $data
     */
    public static function add_order_data($data)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$db_order_data;

        $exist = $wpdb->get_row("SELECT *
	                              FROM $table_name
	                              WHERE ipn = '" . $data['ipn'] . "'
	                              ORDER BY ipn DESC LIMIT 0,1");

        if (!isset($exist)) {
            $wpdb->insert(
                $table_name, [
                    'ipn' => $data['ipn'],
                    'user_id' => $data['user_id'],
                    'cart_items' => $data['cart_items'],
                    'shipping_method_cost' => $data['shipping_method_cost'],
                    'shipping_method_title' => $data['shipping_method_title'],
                    'shipping_method_id' => $data['shipping_method_id'],
                    'coupon_amount' => $data['coupon_amount'],
                    'coupon_code' => $data['coupon_code'],
                    'tax_amount' => $data['tax_amount'],
                    'user_data' => json_encode($data['user_data']),
                    'set_shipping_total' => $data['set_shipping_total'],
                    'set_discount_total' => $data['set_discount_total'],
                    'set_discount_tax' => $data['set_discount_tax'],
                    'set_cart_tax' => $data['set_cart_tax'],
                    'set_shipping_tax' => $data['set_shipping_tax'],
                    'set_total' => $data['set_total'],
                    'wc_cart' => $data['wc_cart'],
                    'get_packages' => $data['get_packages'],
                    'chosen_shipping_methods_data' => $data['chosen_shipping_methods_data'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        } else {
            $wpdb->update(
                $table_name,
                ['ipn' => $data['ipn']], [
                    'ipn' => $data['ipn'],
                    'user_id' => $data['user_id'],
                    'cart_items' => $data['cart_items'],
                    'shipping_method_cost' => $data['shipping_method_cost'],
                    'shipping_method_title' => $data['shipping_method_title'],
                    'shipping_method_id' => $data['shipping_method_id'],
                    'coupon_amount' => $data['coupon_amount'],
                    'coupon_code' => $data['coupon_code'],
                    'tax_amount' => $data['tax_amount'],
                    'user_data' => json_encode($data['user_data']),
                    'set_shipping_total' => $data['set_shipping_total'],
                    'set_discount_total' => $data['set_discount_total'],
                    'set_discount_tax' => $data['set_discount_tax'],
                    'set_cart_tax' => $data['set_cart_tax'],
                    'set_shipping_tax' => $data['set_shipping_tax'],
                    'set_total' => $data['set_total'],
                    'wc_cart' => $data['wc_cart'],
                    'get_packages' => $data['get_packages'],
                    'chosen_shipping_methods_data' => $data['chosen_shipping_methods_data'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        }
    }

    /**
     * Get information about order by ipn
     * @param $ipn
     * @return false|mixed
     */
    public static function get_order_info_by_ipn($ipn)
    {
        global $wpdb;

        $order_info = $wpdb->get_results("Select * FROM " . $wpdb->prefix . "splitit_order_data_with_ipn WHERE ipn='$ipn' LIMIT 1");

        return !empty($order_info) ? $order_info[0] : false;
    }

    /**
     * Check if order exists by ipn
     * @param $ipn
     * @return bool
     */
    public static function check_exist_order_by_ipn($ipn)
    {
        global $wpdb;

        $order_id = $wpdb->get_results("Select order_id FROM " . $wpdb->prefix . "splitit_transactions_log WHERE installment_plan_number='$ipn' LIMIT 1");

        return !empty($order_id[0]) ? true : false;
    }

}
