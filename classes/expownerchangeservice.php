<?php
/**
 * Changes the owner of content objects: one object, or every object with a location in a subtree.
 *
 *   $service = new expOwnerChangeService();
 *   $result = $service->collect( $request, true );         // the plan (dry run): nothing is written
 *   $done = $service->apply( $result['plan'], $request );  // carries the plan out
 *
 * apply() writes in batches (ownerchange.ini [OwnerChangeSettings] BatchSize), one database transaction per batch,
 * and after each batch clears the view caches of the changed objects, hands them to the search engine (owner_id is
 * indexed) and records one audit event per object (content.ezownerchange.change), all children of one
 * content.ezownerchange.subtree event when a subtree changes. A failure rolls the batch back and stops the run.
 *
 * By default only ezcontentobject.owner_id changes. UpdateVersionCreator=enabled also sets the creator of the
 * current version; PublishNewVersion=enabled publishes a new version with the new owner as its creator, the way
 * releases up to 1.3 did (slower, runs the publish workflows, adds a version to the history).
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ezownerchange
 */

class expOwnerChangeService
{
    /** @var array The settings of ownerchange.ini [OwnerChangeSettings] */
    protected $settings;

    /** @var string Where the change comes from, for the audit: admin or cli */
    protected $via;

    /**
     * @param string $via admin or cli
     * @param array|null $settings overrides of the INI settings (tests, the command line script)
     */
    public function __construct( $via = 'admin', ?array $settings = null )
    {
        $this->via = $via === 'cli' ? 'cli' : 'admin';
        $this->settings = ( $settings !== null ? $settings : array() ) + self::iniSettings();
    }

