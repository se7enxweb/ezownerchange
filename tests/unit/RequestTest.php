<?php

use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testObjectScopeIsTheDefault(): void
    {
        $r = expOwnerChangeRequest::fromArray( array( 'new_owner_id' => '14', 'object_id' => '57' ) );
        $this->assertTrue( $r->isValid() );
        $this->assertSame( 'object', $r->scope );
        $this->assertSame( 14, $r->newOwnerID );
        $this->assertSame( 57, $r->objectID );
    }

    public function testANodeWithoutAnObjectMeansASubtree(): void
    {
        $r = expOwnerChangeRequest::fromArray( array( 'new_owner_id' => 14, 'node_id' => 2 ) );
        $this->assertSame( 'subtree', $r->scope );
        $this->assertTrue( $r->isSubtree() );
        $this->assertTrue( $r->isValid() );
    }

    public function testDryRunUnlessSaidOtherwise(): void
    {
        $this->assertTrue( expOwnerChangeRequest::fromArray( array( 'new_owner_id' => 14, 'object_id' => 1 ) )->dryRun );
        $this->assertFalse( expOwnerChangeRequest::fromArray( array( 'new_owner_id' => 14, 'object_id' => 1, 'dry_run' => false ) )->dryRun );
        $this->assertTrue( expOwnerChangeRequest::fromArray( array( 'new_owner_id' => 14, 'object_id' => 1, 'dry_run' => 'yes' ) )->dryRun );
    }

    public function testMissingOrMalformedIdsAreErrors(): void
    {
        $r = expOwnerChangeRequest::fromArray( array( 'new_owner_id' => '14abc', 'object_id' => '-3' ) );
        $this->assertFalse( $r->isValid() );
        $this->assertSame( array( 'new_owner', 'object' ), $r->errors );
    }

    public function testASubtreeNeedsItsNode(): void
    {
        $r = expOwnerChangeRequest::fromArray( array( 'new_owner_id' => 14, 'scope' => 'subtree' ) );
        $this->assertSame( array( 'node' ), $r->errors );
    }

    public function testUnknownScopeIsRefused(): void
    {
        $r = expOwnerChangeRequest::fromArray( array( 'new_owner_id' => 14, 'object_id' => 1, 'scope' => 'everything' ) );
        $this->assertContains( 'scope', $r->errors );
    }

    public function testTheOwnerFilterCannotBeTheNewOwner(): void
    {
        $r = expOwnerChangeRequest::fromArray( array( 'new_owner_id' => 14, 'node_id' => 2, 'from_owner_id' => 14 ) );
        $this->assertSame( array( 'same_owner' ), $r->errors );
    }

    public function testEveryErrorKeyHasAText(): void
    {
        $messages = expOwnerChangeRequest::messages();
        foreach ( array( 'scope', 'new_owner', 'object', 'node', 'same_owner', 'not_a_user', 'not_found', 'too_many' ) as $key )
            $this->assertArrayHasKey( $key, $messages );
    }

    public function testFlags(): void
    {
        foreach ( array( true, '1', 'yes', 'On', 'TRUE', 'enabled' ) as $yes )
            $this->assertTrue( expOwnerChangeRequest::flag( $yes ) );
        foreach ( array( false, '0', 'no', 'disabled', '', 'maybe' ) as $no )
            $this->assertFalse( expOwnerChangeRequest::flag( $no ) );
    }
}
