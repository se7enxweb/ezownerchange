<?php
/**
 * One owner change request, checked before anything is read or written: the new owner, the scope (one object or
 * a subtree), the start (object or node), an optional filter on the current owner and the dry run flag.
 *
 * Plain PHP with no kernel calls, so the admin view, the command line script and the unit tests share the same
 * rules.
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ezownerchange
 */

class expOwnerChangeRequest
{
    const SCOPE_OBJECT = 'object';
    const SCOPE_SUBTREE = 'subtree';

    /** @var int */
    public $newOwnerID = 0;

    /** @var string self::SCOPE_* */
    public $scope = self::SCOPE_OBJECT;

    /** @var int The object of an object scope (0 = none) */
    public $objectID = 0;

    /** @var int The root node of a subtree scope, or the node the object was opened from (0 = none) */
    public $nodeID = 0;

    /** @var int Only objects owned by this user now (0 = every owner) */
    public $fromOwnerID = 0;

    /** @var bool Report what would change, change nothing */
    public $dryRun = true;

    /** @var string[] What is wrong with the request (message keys, see messages()) */
    public $errors = array();

    /**
     * Builds a request from loosely typed input (POST variables, view parameters or command line options).
     *
     * @param array $input keys new_owner_id, scope, object_id, node_id, from_owner_id, dry_run
     * @return expOwnerChangeRequest
     */
    public static function fromArray( array $input )
    {
        $request = new self();
        $request->newOwnerID = self::id( $input, 'new_owner_id' );
        $request->objectID = self::id( $input, 'object_id' );
        $request->nodeID = self::id( $input, 'node_id' );
        $request->fromOwnerID = self::id( $input, 'from_owner_id' );
        $scope = isset( $input['scope'] ) ? strtolower( trim( (string)$input['scope'] ) ) : '';
        if ( $scope === '' )
            $scope = ( $request->objectID === 0 && $request->nodeID > 0 ) ? self::SCOPE_SUBTREE : self::SCOPE_OBJECT;
        $request->scope = $scope;
        $request->dryRun = !array_key_exists( 'dry_run', $input ) || self::flag( $input['dry_run'] );
        $request->validate();
        return $request;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return $this->errors === array();
    }

    /**
     * @return bool
     */
    public function isSubtree()
    {
        return $this->scope === self::SCOPE_SUBTREE;
    }

    /**
     * Checks the request and fills $errors.
     *
     * @return bool
     */
    public function validate()
    {
        $this->errors = array();
        if ( $this->scope !== self::SCOPE_OBJECT && $this->scope !== self::SCOPE_SUBTREE )
            $this->errors[] = 'scope';
        if ( $this->newOwnerID <= 0 )
            $this->errors[] = 'new_owner';
        if ( $this->scope === self::SCOPE_OBJECT && $this->objectID <= 0 )
            $this->errors[] = 'object';
        if ( $this->scope === self::SCOPE_SUBTREE && $this->nodeID <= 0 )
            $this->errors[] = 'node';
        if ( $this->fromOwnerID > 0 && $this->fromOwnerID === $this->newOwnerID )
            $this->errors[] = 'same_owner';
        return $this->errors === array();
    }

    /**
     * The English texts of the error keys (translated by the caller in the context extension/ezownerchange).
     *
     * @return array key => text
     */
    public static function messages()
    {
        return array(
            'scope' => 'The scope must be "object" or "subtree".',
            'new_owner' => 'Choose the new owner.',
            'object' => 'Name the object whose owner changes.',
            'node' => 'Name the node at the top of the subtree.',
            'same_owner' => 'The current owner filter and the new owner are the same user.',
            'not_a_user' => 'The new owner is not a user account.',
            'not_found' => 'The object or node does not exist.',
            'too_many' => 'The subtree holds more objects than one request may change. Use the command line script.'
        );
    }

    /**
     * A positive integer from the input, or 0.
     *
     * @param array $input
     * @param string $key
     * @return int
     */
    protected static function id( array $input, $key )
    {
        if ( !isset( $input[$key] ) )
            return 0;
        $value = $input[$key];
        if ( is_array( $value ) )
            $value = reset( $value );
        if ( is_int( $value ) )
            return $value > 0 ? $value : 0;
        $value = trim( (string)$value );
        return ( $value !== '' && ctype_digit( $value ) ) ? (int)$value : 0;
    }

    /**
     * A yes/no value: true, 1, "1", "yes", "on", "true", "enabled" are yes; everything else is no.
     *
     * @param mixed $value
     * @return bool
     */
    public static function flag( $value )
    {
        if ( is_bool( $value ) )
            return $value;
        return in_array( strtolower( trim( (string)$value ) ), array( '1', 'yes', 'on', 'true', 'enabled' ), true );
    }
}
