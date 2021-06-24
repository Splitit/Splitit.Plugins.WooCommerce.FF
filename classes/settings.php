<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Settings
 */
class Settings
{

    /**
     * Return fields for plugin settings page
     * @param null $installments
     * @return array
     */
    public static function get_fields($installments = null)
    {
        $fields = [
            'enabled' => [
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable Payment',
                'default' => 'no'
            ],
            '_Connection_Settings_section' => [
                'type' => 'title',
                'title' => 'Connection Settings',
                'description' => 'Connection Settings',
            ],
            'splitit_api_username' => [
                'title' => 'API Username',
                'type' => 'text',
            ],
            'splitit_api_password' => [
                'title' => 'API Password',
                'type' => 'password',
            ],
            'splitit_api_key' => [
                'title' => 'API Key',
                'type' => 'text',
            ],
            'splitit_environment' => [
                'title' => 'Environment',
                'type' => 'select',
                'options' => [
                    'sandbox' => 'Sandbox',
                    'production' => 'Production',
                ],
            ],
            'splitit_check_credentials' => [
                'title' => '<a href="#" class="checkApiCredentials" id="checkApiCredentials">Check Credentials</a>',
                'css' => 'display:none;',
                'desc_tip' => true,
                'description' => 'Before starting the check - save the form',
            ],

            '_Payment_Method_Settings_section' => [
                'type' => 'title',
                'title' => 'Payment Method Settings',
                'description' => 'Payment Method Settings'
            ],

            'splitit_settings_3d' => [
                'title' => '3DS On/Off',
                'type' => 'select',
                'options' => [
                    '1' => 'On',
                    '0' => 'Off',
                ],
                'default' => '0',
            ],

            'splitit_auto_capture' => [
                'title' => 'Splitit Auto-Capture',
                'type' => 'select',
                'options' => [
                    '1' => 'On',
                    '0' => 'Off',
                ],
                'default' => '0',
            ],

            'splitit_inst_conf' => self::get_installment_fields($installments),

            '_Payment_Form_and_Upstream_Messaging_User_Interface_Setting' => [
                'type' => 'title',
                'title' => 'FlexFields User Interface Settings',
                'description' => 'FlexFields is the set of sensitive credit card fields that Splitit provides to collect the credit card information'
            ],

            'splitit_upstream_messaging_selection' => [
                'title' => 'Upstream Messaging Selection',
                'type' => 'multiselect',
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'css' => 'width: 450px;',
                'custom_attributes' => [
                    'data-placeholder' => 'Upstream Messaging Selection',
                ],
                'options' => self::upstream_messaging_selection()
            ],

            'splitit_upstream_messaging_css' => [
                'title' => 'Upstream Messaging CSS',
                'type' => 'textarea',
                'default' => '<style></style>',
            ],

            'splitit_flex_fields_css' => [
                'title' => 'Flex Fields CSS',
                'type' => 'textarea',
                'default' => '<style></style>'
            ],

            'splitit_footer_allowed_card_brands' => [
                'title' => 'Footer Allowed Card Brands',
                'type' => 'multiselect',
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'css' => 'width: 450px;',
                'custom_attributes' => [
                    'data-placeholder' => 'Footer Allowed Card Brands',
                ],
                'options' => self::footer_card_brands()
            ],
        ];

        return $fields;
    }


