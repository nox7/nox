<?php
	namespace Nox\ClassLoader;

	use Nox\ClassLoader\Exceptions\ControllerMissingExtension;
	use Nox\ClassLoader\Exceptions\ModelMissingImplementation;
	use Nox\ORM\Attributes\Model;
	use Nox\ORM\Interfaces\MySQLModelInterface;
	use Nox\Router\Attributes\Controller;
	use Nox\Router\BaseController;
	use ReflectionAttribute;
	use ReflectionClass;
	use ReflectionException;

	/**
	 * Helper class to identify if a class is a Nox Model class by a set criteria
	 */
	class ModelClassIdentifier{
		/**
		 * @throws ReflectionException
		 * @throws ModelMissingImplementation
		 */
		public function getModelClassReflections(array $loadedClassNames): array{
			$modelClassReflections = [];
			foreach($loadedClassNames as $className) {
				$classReflector = new ReflectionClass($className);
				$modelAttributes = $classReflector->getAttributes(
					name:Model::class,
					flags: ReflectionAttribute::IS_INSTANCEOF,
				);

				// Check if it has the Model attribute
				if (!empty($modelAttributes)) {
					$interfaceNames = $classReflector->getInterfaceNames();
					// Verify it implements the MySQLModelInterface
					if (in_array(MySQLModelInterface::class, $interfaceNames)) {
						// It's a Model class
						$modelClassReflections[] = $classReflector;
					} else {
						throw new ModelMissingImplementation(sprintf(
							"A model that has the #[%s] attribute must implement the %s class. Your model class %s is missing this class implementation.",
							Model::class,
							MySQLModelInterface::class,
							$classReflector->getName(),
						));
					}
				}
			}

			return $modelClassReflections;
		}
	}