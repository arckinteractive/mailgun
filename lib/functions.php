<?php

/**
 * All helpder functions for this plugin can be found here
 */
use Elgg\Notifications\Notification;
use Pelago\Emogrifier;

/**
 * Sends out a full HTML mail
 * 
 * Parts of this method are copied from the 
 * html_email_handler plugin by ColdTrick IT Solutions.
 * (C) ColdTrick IT Solutions 2011 - 2016
 *
 * @param array $options In the format:
 *     to => STR|ARR of recipients in RFC-2822 format (http://www.faqs.org/rfcs/rfc2822.html)
 *     from => STR of senden in RFC-2822 format (http://www.faqs.org/rfcs/rfc2822.html)
 *     subject => STR with the subject of the message
 *     body => STR with the message body
 *     text STR with the plaintext version of the message
 *     html => STR with the HTML version of the message
 *     cc => NULL|STR|ARR of CC recipients in RFC-2822 format (http://www.faqs.org/rfcs/rfc2822.html)
 *     bcc => NULL|STR|ARR of BCC recipients in RFC-2822 format (http://www.faqs.org/rfcs/rfc2822.html)
 *     date => NULL|UNIX timestamp with the date the message was created
 *     attachments => NULL|ARR of array(array('mimetype', 'filename', 'content'))
 *
 * @return bool
 */
function mailgun_send_email(array $options = null) {

	static $limit_subject;

	$site = elgg_get_site_entity();

	// make site email
	$site_from = mailgun_make_rfc822_address($site);

	if (!isset($limit_subject)) {

		$limit_subject = false;

		if (elgg_get_plugin_setting("limit_subject", "mailgun") == "yes") {
			$limit_subject = true;
		}
	}

	// set default options
	$default_options = array(
		"to" => array(),
		"from" => $site_from,
		"subject" => "",
		"html" => "",
		"text" => "",
		"cc" => array(),
		"bcc" => array(),
		"date" => null,
		"template" => 'mailgun/notification/body'
	);

	// merge options
	$options = array_merge($default_options, $options);

	// redo to/from for notifications
	$notification = elgg_extract('notification', $options);

	if (!empty($notification) && ($notification instanceof Notification)) {

		$recipient = $notification->getRecipient();
		$sender = $notification->getSender();

		$options['to'] = mailgun_make_rfc822_address($recipient);

		if (!isset($options['recipient'])) {
			$options['recipient'] = $recipient;
		}

		if (!isset($options['from'])) {
			if (!($sender instanceof ElggUser) && $sender->email) {
				$options['from'] = mailgun_make_rfc822_address($sender);
			} else {
				$options['from'] = $site_from;
			}
		}
	}

	if (empty($options['html']) && empty($options['text'])) {
		$options['html'] = mailgun_make_html_body($options);
		$options['text'] = $options['body'];
	}

	// can we send a message
	if (empty($options["to"]) || (empty($options["html"]) && empty($options["text"]))) {
		return false;
	}

	// normalize URL's in the message text
	if (!empty($options['text'])) {
		$options['text'] = mailgun_normalize_urls($options['text']);
	}

	/* It looks like mailgun is base64 encoding the entire email which is possibly messing up base64 encoded embeds.
	 * we should CID these instead.
	 */
	$options['html'] = mailgun_normalize_urls($options['html']);

	// linkify text urls
	$options['html'] = parse_urls($options['html']);

	$options = mailgun_inline_images($options);

	// encode subject to handle special chars
	$subject = $options["subject"];

	// Decode any html entities
	$subject = html_entity_decode($subject, ENT_QUOTES, 'UTF-8');

	if ($limit_subject) {
		$subject = elgg_get_excerpt($subject, 175);
	}

	$subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";

	// $images = elgg_extract("images", $image_attachments);

	$message = array(
		'from' => $options['from'],
		'subject' => $subject,
		'text' => $options['text'],
		'html' => $options['html']
	);

	$message["to"] = is_array($options["to"]) ? implode(", ", $options["to"]) : $options["to"];

	if (!empty($options["cc"])) {
		$message["cc"] = is_array($options["cc"]) ? implode(", ", $options["cc"]) : $options["cc"];
	}

	if (!empty($options["bcc"])) {
		$message["bcc"] = is_array($options["bcc"]) ? implode(", ", $options["bcc"]) : $options["bcc"];
	}

	$attachments = array();

	// Add attachments
	if (!empty($options["attachments"])) {
		$attachments['attachment'] = $options["attachments"];
	}

	// Addy any inline images
	if (!empty($options["inline"])) {
		$attachments['inline'] = $options["inline"];
	}

	try {
		# Instantiate the Mailgun wrapper.
		$mg = mailgun_client();

		# Make the call to the client.
		$message_id = $mg->sendMessage($message, $attachments);

		return $message_id;
	} catch (\Exception $ex) {
		elgg_log($ex->getMessage(), 'ERROR');
		return false;
	}
}

