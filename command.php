<?php
/**
 * Block Audit command
 *
 * @package wp-block-audit-command
 */

namespace Alley\WP;

use Alley\WP\Features\Lazy_Feature;
use Alley\WP\Features\WP_CLI_Feature;

if ( ! defined( 'WP_CLI' ) && ! defined( 'ABSPATH' ) ) {
	return;
}

if ( file_exists( __DIR__ . '/vendor/wordpress-autoload.php' ) ) {
	require_once __DIR__ . '/vendor/wordpress-autoload.php';
}

// Load the feature lazily so that the CommandWithDBObject class is available.
(
	new WP_CLI_Feature(
		new Lazy_Feature(
			fn () => new Features\Block_Audit_Command()
		)
	)
)->boot();
