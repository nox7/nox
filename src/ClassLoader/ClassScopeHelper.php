<?php
	namespace Nox\ClassLoader;

	class ClassScopeHelper{

		/** @var string[] Of currently declared classnames after cacheCurrentClassNames is called */
		public static array $currentClassNameCache = [];

		public static function cacheCurrentClassNames(): void{
			self::$currentClassNameCache = get_declared_classes();
		}

		public static function getNewClassNamesDefined(): array{
			$nowDefinedClasses = get_declared_classes();
			return array_diff($nowDefinedClasses, self::$currentClassNameCache);
		}
	}
