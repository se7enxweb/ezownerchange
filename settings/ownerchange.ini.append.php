<?php /* #?ini charset="utf-8"?

[OwnerChangeSettings]
# The most objects one owner change in the administration may reach. A larger subtree is changed with
# php extension/ezownerchange/bin/php/expownerchange.php (no limit, batches, --dry-run).
WebObjectLimit=500

# Objects changed in one database transaction. A failure rolls back its batch and stops the run.
BatchSize=50

# enabled: also set the creator of each object's current version to the new owner (no new version).
UpdateVersionCreator=disabled

# enabled: the behavior of releases up to 1.3: each object gets a new published version with the new owner as
# its creator. Slower, runs the publish workflows and adds a version to the history of every object.
PublishNewVersion=disabled

# Hand each changed object to the search engine (owner_id is indexed, for example by ezfind).
UpdateSearchIndex=enabled

# Clear the view caches of each changed object.
ClearViewCache=enabled

*/ ?>
