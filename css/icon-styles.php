<?php

if ( AppPresser::is_mp6() ) {
	echo '<style type="text/css">
   	   #adminmenu .toplevel_page_apppresser_settings .wp-menu-image {
	   width: 28px;
	   height: 28px;
	   background-image: url("' . plugins_url( 'images/icon.svg', __DIR__ ) . '") !important;
	   background-position: 5px 1px !important;
		background-size: 70px 30px;
		margin-right: 5px;
	}
	#adminmenu li.toplevel_page_apppresser_settings.wp-has-current-submenu a.wp-has-current-submenu .wp-menu-image {
		background-position: -40px 1px !important;
	}
	#adminmenu .toplevel_page_apppresser_settings .wp-menu-image:before {
		content: "" !important;
	}
	 </style>';
} else {
	echo '<style type="text/css">
	#adminmenuwrap #adminmenu .toplevel_page_apppresser_settings .wp-menu-image {
	   width: 28px;
	   height: 28px;
	   background-image: url("' . plugins_url( 'images/icon.svg', __DIR__ ) . '");
	   background-position: 5px 0 !important;
		background-size: 60px 30px;
	}
	#adminmenu li#toplevel_page_apppresser_settings.wp-has-current-submenu a.wp-has-current-submenu .wp-menu-image {
		background-position: -34px 0 !important;
	}
	</style>';
}