    /**
     * The settings with their defaults.
     *
     * @return array
     */
    public static function iniSettings()
    {
        $defaults = array( 'WebObjectLimit' => 500, 'BatchSize' => 50, 'UpdateVersionCreator' => false,
                           'PublishNewVersion' => false, 'UpdateSearchIndex' => true, 'ClearViewCache' => true,
                           'OwnerClassList' => array() );
        if ( !class_exists( 'eZINI' ) )
            return $defaults;
        $ini = eZINI::instance( 'ownerchange.ini' );
        $settings = $defaults;
        foreach ( array( 'WebObjectLimit', 'BatchSize' ) as $name )
            if ( $ini->hasVariable( 'OwnerChangeSettings', $name ) )
                $settings[$name] = max( 1, (int)$ini->variable( 'OwnerChangeSettings', $name ) );
        foreach ( array( 'UpdateVersionCreator', 'PublishNewVersion', 'UpdateSearchIndex', 'ClearViewCache' ) as $name )
            if ( $ini->hasVariable( 'OwnerChangeSettings', $name ) )
                $settings[$name] = expOwnerChangeRequest::flag( $ini->variable( 'OwnerChangeSettings', $name ) );
        $browse = eZINI::instance( 'browse.ini' );
        if ( $browse->hasVariable( 'ChangeOwner', 'Class' ) )
            $settings['OwnerClassList'] = array_values( array_filter( (array)$browse->variable( 'ChangeOwner', 'Class' ) ) );
        return $settings;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function setting( $name )
    {
        return isset( $this->settings[$name] ) ? $this->settings[$name] : null;
    }

    /**
     * The new owner, when it is a user account (a row in ezuser and, when browse.ini [ChangeOwner] Class[] is set,
     * an object of one of those classes).
     *
     * @param int $userID content object id of the user
     * @return eZContentObject|null
     */
    public function ownerObject( $userID )
    {
        $userID = (int)$userID;
        if ( $userID <= 0 || !eZUser::fetch( $userID ) instanceof eZUser )
            return null;
        $object = eZContentObject::fetch( $userID );
        if ( !$object instanceof eZContentObject )
            return null;
        $classes = $this->setting( 'OwnerClassList' );
        if ( $classes && !in_array( $object->attribute( 'class_identifier' ), $classes, true ) )
            return null;
        return $object;
    }

    /**
     * Builds the plan of a request. Nothing is written.
     *
     * @param expOwnerChangeRequest $request
     * @param bool $checkPermission whether the current user must be allowed to edit each object (the admin view);
     *                              the command line script runs without it unless it is given a user
     * @param int|null $limit the most objects to look at (null: no limit); more make 'over_limit' true
     * @return array plan (expOwnerChangePlan|null), errors (keys of expOwnerChangeRequest::messages()), total,
     *               over_limit, object (the object of an object scope), node (the root of a subtree), owner (the new
     *               owner's object)
     */
    public function collect( expOwnerChangeRequest $request, $checkPermission = true, $limit = null )
    {
        $result = array( 'plan' => null, 'errors' => $request->errors, 'total' => 0, 'over_limit' => false,
                         'object' => null, 'node' => null, 'owner' => null );
        if ( !$request->isValid() )
            return $result;
        $result['owner'] = $this->ownerObject( $request->newOwnerID );
        if ( !$result['owner'] )
        {
            $result['errors'][] = 'not_a_user';
            return $result;
        }

        if ( $request->isSubtree() )
        {
            $node = eZContentObjectTreeNode::fetch( $request->nodeID );
            if ( !$node instanceof eZContentObjectTreeNode )
            {
                $result['errors'][] = 'not_found';
                return $result;
            }
            $result['node'] = $node;
            $result['object'] = $node->object();
            $ids = $this->subtreeObjectIDs( $node->attribute( 'path_string' ), $request->fromOwnerID );
        }
        else
        {
            $object = eZContentObject::fetch( $request->objectID );
            if ( !$object instanceof eZContentObject )
            {
                $result['errors'][] = 'not_found';
                return $result;
            }
            $result['object'] = $object;
            if ( $request->nodeID > 0 )
                $result['node'] = eZContentObjectTreeNode::fetch( $request->nodeID ) ?: null;
            $ids = array( (int)$object->attribute( 'id' ) );
        }

        $result['total'] = count( $ids );
        if ( $limit !== null && $result['total'] > (int)$limit )
        {
            $result['over_limit'] = true;
            $result['errors'][] = 'too_many';
            return $result;
        }

        $rows = array();
        foreach ( array_chunk( $ids, 500 ) as $chunk )
        {
            foreach ( $this->rows( $chunk ) as $row )
            {
                $row['can_change'] = $checkPermission ? $this->canChange( $row['object_id'] ) : true;
                $rows[] = $row;
            }
            if ( $checkPermission )
                eZContentObject::clearCache();
        }
        $result['plan'] = new expOwnerChangePlan( $rows, $request->newOwnerID, $request->fromOwnerID );
        return $result;
    }

    /**
     * Carries a plan out.
     *
     * @param expOwnerChangePlan $plan
     * @param expOwnerChangeRequest $request
     * @param callable|null $progress called after each batch with ( done, total )
     * @return array changed (object ids), failed (object id => reason), batches
     */
    public function apply( expOwnerChangePlan $plan, expOwnerChangeRequest $request, $progress = null )
    {
        $newOwnerID = $plan->newOwnerID();
        $done = array( 'changed' => array(), 'failed' => array(), 'batches' => 0 );
        $total = count( $plan->changeIDs() );
        $audit = class_exists( 'expAuditHook' ) && class_exists( 'expOwnerChangeAuditBranch' );
        $parent = null;
        if ( $audit && $request->isSubtree() )
        {
            $nodeID = $request->nodeID;
            $parent = expAuditHook::begin( expOwnerChangeAuditBranch::SUBTREE, function () use ( $nodeID, $newOwnerID, $request, $total ) {
                return array( 'object' => expAuditHook::node( $nodeID ),
                              'target' => expAuditHook::user( $newOwnerID ),
                              'after' => array( 'owner' => $newOwnerID, 'from_owner' => $request->fromOwnerID ?: null,
                                                'planned' => $total, 'via' => $this->via ) );
            } );
        }

        $db = eZDB::instance();
        foreach ( $plan->batches( $this->setting( 'BatchSize' ) ) as $batch )
        {
            $changed = array();
            $db->begin();
            try
            {
                foreach ( $batch as $objectID )
                {
                    $object = eZContentObject::fetch( $objectID );
                    if ( !$object instanceof eZContentObject )
                    {
                        $done['failed'][$objectID] = 'not_found';
                        continue;
                    }
                    $oldOwnerID = (int)$object->attribute( 'owner_id' );
                    if ( $oldOwnerID === $newOwnerID )
                        continue;
                    $object->setAttribute( 'owner_id', $newOwnerID );
                    $object->store();
                    if ( $this->setting( 'UpdateVersionCreator' ) && !$this->setting( 'PublishNewVersion' ) )
                    {
                        $version = $object->currentVersion();
                        if ( $version instanceof eZContentObjectVersion )
                        {
                            $version->setAttribute( 'creator_id', $newOwnerID );
                            $version->store();
                        }
                    }
                    $changed[$objectID] = $oldOwnerID;
                }
                $db->commit();
            }
            catch ( Throwable $e )
            {
                $db->rollback();
                eZDebug::writeError( 'Owner change failed, batch rolled back: ' . $e->getMessage(), __METHOD__ );
                foreach ( $batch as $objectID )
                    $done['failed'][$objectID] = 'rolled_back';
                break;
            }

            foreach ( $changed as $objectID => $oldOwnerID )
            {
                if ( $this->setting( 'PublishNewVersion' ) )
                    $this->publishNewVersion( $objectID, $newOwnerID );
                if ( $this->setting( 'ClearViewCache' ) )
                    eZContentCacheManager::clearContentCacheIfNeeded( $objectID );
                if ( $this->setting( 'UpdateSearchIndex' ) )
                    eZContentOperationCollection::registerSearchObject( $objectID );
                if ( $audit )
                {
                    $via = $this->via;
                    $record = function () use ( $objectID, $oldOwnerID, $newOwnerID, $via ) {
                        return expAuditHook::emit( expOwnerChangeAuditBranch::CHANGE, function () use ( $objectID, $oldOwnerID, $newOwnerID, $via ) {
                            return array( 'object' => expAuditHook::object( $objectID ),
                                          'before' => array( 'owner' => $oldOwnerID ),
                                          'after' => array( 'owner' => $newOwnerID, 'via' => $via ) );
                        } );
                    };
                    if ( $parent !== null )
                        expAudit::withParent( $parent, $record );
                    else
                        $record();
                }
                $done['changed'][] = $objectID;
            }
            eZContentObject::clearCache();
            $done['batches']++;
            if ( $progress !== null )
                call_user_func( $progress, count( $done['changed'] ), $total );
        }

        if ( $parent !== null )
        {
            $failed = count( $done['failed'] );
            $data = array( 'after' => array( 'changed' => count( $done['changed'] ), 'failed' => $failed ) );
            if ( $failed > 0 )
                $data += array( 'result' => 'failed', 'reason' => 'rolled_back' );
            expAuditHook::end( $parent, $data );
        }
        return $done;
    }

    /**
     * Whether the current user may change the owner of an object: content/edit on it (with its limitations: class,
     * section, owner, subtree ...).
     *
     * @param int $objectID
     * @return bool
     */
    public function canChange( $objectID )
    {
        $object = eZContentObject::fetch( (int)$objectID );
        return $object instanceof eZContentObject && (int)$object->checkAccess( 'edit' ) === 1;
    }

    /**
     * The ids of the objects with a location in the subtree (the root included), each once.
     *
     * @param string $pathString the root's path_string
     * @param int $fromOwnerID only objects owned by this user now (0 = all)
     * @return int[]
     */
    public function subtreeObjectIDs( $pathString, $fromOwnerID = 0 )
    {
        $db = eZDB::instance();
        $path = $db->escapeString( (string)$pathString );
        $sql = "SELECT DISTINCT t.contentobject_id AS id FROM ezcontentobject_tree t";
        $where = " WHERE t.path_string LIKE '" . $path . "%'";
        if ( (int)$fromOwnerID > 0 )
        {
            $sql .= " INNER JOIN ezcontentobject o ON o.id = t.contentobject_id";
            $where .= " AND o.owner_id = " . (int)$fromOwnerID;
        }
        $rows = $db->arrayQuery( $sql . $where . " ORDER BY t.contentobject_id" );
        $ids = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row )
            $ids[] = (int)$row['id'];
        return $ids;
    }

