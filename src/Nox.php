<?php
	namespace Nox;

	use Nox\ClassLoader\ClassLoader;
	use Nox\ClassLoader\ClassScopeHelper;
	use Nox\ClassLoader\Exceptions\ControllerMissingExtension;
	use Nox\ClassLoader\Exceptions\ModelMissingImplementation;
	use Nox\RenderEngine\Renderer;
	use Nox\Router\Mime\MimeTypes;
	use Nox\Router\Router;
	use Nox\Router\StaticDirectorySetting;
	use Nox\Router\StaticFileHandler;
	use Nox\Utils\FileSystem;
	use ReflectionException;
	use ValueError;

	class Nox{

		private string $sourceCodeDirectory;
		private string $viewsDirectory;
		private string $layoutsDirectory;
		public MimeTypes $supportedMimeTypes;
		public Router $router;
		public StaticFileHandler $staticFileHandler;

		/** @var StaticDirectorySetting[]  */
		private array $staticDirectorySettings = [];

		public function __construct(){
			$requestPath = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
			$requestMethod = $_SERVER['REQUEST_METHOD'];

			$this->supportedMimeTypes = new MimeTypes();
			$this->router = new Router(
				noxInstance: $this,
				requestPath: $requestPath,
				requestMethod: $requestMethod
			);
			$this->staticFileHandler = new StaticFileHandler(
				noxInstance: $this,
			);

			// Set the renderer's Nox instance
			Renderer::$noxInstance = $this;
		}

		/**
		 * Sets the directory from which your project's PHP source code (classes and such) are houses. All PHP files
		 * here will be autoloaded into the environment after this call.
		 * @throws ReflectionException
		 * @throws ModelMissingImplementation
		 * @throws ControllerMissingExtension
		 */
		public function setSourceCodeDirectory(string $directoryPath): void{
			// Cache the currently defined class names
			ClassScopeHelper::cacheCurrentClassNames();

			$this->sourceCodeDirectory = $directoryPath;

			// Load all the classes and their subdirectories as well
			$allFullFilePaths = [];
			FileSystem::recursivelyFetchAllFileNames(
				parentDirectory: $this->sourceCodeDirectory,
				arrayToAddTo:$allFullFilePaths,
			);

			// Require every single one of the files
			foreach($allFullFilePaths as $filePath){
				// Verify it is a PHP file before including
				$fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
				if ($fileExtension === "php") {
					require_once $filePath;
				}
			}

			// Fetch all the newly defined class names
			$allAppLoadedClassNames = ClassScopeHelper::getNewClassNamesDefined();

			// Store them in the ClassLoader
			ClassLoader::$allAppLoadedClasses = $allAppLoadedClassNames;

			// Sort them into controllers and models to be used by the router and ORM
			ClassLoader::runClassFiltersAndSorting();

			// Load controller reflections into the router as RoutableControllers
			$this->router->loadRoutableControllersFromControllerReflections(ClassLoader::$controllerClassReflections);
		}

		public function setLayoutsDirectory(string $directoryPath): void{
			$this->layoutsDirectory = $directoryPath;
		}

		public function setViewsDirectory(string $directoryPath): void{
			$this->viewsDirectory = $directoryPath;
		}


		public function getLayoutsDirectory(): string{
			return $this->layoutsDirectory;
		}

		public function getViewsDirectory(): string {
			return $this->viewsDirectory;
		}

		/**
		 * @param string $uriStub
		 * @param string $directoryPath
		 * @return void
		 * @throws ValueError
		 */
		public function addStaticDirectory(string $uriStub, string $directoryPath): void{

			if (empty($uriStub)){
				throw new ValueError("The uriStub must not be an empty string. For root directory static file serving, just use a forward slash.");
			}

			$this->staticDirectorySettings[] = new StaticDirectorySetting(
				uriPathStub: $uriStub,
				staticFilesDirectory: $directoryPath,
			);
		}

		/**
		 * @return StaticDirectorySetting[]
		 */
		public function getStaticDirectorySettings(): array{
			return $this->staticDirectorySettings;
		}

		public function mapExtensionToMimeType(string $fileExtension, string $mimeType): void{
			$this->supportedMimeTypes->addMimeType(
				extension: $fileExtension,
				contentType:$mimeType,
			);
		}
	}