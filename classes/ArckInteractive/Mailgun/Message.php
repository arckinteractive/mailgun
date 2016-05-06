<?php

namespace ArckInteractive\Mailgun;

class Message {

    private $message = null;

    function __construct(\stdClass $message)
    {
        $this->message = array_change_key_case((array) $message);
    }

    public function __get($name)
    {
        if (property_exists($this->message->$name)) {
            return $this->message[$name];
        }
    }

    public function __call($method, $parameters)
    {
        $parsed = preg_replace_callback ('/([A-Z])/', create_function('$matches','return \'-\' . strtolower($matches[1]);'), $method);

        if (preg_match('/^get-(.*)/', $parsed, $matches)) {

            if (isset($this->message[$matches[1]])) {
                return $this->message[$matches[1]];
            }
        }
    }

    /**
     * Returns the raw message
     *      
     * @return array
     */
    public function getRawMessage()
    {
        return $this->message;
    }

    /**
     * Returns an array of the message headers
     *      
     * @return array
     */
    public function getHeaders()
    {
        return $this->message['message-headers'];
    }

    /**
     * Returns the value of the specified header
     *      
     * @return string
     */
    public function getHeader($header)
    {
        foreach ($this->getHeaders as $key => $array) {
            if ($array[0] === $header) {
                return $array[1];
            }
        }
    }

    /**
     * Convenience method to parse the rescipient token
     * from the recipient.
     *      
     * @return mixed
     */
    public function getRecipientToken()
    {
        if (preg_match("/^\w+\+(\S+)@.*/", $this->getTo(), $matches)) {
            return $matches[1];
        }
    }

    /**
     * Convenience method to parse the rescipient token
     * from the recipient.
     * 
     * @param  string  $email the senders email address
     * @param  string  $token The token to insert or if null a token will be generated.
     * @return array   
     */
    public static function addToken($email, $token=null)
    {
        if (preg_match('/\+/', $email)) {
            return throw new Exception('The email address already includes a token.');
        }

        if (!$token) {
            $token = preg_replace('/-/', '', Uuid::uuid1()->toString());
        }

        $parts = explode('@', $email);

        $newEmail = $parts[0] . '+' . $token . '@' . $parts[1];

        return array('email' => $newEmail, 'token' => $token);
    }
}