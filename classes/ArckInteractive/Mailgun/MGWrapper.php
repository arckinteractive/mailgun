<?php

namespace ArckInteractive\Mailgun;

use Mailgun\Mailgun;
use Flintstone\Flintstone;

class MGWrapper {

    private $apiKey = null;

    private $domain = null;

    /**
     * The Mailgun HTTP client
     */
    private $client = null;

    private $logMessages = true;

    private $storage = null;

    /**
     * Our Flinstone key/value database for storing message ID's
     */
    private $database = null;

    /**
     * The age in seconds of events that we will fetch. 
     * 
     * If cron is configured as suggested we will check for stored 
     * messages every 60 seconds however we will look for events that 
     * are eventAge seconds old to ensure that all events have
     * been processed.
     */
    private $eventAge = 1800;

    /**
     * maxAge specifies how long (in seconds) we will store message ID's.
     * 
     * Defaults to two days (172800) which is how long Mailgun will 
     * store messages for free accounts.
     */
    private $maxAge = 172800;

    private $recipient = null;

    private $nextPage = null;

    /**
     * Will hold the response code of the last request
     */
    private $responseCode = null;

    /**
     * Will hold the response message (if there is one) of the last request
     */
    private $responseMessage = null;

    /**
     * Class constructor
     *
     * @param string $apiKey Mailgun API key
     * @param string $domain The domain we are interacting with
     * @param string $path Path to the directory where we will store a key/value database
     * @return mixed
     */
    function __construct($apiKey, $domain, $path, $log=false)
    {
        $this->apiKey  = $apiKey;
        $this->domain  = $domain;
        $this->storage = $path;

        try {
            $this->client = new Mailgun($this->apiKey);
        } catch (Exception $e) {
            throw new Exception("Failed to initialize Mailgun client");
        }

        if ($log) {
            $this->logMessages = true;
        }
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getResponseCode()
    {
        return $this->responseCode;
    }

    public function getResponseMessage()
    {
        return $this->responseMessage;
    }

    /**
     * Set recipient for inbound message
     * 
     * This parameter is only used When polling for stored messages
     *
     * @param string $recipient The inbound recipient to match on
     * @return mixed
     */
    public function setRecipient($recipient)
    {
        $this->recipient = $recipient;
    }

    /**
     * Set the age of events that we will poll for new messages.
     *
     * @param string $seconds
     * @return mixed
     */
    public function setEventAge($seconds)
    {
        $this->eventAge = $seconds;
    }

    /**
     * Send a message
     * 
     * See https://documentation.mailgun.com/user_manual.html#sending-via-api
     *
     * @param array $options The message options
     * @param mixed $attachements An array of message attachments or null
     * @return mixed
     */
    public function sendMessage($options, $attachments=array())
    {
        $response = $this->client->sendMessage($this->domain, $options, $attachments);

        $this->responseCode    = $response->http_response_code;
        $this->responseMessage = $response->http_response_body->message;

        return $this->formatMessageId($response->http_response_body->id);
    }

    /**
     * Get routes
     * 
     * See https://documentation.mailgun.com/api-routes.html
     *
     * @param int $offset The starting offset
     * @param int $limit The number of items to fetch
     * @return mixed
     */
    public function getRoutes($offset=0, $limit=20)
    {
        $results = $this->client->get("routes", array('skip' => $offset, 'limit' => 20));

        $this->responseCode = $results->http_response_code;

        if ($results->http_response_body->total_count) {

            $routes = $results->http_response_body->items;

            // Sort the routes by priority
            usort($routes, function($a, $b) {
                return $a->priority - $b->priority;
            });

            return $routes;
        }

        return null;
    }

    /**
     * Add a route. 
     * 
     * See https://documentation.mailgun.com/api-routes.html
     *
     * @param array $route The route parameters
     * @return mixed
     */
    public function addRoute($route)
    {
        // Multiple actions are | delimited so explode them to an array
        $action = array_map('trim', explode('|', $route['action']));

        try {

            $result = $this->client->post("routes", array(
                'priority'    => $route['priority'],
                'expression'  => $route['expression'],
                'action'      => $action,
                'description' => $route['description']
            ));

            $this->responseCode = $result->http_response_code;

        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }

        return $result;
    }

    /**
     * Delete a route. 
     * 
     * See https://documentation.mailgun.com/api-routes.html
     *
     * @param string $route_id The route ID
     * @return mixed
     */
    public function deleteRoute($route_id)
    {
        $mgClient = null;

        try {
            $result = $this->client->delete("routes/{$route_id}");
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Triggers event handling and logs the message
     * 
     * @param object $message
     * @return void
     */
    public function processMessage($message)
    {        
        // Trigger the receive event so that plugins can process the message
        elgg_trigger_event('receive', 'mg_message', $message);

        // Store the message ID
        $this->storeId($message->getMessageId());

        if ($this->logMessages) {
            $this->logMessage($message);
        }
    }

    /**
     * Fetches the latest stored message events and processes
     * 
     * @param array $params The posted message parameters
     * @return void
     */
    public function processIncomingMessage($params)
    {
        $message = new Message($params);

        if (!$this->retrieveId($message->getMessageId())) {
            $this->processMessage($message);
        }
    }

    /**
     * Fetches the latest stored message events and processes
     * any new messages.
     * 
     * @return void
     */
    public function processStoredMessages()
    {
        while ($items = $this->getStoredEvents()) {

            foreach ($items as $item) {
     
                // Vverify this message is for us as recipient filtering
                // on stored messages is broken.            
                if (preg_match("/{$this->recipient}/", $item->message->headers->to)) {

                    if (!$this->retrieveId($item->message->headers->{'message-id'})) {
                    
                        try {
                    
                            // Fetch the message from Mailgun storage
                            $results = $this->client->get("domains/{$this->domain}/messages/{$item->storage->key}");

                            $this->processMessage(new Message($results->http_response_body));
                    
                        } catch (Exception $e) {
                            error_log($e->getMessage());
                        }
                    }
                }
            }
        }
    }

    /**
     * Fetches the latest stored message events.
     *
     * @return mixed
     */
    public function getStoredEvents($limit=100)
    {
        if (!$this->nextPage) {
         
            $tracker = time() - $this->eventAge;

            $queryString = array(
                'event'        => 'stored',
                'begin'        => $tracker,
                'ascending'    => 'yes',
                'limit'        =>  $limit
                //'recipient'    => $recipient // Recipient filter is not working on stored events
            );

            # Make the call to the client.
            $results = $this->client->get("{$this->domain}/events", $queryString);
        
        } else {

            $results = $this->client->get("{$this->domain}/events/{$this->nextPage}");
        }

        // Get the ID of the next page
        preg_match("/.*\/(\S+)$/", $results->http_response_body->paging->next, $match);

        // Set the nextr page
        $this->nextPage = $match[1];

        $items = $results->http_response_body->items;

        // Do we have any items
        if (!$numItems = count($items)) {
            return null;
        }

        // Do we have recent items
        $last = $items[$numItems - 1];

        if ($last->timestamp < $tracker) {
            return null;
        }

        return $items;
    }

    /**
     * Load our messages database
     *
     * @return mixed
     */
    private function getDatabase()
    {
        if (!$this->database) {
            $this->database = new Flintstone('messages', array('dir' => $this->storage));
        }

        return $this->database;
    }

    /**
     * Store a message ID in the database
     *
     * @param string $message_id The message ID
     * @return mixed
     */
    private function storeId($message_id)
    {
        $this->getDatabase()->set(md5($this->formatMessageId($message_id)), time());
    }

    /**
     * Get a message ID from the database
     *
     * @param string $message_id The message ID
     * @return mixed
     */
    private function retrieveId($message_id)
    {
        return $this->getDatabase()->get(md5($this->formatMessageId($message_id)));
    }

    /**
     * Delete a message ID from the database
     *
     * @param string $message_id The message ID
     * @return mixed
     */
    private function deleteId($message_id)
    {
        return $this->getDatabase()->delete(md5($this->formatMessageId($message_id)));
    }

    /**
     * Purge the database of ID's older than $maxAge
     *
     * @param string $message_id The route ID
     * @return mixed
     */
    public function purgeIds($maxAge=null)
    {
        $maxAge = isset($maxAge) ? $maxAge : $this->maxAge;

        foreach ($this->getDatabase()->getAll() as $hash => $ts) {
            if (time() - $ts > $maxAge) {
                $this->getDatabase()->delete($hash);
            }
        }
    }

    /**
     * Message ID's generally appear in the format:
     * 
     * <CAFLgxAFSaQGf0PgsFne62CU5jNOjVoQY1KRWfNtUtafrSrGWZQ@mail.gmail.com>
     * 
     * However, message ID's returned as part of an event object have the leading
     * and trailing <> removed. For consitenct we will always remove those 
     * characters 
     *
     * @param string $message_id The message ID
     * @return mixed
     */
    private function formatMessageId($message_id)
    {
        if (preg_match("/\<(.*)\>/", $message_id, $matches)) {
            $message_id = $matches[1];
        }

        return $message_id;
    }

    private function logMessage($message)
    {
        $path = $this->storage . '/messages';

        if (!file_exists($path)) {
            mkdir($path, 0770, true);
        }

        $id = $this->formatMessageId($message->getMessageId());

        $path = $path . '/' . $id;

        file_put_contents($path, print_r($message->getRawMessage(), true));
    }
}



