<?php
/** Copy configuration without executing an addon or changing the original data. */
defined( 'ABSPATH' ) || exit;

class AppPresser_Module_Settings {
	const VERSION = 1;
	private $registry;
	private $conflicts;

	public function __construct( $registry, $conflicts ) {
		$this->registry = $registry;
		$this->conflicts = $conflicts;
	}

	public function option_name( $id ) {
		return 'apppresser_module_settings_' . $id;
	}

	public function record( $id ) {
		$value = get_option( 'apppresser_module_migration_' . $id, array() );
		return is_array( $value ) ? $value : array();
	}

	public function values( $id ) {
		$value = get_option( $this->option_name( $id ), array() );
		return is_array( $value ) ? $value : array();
	}

	private function source( $id ) {
		$definition = $this->registry[ $id ];
		$shared = get_option( 'appp_settings', array() );
		$values = array_intersect_key( is_array( $shared ) ? $shared : array(), array_flip( $definition['fields'] ) );
		if ( 'bridge' === $id && empty( $this->record( $id )['navigation_in_customization'] ) ) {
			$values['add_push_page_class'] = $this->navigation_value( $values );
		}
		foreach ( isset( $definition['options'] ) ? $definition['options'] : array() as $key => $unused ) {
			$missing = new stdClass();
			$value = get_option( $key, $missing );
			if ( $value !== $missing ) {
				$values[ $key ] = $value;
			}
		}
		return $values;
	}

	/** Recover a preference saved in the temporary Custom Theme Support settings. */
	private function navigation_value( $values ) {
		$custom_theme = $this->record( 'custom-theme' );
		if ( isset( $custom_theme['status'] ) && 'complete' === $custom_theme['status'] ) {
			$saved = $this->values( 'custom-theme' );
			// An empty settings group on newer builds does not own navigation.
			if ( array_key_exists( 'add_push_page_class', $saved ) ) { return $saved['add_push_page_class']; }
		}
		return array_key_exists( 'add_push_page_class', $values ) ? $values['add_push_page_class'] : '';
	}

	/** Runs on updates too. Locks are per module and site, and can recover after a failed request. */
	public function prepare( $id, $refresh = false ) {
		if ( ! isset( $this->registry[ $id ] ) ) {
			return new WP_Error( 'unknown_module', __( 'Unknown feature.', 'apppresser' ) );
		}
		$record = $this->record( $id );
		$navigation_upgrade = 'bridge' === $id && empty( $record['navigation_in_customization'] );
		$complete = isset( $record['status'] ) && 'complete' === $record['status'];
		if ( $complete && ! $navigation_upgrade ) {
			return true;
		}
		if ( ! $navigation_upgrade && ! $refresh && isset( $record['version'], $record['status'] ) && self::VERSION === $record['version'] && 'prepared' === $record['status'] ) {
			return true;
		}
		$lock = 'apppresser_module_lock_' . $id;
		$locked_at = get_option( $lock );
		if ( $locked_at && (int) $locked_at < time() - 120 ) {
			delete_option( $lock );
		}
		if ( ! add_option( $lock, time(), '', false ) ) {
			return new WP_Error( 'migration_busy', __( 'Settings are being preserved by another request. Try again shortly.', 'apppresser' ) );
		}
		try {
			if ( $complete ) {
				$values = $this->values( $id );
				$values['add_push_page_class'] = $this->navigation_value( $values );
			} else {
				$values = $this->source( $id );
			}
			// Exact values are retained, including false, zero, empty strings and credentials.
			add_option( 'apppresser_module_snapshot_' . $id, $values, '', false );
			update_option( $this->option_name( $id ), $values, false );
			if ( $this->values( $id ) !== $values ) {
				return new WP_Error( 'migration_write', __( 'The settings copy could not be verified. Keep the legacy plugin installed and try again.', 'apppresser' ) );
			}
			$record['version'] = self::VERSION;
			$record['status'] = $complete ? 'complete' : 'prepared';
			if ( 'bridge' === $id ) { $record['navigation_in_customization'] = true; }
			if ( ! $complete ) { $record['prepared_at'] = time(); }
			update_option( 'apppresser_module_migration_' . $id, $record, true );
			return $record === $this->record( $id )
				? true : new WP_Error( 'migration_write', __( 'The migration record could not be saved.', 'apppresser' ) );
		} finally {
			delete_option( $lock );
		}
	}

