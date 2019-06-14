
//{block name="backend/order/controller/mail"}
//{$smarty.block.parent}
Ext.define('Shopware.apps.Order.controller.Mail.BucoPartDocuments', {
    override: 'Shopware.apps.Order.controller.Mail',

    /**
     * Method is called when the user selects another mail template with the combo box
     *
     * @param { Shopware.apps.Order.view.mail.Form } mailFormPanel
     * @param { Ext.form.field.ComboBox } comboBox
     * @param { string } newValue
     * @param { string } oldValue
     */
    onChangeMailTemplateComboBox: function (mailFormPanel, comboBox, newValue, oldValue) {
        var callback = function (clickedButton) {
            if (clickedButton !== 'yes') {
                // Temporarily suspending events to not toggle the "modified" attribute
                mailFormPanel.mailTemplateComboBox.suspendEvents();
                mailFormPanel.mailTemplateComboBox.setValue(oldValue);
                mailFormPanel.mailTemplateComboBox.resumeEvents();

                return;
            }

            mailFormPanel.setLoading(true);

            /** BEGIN MODIFIED */
            var attachmentSelectionModel = this.getAttachmentGrid().selModel;
            /** END MODIFIED */

            Ext.Ajax.request({
                url: '{url controller=order action=createMail}',
                method: 'POST',
                params: {
                    orderId: mailFormPanel.order.get('id'),
                    /** BEGIN MODIFIED */
                    bucoIsPartDocument: attachmentSelectionModel.getCount() === 1 ? attachmentSelectionModel.getSelection()[0].get('bucoIsPartDocument') : false,
                    /** END MODIFIED */
                    mailTemplateName: newValue
                },

                success: function (response) {
                    var decodedResponse = Ext.JSON.decode(response.responseText);
                    var mail = Ext.create('Shopware.apps.Order.model.Mail', decodedResponse.mail);
                    mailFormPanel.loadRecord(mail);
                },

                failure: function (response) {
                    Shopware.Notification.createGrowlMessage(
                        '{s name=document/attachemnt/error}Error{/s}',
                        response.status + '<br />' + response.statusText
                    );
                },

                callback: function (options, success, response) {
                    mailFormPanel.setLoading(false);
                },
                scope: this
            });
        };

        if (mailFormPanel.modified) {
            Ext.Msg.confirm(this.snippets.confirmation.title, this.snippets.confirmation.message, callback, this);
        } else {
            Ext.callback(callback, this, ['yes']);
        }
    },
});
//{/block}
