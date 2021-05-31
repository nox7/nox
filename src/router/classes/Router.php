<?php
	namespace Nox\Router;

	use Nox\ORM\Abyss;
	use Nox\RenderEngine\Renderer;

	require_once __DIR__ . "/../Attributes/Route.php";
	require_once __DIR__ . "/../exceptions/InvalidJSON.php";

	class Router{

		private string $controllersFolder = "";
		private array $controllers = [];
		public ?array $noxConfig = null;
		private ViewSettings $viewSettings;
		public StaticFileHandler $staticFileHandler;

		/** @property ReflectionMethod[] $routableMethods */
		public array $routableMethods = [];

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
		public function loadAll(string $fromDirectory): void{
			// Fetch the nox.json
			$noxJson = file_get_contents($fromDirectory . "/nox.json");
			$noxConfig = json_decode($noxJson, true);
			$this->noxConfig = $noxConfig;

			if ($noxConfig === null){
				throw new InvalidJSON("nox.json syntax is invalid.");
			}

			// Fetch the cache.json for cache config
			$cacheJson = file_get_contents($fromDirectory . sprintf("/%s", $noxConfig['cache-config']));
			$cacheConfig = json_decode($cacheJson, true);

			if ($cacheConfig === null){
				throw new InvalidJSON("cache.json syntax is invalid.");
			}

			/**
			 * Setup the static file serving
			 */
			$this->staticFileHandler = new StaticFileHandler;
			$this->staticFileHandler->setStaticFilesDirectory($fromDirectory . $noxConfig['static-directory']);
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
					$this->loadMVCControllers(sprintf("%s/%s", $innerDirectory, $controllerFileName));
				}else{
					// The class name _must_ be the file name minus the extension
					$fileExtension = pathinfo($controllerFileName, PATHINFO_EXTENSION);
					if ($fileExtension === "php"){
						$className = pathinfo($controllerFileName, PATHINFO_FILENAME);
						require_once $controllerPath;
						$classReflector = new \ReflectionClass($className);
						$controllerMethods = $classReflector->getMethods(\ReflectionMethod::IS_PUBLIC);
						$this->routableMethods[] = [new $className(), $controllerMethods];
					}
				}
			}
		}

		/**
		 * Routes a request to a controller
		 */
		public function route(
			string $requestMethod,
			string $uri
		): mixed{

			// Go through all the methods collected from the controller classes
			foreach ($this->routableMethods as $methodData){
				$classInstance = $methodData[0];
				$methods = $methodData[1];

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
					foreach ($attributes as $attribute){
						$attrName = $attribute->getName();

						// Check if this attribute name is "Route"
						if ($attrName === "Route"){
							$routeAttribute = $attribute->newInstance();

							// Check if the first argument (request method arg)
							// matches the server request method
							if (strtolower($routeAttribute->method) === strtolower($requestMethod)){

								// Is the route a regular expression?
								if ($routeAttribute->isRegex === false){
									// No, it is a plain string match
									if ($routeAttribute->uri === $uri){
										$routeMethodsToAttempt[] = $method;
									}
								}else{
									// Yes, it needs to be matched against the URI
									$didMatch = preg_match_all($routeAttribute->uri, $uri, $matches);
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
					foreach ($routeMethodsToAttempt as $method){
						$attributes = $method->getAttributes();

						// Check everything except the route
						$passedAttributes = 0;
						$neededToRoute = count($attributes) - 1;

						foreach ($attributes as $attribute){
							$attrName = $attribute->getName();

							if ($attrName !== "Route"){
								$attrInstance = $attribute->newInstance();
								if ($attrInstance->passed){
									++$passedAttributes;
								}else{
									// This attribute failed. This method is not routable
									// Move on to the next, break this inner for loop
									break 1;
								}
							}
						}

						if ($passedAttributes === $neededToRoute){
							return $method->invoke($classInstance);
						}
					}
				}

			}
			return null;
		}
	}