	public function complete( $id ) {
		if ( 'bridge' === $id ) {
			$result = $this->prepare( $id );
			if ( is_wp_error( $result ) ) { return $result; }
		}
		$record = $this->record( $id );
		if ( ! isset( $record['status'] ) ) {
			$result = $this->prepare( $id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$record = $this->record( $id );
		}
		if ( 'complete' === $record['status'] ) {
			return true;
		}
		$lock = 'apppresser_module_lock_' . $id;
		$locked_at = get_option( $lock );
		if ( $locked_at && (int) $locked_at < time() - 120 ) {
			delete_option( $lock );
		}
		if ( ! add_option( $lock, time(), '', false ) ) {
			return new WP_Error( 'migration_busy', __( 'Settings are being preserved by another request. Try again shortly.', 'apppresser' ) );
		}
		try {
			$values = $this->values( $id ) + $this->registry[ $id ]['defaults'];
			if ( 'community' === $id && empty( $values['ap_bp_jwt_key'] ) ) {
				$values['ap_bp_jwt_key'] = wp_generate_password( 64, false );
			}
			update_option( $this->option_name( $id ), $values, false );
			if ( $values !== $this->values( $id ) ) {
				return new WP_Error( 'migration_write', __( 'The feature settings could not be saved.', 'apppresser' ) );
			}
			$record['status'] = 'complete';
			$record['completed_at'] = time();
			update_option( 'apppresser_module_migration_' . $id, $record, true );
			return isset( $this->record( $id )['status'] ) && 'complete' === $this->record( $id )['status']
				? true : new WP_Error( 'migration_write', __( 'The migration record could not be saved.', 'apppresser' ) );
		} finally {
			delete_option( $lock );
		}
	}

	public function owns( $id ) {
		$record = $this->record( $id );
		return isset( $record['status'] ) && 'complete' === $record['status'] && ! call_user_func( $this->conflicts, $id );
	}

	public function overlay( $shared ) {
		foreach ( $this->registry as $id => $definition ) {
			if ( ! $definition['fields'] || ! $this->owns( $id ) ) {
				continue;
			}
			foreach ( $definition['fields'] as $key ) {
				unset( $shared[ $key ] );
			}
			$shared = array_merge( $shared, array_intersect_key( $this->values( $id ), array_flip( $definition['fields'] ) ) );
		}
		return $shared;
	}

	/** Existing addon settings forms can save through the public settings helper. */
	public function save_shared( $submitted, $registered ) {
		$original = get_option( 'appp_settings', array() );
		foreach ( $this->registry as $id => $definition ) {
			if ( ! $this->owns( $id ) ) {
				continue;
			}
			$values = $this->values( $id );
			foreach ( $definition['fields'] as $key ) {
				if ( array_key_exists( $key, $submitted ) ) {
					$values[ $key ] = $submitted[ $key ];
				} elseif ( isset( $registered[ $key ] ) && in_array( $key, $definition['checkboxes'], true ) ) {
					$values[ $key ] = '';
				}
				unset( $submitted[ $key ] );
				// Preserve old values unchanged for a deliberate legacy rollback.
				if ( is_array( $original ) && array_key_exists( $key, $original ) ) {
					$submitted[ $key ] = $original[ $key ];
				}
			}
			update_option( $this->option_name( $id ), $values, false );
		}
		return $submitted;
	}

	public function get_iap( $key, $default = false ) {
		$values = $this->values( 'iap' );
		return array_key_exists( $key, $values ) ? $values[ $key ] : $default;
	}

	public function update_iap( $key, $value ) {
		if ( ! isset( $this->registry['iap']['options'] ) || ! array_key_exists( $key, $this->registry['iap']['options'] ) ) {
			return false;
		}
		$values = $this->values( 'iap' );
		$values[ $key ] = $value;
		return update_option( $this->option_name( 'iap' ), $values, false );
	}
}
