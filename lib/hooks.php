<?php

use ArckInteractive\Mailgun\Message;
use Elgg\Notifications\Event;
use Elgg\Notifications\Notification;

/**
 * Send an email notification
 *
 * @param string $hook   Hook name
 * @param string $type   Hook type
 * @param bool   $result Has anyone sent a message yet?
 * @param array  $params Hook parameters
 * @return bool
 */
function mailgun_send_email_notification($hook, $type, $result, $params) {

	if ($result === true) {
		return;
	}

	$notification = elgg_extract('notification', $params);
	if (!$notification instanceof Notification) {
		return false;
	}

	$sender = $notification->getSender();
	$recipient = $notification->getRecipient();

	if (!$sender) {
		return false;
	}

	if (!$recipient || !$recipient->email) {
		return false;
	}

	$to = $recipient->email;

	$site = elgg_get_site_entity();
	$from = $site->email ? : 'noreply@' . $site->getDomain(); // Always use site email
	$params['from_name'] = $sender->getDisplayName(); // But set the name to that of the sender

	// was the token provided with $params through notify_user()
	$token = elgg_extract('token', $params);
	if (!$token) {
		// check if token has been added to notification by "prepare", "notification:*" hook
		$token = $notification->token;
	}

	if (!$token) {
		// check if we have a notification event with an object
		$event = elgg_extract('event', $params);
		if ($event instanceof Event) {
			$object = $event->getObject();
			$token = mailgun_get_entity_notification_token($object, $event->getDescription());
		}
	}

	$params['token'] = $token;
	
	return elgg_send_email($from, $to, $notification->subject, $notification->body, $params);
}

/**
 * If a route has been configured with a store action and polling is 
 * enabled the function will fetch any new messages since the last 
 * poll cycle.
 *
 * @param string $hook   the name of the hook
 * @param string $type   the type of the hook
 * @param mixed  $return current return value
 * @param array  $params supplied params
 *
 * @return void
 */
function mailgun_fetch_stored_messages($hook, $type, $return, $params) {
	if (!elgg_get_plugin_setting('polling', 'mailgun')) {
		return;
	}

	$recipient = elgg_get_plugin_setting('recipient', 'mailgun');
	$age = elgg_get_plugin_setting('event_age', 'mailgun');

	$mg = mailgun_client();

	$mg->setRecipient($recipient);
	$mg->setEventAge($age);

	$mg->processStoredMessages();
}

/**
 * Cleanup the cached inline images
 *
 * @param string $hook   the name of the hook
 * @param string $type   the type of the hook
 * @param mixed  $return current return value
 * @param array  $params supplied params
 *
 * @return void
 */
function mailgun_purge_message_ids($hook, $type, $return, $params) {
	$mg = mailgun_client();

	$mg->purgeIds();
}

/**
 * Cleanup the cached inline images
 *
 * @param string $hook   the name of the hook
 * @param string $type   the type of the hook
 * @param mixed  $return current return value
 * @param array  $params supplied params
 *
 * @return void
 */
function mailgun_image_cache_cleanup($hook, $type, $return, $params) {

	if (empty($params) || !is_array($params)) {
		return;
	}

	$cache_dir = elgg_get_data_path() . 'mailgun/image_cache/';
	if (!is_dir($cache_dir)) {
		return;
	}

	$dh = opendir($cache_dir);
	if (empty($dh)) {
		return;
	}

	$max_lifetime = elgg_extract('time', $params, time()) - (24 * 60 * 60);

	while (($filename = readdir($dh)) !== false) {
		// make sure we have a file
		if (!is_file($cache_dir . $filename)) {
			continue;
		}

		$modified_time = filemtime($cache_dir . $filename);
		if ($modified_time > $max_lifetime) {
			continue;
		}

		// file is past lifetime, so cleanup
		unlink($cache_dir . $filename);
	}

	closedir($dh);
}

/**
 * Sends out a full HTML mail
 *
 * @param string $hook         'email'
 * @param string $type         'system'
 * @param array  $return_value In the format:
 *     to => STR|ARR of recipients in RFC-2822 format (http://www.faqs.org/rfcs/rfc2822.html)
 *     from => STR of senden in RFC-2822 format (http://www.faqs.org/rfcs/rfc2822.html)
 *     subject => STR with the subject of the message
 *     body => STR with the message body
 *     plaintext_message STR with the plaintext version of the message
 *     html_message => STR with the HTML version of the message
 *     cc => NULL|STR|ARR of CC recipients in RFC-2822 format (http://www.faqs.org/rfcs/rfc2822.html)
 *     bcc => NULL|STR|ARR of BCC recipients in RFC-2822 format (http://www.faqs.org/rfcs/rfc2822.html)
 *     date => NULL|UNIX timestamp with the date the message was created
 *     attachments => NULL|ARR of array(array('mimetype', 'filename', 'content'))
 * @param array  $params       The unmodified core parameters
 *
 * @return void|bool
 */
function mailgun_email_handler($hook, $type, $return_value, $params) {

	// if someone else handled sending they should return true|false
	if (empty($return_value) || !is_array($return_value)) {
		return;
	}

	$additional_params = elgg_extract('params', $return_value);

	if (is_array($additional_params)) {
		$return_value = array_merge($return_value, $additional_params);
	}

	if (!isset($return_value['from'])) {
		$site = elgg_get_site_entity();
		$return_value['from'] = $site->email ? : 'noreply@' . $site->getDomain();
		$return_value['from_name'] = $site->name;
	}

	if (!empty($return_value['token']) && !preg_match('/\+(\S+)@.*/', $return_value['from'])) {
		// Add a token to the email
		$token = Message::addToken($return_value['from'], $return_value['token']);
		$return_value['from'] = mailgun_build_rfc822_address($token['email'], $return_value['from_name']);
	}

	return mailgun_send_email($return_value);
}

function mailgun_public_pages($hook, $handler, $return, $params) {
	if (!is_array($return)) {
		$return = array();
	}

	$return[] = "mg/messages";
	$return[] = "mg/webhooks";

	return $return;
}
