<?php

/**
 * Plugin Name: PFM Panel
 * Description: Custom WooCommerce order management panel for Particle for Men.
 * Version: 1.0
 * Author: Particle for Men
 */

<<<<<<< HEAD

 //Plugin.php

=======
>>>>>>> f5b007a70eb1c253267053db148c7ed2e27286a9
defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-pfm-panel-plugin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-pfmp-rest-orders.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-pfmp-rest-stats.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-pfmp-rest-subscriptions.php';
<<<<<<< HEAD
require_once plugin_dir_path(__FILE__) . 'includes/class-pfmp-rest-customers.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-pfmp-admin.php';

register_activation_hook(__FILE__, ['PFMP_Admin', 'register_roles']);

=======
require_once plugin_dir_path(__FILE__) . 'includes/class-pfmp-admin.php';

>>>>>>> f5b007a70eb1c253267053db148c7ed2e27286a9
new PFM_Panel_Plugin();
