
//{block name="backend/order/controller/detail"}
//{$smarty.block.parent}
Ext.define('Shopware.apps.Order.controller.Detail.BucoPartDocuments', {
    override: 'Shopware.apps.Order.controller.Detail',

    bucoOriginalDocumentType: null,

    /**
     * Trying to created a new document for an existing one will cause a confirmation dialog to pop up. We don't want
     * this. Actually, we are not overwriting it. We want to create another one of the same type. If this is allowed
     * for the desired type, we suppress the confirmation by nulling the document type ID we wanna create.
     *
     * The next decoration will restore the document type ID.
     *
     * @param [Ext.data.Model]          The record of the detail page (Shopware.apps.Order.model.Order)
     * @param [Ext.data.Model]          The configuration record of the document form (Shopware.apps.Order.model.Configuration)
     * @param [Ext.container.Container] me
     */
    onCreateDocument: function(order, config, panel) {
        var me = this,
            bucoProhhobitPartDocIds = {$bucoProhibitDocTypeIds|@json_encode nofilter},
            docType = !Number.isNaN(config.get('documentType')) ? config.get('documentType') : me.bucoOriginalDocumentType;

        try {
            if(config.get('bucoCreatePartDocument') && !bucoProhhobitPartDocIds.includes(docType)) {
                me.bucoOriginalDocumentType = docType;
                config.set('documentType', Number.NaN);
            }

            var selModel = panel.bucoPosSelectionModel.getSelection();
            var vals = selModel.map(x => x.getId());
            config.set('bucoPartDocPosIds', vals);
        }
        catch (e) {
            console.warn('[BucoPartDocuments] Couldn\'t inject \'selected positions\' parameter into document creation event handler.');
        }

        me.callParent(arguments);
    },

    /**
     * We just nulled the document type ID to suppress the confirmation message. Now the need the ID again. So, we're
     * restoring it.
     *
     * @param [Ext.data.Model]          The record of the detail page (Shopware.apps.Order.model.Order)
     * @param [Ext.data.Model]          The configuration record of the document form (Shopware.apps.Order.model.Configuration)
     * @param [Ext.container.Container] The panel
     */
    createDocument: function(order, config, panel) {
        var me = this;

        if(Number.isNaN(config.get('documentType')) && Number.isInteger(me.bucoOriginalDocumentType)) {
            config.set('documentType', me.bucoOriginalDocumentType);
            delete me.bucoOriginalDocumentType;
        }

        me.callParent(arguments);
    },

    /**
     * Event listener method which is fired when the user clicks the preview button
     * on the detail page in the document tab panel.
     *
     * @param [Ext.data.Model] order - The order record of the detail page
     * @param [Ext.data.Model] config - The configuration record
     * @param [Ext.container.Container] panel - The form panel
     */
    onDocumentPreview: function(order, config, panel) {
        var me = this;

        try {
            var selModel = panel.bucoPosSelectionModel.getSelection(),
                bucoPartDocPosIds = selModel ? selModel : [],
                bucoPartDocPosIdsParam = bucoPartDocPosIds.map(function(rec, idx) { return '&bucoPartDocPosIds[' + idx + ']=' + rec.getId(); }).join('');

            window.open('{url action="createDocument"}' + '' +
                '?orderId=' + order.get('id') +
                '&preview=1'+ '' +
                '&taxFree=' + config.get('taxFree') +
                '&invoiceNumber=' + config.get('invoiceNumber') +
                '&voucher=' + config.get('voucher') +
                '&displayDate=' + config.get('displayDate') +
                '&deliveryDate=' + config.get('deliveryDate') +
                '&docComment=' + encodeURIComponent(config.get('docComment')) +
                '&bucoCreatePartDocument=' + (config.get('bucoCreatePartDocument') ?  '1' : '0') +
                bucoPartDocPosIdsParam +
                '&temp=1' +
                '&documentType=' + config.get('documentType') )
        }
        catch (e) {
            console.warn('[BucoPartDocuments] Couldn\'t inject \'selected positions\' parameter into document preview.');
            me.callParent(arguments);
        }
    },
});
//{/block}
