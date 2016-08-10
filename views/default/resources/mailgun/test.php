<?php

elgg_admin_gatekeeper();

$user = elgg_get_logged_in_user_entity();
$site = elgg_get_site_entity();

$subject = elgg_echo('mailgun:test:subject');

$message = elgg_view('mailgun/notification/body', [
	'subject' => $subject,
	'body' => elgg_echo('mailgun:test:body', [
		$user->name,
		elgg_get_plugins_path() . 'mailgun/views/default/mailgun/notification/body.php',
		$site->name,
		$site->url
	]),
	'recipient' => $user
		]);

$message = mailgun_css_inliner($message);

if (!empty($message)) {
	$message = mailgun_normalize_urls($message);
	$inline_opts = [
		'html' => $message,
	];
	$inline_opts = mailgun_inline_images($inline_opts);
	$message = $inline_opts['html'];
}

if (get_input('view')) {
	echo $message;
}

if (get_input('send')) {

	// Test sending attachments through notify_user()
	$to = $user->guid;
	$from = $site->guid;
	$subject = elgg_echo('mailgun:test:subject');

	$body = elgg_echo('mailgun:test:body', [
		$user->name,
		elgg_get_plugins_path() . 'mailgun/views/default/mailgun/notification/body.php',
		$site->name,
		$site->url
	]);

	$params = [
		'recipient' => $user,
		'attachments' => [
			dirname(__DIR__) . '/mailgun/manifest.xml',
		],
	];

	notify_user($to, $from, $subject, $body, $params, ['email']);
}