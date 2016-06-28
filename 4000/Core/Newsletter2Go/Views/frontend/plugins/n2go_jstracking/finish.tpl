{extends file='parent:frontend/checkout/finish.tpl'}

{block name='frontend_index_content' append}
    <div id="yc-buy-items" style="display:none;">
    {foreach name=basket from=$sBasket.content item=sBasketItem key=key}
        {if $sBasketItem.articleID}
            <div class="yc-item-summary">
                <input type="hidden" name="ordernumber" value="{$sBasketItem.articleID}">
                <input type="hidden" name="quantity" value="{$sBasketItem.quantity}">
                <input type="hidden" name="price" value="{$sBasketItem.price}">
                <input type="hidden" name="currency" value="{$Shop->getCurrency()->getCurrency()}">
            </div>
        {/if}
    {/foreach}
    </div>
{/block}