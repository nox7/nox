<?php
	namespace Nox\Http;

	class FileUploadPayload extends Payload {
		public function __construct(
			public string $name = "",
			public string $fileName = "",
			public int $fileSize = 0,
			public string $contentType = "",
			public string $contents = "",
		){}
	}