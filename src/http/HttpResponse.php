<?php

	namespace AttrRouter\HttpHelpers;

	/**
	* An abstraction to handle responses back to the client/router system.
	*/
	class HttpResponse{

		/**
		* Quick sets the Content-Type return headers to be JSON and UTF8 encoding
		* @return null
		*/
		public function setJSONHeaders(){
			header("Content-Type: application/json; charset=utf-8");
		}

		/**
		* Returns a JSON-encoded error payload to send to the output buffer. Return this
		* in controllers back to the router. The "status" key willk be -1
		* @param string $errMessage
		* @param array $extraData (Optional) To encode and merge into the return JSON
		* @return string
		*/
		public function getJsonError(string $errMessage, array $extraData = []): string{
			return json_encode(array_merge(
				["status"=>-1, "error"=>$errMessage],
				$extraData,
			));
		}

		/**
		* Returns a JSON-encoded error payload to send to the output buffer. Return this
		* in controllers back to the router. The "status" key willk be -1
		* @param string $errMessage
		* @param array $extraData (Optional) To encode and merge into the return JSON
		* @return string
		*/
		public function getJsonSuccess(array $data = []): string{
			return json_encode(array_merge(
				["status"=>1],
				$data,
			));
		}

		/**
		* Creates a redirect payload to send to the router
		* @param string $path
		* @param int $statusCode (optional)
		* @return array
		*/
		public function redirect(string $path, int $statusCode = 302): array{
			return [
				"path"=>$path,
				"statusCode"=>$statusCode,
			];
		}

	}