    /**
     * Initiate admin scripts and styles
     * @param string $plugin_id
     */
    public static function get_admin_scripts_and_styles($plugin_id = 'splitit')
    {
        if (isset($_GET['section']) && $_GET['section'] == $plugin_id) {
            add_action('admin_enqueue_scripts', ['Settings', 'add_admin_files']);
            add_action('admin_footer', ['Settings', 'wpb_hook_javascript']);
        }

        if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['post'])) {
            add_action('admin_enqueue_scripts', ['Settings', 'add_admin_order_files']);
        }
    }

    public static function add_admin_order_files()
    {
        wp_enqueue_style('admin.css', plugins_url('assets/css/adminOrder.css', dirname(__FILE__)));
        wp_enqueue_script('custom_admin_script', plugins_url('/assets/js/adminOrder.js', dirname(__FILE__)), ['jquery']);
    }

    /**
     * Register admin styles and scripts
     */
    public static function add_admin_files()
    {
        wp_enqueue_style('admin.css', plugins_url('assets/css/admin.css', dirname(__FILE__)));
        wp_enqueue_script('admin', plugins_url('/assets/js/admin.js', dirname(__FILE__)), ['jquery', 'jquery-validate', 'jquery-validate-additional']);

        //Codemiror
        wp_enqueue_style('codemirror.css', plugins_url('assets/codemirror/codemirror.css', dirname(__FILE__)));
        wp_enqueue_script('codemirror', plugins_url('/assets/codemirror/codemirror.js', dirname(__FILE__)), ['jquery']);
        wp_enqueue_script('mode_css', plugins_url('/assets/codemirror/mode/css/css.js', dirname(__FILE__)), ['jquery']);

        //JQuery Validation
        wp_enqueue_script('jquery-validate', plugins_url('/assets/validation/jquery.validate.js', dirname(__FILE__)), ['jquery']);
        wp_enqueue_script('jquery-validate-additional', plugins_url('/assets/validation/additional-methods.js', dirname(__FILE__)), ['jquery', 'jquery-validate']);
    }

    /**
     * Added script for CodeMirror
     */
    public static function wpb_hook_javascript()
    {
        ?>
        <script>
        (function ($) {
            var editor = CodeMirror.fromTextArea(document.getElementById('woocommerce_splitit_splitit_flex_fields_css'), {
                lineNumbers: true,
                mode: 'css',
                tabSize: '10'
            });

            editor.setSize('800', '250');
        })(jQuery);

        (function ($) {
            var editor_upstream_messaging = CodeMirror.fromTextArea(document.getElementById('woocommerce_splitit_splitit_upstream_messaging_css'), {
                lineNumbers: true,
                mode: 'css',
                tabSize: '10'
            });

            editor_upstream_messaging.setSize('800', '250');
        })(jQuery);
        </script>

        <?php
    }

    /**
     * Return allowed card brands in the footer
     * @return string[]
     */
    private static function footer_card_brands()
    {
        return [
            'amex' => 'Amex',
            'jcb' => 'jcb',
            'dinersclub' => 'dinersclub',
            'maestro' => 'maestro',
            'discover' => 'discover',
            'visaelectron' => 'visaelectron',
            'mastercard' => 'mastercard',
            'visa' => 'visa'
        ];
    }

    /**
     * Return allowd sections for Upstream Messaging
     * @return string[]
     */
    private static function upstream_messaging_selection()
    {
        return [
            'home_page_banner' => 'Home Page Banner',
            'shop' => 'Shop',
            'product' => 'Product',
            'footer' => 'Footer',
            'cart' => 'Cart',
            'checkout' => 'Checkout',
        ];
    }

    /**
     * Return count of Installments ranges
     * @param $data
     * @return array
     */
    private static function get_installment_fields($data)
    {

        $return_data = [];

        if (isset($data)) {

            if (!empty($data)) {
                foreach ($data as $key => $value) {
                    $return_data[] = [
                        'ic_from' => [
                            'title' => 'from',
                            'type' => 'number',
                            'class' => 'from',
                            'default' => '0',
                        ],
                        'ic_to' => [
                            'title' => 'to',
                            'type' => 'number',
                            'class' => 'to',
                            'default' => '1000',
                        ],
                        'ic_installment' => [
                            'title' => 'installment',
                            'type' => 'text',
                            'class' => 'installments',
                            'default' => '3, 4, 5',
                        ],
                        'ic_action' => [
                            'title' => '<a href="#" class="delete_instalment"><span class="dashicons dashicons-trash"></span></a>',
                            'css' => 'display:none;',
                        ],
                    ];
                }
            }
        }

        return $return_data;
    }

    public static function update_order_status_to_old($order) {
        $old_status = $order->get_meta('_old_status');

        $order->update_status($old_status);
    }
}
