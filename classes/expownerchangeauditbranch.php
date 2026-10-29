<?php
/**
 * ezownerchange's branch of the audit taxonomy, registered in settings/audit.ini.append.php
 * ([AuditEventSettings] Branches[ezownerchange]). The kernel's catalogue has no name for an owner change, so the
 * subject starts with the extension's name, as the taxonomy asks.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ezownerchange
 */

class expOwnerChangeAuditBranch implements expAuditTaxonomyBranch
{
    const CHANGE = 'content.ezownerchange.change';
    const SUBTREE = 'content.ezownerchange.subtree';

    public function events()
    {
        return array(
            // One object's owner changed: object = the object, before/after owner = the users.
            self::CHANGE => array( 'label' => 'Owner change', 'severity' => 'notice', 'channel' => 'content', 'default' => 'on' ),
            // A subtree run as a whole: object = the root node, after = counts; the objects are its children.
            self::SUBTREE => array( 'label' => 'Owner change of a subtree', 'severity' => 'notice', 'channel' => 'content', 'default' => 'on' )
        );
    }
}
