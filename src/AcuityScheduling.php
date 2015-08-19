<?php

class AcuityScheduling {

	protected $base = 'https://acuityscheduling.com';
	protected $userId = null;
	protected $apiKey = null;
	protected $lastStatusCode = null;

	public function __construct($options) {
		$this->apiKey = $options['apiKey'];
		$this->userId = $options['userId'];
		$this->base		= $options['base'] ? $options['base'] : $this->base;
	}

	public function request($path, $options = []) {
		$url = $this->base.'/api/v1/'.$path;
		return $this->_request($url, array_merge($options, [
			'json' => true,
			'username' => $this->userId,
			'password' => $this->apiKey
		]));
	}

	public function getLastRequestInfo() {
		return [
			'status_code' => $this->lastStatusCode
		];
	}

	/**
	 * Request helper.
	 */
	protected function _request($url, $options) {

		// Set defaults:
		$options = array_merge([
			'method'   => 'GET',
			'username' => null,
			'password' => null,
			'json'     => false,
			'headers'  => []
		], $options);
		$method = $options['method'];
		$headers = $options['headers'];
		$query = $options['query'];
		$data = $options['data'];
		$json = $options['json'] === true;

		// Data type:
		if ($data) {
			if ($json) {
				$headers['Content-Type'] = 'application/json';
				$data = is_string($data) ? $data : json_encode($data);
			} else {
				$headers['Content-Type'] = 'application/x-www-form-urlencoded';
				$data = is_string($data) ? $data : http_build_query($data);
			}
		}
		if ($query && is_array($query)) {
			$url .= '?'.http_build_query($query);
		}

		// Headers:
		$header = [];
		foreach ($headers as $key => $value) {
			$header[] = "{$key}: {$value}";
		}

		// Config:
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		if ($options['username'] && $options['password']) {
			curl_setopt($ch, CURLOPT_USERPWD, "{$options['username']}:{$options['password']}");
		}

		// Methods:
		switch ($method) {
		case 'GET':
			break;
		case 'POST':
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			break;
		case 'PUT':
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		case 'DELETE':
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		default:
			throw new Exception("Invalid request method ({$method})");
		}

		// Request:
		$result = curl_exec($ch);
		$this->lastStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$headerLength = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$body = substr($result, $headerLength);
		$body = $json ? json_decode($body, true) : $body;

		return $body;
	}

	/**
	 * Verify a message signature using your API key.	
	 */
	public static function verifyMessageSignature($secret, $body = null, $signature = null) {

		// Compute hash of message using shared secret:
		$body = is_null($body) ? file_get_contents('php://input') : $body;
		$hash = base64_encode(hash_hmac('sha256', $body, $secret, true));

		// Compare hash to the signature:
		$signature = is_null($signature) ? $_SERVER['HTTP_X_ACUITY_SIGNATURE'] : $signature;
		if ($hash !== $signature) {
			throw new Exception('This message was forged!');
		}
	}

	public static function getEmbedCode($owner, $options)
	{
		$owner = htmlspecialchars($owner);
		$defaults = [
			'height' => '800',
			'width' => '100%'
		];
		foreach ($options as $key => $option) {
			$defaults[$key] = htmlspecialchars($option);
		}
		return
			"<iframe src=\"https://app.acuityscheduling.com/schedule.php?owner={$owner}\" width=\"{$defaults['width']}\" height=\"{$defaults['height']}\" frameBorder=\"0\"></iframe>".
			'<script src="https://d3gxy7nm8y4yjr.cloudfront.net/js/embed.js" type="text/javascript"></script>';
	}
}