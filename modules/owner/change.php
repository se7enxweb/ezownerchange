<?php

$http = eZHTTPTool::instance();

$Module = $Params['Module'];
$moduleName = $Params['ModuleName'];
$functionName = $Params['FunctionName'];

$objectID = $Params['ObjectID'];

$db = eZDB::instance();
$db->begin();

// if browse was cancelled, redirect
if ( $Module->isCurrentAction( 'Cancel' ) )
{
    if ( $Module->hasActionParameter( 'CancelURI' ) )
    {
        return $Module->redirectTo( $Module->actionParameter( 'CancelURI' ) );
    }
    else
    {
        return $Module->redirectTo( $http->sessionVariable( 'LastAccessesURI' ) );
    }
}

if ( $objectID )
{
    $object = eZContentObject::fetch( $objectID );
}

if ( !$objectID or !$object )
{
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
}

if ( $Module->isCurrentAction( 'ChangeOwner' ) )
{
    $selectedObjectIDArray = eZContentBrowse::result( 'ChangeOwner' );

    if ( is_array( $selectedObjectIDArray ) and count( $selectedObjectIDArray ) > 0 )
    {
        $object->setAttribute( 'owner_id', $selectedObjectIDArray[0] );
        $object->store();

	$version = $object->createNewVersionIn( false );

	$version->setAttribute( 'creator_id', $selectedObjectIDArray[0] );
	$version->store();

	// publish the newly created object
        eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $object->attribute( 'id' ),
                                                                  'version'   => $version->attribute( 'version' ) ) );


        // Clean up content cache
        eZContentCacheManager::clearContentCache( $object->attribute( 'id' ) );
    }

    return $Module->redirectTo( $http->sessionVariable( 'LastAccessesURI' ) );
}
else
{
    $browseParams = array();
    $browseParams['action_name'] = 'ChangeOwner';
    $browseParams['from_page'] = '/owner/change/' . $objectID;
    $browseParams['description_template' ] = 'design:content/browse_owner.tpl';
    $browseParams['content'] = array( 'object_id' => $objectID );

    $currentOwner = $object->attribute( 'owner' );
 
    /* Note: This features causes problems for more users than it really solves so we remove it without deletion.

    if ( currentOwner )
    {
        $currentOwnerNodes = $currentOwner->attribute( 'assigned_nodes' );

	$ignoreNodeIDList = array();

        foreach ( $currentOwnerNodes as $currentOwnerNode )
        {
            $ignoreNodeIDList[] = $currentOwnerNode->attribute( 'node_id' );

        }
	
        $browseParams['ignore_nodes_select'] = $ignoreNodeIDList;
    }
    */

    if ( $Params['StartNode'] )
    {
        $browseParams['start_node'] = $Params['StartNode'];
        $browseParams['from_page'] .= '/group/' . $Params['StartNode'];
    }

    $browseParams['cancel_page'] = $http->sessionVariable( 'LastAccessesURI' );
    return eZContentBrowse::browse( $browseParams, $Module );
}

$db->commit();

?>