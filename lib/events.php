<?php

use ArckInteractive\Mailgun\Message;

/**
 * Create a new personal message from an inbound email
 *
 * @param Message $message Incoming message
 * @return bool
 */
function mailgun_create_personal_message(Message $message) {

	if (!elgg_is_active_plugin('messages')) {
		return;
	}

	$sender = $message->getSender();
	if (!$sender) {
		return false;
	}

	$entity = $message->getTargetEntity();
	if ($entity instanceof ElggUser) {
		$recipient = $entity;
	} else {
		$ia = elgg_set_ignore_access(true);
		$recipient = get_entity($entity->fromId);
		elgg_set_ignore_access($ia);
	}

	if (!$recipient || $recipient == $sender) {
		return false;
	}

	$attachments = [];
	if (elgg_is_active_plugin('hypeAttachments')) {
		$attachments = $message->getAttachments();
		$attachment_guids = [];
		foreach ($attachments as $attachment) {
			$attachment_guids[] = $attachment->guid;
		}
		// hypeAttachments will handle the rest
		set_input('message_attachments', $attachment_guids);
	}

	$subject = $message->getSubject();
	$body = $message->getText();

	$ia = elgg_set_ignore_access(true);
	$result = messages_send($subject, $body, $recipient->guid, $sender->guid);
	elgg_set_ignore_access($ia);

	if (!$result) {
		// Cleanup attachments
		foreach ($attachments as $attachment) {
			$attachment->delete();
		}
		return false;
	}

	elgg_log("Message {$message->getMessageId()} has been saved "
	. "as personal message to {$entity->getDisplayName()}");

	return true;
}

/**
 * Create a new comment/discussion reply from an inbound email
 *
 * @param Message $message Incoming message
 * @return bool
 */
function mailgun_create_response(Message $message) {
	
	$sender = $message->getSender();
	if (!$sender) {
		return false;
	}

	$entity = $message->getTargetEntity();
	if (!$entity) {
		return false;
	}

	$subtype = $entity->getSubtype();
	if (in_array($subtype, ['comment', 'discussion_reply'])) {
		if (!$entity->canComment($sender->guid)) {
			// Check if nested comments/replies are supported
			// If not, grab the entity the original comment was made on
			$ia = elgg_set_ignore_access(true);
			$entity = $entity->getContainerEntity();
			elgg_set_ignore_access($ia);
		}
	}

	if (!$entity) {
		return false;
	}

	$response_type = $subtype == 'discussion' ? 'discussion_reply' : 'comment';
	if (!$entity->canWriteToContainer($sender->guid, 'object', $response_type)) {
		// The sender is not allowed to comment/reply
		return false;
	}

	$class = get_subtype_class('object', $response_type);
	if (!$class) {
		$class = ElggObject::class;
	}

	$subject = $message->getSubject();
	$body = $message->getText();

	$ia = elgg_set_ignore_access(true);

	$response = new $class();
	$response->subtype = $response_type;
	$response->owner_guid = $sender->guid;
	$response->container_guid = $entity->guid;
	$response->title = $subject;
	$response->description = $body;
	$response->access_id = $entity->access_id;
	$guid = $response->save();

	elgg_set_ignore_access($ia);

	if (!$guid) {
		return false;
	}

	if (elgg_is_active_plugin('hypeAttachments')) {
		$attachments = $message->getAttachments();
		foreach ($attachments as $attachment) {
			$attachment->origin = 'attachments';
			$attachment->access_id = $response->access_id;
			$attachment->container_guid = $response->container_guid;
			$attachment->save();
			hypeapps_attach($response, $attachment);
		}
	}

	elgg_log("Message {$message->getMessageId()} has been saved as a comment [guid: {$response->guid}] "
	. "on {$entity->getDisplayName()} [guid: {$entity->guid}]");

	return true;
}

/**
 * Handle incoming message
 *
 * @param string  $event   "receive"
 * @param string  $type    "mg_message"
 * @param Message $message Incoming message
 * @return bool
 */
function mailgun_incoming_message_handler($event, $type, $message) {

	$entity = $message->getTargetEntity();
	
	if ($entity instanceof ElggUser || elgg_instanceof($entity, 'object', 'messages')) {
		// inbound message is targeted at a user or is a response to a personal message
		$created = mailgun_create_personal_message($message);
	} else if ($entity instanceof ElggGroup) {
		// inbound messages targeted at a group become discussion topics
		//$created = mailgun_create_discussion($message);
	} else if ($entity instanceof ElggObject) {
		$created = mailgun_create_response($message);
	}

	if ($created) {
		return false; // terminate event
	}
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
