
//{block name="backend/base/attribute/form"}
//{$smarty.block.parent}

//
//{include file="backend/base/attribute/field_handler/Buco.attribute.BucoIsPartDocumentFieldHandler.js"}
//

Ext.define('Buco.attribute.Form-BucoPartDocuments.js', {
    override: 'Shopware.attribute.Form',

    registerTypeHandlers: function() {
        var handlers = this.callParent(arguments);

        try {
            handlers = Ext.Array.insert(handlers, 0, [
                Ext.create('Buco.attribute.BucoIsPartDocumentFieldHandler'),
            ]);
        }
        catch (e) {
            console.warn('[BucoPartDocuments] Couldn\'t inject read-only attribute handler.');
        }

        return handlers;
    }
});
//{/block}