<?php

	namespace Nox\Router;

	use Nox\Http\Redirect;
	use Nox\Router\Exceptions\RecursionDepthExceeded;

	require_once __DIR__ . "/ViewSettings.php";
	require_once __DIR__ . "/Router.php";

	class RequestHandler{

		/**
		 * The maximum times a RequestHandler can be created in one request.
		 */
		public const MAX_RECURSION_DEPTH = 10;

		public function __construct(
			public Router $router,
			public string $requestPath,
			public string $requestType,
			public int $recursionDepth = 0,
		){
			if (substr($requestPath, 0, 1) !== "/") {
				$this->requestPath = sprintf("/%s", $requestPath);
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

					// Do not serve unknown mime types
					if ($mimeType !== null) {
						/*if ($mimeType === null){
							$mimeType = "text/plain";
						}*/

						/**
						 * Set the cache-control header if there is a cache config for
						 * the given mime type
						 */
						$cacheTime = $this->router->staticFileHandler->getCacheTimeForMime($mimeType);
						if ($cacheTime !== null) {
							header(sprintf("cache-control: max-age=%d", $cacheTime));
						}

						header("content-type: $mimeType");
						return $this->router->staticFileHandler->getStaticFileContents($this->requestPath);
					}
				}
			}

			return $this->router->route($this->requestType, $this);
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
				// TODO Allow an attribute to set the response code and route to use
				if ($this->recursionDepth < self::MAX_RECURSION_DEPTH) {
					http_response_code(404);
					if ($this->requestPath !== $this->router->noxConfig['404-route']) {
						$notFoundRouter = new Router(
							requestPath:"/404",
							requestMethod: "GET"
						);
						$notFoundRouter->staticFileHandler = $this->router->staticFileHandler;
						$notFoundRouter->viewSettings = $this->router->viewSettings;
						$notFoundRouter->noxConfig = $this->router->noxConfig;
						$notFoundRouter->controllersFolder = $this->router->controllersFolder;
						$notFoundRouter->loadMVCControllers();
						$notFoundRequestHandler = new RequestHandler($notFoundRouter, "404", "GET", ++$this->recursionDepth);
						$notFoundRequestHandler->processRequest();
						exit();
					} else {
						// The current request path IS the 404-route
						// That means the 404 404'd
						exit();
					}
				}else{
					// Too much recursion
					throw new RecursionDepthExceeded("Too many recursive requests. Last request processed was " . $this->requestPath);
				}
			}
		}

	}
