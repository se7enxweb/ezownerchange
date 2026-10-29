<?php
/**
 * The owner module: change the owner of one object or of every object in a subtree.
 *
 * Policies (Roles and policies, module "owner"):
 *   change   needed for every view; changes the owner of single objects
 *   subtree  also needed to change the owner of a whole subtree
 * Each object's owner changes only where the user may also edit the object (content/edit with its limitations).
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ezownerchange
 */

$Module = array( 'name' => 'Owner', 'variable_params' => false );

$ViewList = array();

// owner/change/<object id>[/(node)/<node id>][/(scope)/subtree][/(group)/<browse start node>]
// GET opens the content browser to choose the new owner (as in releases up to 1.3); every change is a POST:
// the browser's return and the buttons of the preview, which ezformtoken guards with its form token.
$ViewList['change'] = array(
    'script' => 'change.php',
    'functions' => array( 'change' ),
    'params' => array( 'ObjectID' ),
    'unordered_params' => array( 'group' => 'StartNode', 'node' => 'NodeID', 'scope' => 'Scope' ),
    'ui_context' => 'edit',
    'default_navigation_part' => 'ezcontentnavigationpart',
    'post_actions' => array( 'BrowseActionName' ),
    'single_post_actions' => array( 'BrowseCancelButton' => 'Cancel',
                                    'CancelButton' => 'Cancel',
                                    'BrowseOwnerButton' => 'BrowseOwner',
                                    'PreviewButton' => 'Preview',
                                    'ConfirmButton' => 'Confirm' ),
    'post_action_parameters' => array( 'Cancel' => array( 'CancelURI' => 'BrowseCancelURI' ) )
);

$FunctionList = array();
$FunctionList['change'] = array();
$FunctionList['subtree'] = array();