/**
 * This function converts CSS to inline style, the CSS needs to be found in a <style> element
 * 
 * This method copied from the html_email_handler
 * plugin by ColdTrick IT Solutions.
 * (C) ColdTrick IT Solutions 2011 - 2016
 *
 * @param string $html_text the html text to be converted
 * @return false|string
 */
function mailgun_css_inliner($html_text) {
	$result = false;

	if (!empty($html_text) && defined("XML_DOCUMENT_NODE")) {
		$css = "";

		// set custom error handling
		libxml_use_internal_errors(true);

		$dom = new DOMDocument();
		$dom->loadHTML($html_text);

		$styles = $dom->getElementsByTagName("style");

		if (!empty($styles)) {
			$style_count = $styles->length;

			for ($i = 0; $i < $style_count; $i++) {
				$css .= $styles->item($i)->nodeValue;
			}
		}

		// clear error log
		libxml_clear_errors();

		$emo = new Emogrifier($html_text, $css);
		$result = $emo->emogrify();
	}

	return $result;
}

/**
 * Make the HTML body from a $options array
 * 
 * This method copied from the html_email_handler
 * plugin by ColdTrick IT Solutions.
 * (C) ColdTrick IT Solutions 2011 - 2016
 *
 * @param array  $options the options
 * @param string $body    the message body
 *
 * @return string
 */
function mailgun_make_html_body($options = "", $body = "") {

	global $CONFIG;

	$defaults = array(
		"subject" => "",
		"body" => "",
		"language" => get_current_language()
	);

	$options = array_merge($defaults, $options);

	$options['body'] = parse_urls($options['body']);

	// in some cases when pagesetup isn't done yet this can cause problems
	// so manualy set is to done
	$unset = false;
	if (!isset($CONFIG->pagesetupdone)) {
		$unset = true;
		$CONFIG->pagesetupdone = true;
	}

	// generate HTML mail body
	$result = elgg_view($options['template'], $options);

	// do we need to restore pagesetup
	if ($unset) {
		unset($CONFIG->pagesetupdone);
	}

	if (defined("XML_DOCUMENT_NODE")) {
		if ($transform = mailgun_css_inliner($result)) {
			$result = $transform;
		}
	}

	return $result;
}

/**
 * This function build an RFC822 compliant address
 *
 * This function requires the option 'entity'
 * 
 * This method copied from the html_email_handler
 * plugin by ColdTrick IT Solutions.
 * (C) ColdTrick IT Solutions 2011 - 2016
 *
 * @param ElggEntity $entity       entity to use as the basis for the address
 * @param bool       $use_fallback provides a fallback email if none defined
 *
 * @return string the correctly formatted address
 */
function mailgun_make_rfc822_address(ElggEntity $entity, $use_fallback = true) {
	// get the email address of the entity
	$email = $entity->email;
	if (empty($email) && $use_fallback) {
		// no email found, fallback to site email
		$site = elgg_get_site_entity();

		$email = $site->email;
		if (empty($email)) {
			// no site email, default to noreply
			$email = "noreply@" . $site->getDomain();
		}
	}

	$name = '';
	if (!empty($entity->name)) {
		$name = $entity->name;
	}

	return mailgun_build_rfc822_address($email, $name);
}

