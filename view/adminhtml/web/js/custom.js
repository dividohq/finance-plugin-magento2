require([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, modal) {
    'use strict';

    if($("#cancelModal").length > 0){

        /**
         * @param {String} url
         * @returns {Object}
         */
        function getForm(url) {
            return $('<form>', {
                'action': url,
                'method': 'POST'
            }).append($('<input>', {
                'name': 'form_key',
                'value': window.FORM_KEY,
                'type': 'hidden'
            }));
        }

        modal({
            'title': 'Cancel Order',
            'buttons': [{
                text: $.mage.__('Cancel Order and notify lender'),
                click: function () {
                        var url = $('#order-view-cancel-button').data('url');
                        var notifyInput = $('<input>', {
                            'name': 'pbd_notify',
                            'value': true,
                            'type': 'hidden'
                        });
                        var cancelForm = getForm(url).append(notifyInput);
                        if($("#pbdCancelReason").length > 0){
                            var reasonInput = $('<input>', {
                                'name': 'pbd_reason',
                                'value': $("#pbdCancelReason").val(),
                                'type': 'hidden'
                            });
                            cancelForm.append(reasonInput);
                        }
                        cancelForm.appendTo('body').trigger('submit');
                    }
                },
                {
                    text: $.mage.__('Cancel Order (without notifying lender)'),
                    click: function () {
                        getForm(url).appendTo('body').trigger('submit');
                    }
                },
                {
                    text: 'Back',
                    click: function() {
                        this.closeModal();
                    }
                }
            ]
        }, $("#cancelModal"));

        $('#order-view-cancel-button').on('click', function (e) {
            e.stopImmediatePropagation();
            
            $("#cancelModal").modal('openModal')
        });
    }
});