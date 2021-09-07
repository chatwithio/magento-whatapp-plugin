define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    "Magento_Ui/js/modal/modal",
    'uiRegistry',
    'mage/translate',
], function($, alert, modal, uiRegistry) {
    'use strict';
    return function (config) {
        var _modal = $("#whatsapp-modal"),
            apiUrl = "https://api.whatsapp.com/send?phone=$phone&text=$text",
            dataForm = _modal.find("form"),
            messages = config.messages;

        var options = {
            type: 'popup',
            responsive: true,
            buttons: [{
                text: $.mage.__('Send'),
                class: 'primary',
                click: function () {
                    sendMessage();
                }
            },{
                text: $.mage.__('Cancel'),
                class: '',
                click: function () {
                    this.closeModal();
                }
            }]
        };
        var popup = modal(options, _modal);

        function sendMessage(){
            if (dataForm.valid()) {

                $('body').trigger('processStart');

                $.ajax({
                    url: config.url,
                    data: dataForm.serialize(),
                    method: 'POST',
                    dataType: 'json'
                })
                .done(function (response) {
                   _modal.modal('closeModal');

                   window.open(apiUrl.replace('$phone',response.phone).replace('$text',response.message), '_blank').focus();

                   var sales_order_grid = uiRegistry.get('index = sales_order_grid');
                   if(sales_order_grid){
                        sales_order_grid.source.reload({
                            refresh: true
                        });
                   }else{
                        window.location.reload();
                   }

                   
                })
                .fail(function (response) {
                    alert({
                        content: response.message
                    });
                })
                .always(function () {
                    $('body').trigger('processStop');
                });
            }
        }

        
        $(document).on("click", "button.whatsapp", function(e){
            e.preventDefault();
            var message = messages[$(this).data('status')] || null;
            dataForm.find('input[name="phone"]').val($(this).data('phone'));
            dataForm.find('input[name="order_id"]').val($(this).data('id'));
            dataForm.find('textarea[name="message"]').val(message);
            _modal.modal('openModal');
        });
    };
});
