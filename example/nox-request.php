<?php

	use Nox\Nox;
	use Nox\ORM\Abyss;
	use Nox\ORM\DatabaseCredentials;
	use Nox\Router\BaseController;
	use Nox\Router\Exceptions\NoMatchingRoute;

	require_once __DIR__ . "/../vendor/autoload.php";
	require_once __DIR__ . "/nox-env.php";

	$nox = new Nox();
	BaseController::$noxInstance = $nox;

	// Set a static file serving directory
	$nox->addStaticDirectory(
		uriStub: "/",
		directoryPath: __DIR__ . "/resources/static",
	);

	// Support static file mime types so the browser can recognize the static files
	$nox->mapExtensionToMimeType("css", "text/css");
	$nox->mapExtensionToMimeType("map", "text/plain");
	$nox->mapExtensionToMimeType("png", "image/png");
	$nox->mapExtensionToMimeType("jpg", "image/jpeg");
	$nox->mapExtensionToMimeType("jpeg", "image/jpeg");
	$nox->mapExtensionToMimeType("js", "text/javascript");
	$nox->mapExtensionToMimeType("mjs", "text/javascript");
	$nox->mapExtensionToMimeType("gif", "image/gif");
	$nox->mapExtensionToMimeType("weba", "audio/webm");
	$nox->mapExtensionToMimeType("webm", "video/webm");
	$nox->mapExtensionToMimeType("webp", "image/webp");
	$nox->mapExtensionToMimeType("pdf", "application/pdf");
	$nox->mapExtensionToMimeType("svg", "image/svg+xml");

	// Mime caches
	$nox->addCacheTimeForMime("image/png", 86400 * 60);
	$nox->addCacheTimeForMime("image/jpeg", 86400 * 60);
	$nox->addCacheTimeForMime("text/css", 86400 * 60);
	$nox->addCacheTimeForMime("text/plain", 86400 * 60);
	$nox->addCacheTimeForMime("text/javascript", 86400 * 60);
	$nox->addCacheTimeForMime("text/gif", 86400 * 60);
	$nox->addCacheTimeForMime("text/svg", 86400 * 60);
	$nox->addCacheTimeForMime("image/webp", 86400 * 60);

	// Process static files before anything else, to keep static file serving fast
	$nox->router->processRequestAsStaticFile();

	// If the code gets here, then it's not a static file. Load the rest of the setting directories
	$nox->setViewsDirectory(__DIR__ . "/resources/views");
	$nox->setLayoutsDirectory(__DIR__ . "/resources/layouts");
	$nox->setSourceCodeDirectory(__DIR__ . "/src");

	// Setup the Abyss ORM (MySQL ORM)
	// Comment the Abyss credentials out if you do not need MySQL
	// Multiple credentials can be added if you have multiple databases/schemas
	Abyss::addCredentials(new DatabaseCredentials(
		host: NoxEnv::MYSQL_HOST,
		username: NoxEnv::MYSQL_USERNAME,
		password: NoxEnv::MYSQL_PASSWORD,
		database: NoxEnv::MYSQL_DB_NAME,
		port: NoxEnv::MYSQL_PORT,
	));

	// Process the request as a routable request
	try {
		$nox->router->processRoutableRequest();
	} catch (NoMatchingRoute $e) {
		// 404
		http_response_code(404);
		// Process a new routable request, but change the path to our known 404 controller method route
		$nox->router->requestPath = "/404";
		$nox->router->processRoutableRequest();
	}