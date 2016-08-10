<?php

return array(
	'mailgun' => "Mailgun Bi-directional E-mail Handler",
	
	'mailgun:theme_preview:menu' => "HTML notification",
	
	// settings
	'mailgun:settings:apikey'          => 'API Key',
	'mailgun:settings:domain'          => 'Domain',
	'mailgun:settings:embed'           => 'Embed Images',
	'mailgun:settings:inbound'         => 'Inbound Routing',
	'mailgun:settings:polling'         => 'Poll for stored messages',
	'mailgun:settings:polling:subtext' => 'This option should only be enabled if you are using a store action in your inbound routing and requires that cron be configured.',
	
	'mailgun:settings:inbound:info' => 'The inbound routing rules determine how Mailgun will handle incoming email. Routing rules can be added to forward email to an Internet accessible host or to store messages for retrieval via cron. View the <a href="https://documentation.mailgun.com/user_manual.html#receiving-forwarding-and-storing-messages">Mailgun route documentation</a> for more information.<br /><br />When the plugin processes an inboud email it will trigger an event passing the message object. Other plugins can register for the event and determine if the message is theirs or not. In this way any plugin can be built to support inbound email. The Mailgun plugin will not process or store inbound messages internally meaning that after the event is triggered mailgun will exit without any further processing.',
	
	'mailgun:settings:actions:subtext' => 'Multiple actions are delimited with the | symbol ',
	'mailgun:settings:callbacks:subtext' => 'Callback URL\'s to be used in route actions.',
	
	'mailgun:settings:handlers:subtext'  => 'To register a handler use:<br /><br />
		elgg_register_event_handler(\'receive\', \'mg_message\', \'your_plugin_callback_function\');<br /><br />

		A plugin will determine if the message belongs to it by the recipients email address. Generally this is a token or unique string before the @ symbol. 
		If the message belongs to a plugin it should return false to stop further event propagation.

		The following event handlers have been registered for processing incoming messages:',

	'mailgun:settings:stored:info' => 'If a route has been configured to store messages then use the options below to configure message retrieval.',

	'mailgun:settings:stored:recipient'         => 'Recipient Match',
	'mailgun:settings:stored:recipient:subtext' => 'e.g. site2 would match on a message sent to site2+uniquetoken@em.domain.com and a route expression like match_recipient("^site2\+.*@em.domain.com")',

	'mailgun:settings:event:age' => 'Event Age (seconds)',
	'mailgun:settings:event:age:subtext' => 'To find new messages we make a request to the events API specifying an ascending time range that begins some time in the past (e.g. half an hour ago). If for some reason your server was down or cron was not polling you can increase this value to look for new messages further in the past. See <a href="https://documentation.mailgun.com/api-events.html#event-polling">https://documentation.mailgun.com/api-events.html#event-polling</a>',
	
	// notification body
	'mailgun:notification:footer:settings' => "Configure your notification settings %shere%s",

	'mailgun:test:subject' => 'Mailgun Message Test',
	'mailgun:test:body' => '%s, 

		This email template is located at:

		%s 

		Do not modify this file directly as it will be overwritten when updating the plugin. Instead, 
		override the view in your theme or another plugin. 
		
		This email was sent to you using the notify_user() method. Plugin developers can also call 
		mailgun_send_email() directly.

		Site name: %s
		Site URL: %s

		This elgg logo is embedded as an inline image:

		<img src="https://elgg.org/images/elgg_small.png">

		The manifest for this plugin (manifest.xml) is included as an attachment.
	'
);
