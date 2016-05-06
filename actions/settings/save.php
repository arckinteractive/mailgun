<?php

$params    = get_input('params');
$plugin_id = get_input('plugin_id');
$route     = get_input('route');
$route_id  = get_input('route_id');
 
$plugin    = elgg_get_plugin_from_id($plugin_id);

if (!($plugin instanceof ElggPlugin)) {
    register_error(elgg_echo('plugins:settings:save:fail', array($plugin_id)));
    forward(REFERER);
}

$plugin_name = $plugin->getManifest()->getName();

foreach ($params as $k => $v) {
    $result = $plugin->$k = $v;
}

// Create the storage directory if it doesn't exist
$path = elgg_get_config("dataroot") . 'mailgun';

if (!file_exists($path)) {
    mkdir($path, 0770, true);
}

if ($route['expression'] || $route_id) {

    $mg = mailgun_client();

    if ($route['expression']) {
        $mg->addRoute($route);
    }

    if ($route_id) {
        $mg->deleteRoute($route_id);
    }
}

system_message(elgg_echo('plugins:settings:save:ok', array($plugin_name)));
forward(REFERER);
