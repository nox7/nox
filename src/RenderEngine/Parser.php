<?php
	namespace Nox\RenderEngine;

	use Nox\RenderEngine\Exceptions\ParseError;

	require_once __DIR__ . "/Exceptions/ParseError.php";

	class Parser{

		private ?string $head;
		private ?string $body;
		private ?string $fileLocation;
		private ?string $fileContents;
		public array $directives = [];

		public function __construct(string $fileLocation, ?array $viewScope){
			$this->fileLocation = $fileLocation;
			// Include the file to parse its inner PHP code
			ob_start();
			include $fileLocation;
			$this->fileContents = ob_get_contents();
			ob_end_clean();
		}

		/**
		* Lexically parses the file for @ directive tokens
		*/
		public function parse(){
			$contents = $this->fileContents;
			$index = 0;
			$char = null;

			$directives = [];
			$parseState = "";
			$prevParserState = "";
			$prevDirectiveName = "";
			$tokenDelimiter = "";
			$buffer = "";

			// How many { were hit during parsing the LONG_VALUE
			// so it can be incremented and decremented
			// so that hitting the first } won't always
			// stop parsing the value
			$tokenValueDelimiterDepth = 0;

			while ( isset($contents[$index]) ){
				$char = $contents[$index];
				switch ($parseState){
					case "":
						if ($char === "@"){
							$parseState = "PARSE_DIRECTIVE_NAME";
							$buffer .= $char;
						}elseif ($char === "=" && $prevParserState === "PARSE_DIRECTIVE_NAME"){
							$prevParserState = "";
							$parseState = "PARSE_DIRECTIVE_SHORT_VALUE";
						}elseif ($char === "{" && $prevParserState === "PARSE_DIRECTIVE_NAME"){
							$prevParserState = "";
							$parseState = "PARSE_DIRECTIVE_LONG_VALUE";
						}
						break;
					case "PARSE_DIRECTIVE_LONG_VALUE":
						if ($char === $tokenDelimiter){
							if ($tokenValueDelimiterDepth > 0){
								--$tokenValueDelimiterDepth;
								$buffer .= $char;
							}else{
								$prevParserState = "PARSE_DIRECTIVE_LONG_VALUE";
								$parseState = "";
								$tokenDelimiter = "";
								$directives[$prevDirectiveName] = $buffer;
								$buffer = "";
							}
						}elseif ($char === "{"){
							++$tokenValueDelimiterDepth;
							$buffer .= $char;
						}else{
							$buffer .= $char;
						}
						break;
					case "PARSE_DIRECTIVE_SHORT_VALUE":
						if ($char === "\""){
							$prevParserState = "PARSE_DIRECTIVE_SHORT_VALUE";
							$parseState = "PARSE_DIRECTIVE_SHORT_VALUE_TOKEN";
							$tokenDelimiter = $char;
							$buffer .= $char;
						}
						break;
					case "PARSE_DIRECTIVE_SHORT_VALUE_TOKEN":
						if ($char === $tokenDelimiter){
							$prevParserState = "PARSE_DIRECTIVE_SHORT_VALUE_TOKEN";
							$parseState = "";
							$buffer .= $char;

							// Clear the delimiter from the start and end of the buffer
							$buffer = trim($buffer, $tokenDelimiter);

							$directives[$prevDirectiveName] = $buffer;
							$buffer = "";
							$tokenDelimiter = "";
						}elseif ($char === "\n"){
							throw new ParseError("Unexpected EOL when parsing directive string value.");
						}else{
							$buffer .= $char;
						}
						break;
					case "PARSE_DIRECTIVE_NAME";
						if ($char === " "){
							$prevParserState = "PARSE_DIRECTIVE_NAME";
							$parseState = "";
							$directives[$buffer] = "";
							$prevDirectiveName = $buffer;
							$buffer = "";
						}elseif ($char === "{"){
							$prevParserState = "PARSE_DIRECTIVE_NAME";
							$parseState = "PARSE_DIRECTIVE_LONG_VALUE";
							$directives[$buffer] = "";
							$prevDirectiveName = $buffer;
							$tokenDelimiter = "}"; // What to expect
							$buffer = "";
						}elseif ($char === "="){
							$prevParserState = "PARSE_DIRECTIVE_NAME";
							$parseState = "PARSE_DIRECTIVE_SHORT_VALUE";
							$directives[$buffer] = "";
							$prevDirectiveName = $buffer;
							$buffer = "";
						}else{
							$buffer .= $char;
						}

						break;
					default:
						break;
				}

				++$index;
			}

			// Check if there is anything in the buffer
			$trimmedBuffer = trim($buffer);
			if ($trimmedBuffer !== ""){
				// Check if the last directive is empty
				$lastDirectiveValue = $directives[$prevDirectiveName];
				if ($lastDirectiveValue === ""){
					// The last directive is empty
					// Add the current buffer to it after trimming a possible
					// closing bracket from it "}"
					$trimmedBuffer = rtrim($trimmedBuffer, "}");
					$trimmedBuffer = trim($trimmedBuffer);
					$directives[$prevDirectiveName] = $trimmedBuffer;
				}
			}

			$this->directives = $directives;
		}
	}
