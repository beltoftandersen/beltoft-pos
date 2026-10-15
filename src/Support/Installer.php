<?php

namespace BeltoftPos\Support;

defined( 'ABSPATH' ) || exit;

/**
 * The till operator role.
 *
 * Same capabilities as a customer - the role exists to say "this account
 * works the till", not to grant anything. Created only if missing, so the
 * accounts that already carry it keep it and its current label.
 */
class Installer {

	public const ROLE = 'pos_operator';

	public const VERSION_OPTION = 'beltoft_pos_version';

	public static function install(): void {
		if ( ! get_role( self::ROLE ) ) {
			$customer = get_role( 'customer' );

			add_role(
				self::ROLE,
				__( 'Till operator', 'beltoft-pos' ),
				$customer ? $customer->capabilities : array( 'read' => true )
			);
		}

		// Autoloaded: maybe_upgrade() reads it on every request of every host.
		update_option( self::VERSION_OPTION, BELTOFT_POS_VERSION, true );
	}

	/** Deploys replace files without activating, so the version decides. */
	public static function maybe_upgrade(): void {
		if ( BELTOFT_POS_VERSION !== get_option( self::VERSION_OPTION ) ) {
			self::install();
		}
	}
}
