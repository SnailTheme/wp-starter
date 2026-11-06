<?php
/**
 * Offline PHP baseline and EOL regressions.
 *
 * @package ST_WP_Starter
 */

declare(strict_types=1);

// Offline CLI fixtures deliberately use PHP filesystem/JSON APIs and CLI diagnostics.
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped

require dirname( __DIR__ ) . '/scripts/check-php-support.php';

// Keep historical cases fixed when maintainers extend the live support calendar.
$policy = array(
	'minimum_floor' => '8.3',
	'branches'      => array(
		'8.3' => array(
			'released'               => '2023-11-23',
			'security_support_until' => '2027-12-31',
		),
		'8.4' => array(
			'released'               => '2024-11-21',
			'security_support_until' => '2028-12-31',
		),
		'8.5' => array(
			'released'               => '2025-11-20',
			'security_support_until' => '2029-12-31',
		),
	),
);
$checks = 0;
$assert = static function ( bool $condition, string $message ) use ( &$checks ): void {
	++$checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};
$reject = static function ( callable $operation, string $message ) use ( $assert ): void {
	try {
		$operation();
	} catch ( RuntimeException $error ) {
		$assert( true, $message );
		return;
	}
	$assert( false, $message );
};

$dates = array(
	'2026-11-06' => '8.3',
	'2027-12-31' => '8.3',
	'2028-01-01' => '8.4',
	'2028-12-31' => '8.4',
	'2029-01-01' => '8.5',
);
foreach ( $dates as $date => $expected ) {
	$assert( st_wp_starter_supported_php( $policy, $date ) === $expected, 'Incorrect baseline on ' . $date );
}
$reversed             = $policy;
$reversed['branches'] = array_reverse( $policy['branches'], true );
$assert( '8.3' === st_wp_starter_supported_php( $reversed, '2026-11-06' ), 'Branch selection depends on calendar order.' );
$higher_floor                  = $policy;
$higher_floor['minimum_floor'] = '8.4';
$assert( '8.4' === st_wp_starter_supported_php( $higher_floor, '2026-11-06' ), 'Project minimum floor was ignored.' );
$reject( static fn () => st_wp_starter_supported_php( $policy, '2030-01-01' ), 'Exhausted calendar accepted.' );
$reject( static fn () => st_wp_starter_supported_php( $policy, '2026-02-30' ), 'Invalid test date accepted.' );
$bad_policy = $policy;
$bad_policy['branches']['8.3']['security_support_until'] = '2027-02-30';
$reject( static fn () => st_wp_starter_supported_php( $bad_policy, '2026-11-06' ), 'Malformed calendar date accepted.' );
$future_policy                                = $policy;
$future_policy['branches']['8.3']['released'] = '2027-01-01';
$assert( '8.4' === st_wp_starter_supported_php( $future_policy, '2026-11-06' ), 'Unreleased branch selected.' );
$reject( static fn () => st_wp_starter_supported_php( $policy, '2023-11-22' ), 'Calendar with only unreleased branches accepted.' );
$assert( '8.3' === st_wp_starter_supported_php( $policy, '2023-11-23' ), 'Branch unavailable on its release date.' );

$root = sys_get_temp_dir() . '/st-php-policy-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/.github', 0700, true );
$composer = array(
	'require' => array( 'php' => '>=8.3' ),
	'config'  => array( 'platform' => array( 'php' => '8.3.0' ) ),
);
$lock     = array(
	'platform'           => array( 'php' => '>=8.3' ),
	'platform-overrides' => array( 'php' => '8.3.0' ),
);

