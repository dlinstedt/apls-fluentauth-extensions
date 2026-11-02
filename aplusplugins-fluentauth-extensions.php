<?php
/**
 * Plugin Name: APlusPlugins Registration Extensions for FluentAuth
 * Plugin URI: https://aplusplugins.com/
 * Description: Extensible FluentAuth registration validation, optional consent and CRM synchronization.
 * Version: 1.0.30
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: fluent-security
 * Author: APlusPlugins
 * Author URI: https://aplusplugins.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: aplusplugins-fluentauth-extensions
 * Domain Path: /languages
 */
namespace APlusPlugins\FluentAuthExtensions;
if (!defined('ABSPATH')) { exit; }
const VERSION = '1.0.30';
const FILE = __FILE__;
require_once __DIR__ . '/includes/class-plugin.php';
add_action('plugins_loaded', [Plugin::class, 'boot'], 30);

register_activation_hook(__FILE__, [Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Plugin::class, 'deactivate']);
