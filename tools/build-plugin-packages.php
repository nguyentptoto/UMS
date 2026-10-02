<?php
/** Build separate installable ZIPs without local credentials or development packages. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$root = dirname( __DIR__ );
$dist = $root . '/dist';
if ( ! is_dir( $dist ) && ! mkdir( $dist, 0755, true ) ) { throw new RuntimeException( 'Cannot create dist' ); }
if ( ! class_exists( 'ZipArchive' ) ) { throw new RuntimeException( 'PHP ZipArchive is required.' ); }

function package_plugin( $base, $paths, $prefix, $destination ) {
    $zip = new ZipArchive();
    if ( $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) { throw new RuntimeException( 'Cannot create ZIP: ' . $destination ); }
    try {
        foreach ( $paths as $relative ) {
            $path = $base . '/' . $relative;
            if ( ! file_exists( $path ) ) { throw new RuntimeException( 'Missing package input: ' . $relative ); }
            $files = is_dir( $path )
                ? new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) )
                : array( new SplFileInfo( $path ) );
            foreach ( $files as $file ) {
                if ( ! $file->isFile() || $file->isLink() ) { continue; }
                $name = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $base ) + 1 ) );
                if ( ! $zip->addFile( $file->getPathname(), $prefix . '/' . $name ) ) { throw new RuntimeException( 'Cannot package: ' . $name ); }
            }
        }
    } finally {
        if ( ! $zip->close() ) { throw new RuntimeException( 'Cannot finalize ZIP: ' . $destination ); }
    }
    echo basename( $destination ) . ' SHA256 ' . hash_file( 'sha256', $destination ) . "\n";
}

$connector = $root . '/packages/ums-google-sheets-connector';
package_plugin( $connector, array( 'ums-google-sheets-connector.php', 'includes', 'admin', 'integrations', 'tools', 'README.md' ), 'ums-google-sheets-connector', $dist . '/ums-google-sheets-connector-1.0.0.zip' );
package_plugin( $root, array( 'tvn-uniform-management.php', 'admin', 'assets', 'includes', 'user', 'ums.sql', 'update-employee-exit.sql', 'update-inventory-by-factory.sql', 'Readme.md' ), 'UMS', $dist . '/UMS-1.1.0.zip' );
