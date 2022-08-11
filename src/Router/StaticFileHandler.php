<?php

	namespace Nox\Router;

	use Exception;
	use Nox\Nox;
	use Nox\Router\Mime\MimeCache;
	use Nox\Router\Mime\MimeTypes;

	class StaticFileHandler{
		/**
		 * @var MimeCache[]
		 */
		public array $cacheConfig = [];

		public function __construct(
			public Nox $noxInstance,
		){}

		/**
		* Fetches the full path to a static file
		* @param string $filePath
		* @return null | string
		*/
		public function getFullStaticFilePath(string $filePath): null | string{
		// Always process the global (blank) uriAlias last, if it exists
			$rootStaticServingSetting = null;
			foreach($this->noxInstance->getStaticDirectorySettings() as $staticDirectorySetting){
				if ($staticDirectorySetting->uriPathStub !== "/") {
					if (str_starts_with($filePath, $staticDirectorySetting->uriPathStub)) {
						// Replace the alias directory
						$newPath = substr($filePath, strlen($staticDirectorySetting->uriPathStub));
						return sprintf("%s%s", $staticDirectorySetting->staticFilesDirectory, $newPath);
					}
				}else{
					// This is a setting that serves from root URIs (/). It must be processed last so that
					// other static directories can have a chance to be served
					$rootStaticServingSetting = $staticDirectorySetting;
				}
			}

			if ($rootStaticServingSetting !== null){
				return sprintf("%s%s", $rootStaticServingSetting->staticFilesDirectory, $filePath);
			}

			return null;
		}

		/**
		 * Gets the cache time, in seconds, of a MIME type.
		 * Will be null if no cache config exists for the given mime
		 * @param string $mimeType
		 * @return int|null
		 */
		public function getCacheTimeForMime(string $mimeType): ?int{
			foreach($this->cacheConfig as $mimeCache){
				if ($mimeCache->mimeType === $mimeType){
					return $mimeCache->cacheSeconds;
				}
			}

			return null;
		}

		/**
		 * Gets the mime type of the file based on the extension
		 * @param string $filePath
		 * @return string|null
		 */
		public function getStaticFileMime(string $filePath): null | string{
			$fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
			$fileExtension_lowered = strtolower($fileExtension);
			if ($fileExtension !== "") {
				foreach ($this->noxInstance->supportedMimeTypes->recognizedExtensions as $extension => $mimeType) {
					if (strtolower($extension) === strtolower($fileExtension_lowered)) {
						return $mimeType;
					}
				}
			}

			return null;
		}
	}