    /**
     * Name and owner of each object.
     *
     * @param int[] $ids
     * @return array[]
     */
    protected function rows( array $ids )
    {
        $ids = array_map( 'intval', $ids );
        if ( !$ids )
            return array();
        $db = eZDB::instance();
        $list = $db->arrayQuery( "SELECT o.id, o.name, o.owner_id, u.name AS owner_name, m.node_id
                                  FROM ezcontentobject o
                                  LEFT JOIN ezcontentobject u ON u.id = o.owner_id
                                  LEFT JOIN ezcontentobject_tree m ON m.contentobject_id = o.id AND m.node_id = m.main_node_id
                                  WHERE o.id IN (" . implode( ',', $ids ) . ") ORDER BY o.id" );
        $rows = array();
        foreach ( is_array( $list ) ? $list : array() as $row )
            $rows[(int)$row['id']] = array( 'object_id' => (int)$row['id'], 'name' => (string)$row['name'],
                                            'owner_id' => (int)$row['owner_id'], 'owner_name' => (string)$row['owner_name'],
                                            'node_id' => (int)$row['node_id'] );
        $rows = array_values( $rows );
        return $rows;
    }

    /**
     * The behavior of releases up to 1.3: a new version with the new owner as its creator, published through the
     * kernel's publish operation (outside the batch's transaction: the operation has its own).
     *
     * @param int $objectID
     * @param int $newOwnerID
     * @return bool
     */
    protected function publishNewVersion( $objectID, $newOwnerID )
    {
        $object = eZContentObject::fetch( $objectID );
        if ( !$object instanceof eZContentObject )
            return false;
        $db = eZDB::instance();
        $db->begin();
        $version = $object->createNewVersionIn( false );
        $version->setAttribute( 'creator_id', $newOwnerID );
        $version->store();
        $db->commit();
        $result = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $objectID,
                                                                           'version' => $version->attribute( 'version' ) ) );
        return isset( $result['status'] ) && (int)$result['status'] === eZModuleOperationInfo::STATUS_CONTINUE;
    }
}
