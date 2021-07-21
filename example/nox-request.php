<?php

	use Nox\ORM\Abyss;

	require_once __DIR__ . "/../vendor/autoload.php";

	$requestPath = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
	$requestMethod = $_SERVER['REQUEST_METHOD'];

	// Load the router
	$router = new Nox\Router\Router(
		requestPath:$requestPath,
		requestMethod:$requestMethod,
	);
	$router->loadAll(
		fromDirectory: __DIR__,
	);

	// Load the Abyss ORM configuration
	Abyss::loadConfig(__DIR__);

	// Load the request handler
	$requestHandler = new \Nox\Router\RequestHandler($router);
	\Nox\Router\BaseController::$requestHandler = $requestHandler;

	// Process the request
	$requestHandler->processRequest();