<?php

class AppPresser_Settings_Migration {

	private $appp_settings;
	private $theme_mods;
	private $stylesheet;
	private $colors_setting_keys = array();

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
			$this->appp_settings['menu'] = (int)$this->theme_mods['nav_menu_locations']['primary-menu'];
		}

		// primary (apptheme)
		if( isset( $this->theme_mods['nav_menu_locations'], $this->theme_mods['nav_menu_locations']['primary'] ) ) {
			$this->appp_settings['menu'] = (int)$this->theme_mods['nav_menu_locations']['primary'];
		}
		
		// footer-menu (ion & apptheme)
		if( isset( $this->theme_mods['nav_menu_locations'], $this->theme_mods['nav_menu_locations']['footer-menu'] ) ) {
			$this->appp_settings['secondary_menu'] = (int)$this->theme_mods['nav_menu_locations']['footer-menu'];
		}

		// top (apptheme)
		if( isset( $this->theme_mods['nav_menu_locations'], $this->theme_mods['nav_menu_locations']['top'] ) ) {
			$this->appp_settings['top_menu'] = (int)$this->theme_mods['nav_menu_locations']['top'];
		}
		
		// top2 (apptheme)
		if( isset( $this->theme_mods['nav_menu_locations'], $this->theme_mods['nav_menu_locations']['top2'] ) ) {
			$this->appp_settings['top_2_menu'] = (int)$this->theme_mods['nav_menu_locations']['top2'];
		}
	}

	private function migrate_front_page() {
		$front_page_keys = array(
			'list_control',
			'appp_logo',
		);

		foreach ( $front_page_keys as $key ) {
			$this->migrate_setting( $key );
		}
	}

	private function migrate_appbuddy() {

		$this->colors_setting_keys[] = 'ab_color_mod';

		$appbuddy_keys = array(
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
			'homepage_slider_control' => 'checkbox',
			'homepage_slider' => 'checkbox',
			'slider_control' => 'checkbox',
			'slider_category_control',
		);

		foreach ( $appswiper_keys as $key => $type ) {
			$this->migrate_setting( $key, $type );
		}
	}

	private function migrate_colors() {

		if( class_exists( 'AppPresser_Customizer' ) ) {

			// this gets loaded through the theme's appp-settings.php file
			$AppPresser_Customizer = new AppPresser_Customizer();

			$this->colors_setting_keys = array_merge($this->colors_setting_keys, array_keys( $AppPresser_Customizer->colors() ) );

			if( empty( $this->colors_setting_keys ) ) {
				return;
			}

			if( ! isset( $this->appp_settings['theme_mods_'.$this->stylesheet] ) ) {
				$this->appp_settings['theme_mods_'.$this->stylesheet] = array();
			} else if( isset( $this->appp_settings['theme_mods_'.$this->stylesheet] ) && is_string( $this->appp_settings['theme_mods_'.$this->stylesheet] ) ) {
				$this->appp_settings['theme_mods_'.$this->stylesheet] = array();
			}

			foreach ( $this->colors_setting_keys as $key ) {
				if( isset( $this->theme_mods[ $key ] ) && ! empty( $this->theme_mods[ $key ] ) ) {
					$this->appp_settings['theme_mods_'.$this->stylesheet][$key] = $this->theme_mods[$key];
				}
			}

		}
	}

	private function migrate_setting( $key, $type = 'string' ) {
		if( isset( $this->theme_mods[ $key ] ) && ! empty( $this->theme_mods[ $key ] ) ) {
			if( $type == 'int' ) {
				$this->appp_settings[ $key ] = (int)$this->theme_mods[ $key ];
			} else if( $type == 'checkbox' ) {
				if( $this->theme_mods[ $key ] == 1 ) {
					$this->appp_settings[ $key ] = 'on';
				}
			} else {
				$this->appp_settings[ $key ] = $this->theme_mods[ $key ];
			}
		}
	}

	private function save_settings() {
		update_option( 'appp_settings', $this->appp_settings );

		if( isset( $this->appp_settings['theme_mods_'.$this->stylesheet] ) ) {
			// Reload the page to refill the fields with these new settings.
			echo '<script type="text/javascript">location.reload()</script>';
		}
	}

}
