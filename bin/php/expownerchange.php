#!/usr/bin/env php
<?php
/**
 * Changes the owner of one object, or of every object in a subtree, from the command line.
 *
 *   php extension/ezownerchange/bin/php/expownerchange.php --to-owner=<user> --object=<object> --dry-run
 *   php extension/ezownerchange/bin/php/expownerchange.php --to-owner=<user> --node=<node> --from-owner=<user> --dry-run
 *   php extension/ezownerchange/bin/php/expownerchange.php --to-owner=<user> --node=<node> --from-owner=<user>
 *
 * Run it from the root of the installation as the web server's user. --dry-run lists what would change and writes
 * nothing. The command line is trusted like the other maintenance scripts: it changes every object the request
 * reaches, without the per-object content/edit check of the administration. Guide: extension/ezownerchange/doc/cli.md
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ezownerchange
 */

require_once 'autoload.php';
require_once __DIR__ . '/../../classes/expownerchangerequest.php';
require_once __DIR__ . '/../../classes/expownerchangeplan.php';
require_once __DIR__ . '/../../classes/expownerchangeservice.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => "Changes the owner of one object or of every object in a subtree.\n" .
                                                       "Use --dry-run first: it lists what would change and writes nothing.",
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions( '[to-owner:][object:][node:][from-owner:][dry-run][list][batch-size:]', '',
                                array( 'to-owner' => 'Content object id of the new owner (a user account)',
                                       'object' => 'Change the owner of this object only',
                                       'node' => 'Change the owner of every object in the subtree of this node (the node included)',
                                       'from-owner' => 'Only objects owned by this user now',
                                       'dry-run' => 'List what would change, change nothing',
                                       'list' => 'Print one line per object',
                                       'batch-size' => 'Objects per transaction (default: ownerchange.ini BatchSize)' ) );
$script->initialize();

$request = expOwnerChangeRequest::fromArray( array(
    'new_owner_id' => $options['to-owner'],
    'object_id' => $options['object'],
    'node_id' => $options['node'],
    'from_owner_id' => $options['from-owner'],
    'scope' => $options['node'] && !$options['object'] ? expOwnerChangeRequest::SCOPE_SUBTREE : expOwnerChangeRequest::SCOPE_OBJECT,
    'dry_run' => (bool)$options['dry-run']
) );

$settings = array();
if ( $options['batch-size'] )
    $settings['BatchSize'] = max( 1, (int)$options['batch-size'] );
$service = new expOwnerChangeService( 'cli', $settings );
$checkPermission = false; // the command line is trusted, like the other maintenance scripts
$result = $service->collect( $request, $checkPermission, null );

$messages = expOwnerChangeRequest::messages();
if ( $result['errors'] )
{
    foreach ( array_unique( $result['errors'] ) as $key )
        $cli->error( 'FAIL ' . ( isset( $messages[$key] ) ? $messages[$key] : $key ) );
    $script->shutdown( 1 );
}

/** @var expOwnerChangePlan $plan */
$plan = $result['plan'];
$counts = $plan->counts();
$owner = $result['owner'];
$cli->output( sprintf( 'New owner: %s (#%d)', $owner->attribute( 'name' ), $owner->attribute( 'id' ) ) );
if ( $request->isSubtree() )
    $cli->output( sprintf( 'Subtree of node %d: %s', $request->nodeID, $result['node']->attribute( 'name' ) ) );
else
    $cli->output( sprintf( 'Object %d: %s', $request->objectID, $result['object']->attribute( 'name' ) ) );
if ( $request->fromOwnerID > 0 )
    $cli->output( sprintf( 'Only objects owned by #%d', $request->fromOwnerID ) );
$cli->output( sprintf( 'Objects: %d; change %d, unchanged %d, other owner %d, refused %d',
                       $counts['total'], $counts['change'], $counts['unchanged'], $counts['filtered'], $counts['refused'] ) );
if ( $options['list'] )
{
    foreach ( $plan->items() as $item )
        $cli->output( sprintf( '  %-9s #%d %s (owner #%d %s)', $item['status'], $item['object_id'], $item['name'],
                               $item['owner_id'], $item['owner_name'] ) );
}

if ( $request->dryRun )
{
    $cli->output( 'Dry run: nothing changed.' );
    $script->shutdown( 0 );
}
if ( $counts['change'] === 0 )
{
    $cli->output( 'Nothing to change.' );
    $script->shutdown( 0 );
}

$done = $service->apply( $plan, $request, function ( $changed, $total ) use ( $cli ) {
    $cli->output( sprintf( '  %d of %d', $changed, $total ) );
} );
$failed = count( $done['failed'] );
$cli->output( sprintf( '%s Changed %d, failed %d, batches %d', $failed > 0 ? 'FAIL' : 'PASS', count( $done['changed'] ), $failed,
                       $done['batches'] ) );
$script->shutdown( $failed > 0 ? 1 : 0 );
