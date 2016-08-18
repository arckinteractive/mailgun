<?php

namespace ArckInteractive\Mailgun;

use Elgg\Mail\Address;
use ElggEntity;
use ElggFile;
use ElggUser;
use LogicException;
use Ramsey\Uuid\Uuid;
use stdClass;

class Message {

	private $message = null;

	/**
	 * @var ElggEntity
	 */
	private $target;

	/**
	 * @var ElggUser
	 */
	private $sender;

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
	 * @return ElggUser|false
	 */
	public function getSender() {
		if (!isset($this->sender)) {
			$from = $this->getFromEmail();
			$sender = get_user_by_email($from);
			$this->sender = ($sender) ? $sender[0] : false;
		}
		return $this->sender;
	}

	/**
	 * Returns the entity that is targeted by the token of this message
	 * @return ElggEntity|false
	 */
	public function getTargetEntity() {
		if (!isset($this->target)) {
			$token = $this->getRecipientToken();
			if ($token) {
				$entity = mailgun_get_entity_by_notifcation_token($token);
				if ($entity instanceof ElggEntity) {
					$this->target = $entity;
				} else {
					$this->target = false;
				}
			} else {
				$this->target = false;
			}
		}
		return $this->target;
	}

	/**
	 * Convert attachments to ElggFile instances
	 *
	 * @param array $attributes Attributes assigned to new file entities
	 * @return ElggFile[]
	 */
	public function getAttachments(array $attributes = []) {

		$files = [];
		$attachments = $this->message['attachments'];
		if (empty($attachments)) {
			return $files;
		}

		$subtype = elgg_extract('subtype', $attributes, 'file', false);
		unset($attributes['subtype']);
		
		$class = get_subtype_class('object', $subtype);
		if (!$class || !class_exists($class) || !is_subclass_of($class, ElggFile::class)) {
			$class = ElggFile::class;
		}

		$ia = elgg_set_ignore_access(true);

		foreach ($attachments as $attachment) {
			$raw = MGWrapper::fetch($attachment->url);
			if (!$raw || empty($raw->http_response_body)) {
				continue;
			}

			$file = new $class();
			$file->subtype = $subtype;
			foreach ($attributes as $key => $value) {
				$file->$key = $value;
			}

			$originalfilename = $attachment->name;
			$file->originalfilename = $originalfilename;
			if (empty($file->title)) {
				$file->title = htmlspecialchars($file->originalfilename, ENT_QUOTES, 'UTF-8');
			}

			$file->upload_time = time();
			$prefix = $file->filestore_prefix ? : 'file';
			$prefix = trim($prefix, '/');
			$filename = elgg_strtolower("$prefix/{$file->upload_time}{$file->originalfilename}");
			$file->setFilename($filename);
			$file->filestore_prefix = $prefix;

			$file->open('write');
			$file->write($raw->http_response_body);
			$file->close();

			$mime_type = $file->detectMimeType(null, $attachment->{"content-type"});
			$file->setMimeType($mime_type);
			$file->simpletype = elgg_get_file_simple_type($mime_type);

			if (!$file->save() || !$file->exists()) {
				$file->delete();
				continue;
			}

			if (is_callable([$file, 'saveIconFromElggFile']) && $file->saveIconFromElggFile($file)) {
				$file->thumbnail = $file->getIcon('small')->getFilename();
				$file->smallthumb = $file->getIcon('medium')->getFilename();
				$file->largethumb = $file->getIcon('large')->getFilename();
			}

			$files[] = $file;
		}

		elgg_set_ignore_access($ia);

		return $files;
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