try {
	file_put_contents( $root . '/.github/php-support.json', json_encode( $policy, JSON_THROW_ON_ERROR ) );
	file_put_contents( $root . '/style.css', "/*\nRequires PHP: 8.3\n*/\n" );
	file_put_contents( $root . '/composer.json', json_encode( $composer, JSON_THROW_ON_ERROR ) );
	file_put_contents( $root . '/composer.lock', json_encode( $lock, JSON_THROW_ON_ERROR ) );
	$assert( 1 === count( st_wp_starter_check_php_support( $root, '2026-11-06', '8.3.36' ) ), 'Consistent baseline rejected.' );
	$assert( 2 === count( st_wp_starter_check_php_support( $root, '2027-12-01', '8.3.36' ) ), 'EOL reminder missing.' );
	$assert( 1 === count( st_wp_starter_check_php_support( $root, '2027-10-31', '8.3.36' ) ), 'EOL reminder fired at 61 days.' );
	$assert( 2 === count( st_wp_starter_check_php_support( $root, '2027-11-01', '8.3.36' ) ), 'EOL reminder missing at 60 days.' );
	$assert( 2 === count( st_wp_starter_check_php_support( $root, '2027-12-31', '8.3.36' ) ), 'EOL date must remain supported inclusively.' );
	$reject( static fn () => st_wp_starter_check_php_support( $root, '2028-01-01', '8.3.36' ), 'EOL baseline accepted.' );
	$reject( static fn () => st_wp_starter_check_php_support( $root, '2026-11-06', '8.4.1' ), 'Wrong CI runtime accepted.' );
	$reject( static fn () => st_wp_starter_check_php_support( $root, '2026-11-06', '8.2.29' ), 'Below-minimum CI runtime accepted.' );

	foreach ( array(
		'CRLF'                    => "/*\r\nRequires PHP: 8.3\r\n*/\r\n",
		'CR-only'                 => "/*\rRequires PHP: 8.3\r*/\r",
		'comment prefix'          => "/**\n * Requires PHP: 8.3\n */\n",
		'case-insensitive header' => "/*\nrequires php: 8.3\n*/\n",
		'inline comment close'    => "/* Requires PHP: 8.3 */\n",
		'PHP comment header'      => "<?php // Requires PHP: 8.3 ?>\n",
		'first valid duplicate'   => "/*\nRequires PHP: 8.3\nRequires PHP: 8.2\n*/\n",
	) as $name => $header ) {
		file_put_contents( $root . '/style.css', $header );
		$assert( 1 === count( st_wp_starter_check_php_support( $root, '2026-11-06', '8.3.36' ) ), 'WordPress-compatible header rejected: ' . $name );
	}
	foreach ( array(
		'cross-line value'         => "/*\nRequires PHP:\n8.3\n*/\n",
		'first empty duplicate'    => "/*\nRequires PHP:\nRequires PHP: 8.3\n*/\n",
		'first prefixed duplicate' => "/*\n * Requires PHP: 8.2\nRequires PHP: 8.3\n*/\n",
		'header after first 8 KB'  => str_repeat( 'x', 8192 ) . "\nRequires PHP: 8.3\n",
	) as $name => $header ) {
		file_put_contents( $root . '/style.css', $header );
		$reject( static fn () => st_wp_starter_check_php_support( $root, '2026-11-06', '8.3.36' ), 'Invalid WordPress header accepted: ' . $name );
	}
	file_put_contents( $root . '/style.css', "/*\nRequires PHP: 8.3\n*/\n" );

	$bad_composer                   = $composer;
	$bad_composer['require']['php'] = '>=8.4';
	file_put_contents( $root . '/composer.json', json_encode( $bad_composer, JSON_THROW_ON_ERROR ) );
	$reject( static fn () => st_wp_starter_check_php_support( $root, '2026-11-06', '8.3.36' ), 'Inconsistent Composer requirement accepted.' );
	$bad_composer                              = $composer;
	$bad_composer['config']['platform']['php'] = '8.4.0';
	file_put_contents( $root . '/composer.json', json_encode( $bad_composer, JSON_THROW_ON_ERROR ) );
	$reject( static fn () => st_wp_starter_check_php_support( $root, '2026-11-06', '8.3.36' ), 'Inconsistent Composer platform accepted.' );
	file_put_contents( $root . '/composer.json', json_encode( $composer, JSON_THROW_ON_ERROR ) );
	foreach ( array(
		'platform'           => '>=8.4',
		'platform-overrides' => '8.2.0',
	) as $key => $value ) {
		$bad_lock                = $lock;
		$bad_lock[ $key ]['php'] = $value;
		file_put_contents( $root . '/composer.lock', json_encode( $bad_lock, JSON_THROW_ON_ERROR ) );
		$reject( static fn () => st_wp_starter_check_php_support( $root, '2026-11-06', '8.3.36' ), 'Inconsistent lock ' . $key . ' accepted.' );
	}
	unlink( $root . '/composer.lock' );
	$reject( static fn () => st_wp_starter_check_php_support( $root, '2026-11-06', '8.3.36' ), 'Missing lock accepted.' );

	foreach ( array(
		'2028-01-01' => '8.4',
		'2029-01-01' => '8.5',
	) as $date => $branch ) {
		$composer['require']['php']            = '>=' . $branch;
		$composer['config']['platform']['php'] = $branch . '.0';
		$lock['platform']['php']               = '>=' . $branch;
		$lock['platform-overrides']['php']     = $branch . '.0';
		file_put_contents( $root . '/style.css', "/*\nRequires PHP: " . $branch . "\n*/\n" );
		file_put_contents( $root . '/composer.json', json_encode( $composer, JSON_THROW_ON_ERROR ) );
		file_put_contents( $root . '/composer.lock', json_encode( $lock, JSON_THROW_ON_ERROR ) );
		$assert( 1 === count( st_wp_starter_check_php_support( $root, $date, $branch . '.1' ) ), 'Correctly migrated baseline rejected: ' . $branch );
	}
} finally {
	foreach ( array( '.github/php-support.json', 'style.css', 'composer.json', 'composer.lock' ) as $file ) {
		if ( is_file( $root . '/' . $file ) ) {
			unlink( $root . '/' . $file );
		}
	}
	rmdir( $root . '/.github' );
	rmdir( $root );
}

fwrite( STDOUT, $checks . ' PHP support checks passed.' . PHP_EOL );
