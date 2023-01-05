<?php

	namespace Nox\Http;

	/**
	 * An object that abstracts and represents an HTTP request.
	 */
	class Request
	{

		/**
		 * @var string The URL path of the current request
		 */
		private string $path;

		/**
		 * @var string The request method. Always in lowercase. E.g., "post", "get", "patch", etc.
		 */
		private string $method;

		private ?RequestPayload $payload = null;

		private RequestParameters $parameters;

		/**
		 * @deprecated Use non-static alternative
		 * @param string $headerName
		 * @return string|null
		 */
		public static function getFirstHeaderValue(string $headerName): ?string{
			foreach(getallheaders() as $name => $value){
				if (strtolower($name) === strtolower($headerName)){
					return $value;
				}
			}

			return null;
		}

		public function __construct()
		{
			$this->parameters = new RequestParameters();
		}

		/**
		 * Returns the RequestPayload object for this request. This is always null if the request payload was never processed.
		 * Use the attribute #[ProcessRequestBody] or call the Request->processRequestBody() method to populate the request
		 * payload.
		 * @return RequestPayload|null
		 */
		public function getPayload(): ?RequestPayload{
			return $this->payload;
		}

		public function setPayload(RequestPayload $payload): void{
			$this->payload = $payload;
		}

		/**
		 * Returns the first value of a header, or null
		 */
		public function getHeaderValue(string $headerName): ?string{
			foreach(getallheaders() as $name => $value){
				if (strtolower($name) === strtolower($headerName)){
					return $value;
				}
			}

			return null;
		}

		/**
		 * Returns an array of values of a header name (there could be multiple duplicate headers). Empty array if there are no headers with the given name.
		 * @param string $headerName
		 * @return array
		 */
		public function getHeaderValues(string $headerName): array{
			$values = [];
			foreach(getallheaders() as $name => $value){
				if (strtolower($name) === strtolower($headerName)){
					$values[] = $value;
				}
			}

			return $values;
		}

		/**
		 * Fetches the body of a request.
		 */
		public function getRawBody(): string
		{
			return file_get_contents("php://input");
		}

		public function addParameter(
			string $name,
			string $value,
		): void{
			$this->parameters->addParameter($name, $value);
		}

		/**
		 * Gets the value of a URL request parameter, or returns null if it doesn't exist.
		 */
		public function getParameter(string $name): ?string{
			$parameter = $this->parameters->getParameter($name);

			return $parameter?->value;

		}

		public function setPath(string $path): void{
			$this->path = $path;
		}

		public function getPath(): string{
			return $this->path;
		}

		public function setMethod(string $method): void{
			$this->method = $method;
		}

		public function getMethod(): string{
			return $this->method;
		}

		/**
		 * Attempts to fetch the client IP from the request. Returns a blank string if an IP could not be found.
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
		 * Checks if a packet is of type file
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
		 * Parses multipart/form-data, application/json, and application/x-www-form-urlencoded request bodies
		 * into a RequestPayload. The payload is then set in the current request instance and can be retrieved with
		 * $request->getPayload()
		 */
		public function processRequestBody(): void
		{
			// First, check if it's a POST request where the data has already been processed internally
			// by PHP.
			$requestMethodLowered = strtolower($_SERVER['REQUEST_METHOD']);
			$requestPayload = new RequestPayload();
			if ($requestMethodLowered === "post" && !empty($_POST)) {
				// Handle creating the RequestPayload object for the POST fields and then check if the _FILES
				// fields are not empty
				foreach ($_POST as $key => $value) {
					$textPayload = new TextPayload();
					$textPayload->name = $key;
					$textPayload->contents = $value;
					$requestPayload->pushPayload($textPayload);
				}

				if (!empty($_FILES)) {
					/**
					 * @var string $key
					 * @var array{name: string, type: string, tmp_name: string, error: int, size: int} $fileArray
					 */
					foreach ($_FILES as $key => $fileArray) {
						if (!empty($fileArray['tmp_name'])) {
							$fileContents = file_get_contents($fileArray['tmp_name']);
							$fileUpload = new FileUploadPayload();
							$fileUpload->name = $key;
							$fileUpload->contents = $fileContents;
							$fileUpload->contentType = $fileArray['type'];
							$fileUpload->fileSize = $fileArray['size'];
							$fileUpload->fileName = $fileArray['name'];
							$requestPayload->pushPayload($fileUpload);
						}
					}
				}
			}else {
				$contentType = $_SERVER['CONTENT_TYPE'] ?? null;
				if ($contentType !== null){
					$didMatch = preg_match("/multipart\/form-data; boundary=(.+)/", $contentType, $matches);
					if ($didMatch === 1) {
						$boundary = $matches[1];
						$parsedFormData = $this->getAllFormDataFromRequest($boundary);

						/** @var array{headers: array, body: string} $packet */
						foreach ($parsedFormData as $packet) {

							// Check if this packet is a file upload or not
							if ($this->isPacketFileUpload($packet)) {
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
											} elseif ($attribute['name'] === "filename") {
												$fileUpload->fileName = $attribute['value'];
											}
										}
									} elseif (strtolower($header['name']) === "content-type") {
										$fileUpload->contentType = $header['value'];
									}
								}

								if ($payloadName !== null) {
									$fileUpload->name = $payloadName;
									$requestPayload->pushPayload($fileUpload);
								}
							} else {
								// Handle this as a normal payload
								/** @var array{name: string, value: string, attributes: array} $header */
								foreach ($packet['headers'] as $header) {
									if (strtolower($header['name']) === "content-disposition") {
										/** @var array{name: string, value: string} $attribute */
										foreach ($header['attributes'] as $attribute) {
											if ($attribute['name'] === "name") {
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
						if (str_starts_with($contentType, "application/json")) {
							$formData = json_decode(json: file_get_contents("php://input"), associative: true);
							if (json_last_error() !== JSON_ERROR_NONE) {
								// Kill everything
								http_response_code(500);
								exit(sprintf("JSON request body is invalid json. Error: %s", json_last_error_msg()));
							} else {
								// No errors
								// Turn them all into payloads
								foreach ($formData as $key => $value) {
									if (is_array($value)) {
										$arrayPayload = new ArrayPayload();
										$arrayPayload->name = $key;
										$arrayPayload->contents = $value;
										$requestPayload->pushPayload($arrayPayload);
									} else {
										$textPayload = new TextPayload();
										$textPayload->name = $key;
										$textPayload->contents = $value;
										$requestPayload->pushPayload($textPayload);
									}
								}
							}
						} elseif (str_starts_with($contentType, "application/x-www-form-urlencoded")) {
							$body = $this->getRawBody();
							parse_str($body, $payloadAsArray);
							foreach ($payloadAsArray as $key => $value) {
								$textPayload = new TextPayload();
								$textPayload->name = $key;
								$textPayload->contents = $value;
								$requestPayload->pushPayload($textPayload);
							}
						}
					}
				}
			}

			$this->setPayload($requestPayload);
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
		 * Fetches the raw body of a request and parses it as a Form in form-data. Requires a form boundary
		 * to be provided, which can be found in the content-type header of the request.
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
			$rawBody = $this->getRawBody();
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

	}
