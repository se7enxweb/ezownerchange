{* The "Change owner" entry of the content structure's context menu (admininterface.ini ContextMenuTemplateArray).
   Shown only to users with the owner/change policy; the link opens the preview of owner/change, which changes
   nothing until it is confirmed. *}
{if fetch( 'user', 'has_access_to', hash( 'module', 'owner', 'function', 'change' ) )}
<script type="text/javascript">
menuArray['ContextMenu']['elements']['menu-change-owner'] = [];
menuArray['ContextMenu']['elements']['menu-change-owner']['url'] = {"/owner/change/%objectID%/(node)/%nodeID%"|ezurl};
</script>
<hr/>
<a id="menu-change-owner" href="#" onmouseover="ezpopmenu_mouseOver( 'ContextMenu' );">{"Change owner"|i18n( 'extension/ezownerchange' )}</a>
{/if}
