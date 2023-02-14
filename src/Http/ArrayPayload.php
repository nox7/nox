<?php
	namespace Nox\Http;

	class ArrayPayload extends Payload {
		public function __construct(
			public string $name = "",
			public ?array $contents = null,
		){}
	}