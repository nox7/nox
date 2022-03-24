<?php
	namespace Nox\Router;

	use Nox\Http\Request;
	use Nox\ORM\Abyss;
	use Nox\RenderEngine\Renderer;
	use Nox\Router\Attributes\Route;
	use Nox\Router\Attributes\RouteBase;
	use Nox\Router\Exceptions\InvalidJSON;
	use Nox\Router\Exceptions\RouteBaseNoMatch;
	use Nox\Router\Exceptions\RouteMethodMustHaveANonNullReturn;
	use Nox\Router\Interfaces\RouteAttribute;

	require_once __DIR__ . "/Attributes/Route.php";
	require_once __DIR__ . "/Attributes/RouteBase.php";
	require_once __DIR__ . "/Exceptions/InvalidJSON.php";
	require_once __DIR__ . "/Exceptions/RouteBaseNoMatch.php";

	class Router{

		public string $controllersFolder = "";
		private array $controllers = [];
		public ?array $noxConfig = null;
		public ViewSettings $viewSettings;
		public StaticFileHandler $staticFileHandler;

		/** @property \ReflectionMethod[] $routableMethods */
		public array $routableMethods = [];

		/** @property DynamicRoute[] $dynamicRoutes */
		private array $dynamicRoutes = [];

		public function __construct(
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
		 * Registers a dynamic route. All dynamic routes are attempted after the attribute MVC routes.
		 */
		public function addDynamicRoute(DynamicRoute $dynamicRoute): void{
			$this->dynamicRoutes[] = $dynamicRoute;
		}

		/**
		 * Sets the controllers folder
		 */
		public function setControllersFolder(string $path): void{
			$this->controllersFolder = $path;
		}

		/**
		 * Loads all necessary components for the router to function
		 * from the provided directory
		 */
		public function loadAll(
			string $fromDirectory,
		): void{
			// Fetch the nox.json
			$noxJson = file_get_contents($fromDirectory . "/nox.json");
			$noxConfig = json_decode($noxJson, true);
			$this->noxConfig = $noxConfig;

			if ($noxConfig === null){
				throw new InvalidJSON("nox.json syntax is invalid.");
			}

			// Fetch the nox-cache.json for cache config
			$cacheJson = file_get_contents($fromDirectory . "/nox-cache.json");
			$cacheConfig = json_decode($cacheJson, true);

			if ($cacheConfig === null){
				throw new InvalidJSON("nox-cache.json syntax is invalid.");
			}

			// Fetch the nox-mime.json for recognized static mime types to serve
			$mimeJson = file_get_contents($fromDirectory . "/nox-mime.json");
			$mimeTypesConfig = json_decode($mimeJson, true);

			if ($mimeTypesConfig === null){
				throw new InvalidJSON("nox-mime.json syntax is invalid.");
			}

			/**
			 * Create the mime types instance to give to the static file handler
			 */
			$mimeTypes = new MimeTypes;

			foreach($mimeTypesConfig as $extension=>$contentType){
				$mimeTypes->addMimeType($extension, $contentType);
			}

			/**
			 * Setup the static file serving
			 */
			$this->staticFileHandler = new StaticFileHandler;
			$this->staticFileHandler->mimeTypes = $mimeTypes;

			// Check for the single static directory definition
			if (array_key_exists("static-directory", $noxConfig)) {
				$this->staticFileHandler->addStaticFileDirectory(
					$fromDirectory . $noxConfig['static-directory'],
					"",
				);
			}else{
				// Support multiple static directory definition via directory alias prepends
				foreach($noxConfig['static-directories'] as $uriAlias=>$directoryPath) {
					$this->staticFileHandler->addStaticFileDirectory(
						$fromDirectory . $directoryPath,
						$uriAlias,
					);
				}
			}
			$this->staticFileHandler->setCacheConfig($cacheConfig);

			/**
			 * Setup the layouts and views folder
			 */
			$this->viewSettings = new ViewSettings;
			$this->viewSettings->setLayoutsFolder($fromDirectory . $noxConfig['layouts-directory']);
			$this->viewSettings->setViewsFolder($fromDirectory . $noxConfig['views-directory']);

			/**
			 * Set our own controllers folder
			 */
			$this->setControllersFolder($fromDirectory . $noxConfig['controllers-directory']);
			$this->loadMVCControllers();

			/**
			 * Set the ViewSettings as a static property of the Renderer
			 */
			Renderer::$viewSettings = $this->viewSettings;

			/**
			 * Set the models directory for the ORM, if it's not blank
			 */
			if (!empty($noxConfig['mysql-models-directory'])){
				Abyss::$modelsDirectory = $fromDirectory . $noxConfig['mysql-models-directory'];
			}
		}

		/**
		 * Loads the MVC controller classes
		 * from the controllers folder
		 * @throws \ReflectionException
		 */
		public function loadMVCControllers(
			string $innerDirectory = ""
		): void{
			if ($innerDirectory === ""){
				$fileNames = array_diff(
					scandir(
						$this->controllersFolder
					),
					['.','..'],
				);
			}else{
				$fileNames = array_diff(
					scandir(
						sprintf(
							"%s/%s",
							$this->controllersFolder,
							$innerDirectory
						)
					),
					['.','..'],
				);
			}

			foreach ($fileNames as $controllerFileName){
				if ($innerDirectory === ""){
					$controllerPath = sprintf(
						"%s/%s",
						$this->controllersFolder,
						$controllerFileName,
					);
				}else{
					$controllerPath = sprintf(
						"%s/%s/%s",
						$this->controllersFolder,
						$innerDirectory,
						$controllerFileName,
					);
				}

				if (is_dir($controllerPath)){
					$this->loadMVCControllers(
						innerDirectory: sprintf("%s/%s", $innerDirectory, $controllerFileName),
					);
				}else{
					// Steps to find out which classes were defined in the file and if they are controllers
					// 1) Use get_declared_classes()
					// 2) Require the controller path
					// 3) Use get_declared_classes() again, then array_diff() to find which new classes were added
					// 4) Using a reflection, find out if any of them extend the BaseController

					// Is the iterated file a PHP file?
					$fileExtension = pathinfo($controllerFileName, PATHINFO_EXTENSION);
					if ($fileExtension === "php") {
						$currentDefinedClasses = get_declared_classes();
						require_once $controllerPath;
						$nowDefinedClasses = get_declared_classes();
						$newClassNames = array_diff($nowDefinedClasses, $currentDefinedClasses);
						if (!empty($newClassNames)){
							foreach($newClassNames as $className) {
								$classReflector = new \ReflectionClass($className);
								$parentClass = $classReflector->getParentClass();
								if ($parentClass instanceof \ReflectionClass){
									if ($parentClass->getName() === BaseController::class){
										// It's a Controller class
										$controllerMethods = $classReflector->getMethods(\ReflectionMethod::IS_PUBLIC);
										$this->routableMethods[] = [
											new $className(),
											$controllerMethods,
											$this->requestPath,
										];
									}
								}
							}
						}
					}
				}
			}
		}

		/**
		 * Will filter out the methods that do not have the right base
		 */
		private function filterOutRoutesWithNonMatchingBase(array $routableMethods): array{
			$filteredRoutableMethods = [];
			foreach($this->routableMethods as $methodData){
				$classInstance = $methodData[0];
				try {
					$baselessRequestPath = $this->getBaselessRouteForClass(new \ReflectionClass($classInstance));
					$filteredRoutableMethods[] = [
						$classInstance,
						$methodData[1],
						$baselessRequestPath,
					];
				}catch(RouteBaseNoMatch $e){
					continue;
				}
			}

			return $filteredRoutableMethods;
		}

		/**
		 * Checks if a class can be routed.
		 * Currently only checks for the presence and validity RouteBase attribute.
		 * @throws RouteBaseNoMatch
		 */
		public function getBaselessRouteForClass(\ReflectionClass $classReflection): string{
			$attributes = $classReflection->getAttributes();
			$hasRouteBase = false;

			foreach($attributes as $attributeReflection){
				$attributeName = $attributeReflection->getName();
				if ($attributeName === "Nox\\Router\\Attributes\\RouteBase"){
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
							// Add the matches to the requests GET array
							foreach ($matches as $name=>$match){
								if (is_string($name)){
									if (isset($match[0])){
										$_GET[$name] = $match[0];
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
			$attributes = $reflectionClass->getAttributes();

			/** @var \ReflectionAttribute $attribute */
			foreach($attributes as $attribute){
				if ($attribute->getName() === "Nox\\Router\\Attributes\\RouteBase"){
					/** @var RouteBase $attrInstance */
					return $attribute->newInstance();
				}
			}

			return null;
		}

		/**
		 * Fetches all accessible routable URIs.
		 * An accessible route is determined by the calling HTTP session.
		 * For example, a user logged in will see different available routes
		 * returned here than a user that is not logged in (should that route method
		 * implement an attribute that denies unauthenticated session).
		 */
		public function getAllAccessibleRouteURIs(
			bool $includeRegexRoutes = false,
		): array{
			$availableURIs = [];

			/** @var array $methodData */
			foreach ($this->routableMethods as $methodData){
				$classInstance = $methodData[0];
				$classReflection = new \ReflectionClass($classInstance);

				// Get the route base, if there is one
				/** @var RouteBase $routeBase */
				$routeBase = $this->getRouteBaseFromClass($classReflection);
				$baseUri = "";
				if ($routeBase){
					if ($routeBase->isRegex === false){
						$baseUri = $routeBase->uri;
					}else{
						if ($routeBase->isRegex && $includeRegexRoutes){
							$baseUri = $routeBase->uri;
						}else{
							continue;
						}
					}
				}

				/** @var \ReflectionMethod[] $methods */
				$methods = $methodData[1];

				/** @var \ReflectionMethod $method */
				foreach($methods as $method){
					// Get the attributes (if any) of the method
					$attributes = $method->getAttributes();

					// Variables to keep track of route-affecting attributes
					// and if they allow the route to pass.
					$numMethodsToBeApproved = 0;
					$numMethodsApproved = 0;
					$thisMethodURI = null;
					foreach($attributes as $attribute){
						$routeAttribute = $attribute->newInstance();
						if ($attribute->getName() === "Nox\\Router\\Attributes\\Route"){
							/** @var Route $routeAttribute */
							if ($routeAttribute->isRegex === true && $includeRegexRoutes) {
								$thisMethodURI = $baseUri . $routeAttribute->uri;
							}elseif ($routeAttribute->isRegex === false){
								$thisMethodURI = $baseUri . $routeAttribute->uri;
							}else{
								// Skip this method
								// It's a regex route but includeRegexRoutes is false
								continue;
							}
						}else{
							if ($routeAttribute instanceof RouteAttribute){
								++$numMethodsToBeApproved;
								if ($routeAttribute->getAttributeResponse()->isRouteUsable){
									++$numMethodsApproved;
								}
							}
						}
					}

					// Did this route's other methods match to the needed amount to be approved
					// As in, is this route usable/accessible by the current HTTP session that
					// calls this function in the first place?
					if ($numMethodsToBeApproved === $numMethodsApproved){
						if ($thisMethodURI !== null) {
							$availableURIs[] = $thisMethodURI;
						}
					}
				}
			}

			// Now check all the dynamic route methods
			/** @var DynamicRoute $dynamicRoute */
			foreach($this->dynamicRoutes as $dynamicRoute){
				// Check the onRenderCheck callback
				if ($dynamicRoute->onRouteCheck !== null) {
					/** @var DynamicRouteResponse $dynamicRouteResponse */
					$dynamicRouteResponse = $dynamicRoute->onRouteCheck->call(new BaseController);
					if (!$dynamicRouteResponse->isRouteUsable) {
						// Skip this dynamic route
						continue;
					}
				}

				if ($dynamicRoute->isRegex){
					if ($includeRegexRoutes){
						$availableURIs[] = $dynamicRoute->requestPath;
					}
				}else{
					$availableURIs[] = $dynamicRoute->requestPath;
				}
			}

			return $availableURIs;
		}

		/**
		 * Routes a request to a controller
		 * @throws RouteMethodMustHaveANonNullReturn
		 * @throws \ReflectionException
		 */
		public function route(
			RequestHandler $currentRequestHandler,
		): mixed{
			$requestMethod = $this->requestMethod;
			$filteredRoutableMethods = $this->filterOutRoutesWithNonMatchingBase($this->routableMethods);

			// Go through all the methods collected from the controller classes
			foreach ($filteredRoutableMethods as $methodData){
				$classInstance = $methodData[0];
				$methods = $methodData[1];

				// The request path here will be modified if the class
				// the Route attribute is in has a RouteBase.
				// The base, at this point, is already checked and the $requestPath
				// below will have the base chopped off
				$requestPath = $methodData[2];

				// Because when a base is chopped off, it is possible
				// for the $requestPath to be "" even though
				// the router constructor checks for this.
				// Fix it here
				if (empty($requestPath)){
					$requestPath = "/";
				}

				// The router will first find all methods
				// that have a matching route.
				// Then, later, it will verify any additional attributes
				// also pass. Otherwise, no route is returned/invoked
				$routeMethodsToAttempt = [];

				// Loop through the methods
				foreach($methods as $method){

					// Get the attributes (if any) of the method
					$attributes = $method->getAttributes();

					/**
					 * To be defined eventually...
					 */
					$routeClass = null;
					$routeMethod = null;
					$attemptRouting = false;

					// Loop through attributes and only check the route here
					/** @var \ReflectionAttribute $attribute */
					foreach ($attributes as $attribute){
						$attrName = $attribute->getName();

						// Check if this attribute name is "Route"
						if ($attrName === Route::class){
							$routeAttribute = $attribute->newInstance();

							// Check if the first argument (request method arg)
							// matches the server request method
							if (strtolower($routeAttribute->method) === strtolower($requestMethod)){

								// Is the route a regular expression?
								if ($routeAttribute->isRegex === false){
									// No, it is a plain string match
									if ($routeAttribute->uri === $requestPath){
										$routeMethodsToAttempt[] = $method;
									}
								}else{
									// Yes, it needs to be matched against the URI
									$didMatch = preg_match_all($routeAttribute->uri, $requestPath, $matches);
									if ($didMatch === 1){
										// Add the matches to the requests GET array
										foreach ($matches as $name=>$match){
											if (is_string($name)){
												if (isset($match[0])){
													// Define the matched parameter into the BaseController::$requestParameters
													BaseController::$requestParameters[$name] = $match[0];

													// TODO Deprecate/Remove this
													$_GET[$name] = $match[0];
												}
											}
										}

										$routeMethodsToAttempt[] = $method;
									}
								}
							}
						}
					}

					// Loop through the methods that routes matched
					// and run their additional attributes, if any.
					// The first one to pass all should be invoked as the correct
					// route.
					$acceptedRoutes = [];

					/** @var \ReflectionMethod $routableMethod */
					foreach ($routeMethodsToAttempt as $routableMethod){
						$attributes = $routableMethod->getAttributes();

						// Keep track of which attributes are RouteAttribute instances
						$neededToRoute = 0;

						// Keep track of which RouteAttributes approve of this request
						$passedAttributes = 0;

						foreach ($attributes as $attribute){
							/** @var RouteAttribute $attrInstance */
							$attrInstance = $attribute->newInstance();
							if ($attrInstance instanceof RouteAttribute){
								++$neededToRoute;

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
											// Instantiate a new request handler now and handle it
											// A new router must also be created
											$newRouter = new Router(
												$attributeResponse->newRequestPath,
												$this->requestMethod,
											);
											$newRouter->staticFileHandler = $this->staticFileHandler;
											$newRouter->viewSettings = $this->viewSettings;
											$newRouter->noxConfig = $this->noxConfig;
											$newRouter->controllersFolder = $this->controllersFolder;
											$newRouter->loadMVCControllers();
											$newRequestHandler = new RequestHandler($newRouter);
											$newRequestHandler->processRequest();
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
						}

						// If the number of valid RouteAttribute attributes equals the number
						// found on this route method, then invoke this route controller
						if ($passedAttributes === $neededToRoute){
							$routeReturn = $routableMethod->invoke($classInstance);
							if ($routeReturn === null){
								// A route must have a return type, otherwise
								// returning null here would make the request handler
								// think this is a 404
								throw new RouteMethodMustHaveANonNullReturn(
									sprintf(
										"A route was matched and the method %s::%s was called, but null was returned. All route methods must have a non-null return type.",
										$classInstance::class,
										$routableMethod->name,
									)
								);
							}else{
								return $routeReturn;
							}
						}
					}
				}
			}

			// If nothing was returned at this point, now check all of the dynamic routes
			// that are manually added
			/** @var DynamicRoute $dynamicRoute */
			foreach($this->dynamicRoutes as $dynamicRoute){
				// Check if this route can be processed

				if ($dynamicRoute->requestMethod === $this->requestMethod) {
					if ($dynamicRoute->onRouteCheck !== null) {
						/** @var DynamicRouteResponse $dynamicRouteResponse */
						$dynamicRouteResponse = $dynamicRoute->onRouteCheck->call(new BaseController);
						if ($dynamicRouteResponse->isRouteUsable === true) {
							// All good
						} else {
							if ($dynamicRouteResponse->responseCode !== null || $dynamicRouteResponse->newRequestPath !== null) {
								if ($dynamicRouteResponse->responseCode !== null) {
									http_response_code($dynamicRouteResponse->responseCode);
								}
								if ($dynamicRouteResponse->newRequestPath !== null) {
									// There is a new request path
									// Instantiate a new request handler now and handle it
									// A new router must also be created
									$newRouter = new Router(
										$dynamicRouteResponse->newRequestPath,
										$this->requestMethod,
									);
									$newRouter->staticFileHandler = $this->staticFileHandler;
									$newRouter->viewSettings = $this->viewSettings;
									$newRouter->noxConfig = $this->noxConfig;
									$newRouter->controllersFolder = $this->controllersFolder;
									$newRouter->loadMVCControllers();
									$newRequestHandler = new RequestHandler($newRouter);
									$newRequestHandler->processRequest();
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
							$renderReturn = $dynamicRoute->onRender->call(new BaseController);
							if ($renderReturn === null){
								throw new RouteMethodMustHaveANonNullReturn(
									sprintf(
										"A dynamic route was matched and called for the route %s, but the dynamic route's onRender callback returned null. All dynamic route callbacks must return a non-null value.",
										$this->requestPath,
									)
								);
							}
							return $renderReturn;
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

										// TODO Deprecate/Remove this
										$_GET[$name] = $match[0];
									}
								}
							}
							$renderReturn = $dynamicRoute->onRender->call(new BaseController);
							if ($renderReturn === null){
								throw new RouteMethodMustHaveANonNullReturn(
									sprintf(
										"A dynamic route was matched and called for the route %s, but the dynamic route's onRender callback returned null. All dynamic route callbacks must return a non-null value.",
										$this->requestPath,
									)
								);
							}
							return $renderReturn;
						}
					}
				}
			}

			return null;
		}
	}
