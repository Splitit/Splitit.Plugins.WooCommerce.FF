<?php

/**
 * Trait UpstreamMessagingTrait
 */
trait UpstreamMessagingTrait
{

    /**
     * Method for initiate Upstream Messaging
     */
    public function upstream_messaging_script()
    {
        if ($this->enabled == "yes") {
            ?>
            <script>
                (function (i, s, o, g, r, a, m) {
                    i['SplititObject'] = r;
                    i[r] = i[r] || function () {
                        (i[r].q = i[r].q || []).push(arguments);
                    }, i[r].l = 1 * new Date();
                    a = s.createElement(o),
                        m = s.getElementsByTagName(o)[0];
                    a.async = 1;
                    a.src = g;
                    m.parentNode.insertBefore(a, m);
                })(window, document, 'script', '//upstream.production.splitit.com/v1/dist/upstream-messaging.js?v=' + (Math.ceil(new Date().getTime() / 100000)), 'splitit');
                splitit('init', {
                    apiKey: '<?= $this->settings['splitit_api_key']?>',
                    lang: '<?= str_replace("_", "-", get_locale()); ?>',
                    currency: '<?= get_woocommerce_currency(); ?>',
                    currencySymbol: '<?= get_woocommerce_currency_symbol(get_woocommerce_currency()); ?>',
                    debug: false
                });
            </script>
            <?php
        }
    }

    /**
     * Method for insert custom styles for Upstream Messaging from settings page
     */
    public function upstream_messaging_custom_styles()
    {
        if ($this->enabled == "yes") {
            echo "<style>" . strip_tags($this->settings['splitit_upstream_messaging_css']) . "</style>";
        }

        echo "<style>
                .splitit_cart_page_banner img,
                .splitit_home_page_banner img,
                .splitit_shop_page_banner img,
                .splitit_checkout_page_banner img,
                .splitit_product_page_banner img
                {
                    display: inline-block;
                }
            </style>";
    }

    /**
     * Method for initiate function for footer credit card
     */
    public function init_footer_credit_cards()
    {
        if ($this->enabled == "yes") {
            add_action('wp_footer', [$this, 'footer_credit_cards']);
        }
    }

    /**
     * Method for output credit carts in footer
     */
    public function footer_credit_cards()
    {
        if ($this->enabled == "yes") {
            if (!empty($this->settings['splitit_footer_allowed_card_brands'])) {
                $credit_cards = implode(',', $this->settings['splitit_footer_allowed_card_brands']);

                if (!empty($credit_cards)) {
                    ?>
                    <fieldset data-splitit-placeholder='cards' data-splitit-style-banner-border="none"
                              data-splitit-cards="<?= $credit_cards; ?>" class="splitit_footer_cards_banner"></fieldset>
                    <style>
                        .splitit_footer_cards_banner {
                            margin: 15px;
                        }
                        .splitit_footer_cards_banner img{
                            display: inline-block;
                            width: 60px;
                            height: auto;
                        }

                        .splitit_footer_cards_banner legend ~ img{
                            margin-left: 10px;
                        }

                        .splitit_footer_cards_banner > img:last-child{
                            margin-right: 10px;
                        }
                    </style>
                    <?php
                }
            }
        }
    }

    /**
     * Method for initiate function for home page banner
     */
    public function init_home_page_banner()
    {
        if ($this->enabled == "yes") {
            add_action('wp_head', [$this, 'home_page_banner']);
        }
    }

    /**
     * Method for output home page banner
     */
    public function home_page_banner()
    {
        if ($this->enabled == "yes") {
            if (in_array('home_page_banner', $this->splitit_upstream_messaging_selection) && (is_home() || is_front_page())) {
                ?>
                <div class="splitit_home_page_wrapper">
                    <img class="splitit_home_page_banner" data-splitit="true" data-splitit-style-align="center"
                         data-splitit-style-banner-border="none" data-splitit-placeholder='banner'
                         data-splitit-banner='white:use-cc-pay-over-time' width='728'/>
                </div>
                <style>
                    .splitit_home_page_wrapper {
                        width: 100%;
                        display: flex;
                        justify-content: center;
                    }
                </style>
                <?php
            }
        }
    }

    /**
     * Method for initiate function for banner on shop page
     */
    public function init_shop_page()
    {
        if ($this->enabled == "yes") {
            add_action('woocommerce_before_shop_loop', [$this, 'shop_page']);
        }
    }

    /**
     * Method for output banner on shop page
     */
    public function shop_page()
    {
        if ($this->enabled == "yes") {
            if (in_array('shop', $this->splitit_upstream_messaging_selection) && is_shop()) {
                ?>
                     <div class="splitit_shop_page_wrapper">
                <img class="splitit_shop_page_banner" data-splitit-placeholder='banner'
                     data-splitit-banner='white:use-cc-pay-over-time' data-splitit-style-banner-border="none"
                     width='728'/>
                     </div>

                <style>
                    .splitit_shop_page_wrapper {
                        width: 100%;
                        display: flex;
                        justify-content: center;
                    }
                </style>
                <?php
            }
        }
    }

