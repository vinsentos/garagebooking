
<?php
/**
 * Plugin Name: GarageBooking
 * Plugin URI: https://github.com/vinsentos/garageBooking
 * Description: A garage booking plugin with DVLA API integration and update feature.
 * Version: 1.0.0
 * Author: Tosin Orojinmi
 * Author URI: mailto:info@tosinorojinmi.com
 * Text Domain: garagebooking
 * Domain Path: /languages
 * 
 * GitHub Plugin URI: vinsentos/garageBooking
 * GitHub Branch: main
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('GARAGEBOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GARAGEBOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once GARAGEBOOKING_PLUGIN_DIR . 'includes/class-garagebooking.php';

// Initialize Plugin
function garagebooking_init() {
    new GarageBooking();
}
add_action('plugins_loaded', 'garagebooking_init');
?>
