<?php
	namespace Nox\Http\Attributes;

	require_once __DIR__ . "/../Request.php";
	require_once __DIR__ . "/../../Router/BaseController.php";
	require_once __DIR__ . "/ChosenRouteAttribute.php";

	use Nox\Http\FileUploadPayload;
	use Nox\Http\Request;
	use Nox\Http\RequestPayload;
	use Nox\Http\TextPayload;
	use Nox\Router\BaseController;

	/**
	 * @since 1.5.0
	 */
	#[\Attribute(\Attribute::TARGET_METHOD)]
	class ProcessRequestBody extends ChosenRouteAttribute {
		public function __construct(){
			// First, try and just reference the POST data that PHP processes internally.
			// Because php://input is blank if this is a POST request that PHP already handled.
			if (strtolower($_SERVER['REQUEST_METHOD']) === "post" && !empty($_POST)){
				BaseController::$requestPayload = &$_POST;

				// Handle creating the RequestPayload object for the POST fields and then check if the _FILES
				// fields are not empty
				$requestPayload = new RequestPayload();
				foreach($_POST as $key=>$value){
					$textPayload = new TextPayload();
					$textPayload->name = $key;
					$textPayload->contents = $value;
					$requestPayload->pushPayload($textPayload);
				}

				if (!empty($_FILES)){
					/**
					 * @var string $key
					 * @var array{name: string, type: string, tmp_name: string, error: int, size: int} $fileArray
					 */
					foreach($_FILES as $key=>$fileArray){
						if (!empty($fileArray['tmp_name'])) {
							$fileContents = file_get_contents($fileArray['tmp_name']);
							$fileUpload = new FileUploadPayload();
							$fileUpload->name = $key;
							$fileUpload->contents = $fileContents;
							$fileUpload->contentType = $fileArray['type'];
							$fileUpload->fileSize = $fileArray['size'];
							$fileUpload->fileName = $fileArray['name'];
							$requestPayload->pushPayload($fileUpload);
						}
					}
				}

				Request::setRequestPayload($requestPayload);
			}else{
				$request = new Request();
				BaseController::$requestPayload = $request->processRequestBody();
			}
		}
	}