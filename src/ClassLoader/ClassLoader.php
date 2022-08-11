<?php
	namespace Nox\ClassLoader;

	use Nox\ClassLoader\Exceptions\ControllerMissingExtension;
	use Nox\ORM\Attributes\Model;
	use ReflectionClass;
	use ReflectionException;

	class ClassLoader{

		/** @var string[] All classes loaded by the Nox framework from the user's app */
		public static array $allAppLoadedClasses = [];

		/** @var ReflectionClass[] Reflections of classes identified as Controllers */
		public static array $controllerClassReflections = [];

		/** @var ReflectionClass[] Reflections of classes identified as Models */
		public static array $modelClassReflections = [];

		/**
		 * Will loop through self::$allAppLoadedClasses and determine which type of Nox-identified class
		 * they are. E.g., a Controller or a Model class. Then it will sort them into the necessary static properties
		 * @return void
		 * @throws ControllerMissingExtension
		 * @throws ReflectionException
		 * @throws Exceptions\ModelMissingImplementation
		 */
		public static function runClassFiltersAndSorting(){
			$controllerClassIdentifier = new ControllerClassIdentifier();
			$modelClassIdentifier = new ModelClassIdentifier();
			self::$controllerClassReflections = $controllerClassIdentifier->getControllerClassReflections(self::$allAppLoadedClasses);
			self::$modelClassReflections = $modelClassIdentifier->getModelClassReflections(self::$allAppLoadedClasses);
		}

	}
