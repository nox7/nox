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
		 * @return Payload[]
		 */
		public function getAllPayloads(): array{
			return $this->payloadObjects;
		}

		/**
		 * @param string $name
		 * @return ArrayPayload|null
		 * @throws NoPayloadFound
		 */
		public function getArrayPayload(string $name): ArrayPayload | null{
			foreach($this->payloadObjects as $payload){
				if ($payload instanceof ArrayPayload) {
					if (strtolower($payload->name) === strtolower($name)) {
						return $payload;
					}
				}
			}

			throw new NoPayloadFound(
				sprintf(
					"No array payload found in the request body with the name %s",
					$name,
				),
			);
		}

		/**
		 * @param string $name
		 * @return TextPayload
		 * @throws NoPayloadFound
		 */
		public function getTextPayload(string $name): TextPayload{
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
		 * @return TextPayload|null
		 */
		public function getTextPayloadNullable(string $name): TextPayload | null{
			try{
				return $this->getTextPayload($name);
			}catch(NoPayloadFound){
				return null;
			}
		}

		/**
		 * @param string $name
		 * @return FileUploadPayload
		 * @throws NoPayloadFound
		 */
		public function getFileUploadPayload(string $name): FileUploadPayload{
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