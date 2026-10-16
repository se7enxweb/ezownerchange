<?php /* #?ini charset="utf-8"?

# The content browser that chooses the new owner. Class[] lists the classes of user accounts; add your own user
# classes here (a site.ini-style override in settings/override/browse.ini.append.php). Only objects of these
# classes that are user accounts can become owners, in the admin and on the command line.
[ChangeOwner]
SelectionType=single
ReturnType=ObjectID
StartNode=users
TopLevelNodes[]
TopLevelNodes[]=users
Class[]
Class[]=user

*/ ?>
