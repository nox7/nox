<?php
	namespace Nox\Router;

	use Nox\Http\Request;
	use Nox\ORM\Abyss;
	use Nox\RenderEngine\Renderer;
	use Nox\Router\Attributes\Route;
	use Nox\Router\Attributes\RouteBase;
	use Nox\Router\Exceptions\InvalidJSON;
	use Nox\Router\Exceptions\RouteBaseNoMatch;
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

		public function __construct(
			public string $requestPath,
			public string $requestMethod,
		){
			if (!str_starts_with($this->requestPath, "/")){
				$this->requestPath = "/" . $this->requestPath;
			}
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
		 */
		public function loadMVCControllers(
			string $innerDirectory = ""
		): void{
			if ($innerDirectory === ""){
				$fileNames = array_diff(scandir($this->controllersFolder), ['.','..']);
			}else{
				$fileNames = array_diff(scandir(sprintf("%s/%s", $this->controllersFolder, $innerDirectory)), ['.','..']);
			}

			foreach ($fileNames as $controllerFileName){
				if ($innerDirectory === ""){
					$controllerPath = sprintf("%s/%s", $this->controllersFolder, $controllerFileName);
				}else{
					$controllerPath = sprintf("%s/%s/%s", $this->controllersFolder, $innerDirectory, $controllerFileName);
				}

				if (is_dir($controllerPath)){
					$this->loadMVCControllers(
						innerDirectory: sprintf("%s/%s", $innerDirectory, $controllerFileName),
					);
				}else{
					// The class name _must_ be the file name minus the extension
					$fileExtension = pathinfo($controllerFileName, PATHINFO_EXTENSION);
					if ($fileExtension === "php"){
						$className = pathinfo($controllerFileName, PATHINFO_FILENAME);
						require_once $controllerPath;
						$classReflector = new \ReflectionClass($className);
						$controllerMethods = $classReflector->getMethods(\ReflectionMethod::IS_PUBLIC);
						try {
							$baselessRequestPath = $this->getBaselessRouteForClass($classReflector);
							$this->routableMethods[] = [
								new $className(),
								$controllerMethods,
								$baselessRequestPath,
							];
						}catch(RouteBaseNoMatch $e){

						}
					}
				}
			}
		}

		/**
		 * Checks if a class can be routed.
		 * Currently only checks for the presence and validity RouteBase attribute.
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
		 * Routes a request to a controller
		 */
		public function route(
			string $requestMethod,
			RequestHandler $currentRequestHandler,
		): mixed{

			// Go through all the methods collected from the controller classes
			foreach ($this->routableMethods as $methodData){
				$classInstance = $methodData[0];
				$methods = $methodData[1];

				// The request path here will be modified if the class
				// the Route attribute is in has a RouteBase.
				// The base, at this point, is already checked and the $requestPath
				// below will have the base chopped off
				$requestPath = $methodData[2];

				if (!str_starts_with($requestPath, "/")){
					$requestPath = "/" . $requestPath;
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
						if ($attrName === "Nox\\Router\\Attributes\\Route"){
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
											$newRequestHandler = new RequestHandler(
												$newRouter,
												$attributeResponse->newRequestPath,
												$currentRequestHandler->requestType,
												$currentRequestHandler->recursionDepth,
											);
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
							}else {
								return $routeReturn;
							}
						}
					}
				}

			}
			return null;
		}
	}
