<?php

	namespace Nox\Router;

	use Nox\Http\Redirect;
	use Nox\Http\Rewrite;
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
			public int $recursionDepth = 0,
		){}

		/**
		 * Process an HTTP request
		 */
		public function getRouteResult(): mixed{
			// These values are already force-lowered in case sensitivity
			$requestMethod = $this->router->requestMethod;
			$requestPath = $this->router->requestPath;

			if ($requestMethod === "get"){

				// Check for a static file
				if ($this->router->staticFileHandler->doesStaticFileExist($requestPath)){
					$mimeType = $this->router->staticFileHandler->getStaticFileMime($requestPath);

					// Do not serve unknown mime types
					if ($mimeType !== null) {
						/**
						 * Set the cache-control header if there is a cache config for
						 * the given mime type
						 */
						$cacheTime = $this->router->staticFileHandler->getCacheTimeForMime($mimeType);
						if ($cacheTime !== null) {
							header(sprintf("cache-control: max-age=%d", $cacheTime));
						}

						header("content-type: $mimeType");
						return $this->router->staticFileHandler->getStaticFileContents($requestPath);
					}
				}
			}

			return $this->router->route($this);
		}

		/**
		 * Handles the result of a processed request
		 */
		public function processRequest(): void{
			$routeResult = $this->getRouteResult();
			if ($routeResult instanceof Redirect) {
				http_response_code($routeResult->statusCode);
				header(
					sprintf("location: %s", $routeResult->path)
				);
				exit();
			}elseif ($routeResult instanceof Rewrite){
				http_response_code($routeResult->statusCode);
				$rewriteRouter = new Router(
					requestPath:$routeResult->path,
					requestMethod: "get",
				);
				$rewriteRouter->staticFileHandler = $this->router->staticFileHandler;
				$rewriteRouter->viewSettings = $this->router->viewSettings;
				$rewriteRouter->noxConfig = $this->router->noxConfig;
				$rewriteRouter->controllersFolder = $this->router->controllersFolder;
				$rewriteRouter->loadMVCControllers();
				$rewriteRouter = new RequestHandler(
					$rewriteRouter,
					++$this->recursionDepth,
				);
				$rewriteRouter->processRequest();

				exit();
			}elseif ($routeResult !== null){
				// Successful route with an outputtable result.
				// This is the result of the route (page, response, etc)
				// Output it and be done with things
				print($routeResult);
				exit();
			}else{
				// TODO Allow an attribute to set the response code and route to use
				if ($this->recursionDepth < self::MAX_RECURSION_DEPTH) {
					http_response_code(404);
					if ($this->router->requestPath !== $this->router->noxConfig['404-route']) {
						$notFoundRouter = new Router(
							requestPath:"/404",
							requestMethod: "get",
						);
						$notFoundRouter->staticFileHandler = $this->router->staticFileHandler;
						$notFoundRouter->viewSettings = $this->router->viewSettings;
						$notFoundRouter->noxConfig = $this->router->noxConfig;
						$notFoundRouter->controllersFolder = $this->router->controllersFolder;
						$notFoundRouter->loadMVCControllers();
						$notFoundRequestHandler = new RequestHandler(
							$notFoundRouter,
							++$this->recursionDepth,
						);
						$notFoundRequestHandler->processRequest();
					}

					exit();
				}else{
					// Too much recursion
					throw new RecursionDepthExceeded(
						sprintf(
							"Too many recursive requests. Last request processed was %s",
							$this->router->requestPath,
						),
					);
				}
			}
		}

	}
