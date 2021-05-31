<?php

	namespace Nox\Router;

	use Nox\Http\Redirect;

	require_once __DIR__ . "/ViewSettings.php";
	require_once __DIR__ . "/Router.php";

	class RequestHandler{

		public function __construct(
			public Router $router,
			public string $requestPath,
			public string $requestType,
		){
			if (empty($this->requestPath)){
				$this->requestPath = "/";
			}
		}

		/**
		* Process an HTTP request
		*/
		public function getRouteResult(): mixed{
			// $clientIP = $_SERVER['REMOTE_ADDR'];

			if ($this->requestType === "GET"){

				// Check for a static file
				if ($this->router->staticFileHandler->doesStaticFileExist($this->requestPath)){
					$mimeType = $this->router->staticFileHandler->getStaticFileMime($this->requestPath);
					if ($mimeType === null){
						$mimeType = "text/plain";
					}

					/**
					* Set the cache-control header if there is a cache config for
					* the given mime type
					*/
					$cacheTime = $this->router->staticFileHandler->getCacheTimeForMime($mimeType);
					if ($cacheTime !== null){
						header(sprintf("cache-control: max-age=%d", $cacheTime));
					}

					header("content-type: $mimeType");
					return $this->router->staticFileHandler->getStaticFileContents($this->requestPath);
				}
			}

			return $this->router->route($this->requestType, $this->requestPath);
		}

		/**
		 * Handles the result of a processed request
		 */
		public function processRequest(): void{
			$routeResult = $this->getRouteResult();
			if ($routeResult instanceof Redirect){
				http_response_code($routeResult->statusCode);
				header(
					sprintf("location: %s", $routeResult->path)
				);
				exit();
			}elseif ($routeResult !== null){
				print($routeResult);
			}else{
				http_response_code(404);
				if ($this->requestPath !== $this->router->noxConfig['404-route']) {
					$notFoundRequestHandler = new RequestHandler($this->router, "/404", "GET");
					$notFoundRequestHandler->processRequest();
				}else{
					// The current request path IS the 404-route
					// That means the 404 404'd
					exit();
				}
			}
		}

	}
