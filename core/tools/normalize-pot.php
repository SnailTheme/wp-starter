<?php
/**
 * Normalize generated POT file headers.
 *
 * WP-CLI's `i18n make-pot` is the source of truth for generating translation
 * templates. This helper only fills in project-owned header values that WP-CLI
 * cannot infer for private/non-WordPress.org themes.
 *
 * Default usage is developer-facing and keeps generation dates intact:
 * php core/tools/normalize-pot.php languages/st-wp-starter.pot
 *
 * CI usage can additionally normalize volatile dates before comparing files:
 * php core/tools/normalize-pot.php languages/st-wp-starter.pot --ci
 *
 * @package ST_WP_Core
 */

declare(strict_types=1);

$pot_file       = '';
$ci_mode        = false;
$bug_report_url = 'https://github.com/SnailTheme/st-wp-starter';

foreach ( array_slice( $argv, 1 ) as $argument ) {
	if ( '--ci' === $argument ) {
		$ci_mode = true;
		continue;
	}

	if ( str_starts_with( $argument, '--bug-report-url=' ) ) {
		$bug_report_url = substr( $argument, strlen( '--bug-report-url=' ) );
		continue;
	}

	if ( '' === $pot_file ) {
		$pot_file = $argument;
		continue;
	}

	$pot_file = '';
	break;
}

if ( '' === $pot_file || ! is_file( $pot_file ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI build helper writes to STDERR directly.
	fwrite( STDERR, "Usage: php core/tools/normalize-pot.php languages/st-wp-starter.pot [--ci] [--bug-report-url=https://github.com/SnailTheme/st-wp-starter]\n" );
	exit( 1 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local POT file, not a remote URL.
$pot_contents = (string) file_get_contents( $pot_file );

$pot_contents = preg_replace(
	'/^"Report-Msgid-Bugs-To:.*\\\\n"$/m',
	'"Report-Msgid-Bugs-To: ' . $bug_report_url . '\n"',
	$pot_contents
);

if ( $ci_mode ) {
	$pot_contents = preg_replace(
		'/^"POT-Creation-Date:.*\\\\n"$/m',
		'"POT-Creation-Date: \n"',
		$pot_contents
	);
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI build helper updates a generated local POT file.
file_put_contents( $pot_file, $pot_contents );
