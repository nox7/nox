<?php
	namespace Nox\Http;

	class ArrayPayload extends Payload {
		public string $name;
		public array|null $contents;
	}