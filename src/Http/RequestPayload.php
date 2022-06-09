<?php
	namespace Nox\Http;

	use Nox\Http\Exceptions\NoPayloadFound;
	use phpDocumentor\Reflection\File;

	class RequestPayload{
		/** @var Payload[]  */
		private array $payloadObjects = [];

		public function setPayloadObjects(array $payloadInstances): void{
			$this->payloadObjects = $payloadInstances;
		}

		public function pushPayload(Payload $payload): void{
			$this->payloadObjects[] = $payload;
		}

		/**
		 * @param string $name
		 * @throws NoPayloadFound
		 */
		public function getTextPayload(string $name): TextPayload | null{
			foreach($this->payloadObjects as $payload){
				if ($payload instanceof TextPayload) {
					if (strtolower($payload->name) === strtolower($name)) {
						return $payload;
					}
				}
			}

			throw new NoPayloadFound(
				sprintf(
					"No text payload found in the request body with the name %s",
					$name,
				),
			);
		}

		/**
		 * @param string $name
		 * @throws NoPayloadFound
		 */
		public function getFileUploadPayload(string $name): FileUploadPayload | null{
			foreach($this->payloadObjects as $payload){
				if ($payload instanceof FileUploadPayload) {
					if (strtolower($payload->name) === strtolower($name)) {
						return $payload;
					}
				}
			}

			throw new NoPayloadFound(
				sprintf(
					"No file upload payload found in the request body with the name %s",
					$name,
				),
			);
		}
	}