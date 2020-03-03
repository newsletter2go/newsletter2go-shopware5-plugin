{extends file='parent:frontend/checkout/finish.tpl'}

{block name="frontend_index_header_javascript" append}
    <script type="text/javascript" id="n2g_script">
        !function(e,t,n,c,r,a,i) { e.Newsletter2GoTrackingObject=r,e[r]=e[r]||function() { (e[r].q=e[r].q||[]).push(arguments) },
        e[r].l=1*new Date,a=t.createElement(n),i=t.getElementsByTagName(n)[0],a.async=1,
        a.src=c,i.parentNode.insertBefore(a,i) } (window,document,"script","//static.newsletter2go.com/utils.js","n2g");

        n2g('create', '{$companyId}');
        n2g('ecommerce:addTransaction', {
            'id': '{$sAddresses.billing.orderID}',
            'affiliation': '{$sShopname}',
            'revenue': '{$sAmount}',
            'shipping': '{$sShippingcosts}',
            'tax': '{$sAmountTax}'
        } );
        {foreach name=basket from=$sBasket.content item=sBasketItem key=key}
        {if $sBasketItem.articleID}
        n2g('ecommerce:addItem', {
            'id': '{$sAddresses.billing.orderID}',
            'name': '{$sBasketItem.articlename}',
            'sku': '{$sBasketItem.ordernumber}',
            'category': '{$helper->getArticleCategories($sBasketItem.articleID)}',
            'price': '{floatval(str_replace(',', '.', str_replace('.', '', $sBasketItem.price)))}',
            'quantity': '{$sBasketItem.quantity}'
        } );
        {/if}
        {/foreach}
        n2g('ecommerce:send');
    </script>
{/block}
