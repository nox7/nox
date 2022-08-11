<?php
	require_once __DIR__ . "/../../vendor/autoload.php";
	require_once __DIR__ . "/../nox-env.php";

	use Nox\ClassLoader\ClassLoader;
	use Nox\Nox;
	use Nox\ORM\Abyss;
	use Nox\ORM\DatabaseCredentials;

	// Load the source directory
	$nox = new Nox();
	$nox->setSourceCodeDirectory(__DIR__ . "/../src");

	// Get the model reflections
	$modelReflections = ClassLoader::$modelClassReflections;

	// Load the credentials for any and all databases used by the models
	Abyss::addCredentials(new DatabaseCredentials(
		host: NoxEnv::MYSQL_HOST,
		username: NoxEnv::MYSQL_USERNAME,
		password: NoxEnv::MYSQL_PASSWORD,
		database: NoxEnv::MYSQL_DB_NAME,
		port: NoxEnv::MYSQL_PORT,
	));

	$abyss = new Abyss();

	// Sync the models to the current local MySQL database
	print("Synchronizing Models to MySQL database.\n");
	$abyss->syncModels($modelReflections);
	print("Synchronization finished.\n");