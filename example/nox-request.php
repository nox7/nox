<?php
	require_once __DIR__ . "/../vendor/autoload.php";

	// Load the router
	$router = new Nox\Router\Router;
	$router->loadAll(__DIR__);

	// Load the request handler
	$requestHandler = new \Nox\Router\RequestHandler(
		$router,
		$_GET['requestPath'],
		$_SERVER['REQUEST_METHOD']
	);

	// Process the request
	$requestHandler->processRequest();