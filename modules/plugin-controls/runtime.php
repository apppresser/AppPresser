<?php
/** Loaded by the MU plugin before WordPress selects ordinary plugins. */
defined( 'ABSPATH' ) || exit;
if ( class_exists( 'AppPresserPluginControls', false ) ) { return; }
if ( ! defined( 'APPPRESSER_PLUGIN_CONTROLS_LOADED' ) ) { define( 'APPPRESSER_PLUGIN_CONTROLS_LOADED', true ); }
require_once __DIR__ . '/class-plugin-controls.php';
AppPresserPluginControls::run();
