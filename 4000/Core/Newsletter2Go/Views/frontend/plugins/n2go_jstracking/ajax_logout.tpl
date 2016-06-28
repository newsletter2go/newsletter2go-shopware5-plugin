{extends file='parent:frontend/account/ajax_logout.tpl'}

{block name='frontend_account_ajax_logout_box' append}
    <script type='text/javascript'>
        YcTracking.resetUser();
    </script>
{/block}