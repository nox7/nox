<?php

	namespace Nox\Router;

	require_once __DIR__ . "/MimeTypes.php";

	class StaticFileHandler{
		public ?string $staticDirectory = null;

		/** @var array Keys are the uri alias and values are a key/value array. See addStaticFileDirectory */
		public array $staticDirectories = [];
		public string $cacheFile = "";
		public array $cacheConfig = [];

		/**
		 * The mime types that are recognized for static file serving. Identified by
		 * the extension as the array key without a period (e.g, "css")
		 */
		public ?MimeTypes $mimeTypes = null;

		/**
		 * Sets a URI prepend alias for accessing a static file directory
		 * @param string $directoryPath The path to the static files to be served
		 * @param strign $uriAlias (Optional) The alias, if any, that a URI must start with to be a valid static file request.
		 */
		public function addStaticFileDirectory(
			string $directoryPath,
			string $uriAlias = ""
		){
			$this->staticDirectories[$uriAlias] = $directoryPath;
		}

		/**
		* Fetches the full path to a static file
		* @param string $filePath
		* @return string
		*/
		public function getFullStaticFilePath(string $filePath): ?string{
			// Support for the single-static directory setting
			if ($this->staticDirectory !== null) {
				return sprintf("%s/%s", $this->staticDirectory, $filePath);
			}else{
				// Always process the global (blank) uriAlias last, if it exists
				foreach($this->staticDirectories as $uriAlias=>$directoryPath){
					if (!empty($uriAlias)) {
						if (str_starts_with($filePath, $uriAlias)) {
							// Replace the alias directory
							$newPath = substr($filePath, strlen($uriAlias));
							return sprintf("%s%s", $directoryPath, $newPath);
						}
					}
				}

				// Process the blank uriAlias last, here
				if (array_key_exists("", $this->staticDirectories)){
					return sprintf("%s/%s", $this->staticDirectories[""], $filePath);
				}
			}

			return null;
		}

		/**
		* Whether or not a static file exists at the path
		* @param string $filePath
		* @return bool
		*/
		public function doesStaticFileExist(string $filePath): bool{
			$fullPath = $this->getFullStaticFilePath($filePath);
			if ($fullPath) {
				return file_exists($fullPath) && !is_dir($fullPath);
			}else{
				return false;
			}
		}

		/**
		* Sets the array of seconds for key mime types
		*/
		public function setCacheConfig(array $cacheConfig): void{
			$this->cacheConfig = $cacheConfig;
		}

		/**
		* Gets the cache time, in seconds, of a MIME type.
		* Will be null if no cache config exists for the given mime
		* @param string $mime
		* @return int|null
		*/
		public function getCacheTimeForMime(string $mime): ?int{
			if (isset($this->cacheConfig[$mime])){
				return (int) $this->cacheConfig[$mime];
			}

			return null;
		}

		/**
		* Gets the mime type of the file based on the extension
		* @param string $filePath
		* @return string|null
		*/
		public function getStaticFileMime(string $filePath): ?string{
			$extension = pathinfo($filePath, PATHINFO_EXTENSION);
			$extension_lowered = strtolower($extension);
			if ($extension !== ""){
				if (array_key_exists($extension_lowered, $this->mimeTypes->recognizedExtensions)){
					return $this->mimeTypes->recognizedExtensions[$extension_lowered];
				}else{
					return null;
				}
			}else{
				return null;
			}
		}

		/**
		* Gets the mime type of the file based on the extension
		* @param string $filePath
		*/
		public function getStaticFileContents(string $filePath): string{
			$fullPath = $this->getFullStaticFilePath($filePath);
			return file_get_contents($fullPath);
		}
	}
