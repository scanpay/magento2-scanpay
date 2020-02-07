/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/action/select-payment-method'
    ],
    function (Component, customer, urlBuilder, fullScreenLoader, errorProcessor, selectPaymentMethod) {
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
                    'method': this.item.method,
                };
            },
            afterPlaceOrder: function () {
                this.selectPaymentMethod();
                fullScreenLoader.startLoader();
                var self = this;
                var err = {};
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '/scanpay/Payment/GetPaymentURL?isAjax=true', true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
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
                    var basemethod = 'scanpaypaymentmodule';
                    var method = self.item.method;
                    var query = '';
                    if (method.substr(0, basemethod.length) !== basemethod) {
                        throw 'invalid payment method';
                    }
                    var go = method.substr(basemethod.length);
                    if (go) {
                        go = go.substr(1);
                        query = '?go=' + go;
                    }
                    window.location = resObj.url + query;
                };
                xhr.onerror = function (e) {
                    fullScreenLoader.stopLoader();
                    err.message = 'Internal server error: Connection error';
                    return self.messageContainer.addErrorMessage(err);
                };
                xhr.send();
            }
        });
    }
);
