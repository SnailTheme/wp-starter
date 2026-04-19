<?php
/**
 * Normalize generated POT file headers.
 *
 * WP-CLI's `i18n make-pot` intentionally writes generation-time metadata.
 * That is useful for local humans, but noisy for CI because a fresh generation
 * can differ only by timestamp or by the checkout directory name. This tiny
 * helper keeps the committed POT deterministic while still letting CI fail when
 * actual translation strings change.
 *
 * Usage:
 * php core/tools/normalize-pot.php languages/st-wp-starter.pot
 *
 * @package ST_WP_Core
 */

declare(strict_types=1);

$pot_file = $argv[1] ?? '';

if ( '' === $pot_file || ! is_file( $pot_file ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI build helper writes to STDERR directly.
	fwrite( STDERR, "Usage: php core/tools/normalize-pot.php languages/st-wp-starter.pot\n" );
	exit( 1 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local POT file, not a remote URL.
$pot_contents = (string) file_get_contents( $pot_file );

$pot_contents = preg_replace(
	'/^"Report-Msgid-Bugs-To:.*\\\\n"$/m',
	'"Report-Msgid-Bugs-To: https://wordpress.org/support/theme/st-wp-starter\n"',
	$pot_contents
);

$pot_contents = preg_replace(
	'/^"POT-Creation-Date:.*\\\\n"$/m',
	'"POT-Creation-Date: \n"',
	$pot_contents
);

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI build helper updates a generated local POT file.
file_put_contents( $pot_file, $pot_contents );
