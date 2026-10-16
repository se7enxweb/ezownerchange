# What the audit records

Exponential 6.0.15 and later keep an audit: one JSON line per event in a file per channel and day (see the kernel's
audit guide). ezownerchange adds two event names in its own branch, registered in `settings/audit.ini.append.php`:

| Name | Label (eng-US / ger-DE) | Severity | Default | When |
|---|---|---|---|---|
| `content.ezownerchange.change` | Owner change / Besitzerwechsel | notice | on | One object's owner changed |
| `content.ezownerchange.subtree` | Owner change of a subtree / Besitzerwechsel eines Teilbaums | notice | on | A subtree change as a whole. It is the parent of the change events of its objects |

Both are written to the `content` channel. A dry run (the preview, `--dry-run`) writes nothing.

## The fields

`content.ezownerchange.change`:

| Field | Holds |
|---|---|
| `actor` | Who changed it: the signed-in user in the administration, or the command line, with the script's command and operating system user |
| `object` | The object: id, name, class, main node |
| `before.owner` | The previous owner's object id |
| `after.owner` | The new owner's object id |
| `after.via` | `admin` or `cli` |
| `parent` | The subtree event, when the object changed as part of a subtree |

`content.ezownerchange.subtree`:

| Field | Holds |
|---|---|
| `object` | The root node of the subtree |
| `target` | The new owner |
| `after.owner`, `after.from_owner` | The new owner, and the owner filter (`null` when there is none) |
| `after.planned`, `after.changed`, `after.failed` | Objects the confirmed plan changes, changed, and failed |
| `result`, `reason` | `failed` and `rolled_back` when a batch was rolled back |

## Real records

The two records below come from a command line run against throwaway test content (`--to-owner=41351 --node=40067`) and are shown as
the audit wrote them. The `request` block (host, process, timing) and the hash chain fields are left out:

```json
{
    "actor": {
        "cli": {
            "command": "extension/ezownerchange/bin/php/expownerchange.php --to-owner=41351 --node=40067",
            "os_user": "alpha"
        },
        "login": "anonymous",
        "roles": [1],
        "user_id": 10
    },
    "after": { "owner": 41351, "via": "cli" },
    "before": { "owner": 41350 },
    "channel": "content",
    "depth": 1,
    "id": "01M51KWSJYP9Q6PAXTFHPR8W2Z",
    "name": "content.ezownerchange.change",
    "object": { "class": "article", "id": 41354, "main_node": 40069, "name": "T529 article two", "type": "object" },
    "parent": "01M51KWSF65B0N03V21G33KNT7",
    "result": "success",
    "severity": "notice",
    "time": "2026-10-16T05:45:25.342Z",
    "verb": "change"
}
```

```json
{
    "after": { "changed": 3, "failed": 0, "from_owner": null, "owner": 41351, "planned": 3, "via": "cli" },
    "channel": "content",
    "depth": 0,
    "id": "01M51KWSF65B0N03V21G33KNT7",
    "name": "content.ezownerchange.subtree",
    "object": { "class": "folder", "id": 40067, "name": "T529 owner change test", "object_id": 41352, "parent": 2,
                "path": "/1/2/40067/", "type": "node" }
}
```


## Refusals

A user without `owner/change` is refused by the kernel, which records `access.permission.refused` with
`after.policy` = `owner/change`. A user without `owner/subtree` who asks for a subtree change is recorded as
`access.permission.refused` for `owner/subtree`.

## Reading them

In the administration, open **Audit** and filter by name `content.ezownerchange.*`, or open an object's audit
tab. Switch the events off in `settings/override/audit.ini.append.php`:

```ini
[AuditEventSettings]
Disabled[]=content.ezownerchange.*
```
