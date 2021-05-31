<?php

	namespace Nox\Http;

	/**
	* An abstraction for the request payload
	*/
	class Request
	{

		/**
		 * Fetches the raw body of a request
		 */
		public function getRawBody(): string
		{
			return file_get_contents("php://input");
		}

		/**
		 * Fetches a value from the POST payload. Safe-checks with isset
		 * @param string $name
		 * @param mixed $default Will return this if the POST[$name] is not set
		 * @return mixed
		 */
		public function getPostValue(string $name, mixed $default): mixed
		{
			if (isset($_POST[$name])) {
				return $_POST[$name];
			} else {
				return $default;
			}
		}

		/**
		 * Fetches a value from the GET query parameters. Safe-checks with isset
		 * @param string $name
		 * @param mixed $default Will return this if the GET[$name] is not set
		 * @return mixed
		 */
		public function getGetValue(string $name, mixed $default): mixed
		{
			if (isset($_GET[$name])) {
				return $_GET[$name];
			} else {
				return $default;
			}
		}

		/**
		 * Fetches a value from the cookie string in the request. Safe-checks with isset
		 * @param string $name
		 * @param mixed $default Will return this if the GET[$name] is not set
		 * @return mixed
		 */
		public function getCookieValue(string $name, mixed $default): mixed
		{
			if (isset($_COOKIE[$name])) {
				return $_COOKIE[$name];
			} else {
				return $default;
			}
		}

		/**
		 * Old method of fetching files from the FILE payload.
		 * @deprecated
		 */
		public function getFileValue(string $value): ?array
		{

			if (empty($_FILES)) {
				return null;
			}

			if (!isset($_FILES[$value])) {
				return null;
			}

			// tmp_name can be an array for multiple files
			if (is_array($_FILES[$value]['tmp_name'])) {
				foreach ($_FILES[$value]['tmp_name'] as $tmp_name) {
					if (!is_uploaded_file($tmp_name)) {
						return null;
					}
				}
			} else {
				if (!is_uploaded_file($_FILES[$value]['tmp_name'])) {
					return null;
				}
			}

			// Error can also be an array
			if (is_array($_FILES[$value]['error'])) {
				foreach ($_FILES[$value]['error'] as $error) {
					if ($error != 0) {
						return null;
					}
				}
			} else {
				if ($_FILES[$value]['error'] != 0) {
					return null;
				}
			}

			return $_FILES[$value];
		}

		/**
		 * Attempts to fetch the IP of the originating request.
		 */
		public function getIP(): string
		{
			if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
				// IP is from shared internet
				return $_SERVER['HTTP_CLIENT_IP'];
			} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				// IP is from a proxy
				return $_SERVER['HTTP_X_FORWARDED_FOR'];
			} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
				// IP is from a remote address
				return $_SERVER['REMOTE_ADDR'];
			}

			return "";
		}

		/**
		 * Parses multipart/form-data
		 */
		public function processRequestBody(): array
		{
			$requestMethod = strtolower($_SERVER['REQUEST_METHOD']);
			$contentType = $_SERVER['CONTENT_TYPE'];
			preg_match("/multipart\/form-data; boundary=(.+)/", $contentType, $matches);
			$boundary = $matches[1];
			if ($requestMethod === "patch" || $requestMethod === "post" || $requestMethod === "put") {
				$formData = [];
				$parsedFormData = $this->getAllFormDataFromRequest($boundary);
				foreach ($parsedFormData as $packet) {
					foreach ($packet['headers'] as $header) {
						$parsedHeader = $this->parseHeaderValue($header);
						if (array_key_exists("name", $parsedHeader)){
							$formData[$parsedHeader['name']] = $packet['body'];
						}
					}
				}

				return $formData;
			}else{
				return [];
			}
		}

		/**
		 * Processes all the form-data in the raw request
		 */
		private function getAllFormDataFromRequest(string $boundary): array
		{
			$data = [];
			$rawBody = file_get_contents("php://input");
			$state = "PARSE_BOUNDARY";
			$currentPacket = null;
			$buffer = "";
			$lastHeaderName = "";
			$index = 0;
			$iterations = 0;
			$maxIterations = 10000;
			while (isset($rawBody[$index])) {
				++$iterations;
				if ($iterations > $maxIterations) {
					break;
				}
				$char = $rawBody[$index];
				$nextChar = $rawBody[$index + 1] ?? "";
				if ($state === "PARSE_BOUNDARY") {

					if ($buffer === "--" . $boundary) {
						if ($char === "-") {
							// Could signify ending of data
							$buffer .= $char;
							$state = "PARSE_LAST_BOUNDARY";
							++$index;
						} else {
							$textChar = "";
							if ($char === "\r"){
								$textChar = '\r';
							}elseif ($char === "\n"){
								$textChar = '\n';
							}else{
								$textChar = $char;
							}
							if ($char === "\r" && $nextChar === "\n") {
								// Line over, boundary parsed
								$currentPacket = [
									"headers" => [],
									"body" => "",
								];
								$buffer = "";
								$state = "PARSING_PACKET_HEADERS";
								$index += 2;
							}
						}
					} else {
						// Continue to consume
						$buffer .= $char;
						++$index;
					}
				} elseif ($state === "PARSE_LAST_BOUNDARY") {
					if ($char === "-") {
						// Second - hit
						// Done
						break;
					}
				} elseif ($state === "PARSING_PACKET_HEADERS") {
					if ($char === ":") {
						// Finished parsing a header
						$lastHeaderName = $buffer;
						$currentPacket['headers'][$lastHeaderName] = "";
						$buffer = "";
						++$index;
					} else {
						if ($char === "\r" && $nextChar === "\n") {
							if (empty($buffer)) {
								// This means a blank line which separates the headers
								// from the form-data body content
								$state = "PARSING_PACKET_BODY";
							} else {
								// End of header line
								$currentPacket['headers'][$lastHeaderName] = $buffer;
								$buffer = "";
							}
							$index += 2;
						} else {
							$buffer .= $char;
							++$index;
						}
					}
				} elseif ($state === "PARSING_PACKET_BODY") {
					if ($buffer === "--" . $boundary) {
						// Swap state, don't clear the buffer
						// But the body of this packet is done being parsed
						// Also don't increase the index.
						$state = "PARSE_BOUNDARY";
						$data[] = $currentPacket;
						$lastHeaderName = "";
					} else {
						if ($char === "\r" && $nextChar === "\n") {
							// Append data to current packet body
							$currentPacket['body'] .= $buffer;
							$buffer = "";
							$index += 2;
						} else {
							$buffer .= $char;
							++$index;
						}
					}
				}
			}

			return $data;
		}

		/**
		 * Parses a header into a key value pair
		 */
		private function parseHeaderValue(string $headerValue): array
		{
			$header = [];
			preg_match("/^(.+);(.*)/", $headerValue, $matches);
			$header['value'] = trim($matches[1]);
			preg_match_all("/([^=]+)=\"(.+?)\"/", $matches[2], $attributeMatches);
			foreach($attributeMatches[1] as $index=>$attributeName) {
				$header[trim($attributeName)] = trim($attributeMatches[2][$index]);
			}
			return $header;
		}

	}
