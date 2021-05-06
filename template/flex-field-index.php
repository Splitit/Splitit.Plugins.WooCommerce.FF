<div class="splitit-design-classic" id="splitit-card-data" style="display:none;">
    <div class="splitit-cc-group">
        <div id="splitit-card-holder-full-name"></div>
        <div id="splitit-card-number"></div>
        <div id="splitit-expiration-date"></div>
        <div id="splitit-cvv"></div>
        <div class="splitit-cc-group-separator"></div>
    </div>

    <div id="splitit-installment-picker"></div>
    <div id="splitit-error-box"></div>
    <div id="splitit-terms-conditions"></div>
</div>
<script type="application/javascript">
    var overlay = '.nv-content-wrap';
    var a = '.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table, #order_review, .woocommerce, .nv-content-wrap, .entry-content, #payment, form, .blockUI, .entry-content, form#order_review, .woocommerce-order-pay, .nv-content-wrap';
    var checkoutHasErrors = false;
    localStorage.setItem('flex_fields_success', 'false');
    (function ($) {
        "use strict";
        $(document).ready(function ($) {
            //Start spinner
            var a = '.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table, #order_review';
            $(a).block({
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: .6
                }
            });


            if(flexFieldsInstance === undefined) {

                flexFieldsInstance = Splitit.FlexFields.setup({
                    debug: "<debug>",
                    useSandboxApi: "<useSandboxApi>",
                    culture: "<culture>",
                    container: "#splitit-card-data",
                    fields: {
                        cardholderName: {
                            selector: '#splitit-card-holder-full-name'
                        },
                        number: {
                            selector: "#splitit-card-number"
                        },
                        cvv: {
                            selector: "#splitit-cvv"
                        },
                        expirationDate: {
                            selector: "#splitit-expiration-date"
                        }
                    },
                    installmentPicker: {
                        selector: "#splitit-installment-picker"
                    },
                    termsConditions: {
                        selector: "#splitit-terms-conditions"
                    },
                    errorBox: {
                        selector: "#splitit-error-box"
                    },
                    loader: ''
                }).ready(function () {
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'flex_field_initiate_method',
                            order_id: "<order_id>",
                            numberOfInstallments: '',
                        },
                        success: function (data) {

                            if (typeof data == 'undefined' || typeof data.publicToken == 'undefined') {
                                if (typeof reportExternalError != 'undefined') {
                                    console.log('Public token is not defined');
                                } else {
                                    console.log('Public Token is not defined');
                                }

                                if ($('#payment_method_splitit:checked').val() == "splitit") {
                                    jQuery('.woocommerce-error').remove();

                                    if (jQuery('form[name="checkout"]').length) {
                                        jQuery('form[name="checkout"]').prepend('<ul class="woocommerce-error">' + data.error.message + '</ul>');
                                    } else {
                                        jQuery('#order_review').prepend('<ul class="woocommerce-error">' + data.error.message + '</ul>');
                                    }
                                }

                                if (!$('#custom_splitit_error').length) {
                                    $('.payment_box.payment_method_splitit').prepend('<p id="custom_splitit_error" style="color:red;">' + data.error.message + '</p>');
                                }
                                $(a).unblock();//Stop spinner
                                jQuery(overlay).unblock();//Stop spinner
                                removeLoader();
                            } else {
                                flexFieldsInstance.setPublicToken(data.publicToken);
                                localStorage.setItem('ipn', data.installmentPlan.InstallmentPlanNumber);
                                flexFieldsInstance.show();
                                $(a).unblock();//Stop spinner
                                jQuery(overlay).unblock();//Stop spinner
                            }
                        },
                        error: function (error) {
                            $(a).unblock();
                            jQuery(overlay).unblock();//Stop spinner
                            removeLoader();
                        }
                    });
                }).onSuccess(function (result) {
                    var instNum = flexFieldsInstance.getState().planNumber;
                    var numOfInstallments = flexFieldsInstance.getState().selectedNumInstallments;

                    //add data to hidden input
                    //jQuery('input[name="flex_field_ipn"]').val(instNum);
                    //jQuery('input[name="flex_field_num_of_inst"]').val(numOfInstallments);
                    jQuery('html').find('input[name="flex_field_ipn"]').val(instNum);
                    jQuery('html').find('input[name="flex_field_num_of_inst"]').val(numOfInstallments);

                    //Set item in local storage for inform about flex fields success
                    localStorage.setItem('flex_fields_success', 'true');

                    //Submit checkout
                    jQuery('form[name="checkout"]').submit();

                    //Or submit pay order
                    jQuery("form#order_review").submit();
                }).onError(function (result) {
                    localStorage.setItem('flex_fields_success', 'false');
                    $("#splitit-btn-pay").removeAttr('disabled');
                    jQuery(overlay).unblock();//Stop spinner
                    removeLoader();
                });
            }
        });
    })(jQuery);

    jQuery('form[name="checkout"]').on('checkout_place_order', function () {
        //Check that payment method is splitit
        if (jQuery('input[name="payment_method"]:checked').val() == "splitit") {
            //Check if flex fields has errors
            if (!flexFieldsInstance.getState().validationStatus.isValid) {
                jQuery(overlay).unblock();//Stop spinner
                return false;
            }

            //Check that flex fields end with success
            var flex_fields_success = localStorage.getItem('flex_fields_success');

            if (flex_fields_success == 'true') {
                return true;
            }

            return false;
        }
    });

    jQuery(".woocommerce-checkout-review-order-table :input").change(function() {
        jQuery(a).block({
            message: null,
            overlayCSS: {
                background: "#fff",
                opacity: .6
            }
        });

        var val = jQuery( this ).val();
        if (val) {
            isRefreshPage = true;
        }

        jQuery('body').trigger( 'update_checkout' );
    });

    var isRefreshPage = false;
    jQuery('form.checkout').on('change','input[name^="shipping_method"]',function() {

        jQuery(a).block({
            message: null,
            overlayCSS: {
                background: "#fff",
                opacity: .6
            }
        });

        var val = jQuery( this ).val();
        if (val) {
            isRefreshPage = true;
        }
    });

    jQuery('body').on('updated_checkout', function(){
        if (isRefreshPage) {
            var planNumber = flexFieldsInstance.getState().planNumber;

            jQuery.ajax({
                url: ajaxurl,
                data: {
                    'action': 'flex_field_initiate_method',
                    'ipn': planNumber,
                    'order_id': "<order_id>",
                    'numberOfInstallments': '',
                },
                method: "POST",
                dataType: 'json',
                success: function (data) {
                    jQuery('.woocommerce-error').remove();
                    jQuery('#custom_splitit_error').remove();
                        if(typeof data.error != 'undefined') {
                            flexFieldsInstance.hide();
                            if (!jQuery('#custom_splitit_error').length) {
                                jQuery('.payment_box.payment_method_splitit').prepend('<p id="custom_splitit_error" style="color:red;">' + data.error.message + '</p>');
                            }

                        } else {

                            if(jQuery.type( planNumber ) === "null") {
                                jQuery('#custom_splitit_error').remove();
                                flexFieldsInstance.setPublicToken(data.publicToken);
                                localStorage.setItem('ipn', data.installmentPlan.InstallmentPlanNumber);
                                flexFieldsInstance.show();
                            }
                        }

                        flexFieldsInstance.synchronizePlan();

                    jQuery(a).unblock();//Stop spinner
                    jQuery(overlay).unblock();//Stop spinner
                }
            });

        }
    });

    jQuery('form.checkout').on('change','input[name^="payment_method"]',function() {
        jQuery('.woocommerce-error').remove();
    });

    function performPayment(sender) {
        if (jQuery('input[name="payment_method"]:checked').val() != "splitit") {
            return;
        }

        jQuery(overlay).block({
            message: null,
            overlayCSS: {
                background: "#fff",
                opacity: .6
            }
        });

        flexFieldsInstance.updateDetails({
            billingAddress: {
                AddressLine: jQuery('input[name="billing_address_1"]').val(),
                AddressLine2: jQuery('input[name="billing_address_2"]').val(),
                City: jQuery('input[name="billing_city"]').val(),
                State: jQuery('select[name="billing_state"]').val(),
                Country: jQuery('select[name="billing_country"]').val(),
                Zip: jQuery('input[name="billing_postcode"]').val()
            },
            consumerData: {
                FullName: jQuery('input[name="billing_first_name"]').val() + ' ' + jQuery('input[name="billing_last_name"]').val(),
                Email: jQuery('input[name="billing_email"]').val(),
                PhoneNumber: jQuery('input[name="billing_phone"]').val(),
                CultureName: "<culture>"
            }
        }, function() {

            var result = {};
            jQuery.each(jQuery('form.checkout').serializeArray(), function () {
                result[this.name] = this.value;
            });

            jQuery.ajax({
                url: ajaxurl,
                method: 'POST',
                dataType: 'json',
                async: false,
                data: {
                    action: 'checkout_validate',
                    fields: result,
                    ipn: localStorage.getItem('ipn')
                },
                success: function (data) {
                    if (data.result == 'success') {
                        jQuery('.woocommerce-error').remove();

                        jQuery(sender).attr('disabled', true);

                        flexFieldsInstance.checkout();
                    } else {
                        var $form = jQuery('form.woocommerce-checkout');

                        jQuery('.woocommerce-error').remove();


                        if (data.messages) {
                            $form.prepend('<ul class="woocommerce-error">' + data.messages + '</ul>');
                        } else {
                            $form.prepend('<ul class="woocommerce-error">' + data + '</ul>');
                        }

                        $form.find('.input-text, select').blur();

                        jQuery('html, body').animate({
                            scrollTop: (jQuery('form.woocommerce-checkout').offset().top - 100)
                        }, 1000);

                        jQuery('#place_order').attr('disabled', false);
                        jQuery(overlay).unblock();//Stop spinner
                    }
                },
                error: function (error) {
                    jQuery('html, body').animate({
                        scrollTop: (jQuery('form.woocommerce-checkout').offset().top - 100)
                    }, 1000);
                    jQuery(overlay).unblock();//Stop spinner
                }
            });

        });

    }


    //Order pay
    localStorage.setItem('order_pay', 'false');

    window.removeLoader = function () {
        setTimeout(() =>  jQuery('#order_review').unblock(), 1000);
    };

    jQuery("form#order_review").submit(function (e) {
        if (jQuery('#payment_method_splitit').is(':checked')) {
            var order_pay = localStorage.getItem('order_pay');

            if (order_pay == 'false') {
                e.preventDefault();
                jQuery(this).remove('#flex_field_hidden_checkout_field');
                jQuery(this).append('<div id="flex_field_hidden_checkout_field"><input type="hidden" class="input-hidden" name="flex_field_ipn" id="flex_field_ipn" value=""> <input type="hidden" class="input-hidden" name="flex_field_num_of_inst" id="flex_field_num_of_inst" value=""> </div>');
                if (!flexFieldsInstance.getState().validationStatus.isValid) {
                    localStorage.setItem('order_pay', 'false');
                    removeLoader();
                } else {
                    localStorage.setItem('order_pay', 'true');
                }

                var result = {};
                jQuery.each(jQuery(this).serializeArray(), function () {
                    result[this.name] = this.value;
                });

                jQuery.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    dataType: 'json',
                    async: false,
                    data: {
                        action: 'order_pay_validate',
                        fields: result,
                        no_add_order_data_to_db: true
                    },
                    success: function (data) {
                        if (data.result == 'success') {
                            jQuery('.woocommerce-error').remove();

                            removeLoader();

                            var order_pay = localStorage.getItem('order_pay');

                            if (order_pay == 'true') {
                                flexFieldsInstance.checkout();
                            } else {
                                localStorage.setItem('order_pay', 'false');
                            }
                        } else {

                            localStorage.setItem('order_pay', 'false');

                            var $form = jQuery('form#order_review');

                            jQuery('.woocommerce-error').remove();


                            if (data.messages) {
                                $form.prepend('<ul class="woocommerce-error">' + data.messages + '</ul>');
                            } else {
                                $form.prepend('<ul class="woocommerce-error">' + data + '</ul>');
                            }

                            jQuery('html, body').animate({
                                scrollTop: (jQuery('form#order_review').offset().top - 100)
                            }, 1000);

                            jQuery('#place_order').attr('disabled', false);

                            removeLoader();
                        }
                    },
                    error: function (error) {
                        jQuery('html, body').animate({
                            scrollTop: (jQuery('form#order_review').offset().top - 100)
                        }, 1000);
                        removeLoader();
                    }
                });


            }
        }
    });


</script>
