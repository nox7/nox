<?php

	namespace Nox\Router;

	require_once __DIR__ . "/MimeTypes.php";

	class StaticFileHandler{
		public string $staticDirectory = "";
		public string $cacheFile = "";
		public array $cacheConfig = [];

		public function setStaticFilesDirectory(string $directoryPath): void{
			$this->staticDirectory = $directoryPath;
		}

		/**
		* Fetches the full path to a static file
		* @param string $filePath
		* @return string
		*/
		public function getFullStaticFilePath(string $filePath): string{
			return sprintf("%s/%s", $this->staticDirectory, $filePath);
		}

		/**
		* Whether or not a static file exists at the path
		* @param string $filePath
		* @return bool
		*/
		public function doesStaticFileExist(string $filePath): bool{
			$fullPath = $this->getFullStaticFilePath($filePath);
			return file_exists($fullPath) && !is_dir($fullPath);
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
			if ($extension !== ""){
				if (isset(MimeTypes::RECOGNIZED_EXTENSIONS[$extension])){
					return MimeTypes::RECOGNIZED_EXTENSIONS[$extension];
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
