<?php

	namespace Nox\Router\Mime;

	class MimeTypes{

		/**
		 * Array of recognized static file mime types to serve
		 */
		public array $recognizedExtensions = [];

		/**
		 * Add a recognized file extension to be associated with a content type. Do not
		 * start the extension with a period.
		 */
		public function addMimeType(string $extension, string $contentType): void{
			$this->recognizedExtensions[$extension] = $contentType;
		}

		/**
		 * Remove a recognized file extension by the extension name. Do not start the extension
		 * with a period.
		 */
		public function removeMimeType(string $extension): void{
			if (array_key_exists($extension, $this->recognizedExtensions)){
				unset($this->recognizedExtensions[$extension]);
			}
		}
	}
