require([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, modal) {
    'use strict';

    modal({
        'title': 'Cancel Order'
    }, $("#cancelModal"));

    console.log("Heere");

    $(document).on('click', '#order-view-cancel-button', function (e) {
        e.stopImmediatePropagation();
        
        var url = $('#order-view-cancel-button').data('url');
        
        $("#cancelModal")
            .modal({
                'buttons': [{
                    text: 'Confirm',
                    click: function () {
                        getForm(url).appendTo('body').trigger('submit');
                    }
                },
                {
                    text: 'Cancel and notify lender',
                    click: function () {
                        var reasonInput = $('<input>', {
                            'name': 'pbd_reason',
                            'value': $("#pbdCancelReason").val(),
                            'type': 'hidden'
                        });
                        getForm(url).append(reasonInput).appendTo('body').trigger('submit');
                    }
                },
                {
                    text: 'Back',
                    click: () => {
                        this.closeModal();
                    }
                }]
            })
            .modal('openModal')
    });
});