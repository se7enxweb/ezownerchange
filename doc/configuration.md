# Configuration

Every setting below ships in the extension's `settings/` directory. Override a setting in
`settings/override/<file>.ini.append.php` (or in a siteaccess) rather than editing the extension's files.

## ownerchange.ini

```ini
[OwnerChangeSettings]
WebObjectLimit=500
BatchSize=50
UpdateVersionCreator=disabled
PublishNewVersion=disabled
UpdateSearchIndex=enabled
ClearViewCache=enabled
```

| Setting | Default | Meaning |
|---|---|---|
| `WebObjectLimit` | `500` | The most objects one change in the administration may reach. A bigger subtree is refused there ("Use the command line script"); the command line has no limit |
| `BatchSize` | `50` | Objects changed in one database transaction. A failure rolls back its batch and stops the run. The command line can override it with `--batch-size` |
| `UpdateVersionCreator` | `disabled` | `enabled`: also sets the creator of each object's current version to the new owner, without a new version |
| `PublishNewVersion` | `disabled` | `enabled`: the behavior of releases up to 1.3. Each changed object gets a new version with the new owner as its creator, published through the kernel's publish operation. It is slower, runs the publish workflows, adds a version to every object's history, and makes `UpdateVersionCreator` unnecessary. The preview says when it is on |
| `UpdateSearchIndex` | `enabled` | Hands each changed object to the search engine, because the owner is indexed. It follows `site.ini [SearchSettings] DelayedIndexing` |
| `ClearViewCache` | `enabled` | Clears the view caches of each changed object (`eZContentCacheManager::clearContentCacheIfNeeded()`) |

Values are `enabled` or `disabled`; `true`, `yes`, `on` and `1` count as enabled.

## browse.ini

The content browser that chooses the new owner. The same class list decides who may become an owner, both in the
administration and on the command line:

```ini
[ChangeOwner]
SelectionType=single
ReturnType=ObjectID
StartNode=users
TopLevelNodes[]
TopLevelNodes[]=users
Class[]
Class[]=user
```

| Setting | Default | Meaning |
|---|---|---|
| `SelectionType` | `single` | One owner. Keep it |
| `ReturnType` | `ObjectID` | The browser returns the user's object id. Keep it |
| `StartNode` | `users` | Where the browser opens. A link can start elsewhere with `owner/change/<object>/(group)/<node>` |
| `TopLevelNodes[]` | `users` | The trees the browser offers |
| `Class[]` | `user` | The classes of user accounts. Add every class your site uses for users. An object of another class, or one without a user account, is refused as the new owner |

Example for a site with a second user class:

```ini
# settings/override/browse.ini.append.php
[ChangeOwner]
Class[]
Class[]=user
Class[]=member
```

## admininterface.ini

```ini
[AdditionalMenuSettings]
ContextMenuTemplateArray[]=popupmenu/ownercontextmenu.tpl
SubitemsContextMenuTemplateArray[]=popupmenu/ownersubitemscontextmenu.tpl
```

These add **Change owner** to the context menus of the content structure and of the sub-items list. Remove the
lines in an override to hide the entries. The tool itself stays reachable for users with the policy.

## audit.ini

```ini
[AuditEventSettings]
Branches[ezownerchange]=expOwnerChangeAuditBranch
```

This registers the extension's audit events. Both events are on by default. Switch them off like any other audit
name, for example `Disabled[]=content.ezownerchange.*` (see [audit.md](audit.md)).

## module.ini, design.ini, site.ini

The rest is wiring, and needs no change:

- `module.ini`: `ExtensionRepositories[]=ezownerchange`, `ModuleList[]=owner`
- `design.ini`: `DesignExtensions[]=ezownerchange`
- `site.ini`: `[RegionalSettings] TranslationExtensions[]=ezownerchange` (the eng-US and ger-DE translations)