/**
 * Build an RFD822 address from email and name
 * 
 * @param string $email Email
 * @param string $name  Name
 * @return string
 */
function mailgun_build_rfc822_address($email, $name = '') {

	if (strstr($name, ",")) {
		$name = '"' . $name . '"'; // Protect the name with quotations if it contains a comma
	}

	$name = "=?UTF-8?B?" . base64_encode($name) . "?="; // Encode the name. If may content non ASCII chars.
	return $name . " <" . $email . ">";
}

/**
 * Normalize all URL's in the text to full URL's
 * 
 * This method copied from the html_email_handler
 * plugin by ColdTrick IT Solutions.
 * (C) ColdTrick IT Solutions 2011 - 2016
 *
 * @param string $text the text to check for URL's
 * @return string
 */
function mailgun_normalize_urls($text) {

	static $pattern = '/\s(?:href|src)=([\'"]\S+[\'"])/i';

	if (empty($text)) {
		return $text;
	}

	if (preg_match('/cid:/', $text) || preg_match('/data:/', $text)) {
		return $text;
	}

	// find all matches
	$matches = array();

	preg_match_all($pattern, $text, $matches);

	if (empty($matches) || !isset($matches[1])) {
		return $text;
	}

	// go through all the matches
	$urls = $matches[1];
	$urls = array_unique($urls);

	foreach ($urls as $url) {

		// remove wrapping quotes from the url
		$real_url = substr($url, 1, -1);

		// normalize url
		$new_url = elgg_normalize_url($real_url);

		// make the correct replacement string
		$replacement = str_replace($real_url, $new_url, $url);

		// replace the url in the content
		$text = str_replace($url, $replacement, $text);
	}

	return $text;
}

/**
 * Convert images to inline images
 *
 * This can be enabled with a plugin setting (default: off)
 * 
 * This method copied from the html_email_handler
 * plugin by ColdTrick IT Solutions.
 * (C) ColdTrick IT Solutions 2011 - 2016
 *
 * @param string $text the text of the message to embed the images from
 * @return string
 */
function mailgun_inline_images($options) {

	static $plugin_setting;

	if (empty($options['html']) || !elgg_get_plugin_setting('embed_images', 'mailgun')) {
		return $options;
	}

	$image_urls = mailgun_find_images($options['html']);

	if (empty($image_urls)) {
		return $options;
	}

	foreach ($image_urls as $url) {

		if (preg_match('/cid:/', $url)) {
			continue;
		}

		// remove wrapping quotes from the url
		$image_url = substr($url, 1, -1);

		// get the image contents
		$contents = mailgun_get_image($image_url);

		if (!$contents) {
			continue;
		}

		// build inline image
		$replacement = str_replace($image_url, "cid:" . $contents['name'], $url);

		// replace in text
		$options['html'] = str_replace($url, $replacement, $options['html']);
		$options['inline'][] = $contents['path'];
	}

	return $options;
}

/**
 * Get the contents of an image url for embedding\
 * 
 * This method copied from the html_email_handler
 * plugin by ColdTrick IT Solutions.
 * (C) ColdTrick IT Solutions 2011 - 2016
 *
 * @param string $image_url the URL of the image
 * @return false|string
 */
