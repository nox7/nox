<?php
	namespace Nox\Router;

	use Exception;
	use Nox\Http\Attributes\ChosenRouteAttribute;
	use Nox\Http\Interfaces\ArrayLike;
	use Nox\Http\Redirect;
	use Nox\Http\Rewrite;
	use Nox\Nox;
	use Nox\Router\Attributes\Route;
	use Nox\Router\Attributes\RouteBase;
	use Nox\Router\Exceptions\NoMatchingRoute;
	use Nox\Router\Exceptions\RouteBaseNoMatch;
	use Nox\Router\Interfaces\RouteAttribute;
	use ReflectionAttribute;
	use ReflectionClass;
	use ReflectionException;
	use ReflectionMethod;

	require_once __DIR__ . "/Attributes/Route.php";
	require_once __DIR__ . "/Attributes/RouteBase.php";
	require_once __DIR__ . "/Exceptions/InvalidJSON.php";
	require_once __DIR__ . "/Exceptions/RouteBaseNoMatch.php";

	class Router{

		public string $controllersFolder = "";
		public ViewSettings $viewSettings;

		/** @var RoutableController[] $routableControllers */
		public array $routableControllers = [];

		/** @var DynamicRoute[] $dynamicRoutes */
		private array $dynamicRoutes = [];

		public function __construct(
			public Nox $noxInstance,
			public string $requestPath,
			public string $requestMethod,
		){
			// Force the requestPath and requestMethod to be lowercase
			// UPDATE Do not lower the request path. Linux is case sensitive for files
			// $this->requestPath = strtolower($this->requestPath);
			$this->requestMethod = strtolower($this->requestMethod);

			if (!str_starts_with($this->requestPath, "/")){
				$this->requestPath = "/" . $this->requestPath;
			}
		}

		/**
		 * Attempts to process a request as a static file. Code will stop execution after this call
		 * if the request is a static file.
		 * @return void
		 * @throws Exception
		 */
		public function processRequestAsStaticFile(): void{
			if ($this->requestMethod === "get") {
				$mimeType = $this->noxInstance->staticFileHandler->getStaticFileMime($this->requestPath);
				// Do not serve unknown mime types
				if ($mimeType !== null) {
					$staticFilePath = $this->noxInstance->staticFileHandler->getFullStaticFilePath($this->requestPath);
					if ($staticFilePath !== null) {
						if (file_exists($staticFilePath) && !is_dir($staticFilePath)){
							/**
							 * Set the cache-control header for the given mime type if there is a cache
							 * setting for that mime type.
							 */
							$cacheTime = $this->noxInstance->staticFileHandler->getCacheTimeForMime($mimeType);
							if ($cacheTime !== null) {
								header(sprintf("cache-control: max-age=%d", $cacheTime));
							}

							header("content-type: $mimeType");
							print(file_get_contents(realpath($staticFilePath)));
							exit();
						}
					}
				}
			}
		}

		/**
		 * Registers a dynamic route. All dynamic routes are attempted after the attribute MVC routes.
		 */
		public function addDynamicRoute(DynamicRoute $dynamicRoute): void{
			$this->dynamicRoutes[] = $dynamicRoute;
		}

		/**
		 * Process the request as routable - as in, controllers should be handling the current request.
		 * @return void
		 * @throws NoMatchingRoute
		 */
		public function processRoutableRequest(){
			$routeResult = $this->routeCurrentRequest();
			if ($routeResult instanceof Rewrite){
				// Set the response code provided
				http_response_code($routeResult->statusCode);
				// Change the request path of this router
				$this->requestPath = $routeResult->path;
				// Recursively call this method
				$this->processRoutableRequest();
				exit();
			}elseif ($routeResult instanceof Redirect){
				http_response_code($routeResult->statusCode);
				header(
					sprintf("location: %s", $routeResult->path)
				);
				exit();
			}elseif ($routeResult !== null){
				// Successful. Output the result of the request
				print($routeResult);
				exit();
			}
		}

		/**
		 * Will filter out the controllers that do not have the right base matched against the current router request path.
		 * @return RoutableController[]
		 */
		private function getControllersMatchingRequestBase(): array{
			$filteredRoutableControllers = [];
			foreach($this->routableControllers as $routableController){
				try {
					$baselessRequestPath = $this->getBaselessRouteForClass($routableController->reflectionClass);
					$routableController->baselessRequestPath = $baselessRequestPath;
					$filteredRoutableControllers[] = $routableController;
				}catch(RouteBaseNoMatch){
					continue;
				}
			}

			return $filteredRoutableControllers;
		}

		/**
		 * Checks if a class can be routed against the current router requestPath.
		 * Currently, this only checks for the presence and validity RouteBase attribute.
		 * @throws RouteBaseNoMatch
		 */
		public function getBaselessRouteForClass(\ReflectionClass $classReflection): string{
			$attributes = $classReflection->getAttributes();
			$hasRouteBase = false;

			foreach($attributes as $attributeReflection){
				$attributeName = $attributeReflection->getName();
				if ($attributeName === RouteBase::class){
					$hasRouteBase = true;
					// Check if the route base matches the current request URI

					/** @var RouteBase $routeBaseAttribute */
					$routeBaseAttribute = $attributeReflection->newInstance();

					// Is the route a regular expression?
					if ($routeBaseAttribute->isRegex === false){
						// No, it is a plain string match
						if (str_starts_with($this->requestPath, $routeBaseAttribute->uri)){
							return substr($this->requestPath, strlen($routeBaseAttribute->uri));
						}
					}else{
						// Yes, it needs to be matched against the URI
						$didMatch = preg_match_all($routeBaseAttribute->uri, $this->requestPath, $matches);

						if ($didMatch === 1){
							// Add the matches to the requests BaseController requestParameters array
							foreach ($matches as $name=>$match){
								if (is_string($name)){
									if (isset($match[0])){
										BaseController::$requestParameters[$name] = $match[0];
									}
								}
							}

							$stringToCut = $matches[0][0];
							return substr($this->requestPath, strlen($stringToCut));
						}
					}
				}
			}

			// If the code got here, no match happened.
			// The calling function should not use this result
			if ($hasRouteBase){
				throw new RouteBaseNoMatch;
			}else{
				return $this->requestPath;
			}
		}

		/**
		 * Attempts to fetch the base route from a class, if it has one. If the class
		 * has no RouteBase, then null is returned
		 */
		public function getRouteBaseFromClass(\ReflectionClass $reflectionClass): ?object{
			$routeBaseAttributes = $reflectionClass->getAttributes(
				name: RouteBase::class,
				flags: ReflectionAttribute::IS_INSTANCEOF,
			);

			if (!empty($routeBaseAttributes)){
				return $routeBaseAttributes[0]->newInstance();
			}

			return null;
		}

		/**
		 * Fetches all accessible routable URIs.
		 * An accessible route is determined by the calling HTTP session.
		 * For example, a user logged in will see different available routes
		 * returned here than a user that is not logged in (should that route method
		 * implement an attribute that denies unauthenticated session). Routes that utilize regular expressions
		 * are not included here, because it is impossible to tell - from the framework side - all the possible
		 * ways that regular expression route could lead to a valid page.
		 */
		public function getAllNonRegExURIs(): array{
			$availableURIs = [];

			foreach ($this->routableControllers as $routableController){

				// Get the route base, if there is one
				/** @var RouteBase $routeBase */
				$routeBase = $this->getRouteBaseFromClass($routableController->reflectionClass);
				$baseUri = "";
				if ($routeBase){
					if ($routeBase->isRegex === false){
						$baseUri = $routeBase->uri;
					}else{
						// Skip regular expression routes entirely
						if ($routeBase->isRegex){
							continue;
						}
					}
				}

				/** @var ReflectionMethod[] $methods */
				$publicControllerReflectionMethods = $routableController->reflectionClass->getMethods(filter: ReflectionMethod::IS_PUBLIC);

				foreach($publicControllerReflectionMethods as $reflectionMethod){
					$allURIsForThisControllerMethod = [];

					// Get the attributes (if any) of the method
					$routeAttributes = $reflectionMethod->getAttributes(
						name: Route::class,
						flags:ReflectionAttribute::IS_INSTANCEOF,
					);

					foreach($routeAttributes as $routeAttribute) {
						/** @var RouteAttribute $routeAttribute */
						$routeAttribute = $routeAttribute->newInstance();
						if ($routeAttribute->isRegex === false) {
							$allURIsForThisControllerMethod[] = $baseUri . $routeAttribute->uri;
						}
					}

					// Now check if there are RouteAttribute attribute instances on this method
					// That would prevent this route from being seen by whatever criteria it has.
					$routeAttributeAttributes = $reflectionMethod->getAttributes(
						name: RouteAttribute::class,
						flags:ReflectionAttribute::IS_INSTANCEOF,
					);
					$routeAttributesPassed = 0;
					foreach($routeAttributeAttributes as $reflectionAttribute){
						$instanceOfRouteAttribute = $reflectionAttribute->newInstance();
						if ($instanceOfRouteAttribute->getAttributeResponse()->isRouteUsable){
							++$routeAttributesPassed;
						}
					}

					// Did this route's other methods match to the needed amount to be approved
					// As in, is this route usable/accessible by the current HTTP session that
					// calls this function in the first place?
					if ($routeAttributesPassed === count($routeAttributeAttributes)){
						// Add all URIs found here to the total available URIs
						foreach($allURIsForThisControllerMethod as $uri){
							$availableURIs[] = $uri;
						}
					}
				}
			}

			// Now check all the dynamic route methods
			foreach($this->dynamicRoutes as $dynamicRoute) {
				if (!$dynamicRoute->isRegex){
					// Check the onRenderCheck callback
					if ($dynamicRoute->onRouteCheck !== null) {
						/** @var DynamicRouteResponse $dynamicRouteResponse */
						$dynamicRouteResponse = $dynamicRoute->onRouteCheck->call(new BaseController);
						if (!$dynamicRouteResponse->isRouteUsable) {
							// Skip this dynamic route
							continue;
						}
					}

					$availableURIs[] = $dynamicRoute->requestPath;
				}
			}

			return $availableURIs;
		}

		/**
		 * @param ReflectionClass[] $controllerReflections
		 * @return void
		 */
		public function loadRoutableControllersFromControllerReflections(array $controllerReflections): void{
			foreach ($controllerReflections as $reflection){
				$this->routableControllers[] = new RoutableController(
					reflectionClass: $reflection,
					baselessRequestPath: null, // Defined later during the routeCurrentRequest()
				);
			}
		}

		/**
		 * Routes a request to a controller method, if one matches the set criteria
		 * @throws ReflectionException
		 * @throws NoMatchingRoute
		 */
		public function routeCurrentRequest(): mixed{
			$requestMethod = $this->requestMethod;

			// Get all methods from classes that have either no #[RouteBase] or classes that
			// have #[RouteBase] that match the current router request
			$filteredRoutableControllers = $this->getControllersMatchingRequestBase();

			// Go through all the methods collected from the controller classes
			foreach ($filteredRoutableControllers as $routableController){
				$classInstance = $routableController->reflectionClass->newInstance();
				$controllerPublicMethods = $routableController->reflectionClass->getMethods(filter: ReflectionMethod::IS_PUBLIC);

				// The request path here will be modified if the class
				// the Route attribute is in has a RouteBase.
				// The base, at this point, is already checked and the $requestPath
				// below will have the base chopped off
				$requestPathToCheckMethodsWith = $routableController->baselessRequestPath;

				// Because when a base is chopped off, it is possible
				// for the $requestPath to be "" even though
				// the router constructor checks for this.
				// Fix it here
				if (empty($requestPathToCheckMethodsWith)){
					$requestPathToCheckMethodsWith = "/";
				}

				// The router will first find all methods
				// that have a matching route.
				// Then, later, it will verify any additional attributes
				// also pass. Otherwise, no route is returned/invoked
				$routeReflectionMethodsToAttempt = [];

				// Loop through the methods
				foreach($controllerPublicMethods as $controllerPublicReflectionMethod) {

					// Get the attributes (if any) of the method
					$routeAttributes = $controllerPublicReflectionMethod->getAttributes(
						name: Route::class,
						flags: ReflectionAttribute::IS_INSTANCEOF
					);

					// Loop through attributes and only check the route here
					/** @var ReflectionAttribute $attribute */
					foreach ($routeAttributes as $routeAttribute) {
						$routeAttributeInstance = $routeAttribute->newInstance();

						// Check if the first argument (request method arg)
						// matches the server request method
						if (strtolower($routeAttributeInstance->method) === strtolower($requestMethod)) {

							// Is the route a regular expression?
							if ($routeAttributeInstance->isRegex === false) {
								// No, it is a plain string match
								if ($routeAttributeInstance->uri === $requestPathToCheckMethodsWith) {
									$routeReflectionMethodsToAttempt[] = $controllerPublicReflectionMethod;
								}
							} else {
								// Yes, it needs to be matched against the URI
								$didMatch = preg_match_all($routeAttributeInstance->uri, $requestPathToCheckMethodsWith, $matches);
								if ($didMatch === 1) {
									// Add the matches to the requests GET array
									foreach ($matches as $name => $match) {
										if (is_string($name)) {
											if (isset($match[0])) {
												// Define the matched parameter into the BaseController::$requestParameters
												BaseController::$requestParameters[$name] = $match[0];
											}
										}
									}

									$routeReflectionMethodsToAttempt[] = $controllerPublicReflectionMethod;
								}
							}
						}
					}
				}

				foreach ($routeReflectionMethodsToAttempt as $reflectionMethod){
					// Keep track of which RouteAttributes approve of this request
					$passedAttributes = 0;

					$noxRouteAttributes = $reflectionMethod->getAttributes(
						name: RouteAttribute::class,
						flags:ReflectionAttribute::IS_INSTANCEOF,
					);

					foreach ($noxRouteAttributes as $attribute){
						/** @var RouteAttribute $attrInstance */
						$attrInstance = $attribute->newInstance();
						$attributeResponse = $attrInstance->getAttributeResponse();
						if ($attributeResponse->isRouteUsable){
							++$passedAttributes;
						}else{
							// This attribute says the route is not currently usable.

							// However, a route can alter the current HTTP response
							// Check if this AttributeResponse is doing so
							if ($attributeResponse->responseCode !== null){
								http_response_code($attributeResponse->responseCode);
								if ($attributeResponse->newRequestPath !== null){
									// There is a new request path
									// Rewrite this request
									$this->requestPath = $attributeResponse->newRequestPath;
									$this->routeCurrentRequest();
									exit();
								}else{
									// A response code was set, but no new request path.
									// Just return a blank string in this case.
									return "";
								}
							}else{
								// Break this current loop and move on to the next.
								// The route isn't usable, but the attribute response
								// did not change the HTTP response code or rewrite the route path
								break 1;
							}
						}
					}

					// If the number of valid RouteAttribute attributes equals the number
					// found on this route method, then invoke this route controller
					if ($passedAttributes === count($noxRouteAttributes)){
						/**
						 * Check any Attributes that extend the internal Nox attribute ChosenRouteAttribute
						 * which are attributes that should only run for chosen routes - as they can affect the
						 * response.
						 * @since 1.5.0
						 */
						$chosenRouteAttributes = $reflectionMethod->getAttributes(
							name: ChosenRouteAttribute::class,
							flags:ReflectionAttribute::IS_INSTANCEOF,
						);

						foreach($chosenRouteAttributes as $chosenRouteAttribute){
							$chosenRouteAttribute->newInstance();
						}

						// Invoke the controller's chosen public method
						$routeReturn = $reflectionMethod->invoke($classInstance);
						// Check if the routeReturn is an object that implements the ArrayLike interface
						// If so, convert it to an array
						if ($routeReturn instanceof ArrayLike){
							$routeReturn = $routeReturn->toArray();
						}

						// Check if arrays should be output as JSON
						if (is_array($routeReturn) && BaseController::$outputArraysAsJSON){
							return json_encode($routeReturn);
						}

						return $routeReturn;
					}
				}
			}

			// If nothing was returned at this point, now check all the dynamic routes
			// that are manually added
			foreach($this->dynamicRoutes as $dynamicRoute){
				// Check if this route can be processed
				if ($dynamicRoute->requestMethod === $this->requestMethod) {
					if ($dynamicRoute->onRouteCheck !== null) {
						/** @var DynamicRouteResponse $dynamicRouteResponse */
						$dynamicRouteResponse = $dynamicRoute->onRouteCheck->call(new BaseController);
						if ($dynamicRouteResponse->isRouteUsable === true) {
							// All good
						} else {
							// Route is marked as not usable
							// Is it changing the response code or request path?
							if ($dynamicRouteResponse->responseCode !== null || $dynamicRouteResponse->newRequestPath !== null) {

								if ($dynamicRouteResponse->responseCode !== null) {
									http_response_code($dynamicRouteResponse->responseCode);
								}

								if ($dynamicRouteResponse->newRequestPath !== null) {
									// There is a new request path
									// Instantiate a new request handler now and handle it
									// A new router must also be created
									$this->requestPath = $dynamicRouteResponse->newRequestPath;
									$this->routeCurrentRequest();
									exit();
								}
							}else{
								// Just skip this route
								continue;
							}
						}
					}

					// If we're here, then this route can be checked against the current URI
					if ($dynamicRoute->isRegex === false) {
						if ($this->requestPath === $dynamicRoute->requestPath) {
							return $dynamicRoute->onRender->call(new BaseController);
						}
					}else{
						// Regex checks
						$didMatch = preg_match_all($dynamicRoute->requestPath, $this->requestPath, $matches);
						if ($didMatch === 1){
							// Add the matches to the requests GET array
							foreach ($matches as $name=>$match){
								if (is_string($name)){
									if (isset($match[0])){
										// Define the matched parameter into the BaseController::$requestParameters
										BaseController::$requestParameters[$name] = $match[0];
									}
								}
							}

							return $dynamicRoute->onRender->call(new BaseController);
						}
					}
				}
			}

			throw new NoMatchingRoute();
		}
	}
