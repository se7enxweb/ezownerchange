{* The "Change owner" entry of the sub-items list's context menu (admininterface.ini SubitemsContextMenuTemplateArray).
   Shown only to users with the owner/change policy; the link opens the preview of owner/change. *}
{if fetch( 'user', 'has_access_to', hash( 'module', 'owner', 'function', 'change' ) )}
<script type="text/javascript">
menuArray['SubitemsContextMenu']['elements']['child-menu-change-owner'] = [];
menuArray['SubitemsContextMenu']['elements']['child-menu-change-owner']['url'] = {"/owner/change/%objectID%/(node)/%nodeID%"|ezurl};
</script>
<hr/>
<a id="child-menu-change-owner" href="#" onmouseover="ezpopmenu_mouseOver( 'SubitemsContextMenu' );">{"Change owner"|i18n( 'extension/ezownerchange' )}</a>
{/if}
