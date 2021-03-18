(function ($) {
    'use strict';

    $('html')
        .on('click', '#add_instalment', function (e) {

            e.preventDefault();

            var rowCount = $('#ic_container tr').length;

            var count = rowCount - 2;

            var html = '<tr class="ic_tr" id="ic_tr_' + count + '">                        <td class="forminp">\n' +
                '                <fieldset>\n' +
                '                    <legend class="screen-reader-text"><span>from</span></legend>\n' +
                '                    <input class="input-text regular-input from" type="number" name="woocommerce_splitit_ic_from[]" id="woocommerce_splitit_ic_from_' + count + '" style="" value="" placeholder="">\n' +
                '                                    </fieldset>\n' +
                '            </td>\n' +
                '                                                <td class="forminp">\n' +
                '                <fieldset>\n' +
                '                    <legend class="screen-reader-text"><span>to</span></legend>\n' +
                '                    <input class="input-text regular-input to" type="number" name="woocommerce_splitit_ic_to[]" id="woocommerce_splitit_ic_to_' + count + '" style="" value="" placeholder="">\n' +
                '                                    </fieldset>\n' +
                '            </td>\n' +
                '                                                <td class="forminp">\n' +
                '                <fieldset>\n' +
                '                    <legend class="screen-reader-text"><span>installment</span></legend>\n' +
                '                    <input class="input-text regular-input installments" type="text" name="woocommerce_splitit_ic_installment[]" id="woocommerce_splitit_ic_installment_' + count + '" style="" value="" placeholder="">\n' +
                '                                    </fieldset>\n' +
                '            </td>\n' +
                '                                                <th scope="row" class="titledesc">\n' +
                '                <label for="woocommerce_splitit_ic_action"><a href="#" class="delete_instalment"><span class="dashicons dashicons-trash"></span></a></label>\n' +
                '            </th>\n' +
                '                        </tr>';

            $('#ic_container tbody tr:last')
                .before(html);
        });

    // Find and remove selected table rows
    $('html')
        .on('click', '.delete_instalment', function (e) {

            e.preventDefault();

            $(this)
                .closest('tr')
                .remove();
        });


    $('html')
        .on('click', '#checkApiCredentials', function (e) {
            e.preventDefault();
            var $this = $(this);
            $('body')
                .append('<div class="loading">Loading&#8230;</div>');
            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'check_api_credentials'
                },
                success: function (response) {
                    $('body')
                        .find('.loading')
                        .remove();
                    $this.closest('tr')
                        .find('td')
                        .append('<div class="response">' + response + '</div>');
                    setTimeout(function () {
                        $this.closest('tr')
                            .find('td')
                            .find('.response')
                            .remove();
                    }, 5000);
                },
                error: function (error) {
                    $('body')
                        .find('.loading')
                        .remove();
                    $this.closest('tr')
                        .find('td')
                        .append('<div class="error">' + error.statusText + '</div>');
                    setTimeout(function () {
                        $this.closest('tr')
                            .find('td')
                            .find('.error')
                            .remove();
                    }, 5000);
                }
            });

        });

    $.validator.addMethod(
        'regex',
        function (value, element, regexp) {
            var re = new RegExp(regexp);
            return this.optional(element) || re.test(value);
        },
        'Please check your input.'
    );

    jQuery.validator.addMethod('overlapping', function (value, element) {

        var from = [],
            to = [],
            r = true,
            from_to = [];

        jQuery('#main_ic_container tr.ic_tr')
            .each(function (key, value) {
                from[key] = parseFloat(jQuery(this)
                    .find('.from')
                    .val());
                to[key] = parseFloat(jQuery(this)
                    .find('.to')
                    .val());
                from_to[key] = [];
                from_to[key].push(from[key]);
                from_to[key].push(to[key]);
            });

        let result = true;
        $.each(from, function (index, value) {

            if (index <= (to.length - 1) && index <= (from.length - 1)) {
                let from_item = from[index];
                //let from_item2 = parseFloat(from[index + 1]) ?? false;
                let to_item = to[index];
                //let to_item2 = parseFloat(to[index + 1]) ?? false;

                result = from_item < to_item ? true : false;

                // if (from_item2 && to_item2) {
                //     result = (from_item2 > to_item) && (to_item2 >= from_item2) ? true : false;
                // }

                $.each(from_to, function (i, v) {
                    if(i != index) {
                        if ((v[0] <= from_item && from_item <= v[1]) || (v[0] <= to_item && to_item <= v[1])) {
                            result = false;
                        }
                    }
                });

                if (!result) {
                    r = false;
                }
            }
        });
        return r;
    }, 'From and to can not overlapping');

    jQuery.validator.addMethod('only_integer', function (value, element) {

       var array = value.split(',');

       var r = true;

        $.each( array, function( k, v ) {
            if(!Math.floor(v) == v || !$.isNumeric(v) || v <= 0) {
                r = false;
            }
        });

        return r;
    },  'No. of installments should contain only bigger than zero and integer values');

    $('html')
        .on('submit', 'form#mainform', function (event) {

            var form = $('form#mainform');
            var options = {
                rules: {
                    'woocommerce_splitit_ic_from[]': {
                        required: true,
                        min: 0,
                        overlapping: true
                    },
                    'woocommerce_splitit_ic_to[]': {
                        required: true,
                        min: 0,
                        overlapping: true
                    },
                    'woocommerce_splitit_ic_installment[]': { required: true, only_integer: true },
                    'woocommerce_splitit_splitit_api_key': { pattern: /^(.{8})-(.{4})-(.{4})-(.{4})-(.{12})$/ },

                },
                messages: {
                    'woocommerce_splitit_ic_from[]': {
                        required: 'From can not be empty',
                        min: 'Min number is 0'
                    },
                    'woocommerce_splitit_ic_to[]': {
                        required: 'From can not be empty',
                        min: 'Min number is 0'
                    },
                    'woocommerce_splitit_ic_installment[]': { required: 'From can not be empty' },
                    'woocommerce_splitit_splitit_api_key': { pattern: 'API Key need to match pattern - XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX' },
                },
                errorClass: 'error_class',
                validClass: 'valid_class',
                highlight: function (element, errorClass, validClass) {
                    $(element)
                        .addClass(errorClass)
                        .removeClass(validClass);
                },
                unhighlight: function (element, errorClass, validClass) {
                    $(element)
                        .removeClass(errorClass)
                        .addClass(validClass);
                },
                focusInvalid: false,
                invalidHandler: function (formInvalidHandler, validator) {

                    if (!validator.numberOfInvalids()) {
                        return;
                    }

                    $('html, body')
                        .animate({
                            scrollTop: $(validator.errorList[0].element)
                                .offset().top - 200
                        }, 1000);

                },
                // errorPlacement: function(error, element) {
                //  return true;
                // },
                errorElement: 'span',
            };

            form.validate(options);

            if (form.valid()) {
                return true;
            }

            jQuery(this)
                .find('.doctv_from')
                .css('border', '1px solid red');

            return false;

        });

})(jQuery);
