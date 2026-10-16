# Troubleshooting

## "Change owner" is not in the context menu

- The user needs the `owner/change` policy (see [permissions.md](permissions.md)). Policies apply from the user's
  next request.
- The extension must be active: `ActiveExtensions[]=ezownerchange` in `settings/override/site.ini.append.php`.
- The entries come from `admininterface.ini [AdditionalMenuSettings]`. An override that empties
  `ContextMenuTemplateArray[]` removes them.
- The menu is cached with the page. Clear the caches (`php bin/php/ezcache.php --clear-all`).

## The page says "The new owner is not a user account"

The chosen object has no user account, or its class is not in `browse.ini [ChangeOwner] Class[]`. Add your user
class to that list (see [configuration.md](configuration.md)).

## Every object shows "Refused: you may not edit them"

The owner changes only where you may edit the object. Check your role's `content/edit` policy and its limitations.
An `Owner` limitation ("self") lets you edit only what you own already.

## "The subtree holds more objects than one request may change"

The subtree reaches more than `WebObjectLimit` objects (500 by default). Use the command line script, which has no
limit (see [cli.md](cli.md)), or raise `WebObjectLimit` in `settings/override/ownerchange.ini.append.php`.

## "The content changed after the preview"

Something changed between the preview and the confirmation: another owner, a new or removed object in the subtree,
or a permission. Nothing was written. Check the new preview and confirm again.

## "N objects could not be changed; nothing of their batch was written"

A database error stopped the run. The failed batch was rolled back and the batches before it stayed changed. The
error is in `var/log/error.log` ("Owner change failed, batch rolled back"). Fix the cause and run the change again:
the preview now shows the objects that are left.

## The site still shows the old owner

- With `ClearViewCache=disabled`, view caches keep the old owner until they expire. Clear the content cache.
- With `site.ini [SearchSettings] DelayedIndexing=enabled`, the search index is updated by the indexing cronjob,
  not at once.
- If the site runs on Exponential Velocity, clear its response cache (`./console exp:velocity cache clear`).

## "Class not found" errors after an update

Regenerate the autoloads (`php bin/php/ezpgenerateautoloads.php -e`), clear the caches, and restart Exponential
Velocity if you run it: its workers keep the classes they loaded when they started.

## The command line script says "Running scripts as root may be dangerous"

Run it as the web server's user, not as root, so that the cache files it writes stay writable for the site. Only
add `--allow-root-user` if you know the files it touches.

## The audit has no ezownerchange events

The audit needs Exponential 6.0.15 or later and `audit.ini [AuditSettings]` switched on. Check that
`audit.ini [AuditEventSettings] Branches[ezownerchange]` is set (it ships in the extension) and that no
`Disabled[]` line switches `content.ezownerchange.*` off.
