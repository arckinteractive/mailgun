<?php

include_once(dirname(__FILE__) . '/autoloader.php');

require_once(dirname(__FILE__) . '/lib/functions.php');
require_once(dirname(__FILE__) . '/lib/hooks.php');
require_once(dirname(__FILE__) . '/lib/events.php');

use ArckInteractive\Mailgun\MGWrapper;

// register default Elgg events
elgg_register_event_handler('init', 'system', 'mailgun_init');

/**
 * Gets called during system initialization
 *
 * @return void
 */
function mailgun_init() {

	elgg_register_page_handler('mg', 'mailgun_page_handler');

	// Register the incoming message webhook as a public page
	elgg_register_plugin_hook_handler('public_pages', 'walled_garden', 'mailgun_public_pages');

	// Check for stored inbound emails
	elgg_register_plugin_hook_handler('cron', 'minute', 'mailgun_fetch_stored_messages');

	// Purge stored message ID's
	elgg_register_plugin_hook_handler('cron', 'daily', 'mailgun_purge_message_ids');

	// Clean image cache
	elgg_register_plugin_hook_handler('cron', 'daily', 'mailgun_image_cache_cleanup');

	// Handler that takes care of sending emails as HTML
	elgg_register_plugin_hook_handler('email', 'system', 'mailgun_email_handler');

	// Automatically add tokens to notifications with an event object
	elgg_unregister_plugin_hook_handler('send', 'notification:email', '_elgg_send_email_notification');
	elgg_register_plugin_hook_handler('send', 'notification:email', 'mailgun_send_email_notification');

	// Handle incoming mail
	// Setting higher priority, so that other plugins' handlers are called first
	elgg_register_event_handler('receive', 'mg_message', 'mailgun_incoming_message_handler', 800);

	// A sample event handler (for testing)
	//elgg_register_event_handler('receive', 'mg_message', 'mailgun_sample_incoming_message_handler');

	$action_base = elgg_get_plugins_path() . 'mailgun/actions';
	elgg_register_action('mailgun/settings/save', "$action_base/settings/save.php", 'admin');

}

/**
 * The page handler for mailgun
 *
 * @param array $page the page elements
 *
 * @return bool
 */
function mailgun_page_handler($page) {

	switch ($page[0]) {

		case 'test':
			echo elgg_view_resource('mailgun/test');
			break;

		case 'messages':
			mailgun_client()->processIncomingMessage($_POST);
			break;

		case 'webhooks':
			mailgun_webhooks_page_handler();
			break;
	}

	return true;
}

/**
 * Return our Mailgun client wrapper
 * 
 * The Mailgun HTTP client can be interacted with directly
 * by fetching the client with the getClient() method.
 *
 * @return MGWrapper
 */
function mailgun_client() {
	$apiKey = elgg_get_plugin_setting('api_key', 'mailgun');
	$domain = elgg_get_plugin_setting('domain', 'mailgun');
	$path = elgg_get_config("dataroot") . 'mailgun';

	try {
		$client = new MGWrapper($apiKey, $domain, $path, false);
	} catch (Exception $e) {
		register_error($e->getMessage());
	}

	return $client;
}
