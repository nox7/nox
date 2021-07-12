<?php
	require_once __DIR__ . "/../../vendor/autoload.php";

	use \Nox\ORM\Abyss;

	// Setup Abyss from this directory.
	// This is only necessary when not being used with the Router
	// such as from CLI (this script) or on its own
	Abyss::loadConfig(__DIR__ . "/..");
	$abyss = new Abyss();

	// Sync the models to the current local MySQL database
	$abyss->syncModels();