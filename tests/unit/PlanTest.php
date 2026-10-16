<?php

use PHPUnit\Framework\TestCase;

final class PlanTest extends TestCase
{
    private function rows(): array
    {
        return array(
            array( 'object_id' => 10, 'name' => 'Home', 'owner_id' => 14, 'owner_name' => 'Admin', 'can_change' => true ),
            array( 'object_id' => 11, 'name' => 'News', 'owner_id' => 20, 'owner_name' => 'Editor', 'can_change' => true ),
            array( 'object_id' => 12, 'name' => 'Locked', 'owner_id' => 20, 'owner_name' => 'Editor', 'can_change' => false ),
            array( 'object_id' => 13, 'name' => 'Other', 'owner_id' => 30, 'owner_name' => 'Writer', 'can_change' => true ),
            array( 'object_id' => 11, 'name' => 'News (second location)', 'owner_id' => 20, 'owner_name' => 'Editor', 'can_change' => true ),
        );
    }

    public function testStatusOfEachRow(): void
    {
        $plan = new expOwnerChangePlan( $this->rows(), 14 );
        $status = array_column( $plan->items(), 'status', 'object_id' );
        $this->assertSame( array( 10 => 'unchanged', 11 => 'change', 12 => 'refused', 13 => 'change' ), $status );
        $this->assertSame( array( 11, 13 ), $plan->changeIDs() );
    }

    public function testAnObjectWithTwoLocationsCountsOnce(): void
    {
        $counts = ( new expOwnerChangePlan( $this->rows(), 14 ) )->counts();
        $this->assertSame( 4, $counts['total'] );
        $this->assertSame( 2, $counts['change'] );
        $this->assertSame( 1, $counts['unchanged'] );
        $this->assertSame( 1, $counts['refused'] );
        $this->assertSame( 0, $counts['filtered'] );
    }

    public function testTheOwnerFilterLeavesOtherOwnersAlone(): void
    {
        $plan = new expOwnerChangePlan( $this->rows(), 14, 20 );
        $this->assertSame( array( 11 ), $plan->changeIDs() );
        $this->assertSame( 1, $plan->counts()['filtered'] );
        $this->assertSame( 'filtered', $plan->itemsWithStatus( 'filtered' )[0]['status'] );
    }

    public function testRefusalWinsOverChangeButNotOverUnchanged(): void
    {
        $item = array( 'object_id' => 1, 'owner_id' => 14, 'can_change' => false );
        $this->assertSame( 'unchanged', expOwnerChangePlan::statusOf( $item, 14, 0 ) );
        $item['owner_id'] = 15;
        $this->assertSame( 'refused', expOwnerChangePlan::statusOf( $item, 14, 0 ) );
    }

    public function testFingerprintFollowsTheContent(): void
    {
        $a = new expOwnerChangePlan( $this->rows(), 14 );
        $b = new expOwnerChangePlan( $this->rows(), 14 );
        $this->assertSame( $a->fingerprint(), $b->fingerprint() );
        $rows = $this->rows();
        $rows[1]['owner_id'] = 30;
        $this->assertNotSame( $a->fingerprint(), ( new expOwnerChangePlan( $rows, 14 ) )->fingerprint() );
        $this->assertNotSame( $a->fingerprint(), ( new expOwnerChangePlan( $this->rows(), 15 ) )->fingerprint() );
    }

    public function testBatches(): void
    {
        $rows = array();
        for ( $i = 1; $i <= 7; $i++ )
            $rows[] = array( 'object_id' => $i, 'owner_id' => 99, 'can_change' => true );
        $plan = new expOwnerChangePlan( $rows, 14 );
        $this->assertSame( array( array( 1, 2, 3 ), array( 4, 5, 6 ), array( 7 ) ), $plan->batches( 3 ) );
        $this->assertCount( 7, $plan->batches( 0 ) );
    }

    public function testTheMainNodeIsKeptForTheLinks(): void
    {
        $plan = new expOwnerChangePlan( array( array( 'object_id' => 5, 'owner_id' => 1, 'node_id' => 77, 'can_change' => true ),
                                               array( 'object_id' => 6, 'owner_id' => 1, 'can_change' => true ) ), 14 );
        $this->assertSame( array( 77, 0 ), array_column( $plan->items(), 'node_id' ) );
    }

    public function testRowsWithoutAnIdAreSkipped(): void
    {
        $plan = new expOwnerChangePlan( array( array( 'name' => 'x' ), array( 'object_id' => 0 ) ), 14 );
        $this->assertSame( 0, $plan->counts()['total'] );
    }
}
