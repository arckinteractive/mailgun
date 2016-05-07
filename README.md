
Mailgun Client for Elgg
--------------------------

*Mailgun* is a transactional Email API Service for developers by Rackspace. It offers a complete cloud-based email service for sending, receiving and tracking email sent through your websites and applications. 

Visit [http://www.mailgun.com/](http://www.mailgun.com/) for more information about Mailgun and to register for a free account.

### Features ###

* Handles sending HTML emails with attachments and inline images.
* Inbound email handling by polling for stored messages or by receiving direct messages posts.
* Automatically detects and remove signature blocks and quoted replies.
* All messages encoded to UTF-8 automatically.
* Powerful inbound routing rules allow for multiple sites or developers to share a single domain.



### Configuration ###

#### Outbound ####

Visit https://mailgun.com/signup to signup for a free Mailgun account if you do not already have one. The first thing you need to do in your Mailgun account is to create and verify a domain. Refer to the [Mailgun documentation](https://help.mailgun.com/hc/en-us/articles/202052074-How-do-I-verify-my-domain-) for that step.

The settings required for sending email are the simplest. You just need your API key and domain.

![Plugin Settings](https://www.dropbox.com/s/vn587ehgt2xabb8/Plugin_Settings___Dev_Arck_io.png?dl=1&pv=1)

A sample email template is included in views/default/mailgun/notification/body.php and should be modified for your site by overriding the view. You can view that the template will look like by clicking the View Email Template button. Additionally, clicking the Send Test Email button will send an email to the currently logged in user.

##### Site Email Address #####

The site email address needs to match your configured domain. In your basic site settings you would use for example notifications@em.domain.com.

![Site Email Address](https://www.dropbox.com/s/fd8uzd4pgexgce9/Settings___Basic_Settings___Dev_Arck_io.png?dl=1&pv=1)

#### Inbound ####

Handling inbound emails requires configuring either a store or forward route. If you are a developer working locally where your system cannot be connected to from the public Internet you would configure a route to store your messages for later retrieval. Alternately for a production site you would configure a route to forward messages directly to the site.

Mailgun routes can be configured directly through the plugin settings. Read up on [Mailgun routes](https://documentation.mailgun.com/user_manual.html#routes) before continuing.

##### Forward Route #####

Assuming our configured domain is em.domain.com and we wants all emails sent to `notifications+TOKEN@em.domain.com` to be forwarded to our site we would add a route like:

    Priority:    0
    Expression:  match_recipient('^notifications\+.*@em.domain.com')
    Action:      forward('http://www.domain.com/mg/messages')
    Description: Production notification replies

In the recipient address everything after the + symbol is the unique token (or string) that is used by plugins to identify the message. When the message is received by the mailgun plugin it triggers an event. Other plugins that have registered for the event will check the token and if it is theirs they will process the email and return false to halt further event propagation. 

##### Store Route #####

Using the same domain name as the forward route example (em.domain.com) lets assume you are a developer and need to be able to test inbound email handling but your local development environment can not receive webhooks (posts) from the public Internet. Your route might look like this:

    Priority:    0
    Expression:  match_recipient('^testing\+.*@em.domain.com')
    Action:      store() | stop()
    Description: Production notification replies

In this example any emails matching `testing+TOKEN@em.domain.com` will be stored on the Mailgun servers. With free accounts Mailgun will hold stored messages for 2 days. 

When adding actions to routes multiple actions can be chained together. Use the | symbol to delimit multiple actions. In this example the stop() action stops processing of any lower priority routes.

Next, configure your stored messages to be retrieved. This requires enabling polling and configure a 1 minute cron.

![Message Polling Settings](https://www.dropbox.com/s/0sfql3t3bk5zbg6/Plugin_Settings..png?dl=1&pv=1)

Notice that we need to specify a recipient here. This is a simple string match and is required because you could potentially have multiple store routes configured. When the poller runs we want it to only retrieve messages stored for this testing / developer site. That's it! Now every 60 seconds the poller will fetch any emails sent to your testing email address. 

### Developer Notes ###

The Mailgun plugin provides the settings and functionality to fetch or receive inbound emails however how those emails are handled is up to other plugins. For instance your forum could allow users to reply to notifications by adding a unique token to the outbound emails and registering to receive inbound emails from mailgun. 

I created an additional plugin that can be used as a further example called mailgun_messages. The mailgun messages plugin enhances the core messages plugin to allow email replies to site messages.

The following code samples show how to add a unique token to your outbound emails and to register for and receive email from the mailgun plugin.

#### Sending notifications ####

In this example we have a forum plugin that notifies subscribed members when a new topic is posted.

```php
function send_new_topic_notification($topic)
{
	// Notification options
	$options = array(
		'subject"  => $topic->title,
		'body"     => $topic->description
	);

	// Get the topic subscribers
	$subscribers = $topic->getSubscribers();

	// Get the site email address
	$site_email =  elgg_get_site_entity()->email;

	// Add a token to the site email
	$token = ArckInteractive\Mailgun\Message::addToken($site_email);

	// Store the token on the topic so we can recognize replies
	$topic->reply_token = $token['token'];

	// Set the From address
	$options['from'] = $token['email'];

	// In production run this method in a batch and use Vroom
	foreach ($subscribers as $user) {
		$options['to'] = $user->email;
		mailgun_send_email($options);
	}
}
```

#### Receiving Replies ####

Now to handle all the deep and meaningful replies to the forum topic we can use something like this:

```php
// Register for the receive message event
elgg_register_event_handler('receive', 'mg_message', 'handle_topic_replies');

function handle_topic_replies($message)
{
	// Get the token from the recipient email
	$token = $message->getRecipientToken();
	
	// Query to see if this token belongs to this forum plugin.
	$results = elgg_get_entities_from_metadata(array(
		'type'    => 'object',
		'subtype' => 'forum_topic',
		'limit'   => 1,
		'metadata_name_value_pairs' => array(
			'name'       => 'reply_token',
			'value'      => $token,
			'operator'   => '='
		)
	));
	
	// If this is not our token just return
	if (empty($results)) {
		return;
	}

	// Set the topic
	$topic = $results[0];

	// Get the Elgg user from the sender
	$user = get_user_by_email($message->getSender());

	// We verify the sending user by the senders email address.
	if (empty($user)) {
		return;
	}

	$topic->annotate(
		'topic_reply', 
		$message->getStrippedText(),
		$topic->access_id, 
		$user[0]->guid
	);

	// Stop event propagation
	return false;
}
```

> Written with [StackEdit](https://stackedit.io/).