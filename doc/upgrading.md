# Upgrading from releases up to 1.3

Release 1.4.0 rewrites the extension for Exponential 6 and PHP 8. Everything you could rely on keeps working:
the module and policy names, the address of the view, the content browser settings and the context menu
templates.

## What stays the same

| Item | Status |
|---|---|
| Module `owner`, view `owner/change/<object id>` | Unchanged; `(group)/<node>` still sets where the content browser starts |
| Policy `owner/change` | Unchanged; roles that have it, or `owner/*`, keep working |
| `browse.ini [ChangeOwner]` | Unchanged; your `Class[]` overrides apply, and now also decide who may become an owner |
| `popupmenu/ownercontextmenu.tpl`, `popupmenu/ownersubitemscontextmenu.tpl` | Same names, same `admininterface.ini` entries |
| `content/browse_owner.tpl` | Same name, new text |
| `owner.css` | Still in the extension, now empty, so a site `design.ini` that names it finds the file |
| Your own "Change owner" buttons (the template snippet of the old readme) | Keep working: a POST or a link to `owner/change/<object id>` opens the content browser as before |

## What changes

| Before (1.3) | Now (1.4) |
|---|---|
| Choosing a user in the browser changed the owner at once | The browser leads to a preview. Nothing changes until you confirm |
| Each change published a new version with the new owner as creator | Only the owner changes, in place. Set `PublishNewVersion=enabled` in `ownerchange.ini` to keep the old behavior, or `UpdateVersionCreator=enabled` to set the current version's creator without a new version |
| Any object id could be changed by anyone with the policy | Each object changes only where the user may edit it (`content/edit`) |
| Anything the browser offered could become the owner | Only user accounts of the `browse.ini [ChangeOwner] Class[]` classes |
| One object | One object or a subtree (new policy `owner/subtree`), with an owner filter |
| No search index update | The search index gets each changed object |
| No record | Audit events `content.ezownerchange.change` and `.subtree` |
| No command line | `bin/php/expownerchange.php` with `--dry-run` |
| The menu entry needed `owner/*` (it asked for a function `all` that does not exist) | The menu entry shows with `owner/change` |
| English only, with the texts in kernel translation contexts | eng-US and ger-DE in the context `extension/ezownerchange` |

## Migration steps

1. Update the extension (`composer require se7enxweb/ezownerchange:~1.4.0`, or pull the git checkout).
2. Regenerate the autoloads (`php bin/php/ezpgenerateautoloads.php -e`): the extension has classes now.
3. Clear the caches (`php bin/php/ezcache.php --clear-all`), and restart Exponential Velocity if you run it.
4. Give `owner/subtree` to the roles that should change whole subtrees (see [permissions.md](permissions.md)).
5. If your editors relied on the new version per change (for example to trigger a publish workflow), set
   `PublishNewVersion=enabled` (see [configuration.md](configuration.md)).
6. If your site translated the old texts in `design/admin/content/browse_owner`, `design/standard/content/view`
   or `design/admin/popupmenu` ("Change owner"), the extension's own context `extension/ezownerchange` replaces
   them.

## Version numbers

1.4.0 follows 1.3.1. The about page (`ezinfo/about`) shows "Exponential Owner Change 1.4.0".
