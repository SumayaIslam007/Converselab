<?php
/*
 * Plugin Name: Converselab Notes
 * Description: A full-stack WordPress plugin for creating and displaying notes.
 * Version: 0.1.0
 * Author: Sumaya
 */

if(!defined('ABSPATH')){
    exit;
}

/**
 * Constants
 */

define('CONVERSELAB_VERSION', '1.0.0');
define('CONVERSELAB_TEXT_DOMAIN', 'converselab');
define('CONVERSELAB_FILE', __FILE__ );
define('CONVERSELAB_PATH', plugin_dir_path(__FILE__));
define('CONVERSELAB_URL', plugin_dir_url(__FILE__));

/** 
 * Autoload + bootstrap
 */

require_once CONVERSELAB_PATH . 'includes/autoload.php';
require_once CONVERSELAB_PATH . 'includes/classes/class-noteposttype.php';
require_once CONVERSELAB_PATH . 'includes/classes/class-apiroutes.php';
/** 
 * Lifecycle hooks
 */

register_activation_hook(__FILE__, ['ConverseLab\Plugin', 'set_defaults']);
register_deactivation_hook(__FILE__, ['ConverseLab\Plugin', 'deactivate']);

ConverseLab\Plugin::init();
ConverseLab\NotePostType::init();
ConverseLab\ApiRoutes::init();