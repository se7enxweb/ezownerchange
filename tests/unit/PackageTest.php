<?php

use PHPUnit\Framework\TestCase;

/**
 * The parts of the extension that are files: the audit branch, the translations, the template rules of the
 * administration designs (no inline CSS) and the version fields that must agree.
 */
final class PackageTest extends TestCase
{
    private function root(): string
    {
        return dirname( __DIR__, 2 );
    }

    public function testAuditNamesFollowTheTaxonomy(): void
    {
        $events = ( new expOwnerChangeAuditBranch() )->events();
        $this->assertCount( 2, $events );
        foreach ( $events as $name => $definition )
        {
            $this->assertMatchesRegularExpression( '/^(content|access|system|commerce|data)(\.[a-z][a-z0-9_]*){2,5}$/', $name );
            $this->assertStringStartsWith( 'content.ezownerchange.', $name );
            $this->assertNotEmpty( $definition['label'] );
            $this->assertContains( $definition['default'], array( 'on', 'off', 'always', 'sampled' ) );
        }
    }

    public function testTemplatesHaveNoInlineCss(): void
    {
        $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->root() . '/design', FilesystemIterator::SKIP_DOTS ) );
        $count = 0;
        foreach ( $files as $file )
        {
            if ( substr( $file->getFilename(), -4 ) !== '.tpl' )
                continue;
            $count++;
            $tpl = file_get_contents( $file->getPathname() );
            $this->assertDoesNotMatchRegularExpression( '/<style\b/i', $tpl, $file->getPathname() );
            $this->assertDoesNotMatchRegularExpression( '/\sstyle\s*=/i', $tpl, $file->getPathname() );
        }
        $this->assertGreaterThan( 0, $count );
    }

    public function testEveryTranslatedTextIsInBothLanguages(): void
    {
        $sources = array();
        foreach ( array( 'eng-US', 'ger-DE' ) as $lang )
        {
            $xml = simplexml_load_file( $this->root() . '/translations/' . $lang . '/translation.ts' );
            $this->assertNotFalse( $xml );
            $list = array();
            foreach ( $xml->context as $context )
                foreach ( $context->message as $message )
                {
                    $this->assertNotSame( '', trim( (string)$message->translation ), (string)$message->source );
                    $list[] = (string)$context->name . '|' . (string)$message->source;
                }
            $sources[$lang] = $list;
        }
        $this->assertSame( $sources['eng-US'], $sources['ger-DE'] );
    }

    public function testTemplateTextsAreTranslated(): void
    {
        $xml = simplexml_load_file( $this->root() . '/translations/ger-DE/translation.ts' );
        $known = array();
        foreach ( $xml->context as $context )
            foreach ( $context->message as $message )
                $known[(string)$message->source] = true;
        $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->root() . '/design', FilesystemIterator::SKIP_DOTS ) );
        foreach ( $files as $file )
        {
            if ( substr( $file->getFilename(), -4 ) !== '.tpl' )
                continue;
            preg_match_all( '/([\'"])((?:(?!\1).)+)\1\|i18n\(/', file_get_contents( $file->getPathname() ), $m );
            foreach ( $m[2] as $text )
                $this->assertArrayHasKey( $text, $known, $file->getFilename() . ': ' . $text );
        }
    }

    public function testVersionFieldsAgree(): void
    {
        $xml = simplexml_load_file( $this->root() . '/extension.xml' );
        $version = (string)$xml->metadata->version;
        $this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+(\.\d+)?$/', $version );
        $this->assertStringContainsString( "'Version' => \"" . $version . '"', file_get_contents( $this->root() . '/ezinfo.php' ) );
    }

    public function testSettingsFilesParseAsIni(): void
    {
        foreach ( glob( $this->root() . '/settings/*.ini.append.php' ) as $file )
        {
            $text = file_get_contents( $file );
            $this->assertStringStartsWith( '<?php /* #?ini charset="utf-8"?', $text, basename( $file ) );
            $this->assertStringContainsString( '*/ ?>', $text, basename( $file ) );
        }
    }
}
