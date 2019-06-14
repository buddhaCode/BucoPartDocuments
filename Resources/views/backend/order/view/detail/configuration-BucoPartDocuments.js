
//{namespace name=backend/order/main}
//{block name="backend/order/view/detail/configuration"}
//{$smarty.block.parent}
Ext.define('Shopware.apps.Order.view.detail.Configuration.BucoPartDocuments', {
    override: 'Shopware.apps.Order.view.detail.Configuration',

    bucoSnippets: {
        articleNumber: '{s namespace=backend/order/main name=column/article_number}Article number{/s}',
        articleName: '{s namespace=backend/order/main name=column/article_name}Article name{/s}',
        quantity: '{s namespace=backend/order/main name=column/quantity}Quantity{/s}',
        price: '{s namespace=backend/order/main name=column/price}Price{/s}',
        total: '{s namespace=backend/order/main name=column/total}Total{/s}',
    },

    initComponent: function() {
        var me = this;

        me.callParent(arguments);
        me.add(me.bucoCreatePositionGrid());
    },

    createRightItems: function () {
        var me = this;

        var items = me.callParent(arguments);

        try {
            var partDoc = Ext.create('Ext.form.Checkbox', Ext.apply({
                fieldLabel: '{s name=configuration/bucoSelectPositions}Select positions{/s}',
                name: 'bucoCreatePartDocument',
                uncheckedValue: 0,
                inputValue: 1,
                handler: function(checkbox, checked) {
                    me.bucoPositionsGrid.setDisabled(!checked);
                }
            }, me.formDefaults));

            items.add(partDoc);
        }
        catch (e) {
            console.warn('[BucoPartDocuments] Couldn\'t add \'Select positions\' checkbox to order detail > document > configuration form.');
        }

        return items;
    },

    bucoCreatePositionGrid: function () {
        var me = this;

        me.bucoPosSelectionModel = Ext.create('Ext.selection.CheckboxModel', {
            checkOnly: true,
            mode: 'MULTI'
        });

        me.bucoPositionsGrid = Ext.create('Ext.grid.Panel', {
            columnWidth: 1,
            title: '{s namespace=backend/order/main name=position/window_title}Positions{/s}',
            store: me.record.getPositions(),
            selModel: me.bucoPosSelectionModel,
            disabled: true,
            columns: [
                {
                    header: me.bucoSnippets.articleNumber,
                    dataIndex: 'articleNumber',
                    flex: 1,
                },
                {
                    header: me.bucoSnippets.articleName,
                    dataIndex: 'articleName',
                    flex: 2,
                },
                {
                    header: me.bucoSnippets.quantity,
                    dataIndex: 'quantity',
                    flex: 1,
                },
                {
                    header: me.bucoSnippets.price,
                    dataIndex: 'price',
                    flex: 1,
                    renderer: me.bucoCurrencyColumn,
                },
                {
                    header: me.bucoSnippets.total,
                    dataIndex: 'total',
                    flex: 1,
                    renderer: me.bucoCurrencyColumn
                },
            ],
        });

        return me.bucoPositionsGrid;
    },

    bucoCurrencyColumn: function (value) {
        if ( value === Ext.undefined ) {
            return value;
        }
        return Ext.util.Format.currency(value);
    },
});
//{/block}