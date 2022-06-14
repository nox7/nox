<?php

	namespace Nox\Http;

	use Nox\ORM\MySQLDataTypes\Text;

	/**
	 * An abstraction for the request payload
	 */
	class Request
	{

		private static RequestPayload | null $lastProcessedRequestPayload = null;

		public static function getRequestPayload(): RequestPayload | null{
			return self::$lastProcessedRequestPayload;
		}

		public static function setRequestPayload(RequestPayload $requestPayload): void{
			self::$lastProcessedRequestPayload = $requestPayload;
		}

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
		 * @param array{headers: array, body: string} $formDataPacket
		 * @return bool
		 */
		private function isPacketFileUpload(array $formDataPacket): bool{
			/** @var array{name: string, value: string, attributes: array} $header */
			foreach($formDataPacket['headers'] as $header){
				if (strtolower($header['name']) === "content-disposition"){
					/** @var array{name: string, value: string} $attribute */
					foreach($header['attributes'] as $attribute){
						if ($attribute['name'] === "filename"){
							return true;
						}
					}
				}
			}

			return false;
		}

		/**
		 * Parses multipart/form-data and application/json
		 */
		public function processRequestBody(): array
		{
			$requestPayload = new RequestPayload();
			$requestMethod = strtolower($_SERVER['REQUEST_METHOD']);
			$contentType = $_SERVER['CONTENT_TYPE'];
			$didMatch = preg_match("/multipart\/form-data; boundary=(.+)/", $contentType, $matches);
			if ($didMatch === 1) {
				$boundary = $matches[1];
				$formData = [];
				$parsedFormData = $this->getAllFormDataFromRequest($boundary);
				/** @var array{headers: array, body: string} $packet */
				foreach ($parsedFormData as $packet) {

					// Check if this packet is a file upload or not
					if ($this->isPacketFileUpload($packet)){
						// Handle this as a file upload
						$payloadName = null;
						$fileUpload = new FileUploadPayload();
						$fileUpload->contents = $packet['body'];
						$fileUpload->fileSize = strlen($packet['body']);
						/** @var array{name: string, value: string, attributes: array} $header */
						foreach ($packet['headers'] as $header) {
							if (strtolower($header['name']) === "content-disposition") {
								/** @var array{name: string, value: string} $attribute */
								foreach ($header['attributes'] as $attribute) {
									if ($attribute['name'] === "name") {
										$payloadName = $attribute['value'];
									}elseif ($attribute['name'] === "filename"){
										$fileUpload->fileName = $attribute['value'];
									}
								}
							}elseif (strtolower($header['name']) === "content-type"){
								$fileUpload->contentType = $header['value'];
							}
						}

						if ($payloadName !== null){
							$fileUpload->name = $payloadName;
							$formData[$payloadName] = $fileUpload;
							$requestPayload->pushPayload($fileUpload);
						}
					}else {
						// Handle this as a normal payload
						/** @var array{name: string, value: string, attributes: array} $header */
						foreach ($packet['headers'] as $header) {
							if (strtolower($header['name']) === "content-disposition") {
								/** @var array{name: string, value: string} $attribute */
								foreach ($header['attributes'] as $attribute) {
									if ($attribute['name'] === "name") {
										$formData[$attribute['value']] = $packet['body'];
										$textPayload = new TextPayload();
										$textPayload->name = $attribute['value'];
										$textPayload->contents = $packet['body'];
										$requestPayload->pushPayload($textPayload);
									}
								}
							}
						}
					}
				}
			}else{
				// Check if JSON
				if (substr($contentType,0, 16) === "application/json"){
					$formData = json_decode(json: file_get_contents("php://input"), associative: true);
					if (json_last_error() !== JSON_ERROR_NONE){
						// Kill everything
						http_response_code(500);
						exit(sprintf("JSON request body is invalid json. Error: %s", json_last_error_msg()));
					}else{
						// No errors
						// Turn them all into payloads
						foreach($formData as $key=>$value){
							if (is_array($value)){
								$arrayPayload = new ArrayPayload();
								$arrayPayload->name = $key;
								$arrayPayload->contents = $value;
								$requestPayload->pushPayload($arrayPayload);
							}else{
								$textPayload = new TextPayload();
								$textPayload->name = $key;
								$textPayload->contents = $value;
								$requestPayload->pushPayload($textPayload);
							}
						}
					}
				}
			}

			self::setRequestPayload($requestPayload);

			return $formData;
		}

		/**
		 * Parses a single HTTP header line, as a header may contain a ; and then named attributes.
		 * @param string $headerLine
		 * @return array{name: string, value: string, attributes:array}
		 */
		private function parseHttpHeader(string $headerLine): array{
			list($headerName, $headerRawData) = explode(":", $headerLine);
			$header = [];
			$attributes = [];
			$headerValue = '';
			// Parse the raw header until the first semi-colon or the end of the string
			$charIndex = 0;
			$char = $headerRawData[0];
			while ($char !== null && $charIndex < strlen($headerRawData)){
				if ($char !== ";"){
					$headerValue .= $char;
				}else{
					break;
				}
				++$charIndex;
				$char = $headerRawData[$charIndex] ?? null;
			}

			if ($charIndex < strlen($headerRawData) - 1){
				// There are more header attributes to be parsed, such as a 'name'
				$attributeParseStates = [
					"NO_STATE"=>0,
					"PARSING_NAME"=>1,
					"PARSING_VALUE"=>2,
				];
				$attributeParseState = $attributeParseStates['NO_STATE'];
				$attributeBuffer = "";
				$attributeQuoteEncapsulation = ""; // The quote character used to encapsulate an attribute value, if any
				$char = $headerRawData[++$charIndex] ?? null;
				$currentAttribute = [];
				while ($char !== null && $charIndex < strlen($headerRawData)){
					switch($attributeParseState){
						case $attributeParseStates['NO_STATE']:
							if ($char !== " " && $char !== "," && $char !== ";"){
								$attributeBuffer .= $char;
								$attributeParseState = $attributeParseStates['PARSING_NAME'];
							}
							break;
						case $attributeParseStates['PARSING_NAME']:
							if ($char !== "="){
								$attributeBuffer .= $char;
							}else{
								// Ignore, and change states to value
								$attributeParseState = $attributeParseStates['PARSING_VALUE'];
								$currentAttribute['name'] = $attributeBuffer;
								$attributeBuffer = "";
							}
							break;
						case $attributeParseStates['PARSING_VALUE']:
							if (empty($attributeBuffer)){
								if ($char === '"' || $char === "'"){
									$attributeQuoteEncapsulation = $char;
								}else{
									$attributeBuffer .= $char;
								}
							}else{
								if ($char === " "){
									if (empty($attributeQuoteEncapsulation)){
										// Done parsing
										$currentAttribute['value'] = $attributeBuffer;
										$attributeBuffer = "";
										$attributes[] = $currentAttribute;
										$currentAttribute = [];
										$attributeParseState = $attributeParseStates['NO_STATE'];
									}else{
										// It's in the quotes, consume it
										$attributeBuffer .= $char;
									}
								}else{
									if (!empty($attributeQuoteEncapsulation) && $char === $attributeQuoteEncapsulation){
										// Done with this attribute, emit it
										$currentAttribute['value'] = $attributeBuffer;
										$attributeBuffer = "";
										$attributes[] = $currentAttribute;
										$currentAttribute = [];
										$attributeQuoteEncapsulation = "";
										$attributeParseState = $attributeParseStates['NO_STATE'];
									}else{
										$attributeBuffer .= $char;
									}
								}
							}
							break;
					}
					++$charIndex;
					$char = $headerRawData[$charIndex] ?? null;
				}

				// If the end of the line was hit during parsing and it had no string encapsulation
				// then it was never emitted
				if (!empty($attributeBuffer)){
					$currentAttribute['value'] = $attributeBuffer;
					$attributeBuffer = "";
					$attributes[] = $currentAttribute;
				}
			}
			$header['name'] = $headerName;
			$header['value'] = trim($headerValue);
			$header['attributes'] = $attributes;

			return $header;
		}

		/**
		 * Processes all the form-data in the raw request
		 * @return array{headers: array, body: string}
		 */
		private function getAllFormDataFromRequest(string $boundary): array
		{

			$parseStates = [
				"NO_STATE"=>0,
				"EXPECT_HEADERS"=>1,
				"EXPECT_BODY"=>2,
			];

			$data = [];
			$rawBody = file_get_contents("php://input");
			$state = $parseStates["NO_STATE"];
			$currentPacket = [
				"headers"=>[],
				"body"=>"",
			];
			$buffer = "";
			$maxIterations = 10000;

			// First, break the raw body up into lines
			$lines = explode("\r\n", $rawBody);

			$packetBoundaryEntrance = sprintf("--%s", $boundary);
			$packetBoundaryEndBody = sprintf("--%s--", $boundary);

			// Iterate each line, checking if it is a form boundary
			// and stop at the form boundary end
			foreach($lines as $index=>$line){
				switch($state){
					case $parseStates['NO_STATE']:
						if ($line === $packetBoundaryEntrance){
							$state = $parseStates['EXPECT_HEADERS'];
						}
						break;
					case $parseStates['EXPECT_HEADERS']:
						if (!empty($line)){
							$headerArray = $this->parseHttpHeader(
								headerLine: $line
							);
							$currentPacket['headers'][] = $headerArray;
						}else{
							// Done parsing headers
							$state = $parseStates['EXPECT_BODY'];
						}
						break;
					case $parseStates['EXPECT_BODY']:
						// If the next line is a form boundary, do not add a \r\n to the line
						// else, go ahead and add a \r\n to the line to retain the original intention
						// of the data
						if ($line === $packetBoundaryEntrance || $line === $packetBoundaryEndBody) {
							$data[] = $currentPacket;
							$currentPacket = [
								"headers"=>[],
								"body"=>"",
							];
							$state = $parseStates['EXPECT_HEADERS'];
						}else{
							$nextLine = $lines[$index + 1] ?? "";
							if ($nextLine === $packetBoundaryEntrance || $nextLine === $packetBoundaryEndBody) {
								$currentPacket['body'] .= $line;
							}else {
								$currentPacket['body'] .= sprintf("%s\r\n", $line);
							}
						}
						break;
					default:
						break;
				}
			}

			return $data;
		}

		/**
		 * Parses a header into a key value pair
		 * @deprecated
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
