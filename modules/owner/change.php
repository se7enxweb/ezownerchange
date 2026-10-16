<?php
/**
 * owner/change/<object id>: choose the new owner, preview what changes (dry run), confirm.
 *
 * 1. GET opens the content browser (browse.ini [ChangeOwner]); its return is a POST back here.
 * 2. The preview lists every object the change reaches and what happens to each (change, unchanged, refused,
 *    other owner). Nothing is written. Scope and owner filter can be changed and previewed again.
 * 3. Confirm (a POST with the preview's fingerprint) builds the plan again and changes the owners only when it is
 *    still what was previewed; otherwise the new preview is shown with a notice.
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ezownerchange
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$context = 'extension/ezownerchange';

$objectID = (int)$Params['ObjectID'];
$object = $objectID > 0 ? eZContentObject::fetch( $objectID ) : null;
if ( !$object instanceof eZContentObject )
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
if ( !$object->canRead() )
    return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );

$nodeID = isset( $Params['NodeID'] ) ? (int)$Params['NodeID'] : 0;
if ( $http->hasPostVariable( 'NodeID' ) )
    $nodeID = (int)$http->postVariable( 'NodeID' );
if ( $nodeID <= 0 )
    $nodeID = (int)$object->attribute( 'main_node_id' );
$startNode = isset( $Params['StartNode'] ) ? (int)$Params['StartNode'] : 0;

$selfURI = '/owner/change/' . $objectID . ( $nodeID > 0 ? '/(node)/' . $nodeID : '' )
         . ( isset( $Params['Scope'] ) && $Params['Scope'] === expOwnerChangeRequest::SCOPE_SUBTREE ? '/(scope)/subtree' : '' );
$backURI = $nodeID > 0 ? '/content/view/full/' . $nodeID : (string)$http->sessionVariable( 'LastAccessesURI', '/' );

if ( $Module->isCurrentAction( 'Cancel' ) )
{
    if ( $Module->hasActionParameter( 'CancelURI' ) && (string)$Module->actionParameter( 'CancelURI' ) !== '' )
        return $Module->redirectTo( $Module->actionParameter( 'CancelURI' ) );
    return $Module->redirectTo( $backURI );
}

$user = eZUser::currentUser();
$subtreeAccess = $user->hasAccessTo( 'owner', 'subtree' );
$canSubtree = $subtreeAccess['accessWord'] !== 'no';

// The new owner: from the content browser's return, or from the preview form.
$newOwnerID = 0;
if ( $Module->isCurrentAction( 'ChangeOwner' ) )
{
    $selected = eZContentBrowse::result( 'ChangeOwner' );
    if ( is_array( $selected ) && $selected )
        $newOwnerID = (int)reset( $selected );
}
elseif ( $http->hasPostVariable( 'NewOwnerID' ) )
{
    $newOwnerID = (int)$http->postVariable( 'NewOwnerID' );
}

$startBrowse = function () use ( $Module, $http, $objectID, $nodeID, $startNode, $selfURI, $backURI )
{
    $browse = array( 'action_name' => 'ChangeOwner',
                     'from_page' => $selfURI . ( $startNode > 0 ? '/(group)/' . $startNode : '' ),
                     'description_template' => 'design:content/browse_owner.tpl',
                     'content' => array( 'object_id' => $objectID, 'node_id' => $nodeID ),
                     'cancel_page' => $backURI );
    if ( $startNode > 0 )
        $browse['start_node'] = $startNode;
    return eZContentBrowse::browse( $browse, $Module );
};

// GET, or "Choose another owner": the content browser.
if ( $Module->isCurrentAction( 'BrowseOwner' ) || ( $newOwnerID === 0 && !$Module->isCurrentAction( 'ChangeOwner' ) ) )
    return $startBrowse();

$scope = expOwnerChangeRequest::SCOPE_OBJECT;
if ( $http->hasPostVariable( 'Scope' ) )
    $scope = (string)$http->postVariable( 'Scope' );
elseif ( isset( $Params['Scope'] ) && $Params['Scope'] )
    $scope = (string)$Params['Scope'];
$fromOwnerID = $http->hasPostVariable( 'OnlyCurrentOwner' ) ? (int)$http->postVariable( 'OnlyCurrentOwner' ) : 0;

$request = expOwnerChangeRequest::fromArray( array(
    'new_owner_id' => $newOwnerID,
    'scope' => $scope,
    'object_id' => $scope === expOwnerChangeRequest::SCOPE_SUBTREE ? 0 : $objectID,
    'node_id' => $nodeID,
    'from_owner_id' => $scope === expOwnerChangeRequest::SCOPE_SUBTREE ? $fromOwnerID : 0,
    'dry_run' => !$Module->isCurrentAction( 'Confirm' )
) );
$notices = array();
if ( $request->isSubtree() && !$canSubtree )
{
    // The owner/subtree policy is missing: refused, recorded like every other refusal.
    if ( class_exists( 'expAuditHook' ) )
        expAuditHook::emit( 'access.permission.refused', array( 'object' => array( 'type' => 'module', 'id' => 'owner/subtree' ),
                                                                  'target' => expAuditHook::node( $nodeID ) ) );
    $request->scope = expOwnerChangeRequest::SCOPE_OBJECT;
    $request->objectID = $objectID;
    $request->dryRun = true;
    $request->validate();
    $notices[] = array( 'kind' => 'bad', 'text' => ezpI18n::tr( $context, 'You may not change the owner of a whole subtree (policy owner/subtree).' ) );
}

$service = new expOwnerChangeService( 'admin' );
$result = $service->collect( $request, true, (int)$service->setting( 'WebObjectLimit' ) );
$plan = $result['plan'];
$done = null;

if ( $Module->isCurrentAction( 'Confirm' ) && !$request->dryRun && $plan instanceof expOwnerChangePlan )
{
    $posted = (string)$http->postVariable( 'PlanFingerprint', '' );
    if ( !hash_equals( $plan->fingerprint(), $posted ) )
    {
        $notices[] = array( 'kind' => 'warn', 'text' => ezpI18n::tr( $context, 'The content changed after the preview. Check the new preview and confirm again.' ) );
    }
    else
    {
        $done = $service->apply( $plan, $request );
        $done['count'] = count( $done['changed'] );
        $done['failed_count'] = count( $done['failed'] );
        // The plan as it is now, so the page shows the new owners.
        $request->dryRun = true;
        $result = $service->collect( $request, true, (int)$service->setting( 'WebObjectLimit' ) );
        $plan = $result['plan'];
    }
}

$messages = expOwnerChangeRequest::messages();
$errors = array();
foreach ( array_unique( $result['errors'] ) as $key )
    $errors[] = ezpI18n::tr( $context, isset( $messages[$key] ) ? $messages[$key] : $key );

$node = $nodeID > 0 ? eZContentObjectTreeNode::fetch( $nodeID ) : null;
$subtreeCount = 0;
if ( $node instanceof eZContentObjectTreeNode )
    $subtreeCount = count( $service->subtreeObjectIDs( $node->attribute( 'path_string' ) ) );

$listLimit = 200;
$tpl = eZTemplate::factory();
$tpl->setVariable( 'object', $object );
$tpl->setVariable( 'node', $node );
$tpl->setVariable( 'new_owner', $result['owner'] );
$tpl->setVariable( 'current_owner', $object->attribute( 'owner' ) );
$tpl->setVariable( 'scope', $request->scope );
$tpl->setVariable( 'only_current_owner', $request->fromOwnerID );
$tpl->setVariable( 'can_subtree', $canSubtree );
$tpl->setVariable( 'subtree_count', $subtreeCount );
$tpl->setVariable( 'web_limit', (int)$service->setting( 'WebObjectLimit' ) );
$tpl->setVariable( 'errors', $errors );
$tpl->setVariable( 'notices', $notices );
$tpl->setVariable( 'counts', $plan instanceof expOwnerChangePlan ? $plan->counts() : array() );
$tpl->setVariable( 'items', $plan instanceof expOwnerChangePlan ? array_slice( $plan->items(), 0, $listLimit ) : array() );
$tpl->setVariable( 'list_limit', $listLimit );
$tpl->setVariable( 'fingerprint', $plan instanceof expOwnerChangePlan ? $plan->fingerprint() : '' );
$tpl->setVariable( 'done', $done );
$tpl->setVariable( 'self_uri', $selfURI );
$tpl->setVariable( 'back_uri', $backURI );
$tpl->setVariable( 'publish_new_version', (bool)$service->setting( 'PublishNewVersion' ) );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:owner/change.tpl' );
$Result['path'] = array( array( 'url' => false, 'text' => ezpI18n::tr( $context, 'Change owner' ) ),
                         array( 'url' => false, 'text' => $object->attribute( 'name' ) ) );
return $Result;
