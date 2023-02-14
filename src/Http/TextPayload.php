<?php
	namespace Nox\Http;

	class TextPayload extends Payload {
		public function __construct(
			public string $name = "",
			public ?string $contents = null,
		){}
	}