function mailgun_get_image($image_url) {

	static $proxy_host;
	static $proxy_port;
	static $session_cookie;
	static $cache_dir;

	if (empty($image_url)) {
		return false;
	}

	$image_url = htmlspecialchars_decode($image_url);
	$image_url = elgg_normalize_url($image_url);

	// check cache
	if (!isset($cache_dir)) {

		$cache_dir = elgg_get_config("dataroot") . "mailgun/image_cache/";

		if (!is_dir($cache_dir)) {
			mkdir($cache_dir, "0755", true);
		}
	}

	$cache_file = md5($image_url) . '.jpg';

	if (file_exists($cache_dir . $cache_file)) {
		return array('path' => $cache_dir . $cache_file, 'name' => $cache_file);
	}

	// build cURL options
	$ch = curl_init($image_url);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);

	// set proxy settings
	if (!isset($proxy_host)) {
		$proxy_host = false;

		$setting = elgg_get_plugin_setting("proxy_host", "mailgun");
		if (!empty($setting)) {
			$proxy_host = $setting;
		}
	}

	if ($proxy_host) {
		curl_setopt($ch, CURLOPT_PROXY, $proxy_host);
	}

	if (!isset($proxy_port)) {
		$proxy_port = false;

		$setting = (int) elgg_get_plugin_setting("proxy_port", "mailgun");
		if ($setting > 0) {
			$proxy_port = $setting;
		}
	}

	if (!empty($proxy_port)) {
		curl_setopt($ch, CURLOPT_PROXYPORT, $proxy_port);
	}

	// check if local url, so we can send Elgg cookies
	if (strpos($image_url, elgg_get_site_url()) !== false) {

		if (!isset($session_cookie)) {

			$session_cookie = false;

			$cookie_settings = elgg_get_config("cookie");

			if (!empty($cookie_settings)) {
				$cookie_name = elgg_extract("name", $cookie_settings["session"]);
			} else {
				$cookie_name = 'Elgg';
			}

			$session_cookie = $cookie_name . "=" . session_id();
		}

		if (!empty($session_cookie)) {
			curl_setopt($ch, CURLOPT_COOKIE, $session_cookie);
		}
	}

	// get the image
	$contents = curl_exec($ch);
	$http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	if (empty($contents) || ($http_code !== 200)) {
		return false;
	}

	// write to cache
	file_put_contents($cache_dir . $cache_file, $contents);

	// return result
	return array('path' => $cache_dir . $cache_file, 'name' => $cache_file);
}

/**
 * Find img src's in text
 * 
 * This method copied from the html_email_handler
 * plugin by ColdTrick IT Solutions.
 * (C) ColdTrick IT Solutions 2011 - 2016
 *
 * @param string $text the text to search though
 * @return false|array
 */
function mailgun_find_images($text) {
	static $pattern = '/\ssrc=([\'"]\S+[\'"])/i';

	if (empty($text)) {
		return false;
	}

	// find all matches
	$matches = array();
	preg_match_all($pattern, $text, $matches);

	if (empty($matches) || !isset($matches[1])) {
		return false;
	}

	// return all the found image urls
	return array_unique($matches[1]);
}

/**
 * Get a notification token for an object
 *
 * @param ElggEntity $entity  Entity
 * @param string     $handler Optional handler (context, event etc)
 * @return string|false
 */
function mailgun_get_entity_notification_token($entity = null, $handler = 'default') {

	if (!$entity instanceof ElggEntity) {
		return false;
	}

	if (!$entity->{"mg:$handler"}) {
		$hmac = elgg_build_hmac([
			'guid' => $entity->guid,
			'handler' => $handler,
		]);
		$entity->{"mg:$handler"} = $hmac->getToken();
	}

	return $entity->{"mg:$handler"};
}

/**
 * Returns an entity that is associated with a notification token
 * 
 * @param string $token Notification token
 * @return ElggEntity|false
 */
function mailgun_get_entity_by_notifcation_token($token) {

	$ia = elgg_set_ignore_access(true);

	$token = sanitize_string($token);
	$dbprefix = elgg_get_config('dbprefix');
	$entities = elgg_get_entities([
		'joins' => [
			"JOIN {$dbprefix}metadata md ON md.entity_guid = e.guid",
			"JOIN {$dbprefix}metastrings msn ON msn.id = md.name_id",
			"JOIN {$dbprefix}metastrings msv ON msv.id = md.value_id",
		],
		'wheres' => [
			"msn.string LIKE 'mg:%' AND msv.string = '$token'",
		],
		'limit' => 1,
	]);

	elgg_set_ignore_access($ia);

	return $entities ? $entities[0] : false;
}
