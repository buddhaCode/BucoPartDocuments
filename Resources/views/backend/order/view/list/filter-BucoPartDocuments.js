
//{namespace name=backend/order/main}
//{block name="backend/order/view/list/filter"}
//{$smarty.block.parent}
Ext.define('Shopware.apps.Order.view.list.Filter.BucoPartDocuments', {
    override: 'Shopware.apps.Order.view.list.Filter',
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

    createDocumentsGrid: function() {
        var me = this,
            container = me.callParent(arguments);

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

            container.items.each(function(item) {
                if (item.isXType('order-document-list')) {
                    item.headerCt.insert(1, docCol);
                }
            });
        }
        catch (e) {
            console.warn('[BucoPartDocuments] Couldn\'t add \'Partdocument\' column to order list > document grid in filter bar.');
        }

        return container;
    },
});
//{/block}