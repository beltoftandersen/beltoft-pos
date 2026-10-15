<?php

namespace BeltoftPos\Scanner;

use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;
use BeltoftPos\Support\Till;

defined( 'ABSPATH' ) || exit;

/**
 * The barcode scanner, on the till.
 *
 * beltoft-scanner shows nowhere until a plugin says where; this says: on the
 * POS page, on the POS host, for till users. Hooked only to the scanner's own
 * filter, so without the scanner it never runs.
 *
 * No catalogue rule is added (beltoft_scanner_assess): the till sells every
 * published product. Stock never blocks a scan either - the till's pool
 * answers backorders "yes", and the scanner only caps quantities when
 * backorders are off. Its REST calls go to rest_url(), which Host\Urls keeps
 * on this host, so they are till sessions like the page itself.
 */
class Context {

	public const ID = 'pos';

	public static function register(): void {
		add_filter( 'beltoft_scanner_contexts', array( __CLASS__, 'add_context' ) );
	}

	/**
	 * @param mixed $contexts
	 */
	public static function add_context( $contexts ): array {
		$contexts = is_array( $contexts ) ? $contexts : array();

		$contexts[ self::ID ] = array(
			'applies'  => array( __CLASS__, 'applies' ),
			'may_scan' => array( Till::class, 'is_till_user' ),
		);

		return $contexts;
	}

	public static function applies(): bool {
		if ( ! Host::is_pos_host() ) {
			return false;
		}

		$page = Options::page_id();

		return $page && is_page( $page );
	}
}
