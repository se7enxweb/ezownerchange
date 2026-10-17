/*
  ezownerchange: the "Change owner" entries of the context menus without inline code, so the administration runs
  under a Content Security Policy without 'unsafe-inline'. Printed with ezscript after each entry; it may be printed
  more than once on a page and sets itself up once. Works with the old and the new ezpopupmenu.js.

    <a id="menu-change-owner" data-ezownerchange-menu="ContextMenu" data-ezownerchange-url="/owner/change/%objectID%/(node)/%nodeID%">
      the entry is added to menuArray[menu]['elements'][id] (the URL with the menu's values put in by ezpopupmenu.js),
      and pointing at it calls ezpopmenu_mouseOver( menu ), as the inline script and the onmouseover attribute did.
*/
( function()
{
    function register()
    {
        if ( !document.querySelectorAll )
            return;
        var items = document.querySelectorAll( 'a[data-ezownerchange-menu]' );
        for ( var i = 0; i < items.length; i++ )
        {
            var item = items[i], menuID = item.getAttribute( 'data-ezownerchange-menu' );
            if ( item.getAttribute( 'data-ezownerchange-added' ) || !item.id || window.menuArray === undefined )
                continue;
            var menu = window.menuArray[menuID];
            // the menu itself is set up by the kernel; wait for it
            if ( !menu || typeof menu !== 'object' )
                continue;
            if ( !menu['elements'] )
                menu['elements'] = {};
            menu['elements'][item.id] = { 'url': item.getAttribute( 'data-ezownerchange-url' ) };
            item.setAttribute( 'data-ezownerchange-added', '1' );
        }
    }

    register();

    if ( window.ezOwnerChangeMenuListening || !document.addEventListener )
        return;
    window.ezOwnerChangeMenuListening = true;

    if ( document.readyState === 'loading' )
        document.addEventListener( 'DOMContentLoaded', register );

    document.addEventListener( 'mouseover', function( e )
    {
        for ( var el = e.target; el && el.nodeType === 1; el = el.parentNode )
        {
            if ( el.tagName === 'A' && el.hasAttribute( 'data-ezownerchange-menu' ) )
            {
                if ( typeof window.ezpopmenu_mouseOver === 'function' )
                    window.ezpopmenu_mouseOver( el.getAttribute( 'data-ezownerchange-menu' ) );
                return;
            }
        }
    } );
} )();