    /**
     * Method for initiate function for price break on the product page
     */
    public function init_product_page()
    {
        if ($this->enabled == "yes") {
            add_action('woocommerce_single_product_summary', [$this, 'product_page']);
        }
    }

    /**
     * Method for output price break on the product page
     */
    public function product_page()
    {
        if ($this->enabled == "yes") {
            if (in_array('product', $this->splitit_upstream_messaging_selection) && is_product()) {
                //$price = wc_get_product()->get_price();
                $price = wc_get_price_to_display(wc_get_product(), array('array' => wc_get_product()->get_price()));
                $installments = $this->get_installment_by_price($price);
                if (isset($installments)) {
                    ?>
                    <div
                            class="splitit_product_page_banner"
                            data-splitit='true' ;
                            data-splitit-style-banner-border="none" ;
                            data-splitit-amount="<?= $price ?>" ;
                            data-splitit-type='banner-top' ;
                            data-splitit-style-align='right' ;
                            data-splitit-num-installments="<?= $installments ?>">
                    </div>
                    <script>
                        jQuery(document)
                            .on('found_variation', function (event, value) {
                                jQuery.ajax({
                                    type: 'POST',
                                    url: ajaxurl,
                                    dataType: 'json',
                                    data: {
                                        'price': value.display_price,
                                        'action': 'calculate_new_installment_price_product_page',
                                    },
                                    success: function (response) {
                                        jQuery('.splitit_product_page_banner')
                                            .attr('data-splitit-amount', response.price);
                                        jQuery('.splitit_product_page_banner')
                                            .attr('data-splitit-num-installments', response.installments);
                                        splitit.ui.refresh(true);
                                    },
                                });
                            });
                    </script>
                    <?php
                }
            }
        }
    }

    /**
     * Method for initiate function for price break on the cart page
     */
    public function init_cart_page()
    {
        if ($this->enabled == "yes") {
            add_action('woocommerce_before_cart_totals', [$this, 'cart_page']);
        }
    }

    /**
     * Method for output price break on the cart page
     */
    public function cart_page()
    {
        if ($this->enabled == "yes") {
            if (in_array('cart', $this->splitit_upstream_messaging_selection) && is_cart()) {
                $price = WC()->cart->cart_contents_total;
                $installments = $this->get_installment_by_price($price);
                if (isset($installments)) {
                    ?>
                    <div
                            class="splitit_cart_page_banner"
                            data-splitit='true' ;
                            data-splitit-amount="<?= $price ?>" ;
                            data-splitit-style-banner-border="none" ;
                            data-splitit-type='banner-top' ;
                            data-splitit-style-align='right' ;
                            data-splitit-num-installments="<?= $installments ?>">
                    </div>
                    <script>
                        jQuery(document.body)
                            .on('updated_cart_totals', function () {
                                jQuery.ajax({
                                    type: 'POST',
                                    url: ajaxurl,
                                    dataType: 'json',
                                    data: {
                                        'action': 'calculate_new_installment_price_cart_page',
                                    },
                                    success: function (response) {
                                        jQuery('.splitit_cart_page_banner')
                                            .attr('data-splitit-amount', response.price);
                                        jQuery('.splitit_cart_page_banner')
                                            .attr('data-splitit-num-installments', response.installments);
                                        splitit.ui.refresh(true);
                                    },
                                });
                            });
                    </script>
                    <?php
                }
            }
        }
    }

    /**
     * Method for initiate function for banner on the checkout page
     */
    public function init_checkout_page()
    {
        if ($this->enabled == "yes") {
            add_action('woocommerce_before_checkout_form', [$this, 'checkout_page']);
        }
    }

    /**
     * Method for output banner on the checkout page
     */
    public function checkout_page()
    {
        if ($this->enabled == "yes") {
            if (in_array('checkout', $this->splitit_upstream_messaging_selection) && is_checkout()) {
                ?>
                <img class="splitit_checkout_page_banner" data-splitit-placeholder="banner"
                     data-splitit-banner="white:use-cc-pay-over-time" data-splitit-style-banner-border="none"
                     width="728"/>
                <?php
            }
        }
    }

    /**
     * Method for update price and installments for price break on the product page
     */
    public function calculate_new_installment_price_product_page()
    {
        if ($this->enabled == "yes") {
            if (in_array('product', $this->splitit_upstream_messaging_selection)) {
                $price = $_POST['price'];
                $installments = $this->get_installment_by_price($price);
                echo json_encode([
                    'price' => $price,
                    'installments' => $installments,
                ]);
                wp_die();
            }
        }
    }

