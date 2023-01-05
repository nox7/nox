<?php

	namespace Users;

	use Nox\Http\Attributes\ProcessRequestBody;
	use Nox\Http\Attributes\UseJSON;
	use Nox\Http\Exceptions\NoPayloadFound;
	use Nox\Http\JSON\JSONError;
	use Nox\Http\JSON\JSONResult;
	use Nox\Http\JSON\JSONSuccess;
	use Nox\Http\Request;
	use Nox\Router\Attributes\Controller;
	use Nox\Router\Attributes\Route;
	use Nox\Router\Attributes\RouteBase;
	use Nox\Router\BaseController;

	#[Controller]
	#[RouteBase("/users")]
	class UsersController extends BaseController{

		#[Route("GET", "/")]
		public function usersHomeView(Request $request): string{
			return "Users microservice home view..";
		}

		#[Route("PUT", "/users")]
		#[UseJSON] // Lets the router know to put the response content-type as JSON and to send JSONResult as a JSON string
		#[ProcessRequestBody] // Parses the raw request body if it is a non-GET request
		public function addUser(Request $request): JSONResult{
			// Payload is the request body parsed into an array
			$payload = BaseController::$requestPayload;

			if (isset($payload['some-data'])){
				// Insert into DB or something
			}

			return new JSONSuccess();
		}
	}