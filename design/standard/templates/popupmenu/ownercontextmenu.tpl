{* The "Change owner" entry of the content structure's context menu (admininterface.ini ContextMenuTemplateArray).
   Shown only to users with the owner/change policy; the link opens the preview of owner/change, which changes
   nothing until it is confirmed. ezownerchange_menu.js adds the entry to menuArray from its data attributes, so the
   menu needs no inline script (Content Security Policy). *}
{if fetch( 'user', 'has_access_to', hash( 'module', 'owner', 'function', 'change' ) )}
<hr/>
<a id="menu-change-owner" href="#" data-ezownerchange-menu="ContextMenu" data-ezownerchange-url="{'/owner/change/%objectID%/(node)/%nodeID%'|ezurl( 'no' )|wash}">{"Change owner"|i18n( 'extension/ezownerchange' )}</a>
{ezscript( array( 'ezownerchange_menu.js' ) )}
{/if}
