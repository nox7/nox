<?php

	// No need to require the autoload in the Controller.
	// The controller has the scope of the request.php file

	use Nox\Http\Attributes\ProcessRequestBody;
	use Nox\Http\Attributes\UseJSON;
	use Nox\Http\JSON\JSONResult;
	use Nox\Http\JSON\JSONSuccess;
	use Nox\Router\Attributes\Controller;
	use Nox\Router\Attributes\Route;
	use Nox\Router\Attributes\RouteBase;
	use Nox\Router\BaseController;

	#[Controller]
	#[RouteBase("/\/api\/(?<version>v\d)/", true)]
	class ExampleAPIController extends BaseController{

		#[Route("GET", "/")]
		public function apiHomeView(): string{
			return "API home view example.";
		}

		#[Route("PUT", "/some/resource/to/put")]
		#[UseJSON] // Lets the router know to put the response content-type as JSON and to send JSONResult as a JSON string
		#[ProcessRequestBody] // Parses the raw request body if it is a non-GET request
		public function subView(): JSONResult{
			// Payload is the request body parsed into an array
			$payload = BaseController::$requestPayload;

			if (isset($payload['some-data'])){
				// Insert into DB or something
			}

			return new JSONSuccess();
		}
	}