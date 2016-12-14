<?php

class AppPresser_Settings_Migration {

	private $appp_settings;
	private $theme_mods;
	private $stylesheet;

	public function migrate_check() {

		// WordPress 4.7 broke our customizer code and requires to
		// migrate from the option theme_mods_{slug} to appp_settings

		$appp_settings = appp_get_setting();

		if( ! $appp_settings ) {
			return;
		}

		// Get the current app theme
		$stylesheet = appp_get_setting( 'appp_theme' );

		if( ! $stylesheet ) {
			return;
		}

		// Get the theme mods from the customizer settings
		$theme_mods = get_option( 'theme_mods_' . $stylesheet );

		if( ! $theme_mods ) {
			return;
		}

		// See if our theme_mods existing in our appp_settings
		if( $theme_mods && ! isset( $appp_settings, $appp_settings['theme_mods_'  . $stylesheet]) ) {

			$this->appp_settings = $appp_settings;
			$this->theme_mods = $theme_mods;
			$this->stylesheet = $stylesheet;

			$this->do_migrate();
			
		}
	}

	private function do_migrate() {

		// Menus
		$this->migrate_menus();

		// Front page
		$this->migrate_front_page();

		// AppBuddy
		$this->migrate_appbuddy();

		// AppPush
		$this->migrate_apppush();

		// AppSwiper
		$this->migrate_appswiper();

		// Colors
		$this->migrate_colors();

		// Save
		$this->save_settings();
	}

	private function migrate_menus() {
		// primary-menu (ion)
		if( isset( $this->theme_mods['nav_menu_locations'], $this->theme_mods['nav_menu_locations']['primary-menu'] ) ) {
			$this->appp_settings['menu'] = $this->theme_mods['nav_menu_locations']['primary-menu'];
		}

		// primary (apptheme)
		if( isset( $this->theme_mods['nav_menu_locations'], $this->theme_mods['nav_menu_locations']['primary'] ) ) {
			$this->appp_settings['menu'] = $this->theme_mods['nav_menu_locations']['footer-menu'];
		}
		
		// footer-menu (ion & apptheme)
		if( isset( $this->theme_mods['nav_menu_locations'], $this->theme_mods['nav_menu_locations']['footer-menu'] ) ) {
			$this->appp_settings['secondary_menu'] = $this->theme_mods['nav_menu_locations']['footer-menu'];
		}
	}

	private function migrate_front_page() {
		$front_page_keys = array(
			'list_control',
		);

		foreach ( $front_page_keys as $key ) {
			$this->migrate_setting( $key );
		}
	}

	private function migrate_appbuddy() {
		$appbuddy_keys = array(
			'ab_color_mod',
			'ab_image_mod',
			'ab_text_mod',
		);

		foreach ( $appbuddy_keys as $key ) {
			$this->migrate_setting( $key );
		}
	}

	private function migrate_apppush() {
		$apppush_keys = array(
			'ap_color_mod',
		);

		foreach ( $apppush_keys as $key ) {
			$this->migrate_setting( $key );
		}
	}

	private function migrate_appswiper() {
		$appswiper_keys = array(
			'slider_control',
			'slider_category_control',
		);

		foreach ( $appswiper_keys as $key ) {
			$this->migrate_setting( $key );
		}
	}

	private function migrate_colors() {

		if( class_exists( 'AppPresser_Customizer' ) ) {
			$AppPresser_Customizer = new AppPresser_Customizer();

			$colors_setting_keys = array_keys( $AppPresser_Customizer->colors() );

			if( empty( $colors_setting_keys ) ) {
				return;
			}

			if( ! isset( $this->appp_settings['theme_mods'] ) ) {
				$this->appp_settings['theme_mods'] = array();
			}

			foreach ( $colors_setting_keys as $key ) {
				if( isset( $this->theme_mods[ $key ] ) && ! empty( $this->theme_mod[ $key ] ) ) {
					$this->appp_settings['theme_mods'][$key] = $this->theme_mods[$key];
				}
			}
		}
	}

	private function migrate_setting( $key ) {
		if( isset( $this->theme_mods[ $key ] ) && ! empty( $this->theme_mods[ $key ] ) ) {
			$this->appp_settings[ $key ] = $this->theme_mods[ $key ];
		}
	}

	private function save_settings() {
		update_option( 'appp_settings_2', $this->appp_settings );
	}

}
