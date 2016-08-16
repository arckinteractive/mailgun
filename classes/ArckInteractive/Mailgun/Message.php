<?php

namespace ArckInteractive\Mailgun;

use Elgg\Mail\Address;
use LogicException;
use Ramsey\Uuid\Uuid;
use stdClass;

class Message {

	private $message = null;

	function __construct(stdClass $message) {
		$this->message = array_change_key_case((array) $message);
	}

	public function __get($name) {
		if (property_exists($this->message->$name)) {
			return $this->message[$name];
		}
	}

	public function __call($method, $parameters) {
		$parsed = preg_replace_callback('/([A-Z])/', create_function('$matches', 'return \'-\' . strtolower($matches[1]);'), $method);

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
	public function getRawMessage() {
		return $this->message;
	}

	/**
	 * Returns an array of the message headers
	 *
	 * @return array
	 */
	public function getHeaders() {
		return $this->message['message-headers'];
	}

	/**
	 * Returns the value of the specified header
	 *
	 * @return string
	 */
	public function getHeader($header) {
		foreach ($this->getHeaders as $key => $array) {
			if ($array[0] === $header) {
				return $array[1];
			}
		}
	}

	/**
	 * Convenience method to parse the rescipient token
	 * from the recipient email
	 * @return string
	 */
	public function getRecipientToken() {
		if (preg_match("/\+(\S+)@.*/", $this->getToEmail(), $matches)) {
			return $matches[1];
		}
		return '';
	}

	/**
	 * Get To email
	 * @return string
	 */
	public function getToEmail() {
		$address = Address::fromString($this->getTo());
		return $address->getEmail();
	}

	/**
	 * Parses From email address
	 * @return string
	 */
	public function getFromEmail() {
		$address = Address::fromString($this->getFrom());
		return $address->getEmail();
	}

	/**
	 * Parses From name
	 * @return string
	 */
	public function getFromName() {
		$address = Address::fromString($this->getFrom());
		return $address->getName();
	}

	/**
	 * Get sender as an ElggUser
	 * @return \ElggUser|false
	 */
	public function getSender() {
		$from = $this->getFromEmail();
		$sender = get_user_by_email($from);
		return ($sender) ? $sender[0] : false;
	}

	/**
	 * Returns plaintext version of the email
	 * 
	 * @param bool $stripped If true, will strip email signature and quoted part
	 * @return string
	 */
	public function getText($stripped = true) {
		if ($stripped) {
			return $this->message['stripped-text'];
		} else {
			return $this->message['body-plain'];
		}
	}

	/**
	 * Convenience method to add a token to an email
	 *
	 * @param  string  $email Senders email address
	 * @param  string  $token Token to insert or if null a token will be generated.
	 * @return array
	 */
	public static function addToken($email = null, $token = null) {
		if (!$email) {
			$email = elgg_get_site_entity()->email;
		}

		if (preg_match('/\+/', $email)) {
			throw new LogicException('The email address already includes a token.');
		}

		if (!$token) {
			$token = Uuid::uuid1()->toString();
		}

		$parts = explode('@', $email);

		$newEmail = $parts[0] . '+' . $token . '@' . $parts[1];

		return array(
			'email' => $newEmail,
			'token' => $token,
		);
	}

}
