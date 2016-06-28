{extends file='parent:frontend/index/header.tpl'}

{block name="frontend_index_header_javascript" append}
<script type="text/javascript" src="{$ycTrackingScriptUrl}"></script>
{/block}

{block name="frontend_index_header_javascript_inline" append}
    var yc_trackid = '{$ycTrackingId}';
    var yc_tracklogout = '{$ycTrackLogout}';
    var yc_articleId = '{if $sArticle}{$sArticle.articleID}{/if}';
{/block}