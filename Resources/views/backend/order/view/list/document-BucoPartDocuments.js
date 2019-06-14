
//{namespace name=backend/order/main}
//{block name="backend/order/view/list/document"}
//{$smarty.block.parent}
Ext.define('Shopware.apps.Order.view.list.Document.BucoPartDocuments', {
    override: 'Shopware.apps.Order.view.list.Document',
    // mixins: ['Buco.grid.column.mixin.PartDocument'],

    /**
     * Contains all snippets for the view component
     * @object
     */
    bucoSnippets: {
        columns: {
            bucoIsPartDocument: {
                partDoc: '{s name=column/bucoIsPartDocument/partDoc}Part document{/s}',
                fullDoc: '{s name=column/bucoIsPartDocument/fullDoc}(Full) document{/s}',
            }
        }
    },

    getColumns: function() {
        var me = this,
            items = me.callParent(arguments);

        try {
            var docCol = {
                xtype: 'buco-iconcolumn',
                width: 28,
                items: [
                    {
                        getClass: function(value, metadata, model) {
                            // value argument is always emtpy for action columns. Using model instead.
                            var iconCls =  model.get('bucoIsPartDocument') ? 'sprite-weather-moon-half' : 'sprite-weather-moon';
                            var tooltip = model.get('bucoIsPartDocument') ?
                                me.bucoSnippets.columns.bucoIsPartDocument.partDoc :
                                me.bucoSnippets.columns.bucoIsPartDocument.fullDoc;

                            // Use flaw in ExtJS to inject other attributes to HTML tag.
                            return iconCls +  '" data-qtip="' + tooltip + '" style="cursor:unset !important;';
                        },
                    }
                ],
            };
        }
        catch (e) {
            console.warn('[BucoPartDocuments] Couldn\'t add \'Partdocument\' column to order detail > document grid.');
        }

        return Ext.Array.insert(items, 1, [docCol]);
    },
});
//{/block}