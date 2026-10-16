<?php
/**
 * The plan of an owner change: every object the request reaches, with what happens to it. The preview (dry run)
 * shows it, the confirmation carries it out; both build it the same way, so what was previewed is what changes.
 *
 * Each row is array( 'object_id' => int, 'name' => string, 'owner_id' => int, 'owner_name' => string, 'node_id' => int,
 * 'can_change' => bool ). The status of each row:
 *   change     the owner changes to the new owner
 *   unchanged  the new owner owns it already
 *   filtered   it has another owner than the one the request is limited to
 *   refused    the current user may not edit it (content/edit), so its owner stays
 *
 * Plain PHP, no kernel calls.
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ezownerchange
 */

class expOwnerChangePlan
{
    const CHANGE = 'change';
    const UNCHANGED = 'unchanged';
    const FILTERED = 'filtered';
    const REFUSED = 'refused';

    /** @var array[] The rows with their 'status' */
    protected $items = array();

    /** @var array status => count */
    protected $counts = array( self::CHANGE => 0, self::UNCHANGED => 0, self::FILTERED => 0, self::REFUSED => 0 );

    /** @var int */
    protected $newOwnerID;

    /**
     * @param array[] $rows
     * @param int $newOwnerID
     * @param int $fromOwnerID only rows owned by this user change (0 = every owner)
     */
    public function __construct( array $rows, $newOwnerID, $fromOwnerID = 0 )
    {
        $this->newOwnerID = (int)$newOwnerID;
        $seen = array();
        foreach ( $rows as $row )
        {
            $objectID = isset( $row['object_id'] ) ? (int)$row['object_id'] : 0;
            if ( $objectID <= 0 || isset( $seen[$objectID] ) )
                continue;
            $seen[$objectID] = true;
            $item = array(
                'object_id' => $objectID,
                'name' => isset( $row['name'] ) ? (string)$row['name'] : '',
                'owner_id' => isset( $row['owner_id'] ) ? (int)$row['owner_id'] : 0,
                'owner_name' => isset( $row['owner_name'] ) ? (string)$row['owner_name'] : '',
                'node_id' => isset( $row['node_id'] ) ? (int)$row['node_id'] : 0,
                'can_change' => !empty( $row['can_change'] )
            );
            $item['status'] = self::statusOf( $item, $this->newOwnerID, (int)$fromOwnerID );
            $this->counts[$item['status']]++;
            $this->items[] = $item;
        }
    }

    /**
     * What happens to one row.
     *
     * @param array $item
     * @param int $newOwnerID
     * @param int $fromOwnerID
     * @return string
     */
    public static function statusOf( array $item, $newOwnerID, $fromOwnerID )
    {
        if ( $item['owner_id'] === (int)$newOwnerID )
            return self::UNCHANGED;
        if ( $fromOwnerID > 0 && $item['owner_id'] !== (int)$fromOwnerID )
            return self::FILTERED;
        if ( !$item['can_change'] )
            return self::REFUSED;
        return self::CHANGE;
    }

    /**
     * @return array[]
     */
    public function items()
    {
        return $this->items;
    }

    /**
     * The rows of one status.
     *
     * @param string $status
     * @return array[]
     */
    public function itemsWithStatus( $status )
    {
        return array_values( array_filter( $this->items, function ( $item ) use ( $status ) {
            return $item['status'] === $status;
        } ) );
    }

    /**
     * The ids of the objects whose owner changes, in the order they were found.
     *
     * @return int[]
     */
    public function changeIDs()
    {
        return array_map( function ( $item ) { return $item['object_id']; }, $this->itemsWithStatus( self::CHANGE ) );
    }

    /**
     * @return array status => count, plus 'total'
     */
    public function counts()
    {
        return $this->counts + array( 'total' => count( $this->items ) );
    }

    /**
     * @return int
     */
    public function newOwnerID()
    {
        return $this->newOwnerID;
    }

    /**
     * A fingerprint of what the plan changes, so a confirmation can be refused when the content changed after the
     * preview (another owner, a new child, a lost permission).
     *
     * @return string
     */
    public function fingerprint()
    {
        $parts = array( 'to:' . $this->newOwnerID );
        foreach ( $this->items as $item )
            $parts[] = $item['object_id'] . ':' . $item['owner_id'] . ':' . $item['status'];
        return hash( 'sha256', implode( '|', $parts ) );
    }

    /**
     * The objects split into batches, for one transaction per batch.
     *
     * @param int $size
     * @return int[][]
     */
    public function batches( $size )
    {
        $size = max( 1, (int)$size );
        return array_chunk( $this->changeIDs(), $size );
    }
}
