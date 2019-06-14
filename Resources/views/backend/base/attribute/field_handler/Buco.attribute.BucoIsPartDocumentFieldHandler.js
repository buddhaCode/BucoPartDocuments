// first line magic?!?
Ext.define('Buco.attribute.BucoIsPartDocumentFieldHandler', {
    extend: 'Shopware.attribute.BooleanFieldHandler',

    supports: function(attribute) {
        var me = this;
        var supports = me.callParent(arguments);

        return (supports && attribute.get('tableName') == 's_order_documents_attributes' && attribute.get('columnName') == 'buco_is_part_document');
    },

    create: function(field, attribute) {
        var me = this;
        field = me.callParent(arguments);

        field.readOnly = true;
        return field;
    }
});