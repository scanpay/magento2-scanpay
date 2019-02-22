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
        'mage/storage',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/action/select-payment-method'
    ],
    function (Component, quote, storage, customer, urlBuilder, fullScreenLoader, errorProcessor, selectPaymentMethod) {
        'use strict';

        return Component.extend({
            redirectAfterPlaceOrder: false,

            defaults: {
                template: 'Scanpay_PaymentModule/payment/form'
            },

            getCode: function () {
                return this.item.method;
            },
            getData: function () {
                return {
                    'method': this.item.method
                };
            },
            placeOrder: function (data, event) {
                var basemethod = 'scanpaypaymentmodule';
                var method = this.item.method;
                var query = '';
                if (method.substr(0, basemethod.length) !== basemethod) {
                    throw 'invalid payment method';
                }
                var go = method.substr(basemethod.length);
                if (go) {
                    go = go.substr(1);
                    query = '?go=' + go;
                }

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

                    var err = {};
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '/scanpay/Payment/GetPaymentURL?isAjax=true', true);
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
                        window.location = resObj.url + query;
                    };
                    xhr.onerror = function (e) {
                        fullScreenLoader.stopLoader();
                        err.message = 'Internal server error: Connection error';
                        return self.messageContainer.addErrorMessage(err);
                    };
                    xhr.send(formData);
                }).fail(function (response) {
                    fullScreenLoader.stopLoader();
                    errorProcessor.process(response, self.messageContainer);
                });
            }
        });
    }
);
