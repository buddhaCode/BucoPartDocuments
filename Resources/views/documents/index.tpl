{extends file="parent:documents/index.tpl"}

{block name="document_index_table_each"}
    {if isset($position.BucoPartDocumentsDummy)}
        {* Left empty by intention. *}
    {else}
        {$smarty.block.parent}
    {/if}
{/block}