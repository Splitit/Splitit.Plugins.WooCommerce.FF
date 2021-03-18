(function ($) {
    "use strict";
    $(document).ready(function ($) {
        var flexFieldsInstance = Splitit.FlexFields.setup({
            debug: true,
            useSandboxApi: true,
            culture: flex_fields_params.culture,
            publicToken: flex_fields_params.publicToken,
            container: "#splitit-card-data",
            fields: {
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
            paymentButton: {
                selector: "#splitit-btn-pay"
            }
        }).ready(function () {
            var splititFlexFields = this;
            flexFieldsInstance.show();

            splititFlexFields.updateDetails({
                billingAddress: {
                    AddressLine: "260 Madison Avenue.",
                    AddressLine2: "Appartment 1",
                    City: "New York",
                    State: "NY",
                    Country: "USA",
                    Zip: "10016"
                },
                consumerData: {
                    FullName: "John Smith",
                    Email: "JohnS@splitit.com",
                    PhoneNumber: "1-844-775-4848",
                    CultureName: "en-us"
                }
            });
        }).onSuccess(function (result) {
            // Respond here if everything goes well.
            alert("Payment was successful! Check console for result details.");
            console.log(result);
        }).onError(function (result) {
                $("#custom-error-box ul").children().remove();
                $("#custom-error-box").show();
                $(data).each((idx, el) => {
                    if (el.showError) {
                        var fields = el.fieldTypes.join(',');
                        $("#custom-error-box ul").append(`<li>[${el.code || 'client'}] Fields: ${fields}, Error: ${el.error}</li>`);
                    }
                });
            });


        $("html").on("change", 'input[name="payment_method"]', function () {
            $('body').trigger('update_checkout');
            if ($(this).val() == "splitit") {
                flexFieldsInstance.show();
            }
        });

    });
})(jQuery);

