{extends file='parent:frontend/listing/box_article.tpl'}

{block name='frontend_listing_box_article_actions_buy_now' append}
    <input type="hidden" name="yc_articleId" value="{$sArticle.articleID}">
{/block}
