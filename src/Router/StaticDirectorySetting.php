<?php
	namespace Nox\Router;

	class StaticDirectorySetting{
		public function __construct(
			public string $uriPathStub,
			public string $staticFilesDirectory,
		){}
	}