
//{block name="backend/order/application"}
Ext.define('Buco.grid.column.mixin.PartDocument', {

    /**
     * Contains all snippets for the view component
     * @object
     */
    bucoSnippets: {
        columns: {
            bucoIsPartDocument: {
                partDoc: '{s name=column/bucoIsPartDocument/partDoc}Teildokument{/s}',
                fullDoc: '{s name=column/bucoIsPartDocument/fullDoc}(Voll)Dokument{/s}',
            }
        }
    },

    createPartDocumentColumn: function() {
        return {
            xtype: 'buco-iconcolumn',
            width: 28,
            items: [
                {
                    getClass: function(value, metadata, model) {
                        // value argument is always emtpy for action columns. Using model instead.
                        var iconCls =  model.get('bucoIsPartDocument') ? 'sprite-weather-moon-half' : 'sprite-weather-moon',
                            tooltip = model.get('bucoIsPartDocument') ?
                                me.bucoSnippets.columns.bucoIsPartDocument.partDoc :
                                me.bucoSnippets.columns.bucoIsPartDocument.fullDoc;

                        // Use flaw in ExtJS to inject other attributes to HTML tag.
                        return iconCls +  '" data-qtip="' + tooltip + '" style="cursor:unset !important;';
                    },
                }
            ],
        };
    }
});
//{$smarty.block.parent}
//{/block}
