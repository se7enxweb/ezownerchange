{* The "Change owner" entry of the sub-items list's context menu (admininterface.ini SubitemsContextMenuTemplateArray).
   Shown only to users with the owner/change policy; the link opens the preview of owner/change.
   ezownerchange_menu.js adds the entry to menuArray from its data attributes, so the menu needs no inline script
   (Content Security Policy). *}
{if fetch( 'user', 'has_access_to', hash( 'module', 'owner', 'function', 'change' ) )}
<hr/>
<a id="child-menu-change-owner" href="#" data-ezownerchange-menu="SubitemsContextMenu" data-ezownerchange-url="{'/owner/change/%objectID%/(node)/%nodeID%'|ezurl( 'no' )|wash}">{"Change owner"|i18n( 'extension/ezownerchange' )}</a>
{ezscript( array( 'ezownerchange_menu.js' ) )}
{/if}
