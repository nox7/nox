<?php
	namespace Nox\ClassLoader;

	use Nox\ClassLoader\Exceptions\ControllerMissingExtension;
	use Nox\Router\Attributes\Controller;
	use Nox\Router\BaseController;
	use ReflectionAttribute;
	use ReflectionClass;
	use ReflectionException;

	/**
	 * Helper class to identify if a class is a Nox Controller class by a set criteria
	 */
	class ControllerClassIdentifier{
		/**
		 * @throws ControllerMissingExtension
		 * @throws ReflectionException
		 */
		public function getControllerClassReflections(array $loadedClassNames): array{
			$controllerClassReflections = [];
			foreach($loadedClassNames as $className) {
				$classReflector = new ReflectionClass($className);
				$controllerAttributes = $classReflector->getAttributes(
					name:Controller::class,
					flags: ReflectionAttribute::IS_INSTANCEOF,
				);

				// Check if it has the Controller attribute
				if (!empty($controllerAttributes)) {
					$parentClass = $classReflector->getParentClass();
					// Verify it extends from the BaseController
					if (
						$parentClass instanceof ReflectionClass &&
						$parentClass->getName() === BaseController::class
					) {
						// It's a Controller class
						$controllerClassReflections[] = $classReflector;
					} else {
						throw new ControllerMissingExtension(sprintf(
							"A controller that has the #[%s] attribute must extend the %s class. Your controller class %s is missing this class extension.",
							Controller::class,
							BaseController::class,
							$classReflector->getName(),
						));
					}
				}
			}

			return $controllerClassReflections;
		}
	}