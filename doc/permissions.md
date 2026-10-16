# Permissions

## The policies

The module is called `owner` (the name it has had since the first release). It has two functions:

| Policy | Allows |
|---|---|
| `owner/change` | Opening the tool and changing the owner of single objects. Every view needs it, and the context menu entry appears only with it |
| `owner/subtree` | In addition, changing the owner of every object in a subtree (the scope "This object and everything below it") |

`owner/*` (all functions) grants both. Neither function has limitations; the content policies limit what changes.

## The content policies still apply

For each object, the owner changes only where the user may also **edit** the object: `content/edit` with all its
limitations (class, section, owner, subtree, language and the rest). Objects the user may not edit show as
"Refused: you may not edit them" in the preview and keep their owner. The starting object also needs
`content/read`.

So an editor whose `content/edit` is limited to the subtree "News" can change owners only below "News", even with
`owner/subtree`.

The command line script is trusted and does not check these policies (see [cli.md](cli.md)).

## Role setups

### Administrators

The Administrator role of a default installation has `*/*` (every module, every function) and needs nothing more.

A separate administrator role that should hand content over between users:

| Module | Function | Limitation |
|---|---|---|
| `owner` | `*` | none |
| `content` | `read` | none |
| `content` | `edit` | none |

### Editors who may change single objects

Add to the Editor role (or assign a small role of its own to the Editors group):

| Module | Function | Limitation |
|---|---|---|
| `owner` | `change` | none |

Editors then change the owner of objects they can edit, one object at a time.

### Section editors who may hand over a whole subtree

| Module | Function | Limitation |
|---|---|---|
| `owner` | `change` | none |
| `owner` | `subtree` | none |
| `content` | `edit` | Subtree: the section's root node |

Assign the role to the group. The `content/edit` subtree limitation keeps their owner changes inside their part of
the tree.

### Editors who must not change owners

Give them no `owner` policy. They get the access denied page (HTTP 403), and the audit records
`access.permission.refused` with the policy `owner/change`.

## Setting it up

In the administration, go to **Users > Roles and policies**, edit the role, click **New policy**, choose the
module **owner**, then the function (**change** or **subtree**, or all functions), and save the role.
Policies take effect at the next request of each user.
