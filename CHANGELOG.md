# Changelog

## 1.4.0 (2026-10-16)

Rewritten for Exponential 6 and PHP 8.0 to 8.5.

Added:
- A preview (dry run) of every change and a confirmation that is refused when the content changed after the preview.
- Subtree changes: every object with a location below a node, with an optional filter on the current owner;
  new policy `owner/subtree`.
- Command line script `bin/php/expownerchange.php` with `--to-owner`, `--object`, `--node`, `--from-owner`,
  `--dry-run`, `--list` and `--batch-size`.
- Audit events `content.ezownerchange.change` and `content.ezownerchange.subtree`, labels in eng-US and ger-DE.
- Settings file `ownerchange.ini`: `WebObjectLimit`, `BatchSize`, `UpdateVersionCreator`, `PublishNewVersion`,
  `UpdateSearchIndex`, `ClearViewCache`.
- eng-US and ger-DE translations.
- Unit tests without a database (`phpunit.xml.dist`).
- Documentation: README.md and the guides in doc/.

Updated:
- The owner changes in place, in batches with one transaction each; caches cleared and the search index updated
  for each changed object. The new version per change of earlier releases is the setting `PublishNewVersion`.
- Each object changes only where the user may edit it; the new owner must be a user account of a
  `browse.ini [ChangeOwner] Class[]` class.
- Every change is a POST carrying the ezformtoken token; the address alone opens the content browser.
- The pages use the shared administration components (no CSS of their own) and work in every admin design, light
  and dark.
- The context menu entries show with the policy `owner/change` (they asked for a function `all` before).
- ezinfo.php, extension.xml and composer.json: name "Exponential Owner Change", version 1.4.0, GPL v2 or later,
  PHP 8.0 or later.

Removed:
- The admin2 context menu icon rules from owner.css (the file stays, empty).
- doc/readme.txt, doc/install.txt and doc/changelog.txt, replaced by README.md, the guides in doc/ and this file.

## 1.3.1 (2024-10-23)

- composer.json: vendor name fixed.

## 1.3.0 (2024-10-01)

- Internals refactored; the change also publishes a new version with the new owner as its creator.

## 1.2, 1.1

- Maintenance releases.

## 1.0

- Initial public release.