    /**
     * Method for update price and installments for price break on the cart page
     */
    public function calculate_new_installment_price_cart_page()
    {
        if ($this->enabled == "yes") {
            if (in_array('cart', $this->splitit_upstream_messaging_selection) && is_cart()) {
                $price = WC()->cart->cart_contents_total;
                $installments = $this->get_installment_by_price($price);
                echo json_encode([
                    'price' => $price,
                    'installments' => $installments,
                ]);
                wp_die();
            }
        }
    }

//        public function calculate_new_installment_price_checkout()
//        {
//                //$price = WC()->cart->cart_contents_total;
//                $price = $this->get_order_total();
//                $installments = $this->get_installment_by_price($price);
//                echo json_encode([
//                    'installments' => $installments,
//                ]);
//                wp_die();
//
//        }

    /**
     * Method for getting last installments in range by price
     */
    public function get_installment_by_price($price)
    {
        if ($this->enabled == "yes") {
            $key = $this->get_installment_ic_to_by_price($this->splitit_inst_conf['ic_to'], $price, $this->splitit_inst_conf['ic_from']);

            if (isset($this->splitit_inst_conf['ic_installment'])) {
                if (array_key_exists($key, $this->splitit_inst_conf['ic_installment'])) {
                    $installment = $this->splitit_inst_conf['ic_installment'][$key];

                    $explode = explode(',', $installment);
                    return end($explode);
                }
            }

            return self::DEFAULT_INSTALMENT_PLAN;
        }
    }

    /**
     * Method for getting array of installments by price
     */
    public function get_array_of_installments($price)
    {
        if ($this->enabled == "yes") {
            $key = $this->get_installment_ic_to_by_price($this->splitit_inst_conf['ic_to'], $price, $this->splitit_inst_conf['ic_from']);

            if (isset($this->splitit_inst_conf['ic_installment'])) {
                if (array_key_exists($key, $this->splitit_inst_conf['ic_installment'])) {
                    $installment = $this->splitit_inst_conf['ic_installment'][$key];

                    return explode(',', $installment);
                }
            }

            return [];
        }
    }

    /**
     * Method for getting array of installments by price
     */
    public function check_if_price_in_range($price)
    {
        if ($this->enabled == "yes") {
            $key = $this->get_installment_ic_to_by_price_for_range($this->splitit_inst_conf['ic_to'], $price, $this->splitit_inst_conf['ic_from']);

            if (isset($this->splitit_inst_conf['ic_installment'])) {
                if (array_key_exists($key, $this->splitit_inst_conf['ic_installment'])) {
                    $installment = $this->splitit_inst_conf['ic_installment'][$key];

                    return explode(',', $installment);
                }
            }

            return [];
        }
    }

    /**
     * Method for getting installment range key
     */
    public function get_installment_ic_to_by_price($installments, $price_product, $installments_from)
    {
        if ($this->enabled == "yes") {
            if (isset($installments) && isset($price_product) && isset($installments_from)) {
                $orig_installments = $installments;
                $orig_installments_from = $installments_from;
                sort($installments);
                sort($installments_from);
                $numItems = count($installments);
                $i = 0;
                $last_key = '';
                $last_price = '';
                foreach ($installments as $key => $price) {
                    if (++$i === $numItems) {
                        $last_key = $key;
                        $last_price = $price;
                    }
                    if ($price_product <= $price) {
                        if ($key == 0) {
                            if (isset($installments_from[$key]) && $installments_from[$key] > $price_product) {
                                return -1;
                            }
                        }
                        return array_search($price, $orig_installments);
                    }
                }

                if ($price_product > $last_price) {
                    return array_search($last_price, $orig_installments);
                }
            } else {
                return -1;
            }
        }
    }

    /**
     * Method for getting installment range key
     */
    public function get_installment_ic_to_by_price_for_range($installments, $price_product, $installments_from)
    {
        if ($this->enabled == "yes") {
            if (isset($installments) && isset($price_product) && isset($installments_from)) {
                $orig_installments = $installments;
                $orig_installments_from = $installments_from;
                sort($installments);
                sort($installments_from);
                $numItems = count($installments);
                $i = 0;
                $last_key = '';
                $last_price = '';
                foreach ($installments as $key => $price) {
                    if (++$i === $numItems) {
                        $last_key = $key;
                        $last_price = $price;
                    }
                    if ($price_product <= $price) {
                        if ($key == 0) {
                            if (isset($installments_from[$key]) && $installments_from[$key] > $price_product) {
                                return -1;
                            }
                        }
                        return array_search($price, $orig_installments);
                    }
                }

                if ($price_product > $last_price) {
                    return -1;
                }
            } else {
                return -1;
            }
        }
    }

}
