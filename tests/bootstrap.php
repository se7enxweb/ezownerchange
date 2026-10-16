<?php
/**
 * PHPUnit bootstrap for the ezownerchange unit tests. Loads only the plain PHP classes under test; nothing of
 * Exponential is booted (no database, siteaccess, INI or session). The audit interface is declared here when the
 * kernel is not around, with the signature of kernel/classes/audit/expaudittaxonomybranch.php.
 */

date_default_timezone_set( 'UTC' );

if ( !interface_exists( 'expAuditTaxonomyBranch', false ) )
{
    interface expAuditTaxonomyBranch
    {
        public function events();
    }
}

$checkout = dirname( __DIR__ );
require_once $checkout . '/classes/expownerchangerequest.php';
require_once $checkout . '/classes/expownerchangeplan.php';
require_once $checkout . '/classes/expownerchangeauditbranch.php';
