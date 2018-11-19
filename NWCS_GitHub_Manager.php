<?php
/*
Plugin Name: NWCS GitHub Manager
Plugin URI: https://github.com/nwcybersolutions/NWCS-GitHub-Manager
Description: Install, Update, and Manage GitHub Repositories (Themes & Plugins)
Author: Northwest Cyber Solutions
Author URI: https://nwcybersolutions.com
Version: 2.4.6
License: MIT
License URI: https://opensource.org/licenses/MIT
Text Domain: NWCS GitHub Manager
Domain Path: /languages
*/

// If this file is called directly, abort.
if ( ! defined('WPINC')) {
    die;
}

require __DIR__ . '/autoload.php';

use Pusher\ActionHandlers\ActionHandlerProvider;
use Pusher\Pusher;
use Pusher\PusherServiceProvider;

$pusher = new Pusher;
$pusher->setInstance($pusher);
$pusher->pusherPath = plugin_dir_path(__FILE__);
$pusher->pusherUrl = plugin_dir_url(__FILE__);
$pusher->register(new PusherServiceProvider);
$pusher->register(new ActionHandlerProvider);

do_action('nwcybersolutions_gm_register_dependency', $pusher);

register_activation_hook(__FILE__, array($pusher, 'activate'));

require_once('wp-updates-plugin.php');
new WPUpdatesPluginUpdater_957('https://dashboard.nwcybersolutions.com/api/releases/latest', plugin_basename(__FILE__));

$pusher->init();

if ( ! function_exists('getHostIcon')) {
    function getHostIcon($host)
    {
        if ($host === 'gh') {
            return 'fa-github';
        } elseif ($host === 'bb') {
            return 'fa-bitbucket';
        } else {
            return 'fa-gitlab';
        }
    }
}

if ( ! function_exists('getHostBaseUrl')) {
    function getHostBaseUrl($host)
    {
        if ($host === 'gh') {
            return 'https://github.com/';
        } elseif ($host === 'bb') {
            return 'https://bitbucket.org/';
        } elseif ($host === 'gl') {
            return trailingslashit(get_option('gl_base_url'));
        } else {
            return null;
        }
    }
}

$hidePluginsFromUpdateChecks = function($args, $url) use ($pusher)
{
    if (0 !== strpos($url, 'https://api.wordpress.org/plugins/update-check')) {
        return $args;
    }

    $plugins = json_decode($args['body']['plugins'], true);

    $repository = $pusher->make('Pusher\Storage\PluginRepository');
    $pluginsToHide = array_keys($repository->allPusherPlugins());
    $pluginsToHide[] = plugin_basename(__FILE__);

    foreach ($pluginsToHide as $plugin) {
        unset($plugins['plugins'][$plugin]);
        unset($plugins['active'][array_search($plugin, $plugins['active'])]);
    }

    $args['body']['plugins'] = json_encode($plugins);

    return $args;
};

$hideThemesFromUpdateChecks = function($args, $url) use ($pusher)
{
    if (0 !== strpos($url, 'https://api.wordpress.org/themes/update-check')) {
        return $args;
    }

    $themes = json_decode($args['body']['themes'], true);

    $repository = $pusher->make('Pusher\Storage\ThemeRepository');
    $themesToHide = array_keys($repository->allPusherThemes());

    foreach ($themesToHide as $theme) {
        unset($themes['themes'][$theme]);

        if (isset($themes['active']) and in_array($themes['active'], $themesToHide)) {
            unset($themes['active']);
        }
    }

    $args['body']['themes'] = json_encode($themes);

    return $args;
};

add_filter('http_request_args', $hidePluginsFromUpdateChecks, 5, 2);
add_filter('http_request_args', $hideThemesFromUpdateChecks, 5, 2);

// Add link to help page
add_action('admin_menu', function () {
    global $submenu;

    $submenu['nwcybersolutions_gm'][] = array('Get Help', 'manage_options', 'https://github.com/nwcybersolutions/NWCS-GitHub-Manager');
});

// Dismiss welcome hero
if (isset($_GET['nwcybersolutions_gm-welcome']) and $_GET['nwcybersolutions_gm-welcome'] == '0') {
    update_option('hide-nwcybersolutions_gm-welcome', true);
}

if ( ! function_exists('pusherTableName()')) {
    function pusherTableName()
    {
        global $wpdb;
        $dbPrefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;

        return $dbPrefix . 'nwcybersolutions_gm_packages';
    }
}

if ( ! function_exists('pusher')) {
    /**
     * @return \Pusher\Pusher
     */
    function pusher() {
        return \Pusher\Pusher::getInstance();
    }
}
