<?php

	namespace Nox\Router\Mime;

	class MimeCache{
		public function __construct(
			public string $mimeType,
			public int $cacheSeconds,
		){}
	}
