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
            defaults: {
                template: 'Scanpay_PaymentModule/payment/form'
            },

            initObservable: function () {
                return this;
            },

            getCode: function () {
                return 'scanpaypaymentmodule';
            },
            getData: function () {
                return {
                    'method': this.item.method
                };
            },
            placeOrder: function (data, event) {
                console.log(data);
                console.log(event);
                if (!this.validate()) {
                    alert('invalid');
                    fullScreenLoader.stopLoader();
                    return false;
                }
                this.selectPaymentMethod();
                /*if (event) {
                    event.preventDefault();
                }
                var self = this;
                var placeOrder;
                //var emailValidationResult = customer.isLoggedIn();
                
                var loginFormSelector = 'form[data-role=email-with-possible-login]';
                if (!this.validate() || !additionalValidators.validate()) {
                    return false;
                }*/
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
                console.log(serviceUrl);

                fullScreenLoader.startLoader();
                console.log('post');
                return storage.post(
                    serviceUrl, JSON.stringify(payload)
                ).done(function (response) {
                    console.log('done');
                    alert('yay');
                    console.log(response);/*
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', '/path/to/image.png', true);
                    xhr.onload = function(e) {
                        if (this.status == 200) {
                        // Note: .response instead of .responseText
                        var blob = new Blob([this.response], {type: 'image/png'});
                        }
                    };
                    req.send(null);*/
                }).fail(function (response) {
                    console.log('err');
                    errorProcessor.process(response, self.messageContainer);
                    fullScreenLoader.stopLoader();
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