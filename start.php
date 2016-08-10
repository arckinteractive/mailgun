<?php

include_once(dirname(__FILE__) . '/vendor/autoload.php');

require_once(dirname(__FILE__) . '/lib/functions.php');
require_once(dirname(__FILE__) . '/lib/hooks.php');

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

	// A sample event handler
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
 * @param string  $event
 * @param string  $type
 * @param object  $message  \ArckInteractive\Mailgun\Message
 * @return bool
 */
function mailgun_sample_incoming_message_handler($event, $type, $message) {
	/* First we check to see if the message is for this plugin. 
	 * Plugin developers will assign some unique value to their 
	 * message after the + symbol in the recipient. 
	 * 
	 * This could be a unique token that is generated when a 
	 * notification is sent that allows a user to reply to the 
	 * email e.g. site1+a2bdy4dokf5dbs42ndasotirqn@em.mysite.com

	 * Or for emails coming in that are not from a reply this could 
	 * be a unique string e.g. site1+my_plugin@em.mysite.com
	 */

	// Get the unique token or string from the recipient
	$token = $message->getRecipientToken();

	// Direct imbound messages for this plugin use mgsample
	// as the token.
	if (preg_match("/mgsample/", $token)) {

		// This is a direct inbound message maybe to start a new blog post
		error_log('Received a direct inbound message: ' . $message->getMessageId());
	} else {

		$options = array(
			'type' => 'object',
			'subtyle' => 'mailgun_sample',
			'limit' => 1,
			'annotation_name_value_pairs' => array(
				'name' => 'msg_token',
				'value' => $token,
				'operator' => '='
			)
		);

		$entities = elgg_get_entities_from_annotations($options);

		if (!empty($entities)) {

			// Process the reply to this entity
			// Halt event propagation
			return false;
		}
	}
}

/**
 * Return our Mailgun client wrapper
 * 
 * The Mailgun HTTP client can be interacted with directly
 * by fetching the client with the getClient() method.
 *
 * @return object
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
