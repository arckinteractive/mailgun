<?php

use ArckInteractive\Mailgun\Message;

/**
 * Handle incoming message
 *
 * @param string  $event   "receive"
 * @param string  $type    "mg_message"
 * @param Message $message Incoming message
 * @return bool
 */
function mailgun_incoming_message_handler($event, $type, $message) {

	$token = $message->getRecipientToken();
	if (!$token) {
		return;
	}

	$entity = mailgun_get_entity_by_notifcation_token($token);
	if (!$entity instanceof ElggEntity) {
		return;
	}

	$sender = $message->getSender();
	if (!$sender) {
		return;
	}
	
	$subject = $message->getSubject();
	$body = $message->getText();
	
	if ($entity instanceof ElggUser) {
		if (elgg_is_active_plugin('messages') && $sender->guid != $entity->guid) {

			$ia = elgg_set_ignore_access(true);
			$result = messages_send($subject, $body, $entity->guid, $sender->guid);
			elgg_set_ignore_access($ia);

			if ($result) {
				elgg_log("Message {$message->getMessageId()} has been saved as personal message to {$entity->getDisplayName()}");
				return false; // terminate event
			}
		}
	} else if ($entity instanceof ElggObject) {
		$subtype = $entity->getSubtype();
		switch ($subtype) {

			case 'messages' :
				$recipient = get_entity($entity->fromId);
				if (elgg_is_active_plugin('messages') && $recipient && $sender->guid != $recipient->guid) {
					
					$ia = elgg_set_ignore_access(true);
					$result = messages_send($subject, $body, $recipient->guid, $sender->guid, $entity->guid);
					elgg_set_ignore_access($ia);

					if ($result) {
						elgg_log("Message {$message->getMessageId()} has been saved as personal message to {$recipient->getDisplayName()}");
						return false;
					}
				}
				break;

			default :
				if (in_array($subtype, ['comment', 'discussion_reply'])) {
					if (!$entity->canComment($sender->guid)) {
						// Check if nested comments/replies are supported
						$entity = $entity->getContainerEntity();
					}
				}

				$response_type = $subtype == 'discussion' ? 'discussion_reply' : 'comment';
				if (!$entity->canWriteToContainer($sender->guid, 'object', $response_type)) {
					// not allowed to respond
					return;
				}

				$class = get_subtype_class('object', $response_type);
				if (!$class) {
					$class = ElggObject::class;
				}

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

				if ($guid) {
					elgg_log("Message {$message->getMessageId()} has been saved as a comment [guid: {$response->guid}] 
						on {$entity->getDisplayName()} [guid: {$entity->guid}]");
					return true;
				}

				break;
		}
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
