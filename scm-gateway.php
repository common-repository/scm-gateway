<?php
/*
Plugin Name: SCM Gateway
Description: Provides the functioanlity to connect your blog onWP to SCM system
Author: Completo
Version: 1.1.1
Author URI: http://completo.ru/
*/
add_action('plugins_loaded', 'scm_gateway_load_textdomain');
function scm_gateway_load_textdomain(){
	// Loading the translation file
	// Place MO file (binary transaltion file compilled from PO plain file)
	// How to create file: http://ru.wplang.org/wordpress-perevod-temy-plaginy/
	// Conver from PO to MO: https://po2mo.net/
	load_plugin_textdomain('scm_gateway', FALSE, dirname(plugin_basename(__FILE__)).'/languages/');
}
if (!defined('ABSPATH')) {
	exit;
}
define('SCM_GATEWAY_VERSION', '1.1.1');
define('SCM_GATEWAY_PATH', dirname(__FILE__)."/");
define('SCM_GATEWAY_CLASS_PATH', SCM_GATEWAY_PATH."classes/");
define('SCM_GATEWAY_ADMIN_PAGES_PATH', SCM_GATEWAY_PATH."pages/admin/");
include_once(SCM_GATEWAY_CLASS_PATH."class.scm_gateway.php");
$GLOBALS['scm_gateway'] = new SCM_gateway();
?>