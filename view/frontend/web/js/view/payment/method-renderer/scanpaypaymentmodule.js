/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/storage',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/action/select-payment-method'
    ],
    function (Component, quote, additionalValidators, storage, customer, urlBuilder, fullScreenLoader, errorProcessor, selectPaymentMethod) {
        'use strict';

        return Component.extend({
            redirectAfterPlaceOrder: false,
            
            defaults: {
                template: 'Scanpay_PaymentModule/payment/form'
            },

            getCode: function () {
                return 'scanpaypaymentmodule';
            },
            getData: function () {
                return {
                    'method': this.item.method
                };
            },
            /*
            selectPaymentMethod: function() {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            },
            afterPlaceOrder: function () {
                //window.location.replace(url.build('mymodule/standard/redirect/'));
            },
            // Returns send check to info
            getMailingAddress: function() {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },*/
            placeOrder: function (data, event) {
                if (!this.validate()) {
                    alert('invalid');
                    fullScreenLoader.stopLoader();
                    return false;
                }
                this.selectPaymentMethod();
                if (event) {
                    event.preventDefault();
                }
                /* Code inspired by https://github.com/magento/magento2/blob/develop/app/code/Magento/Checkout/view/frontend/web/js/action/place-order.js */
                var serviceUrl;
                var payload;
                var paymentData = quote.paymentMethod();
                var self = this;
                console.log('check');
                /** Checkout for guest and registered customer. */
                if (!customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/guest-carts/:quoteId/payment-information', {
                        quoteId: quote.getQuoteId()
                    });
                    payload = {
                        cartId: quote.getQuoteId(),
                        email: quote.guestEmail,
                        paymentMethod: paymentData,
                        billingAddress: quote.billingAddress()
                    };
                } else {
                    serviceUrl = urlBuilder.createUrl('/carts/mine/payment-information', {});
                    payload = {
                        cartId: quote.getQuoteId(),
                        paymentMethod: paymentData,
                        billingAddress: quote.billingAddress()
                    };
                }
                fullScreenLoader.startLoader();
                return storage.post(
                    serviceUrl, JSON.stringify(payload)
                ).done(function (orderid) {
                    var formData = new FormData();
                    formData.append('orderid', orderid);
                    alert(orderid);

                    var err = {};
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', urlBuilder.createUrl('/scanpay/index/getpaymenturl', {}), true);
                    xhr.onload = function(e) {
                        fullScreenLoader.stopLoader();
                        if (this.status !== 200) {
                            err.message = 'Internal server error: Non-200 response code';
                            return self.messageContainer.addErrorMessage(err);
                        }
                        var resObj;
                        try {
                            resObj = JSON.parse(this.response);
                        } catch (thrownerr) {
                            err.message = 'Internal server error: Unable to parse json';
                            return self.messageContainer.addErrorMessage(err);
                        }
                        if (resObj.error) {
                            err.message = resObj.error;
                            return self.messageContainer.addErrorMessage(err);
                        }
                        window.location = resObj.url;
                    };
                    xhr.onerror = function (e) {
                        fullScreenLoader.stopLoader();
                        err.message = 'Internal server error: Connection error';
                        return self.messageContainer.addErrorMessage(err);
                    };
                    xhr.send(null);
                }).fail(function (response) {
                    fullScreenLoader.stopLoader();
                    errorProcessor.process(response, self.messageContainer);
                });
                /*if (!customer.isLoggedIn()) {
                    document.querySelector(loginFormSelector).validation();
                    emailValidationResult = Boolean(document.querySelector(loginFormSelector + ' input[name=username]').valid());
                }*/
                    /*
                if (emailValidationResult && this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

                    $.when(placeOrder).fail(function () {
                     self.isPlaceOrderActionAllowed(true);
                    }).done(this.afterPlaceOrder.bind(this));
                    return true;
                }*/
                    
                    /*
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', '/path/to/image.png', true);
                    xhr.responseType = 'blob';
                    xhr.onload = function(e) {
                      if (this.status == 200) {
                        // Note: .response instead of .responseText
                        var blob = new Blob([this.response], {type: 'image/png'});
                      }
                    };
                    */
            }
        });
    }
);