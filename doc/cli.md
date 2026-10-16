# The command line script

`bin/php/expownerchange.php` changes the owner of one object, or of every object in a subtree, without the limit of
the administration (`WebObjectLimit`). It is the tool for bulk changes, for example when someone leaves and their
content goes to a colleague.

Run it from the root of the installation, as the user the web server runs as, so that the cache files it writes
belong to that user:

```bash
sudo -u <web server user> php extension/ezownerchange/bin/php/expownerchange.php --to-owner=<user id> ...
```

The command line is trusted like the other maintenance scripts: it changes every object the request reaches. The
per-object `content/edit` check of the administration does not apply, and the script needs no login.

## Options

| Option | Meaning |
|---|---|
| `--to-owner=ID` | Required. The content object id of the new owner. It must be a user account of a class in `browse.ini [ChangeOwner] Class[]` |
| `--object=ID` | Change the owner of this object only |
| `--node=ID` | Change the owner of every object with a location in the subtree of this node, the node's own object included. An object with several locations is changed once |
| `--from-owner=ID` | Only objects owned by this user now. Objects of other owners are not even listed |
| `--dry-run` | List what would change and write nothing |
| `--list` | Print one line per object: the outcome, the object id and name, the current owner |
| `--batch-size=N` | Objects per database transaction (default: `ownerchange.ini [OwnerChangeSettings] BatchSize`, 50) |
| `-s`, `--siteaccess` | The siteaccess whose settings apply (the standard option of every script) |
| `-h`, `--help` | The help below |

Give either `--object` or `--node`. With both, `--object` wins.

Outcomes in `--list`:

| Outcome | Meaning |
|---|---|
| `change` | The owner changes |
| `unchanged` | The new owner owns it already |

The exit code is 0 when everything worked (also for a dry run and when there is nothing to change) and 1 when the
request is invalid or an object could not be changed.

## Examples

Each example below was run against throwaway content, and the output is real. The users are #41350 (an editor)
and #41351 (the new owner). The test folder is node 40067 (object 41352), and the two articles below it are
objects 41353 and 41354.

### Help

```text
$ php extension/ezownerchange/bin/php/expownerchange.php --help
Usage: extension/ezownerchange/bin/php/expownerchange.php [OPTION]...
Changes the owner of one object or of every object in a subtree.
Use --dry-run first: it lists what would change and writes nothing.

General options:
  -h,--help             display this help and exit
  -q,--quiet            do not give any output except when errors occur
  -s,--siteaccess       selected siteaccess for operations,
                          if not specified default siteaccess is used
  -d,--debug...         display debug output at end of execution,
                          the following debug items can be controlled: 
                          all, accumulator, include, timing, error, warning, debug, notice or strict.
  -c,--colors           display output using ANSI colors (default)
  --no-colors           do not use ANSI coloring
  --logfiles            create log files
  --no-logfiles         do not create log files (default)
  -v,--verbose...       display more information, 
                          used multiple times will increase amount of information
  -r,--allow-root-user  Allows the script to be run by the root user

Options:
  --to-owner=VALUE    Content object id of the new owner (a user account)
  --object=VALUE      Change the owner of this object only
  --node=VALUE        Change the owner of every object in the subtree of this node (the node included)
  --from-owner=VALUE  Only objects owned by this user now
  --dry-run           List what would change, change nothing
  --list              Print one line per object
  --batch-size=VALUE  Objects per transaction (default: ownerchange.ini BatchSize)
```

### One object: preview, then change

```text
$ php extension/ezownerchange/bin/php/expownerchange.php --to-owner=41351 --object=41353 --dry-run
New owner: T529 throwaway target (#41351)
Object 41353: T529 article one
Objects: 1; change 1, unchanged 0, other owner 0, refused 0
Dry run: nothing changed.
```

```text
$ php extension/ezownerchange/bin/php/expownerchange.php --to-owner=41351 --object=41353
New owner: T529 throwaway target (#41351)
Object 41353: T529 article one
Objects: 1; change 1, unchanged 0, other owner 0, refused 0
  1 of 1
PASS Changed 1, failed 0, batches 1
```

### A subtree, listed

The article changed above already has the new owner:

```text
$ php extension/ezownerchange/bin/php/expownerchange.php --to-owner=41351 --node=40067 --dry-run --list
New owner: T529 throwaway target (#41351)
Subtree of node 40067: T529 owner change test
Objects: 3; change 2, unchanged 1, other owner 0, refused 0
  change    #41352 T529 owner change test (owner #41350 T529 throwaway editor)
  unchanged #41353 T529 article one (owner #41351 T529 throwaway target)
  change    #41354 T529 article two (owner #41350 T529 throwaway editor)
Dry run: nothing changed.
```

### Only the content of one user

`--from-owner` limits the subtree to the objects one user owns now:

```text
$ php extension/ezownerchange/bin/php/expownerchange.php --to-owner=41351 --node=40067 --from-owner=41350 --dry-run --list
New owner: T529 throwaway target (#41351)
Subtree of node 40067: T529 owner change test
Only objects owned by #41350
Objects: 2; change 2, unchanged 0, other owner 0, refused 0
  change    #41352 T529 owner change test (owner #41350 T529 throwaway editor)
  change    #41354 T529 article two (owner #41350 T529 throwaway editor)
Dry run: nothing changed.
```

The same change, carried out one object per transaction:

```text
$ php extension/ezownerchange/bin/php/expownerchange.php --to-owner=41351 --node=40067 --from-owner=41350 --batch-size=1
New owner: T529 throwaway target (#41351)
Subtree of node 40067: T529 owner change test
Only objects owned by #41350
Objects: 2; change 2, unchanged 0, other owner 0, refused 0
  1 of 2
  2 of 2
PASS Changed 2, failed 0, batches 2
```

### Running it again

Nothing is left to change, so nothing is written:

```text
$ php extension/ezownerchange/bin/php/expownerchange.php --to-owner=41351 --node=40067
New owner: T529 throwaway target (#41351)
Subtree of node 40067: T529 owner change test
Objects: 3; change 0, unchanged 3, other owner 0, refused 0
Nothing to change.
```

### Mistakes are refused

The new owner must be a user account. Object 41352 is a folder:

```text
$ php extension/ezownerchange/bin/php/expownerchange.php --to-owner=41352 --object=41353 --dry-run
FAIL The new owner is not a user account.
```

`--to-owner` is required:

```text
$ php extension/ezownerchange/bin/php/expownerchange.php --object=41353 --dry-run
FAIL Choose the new owner.
```

Both exit with code 1.

## What a run does

Each run follows the steps of the administration (see [usage.md](usage.md)). It writes in batches, one transaction
each. After each batch, it clears the view caches of the changed objects, hands them to the search engine and
records one audit event per object. A subtree run is recorded as a whole as well (see [audit.md](audit.md)). If a
batch fails, it is rolled back, the run stops, and the script ends with `FAIL` and exit code 1. The batches before
it stay changed, so run the same command again (with `--dry-run` first) to finish.
