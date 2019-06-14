
//{block name="backend/order/model/receipt/fields"}
//{$smarty.block.parent}
{
    name: 'bucoIsPartDocument',
    type: Ext.data.Types.BOOL,
    //mapping: 'attribute.bucoIsPartDocument' // Mapping seems to be unsupported. Using convert as a workaround.
    convert: function(value, record) {
        return !!((record.raw.attribute || {}).bucoIsPartDocument);
    }
},
//{/block}