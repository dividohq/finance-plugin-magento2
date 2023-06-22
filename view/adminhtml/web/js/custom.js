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
                    var reasonInput = $('<input>', {
                            'name': 'pbd_reason',
                            'value': $("#pbdCancelReason").val(),
                            'type': 'hidden'
                        });
                        getForm(url).append(reasonInput).appendTo('body').trigger('submit');
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

        $(document).on('click', '#order-view-cancel-button', function (e) {
            e.stopImmediatePropagation();
            
            $("#cancelModal").modal('openModal')
        });
    }
});