<?php
	namespace Nox\Http;

	class FileUploadPayload extends Payload {
		public string $name;
		public string $fileName;
		public int $fileSize;
		public string $contentType;
		public string $contents;
	}