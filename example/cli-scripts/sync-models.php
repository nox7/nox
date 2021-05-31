<?php
	require_once __DIR__ . "/../../vendor/autoload.php";

	// For this example, User class
	require_once __DIR__ . "/../classes/User.php";

	use \Nox\ORM\Abyss;

	// Setup Abyss from this directory.
	// This is only necessary when not being used with the Router
	// such as from CLI (this script) or on its own
	Abyss::loadConfig(__DIR__ . "/..");
	$abyss = new Abyss();

	// Sync the models to the current local MySQL database
	$abyss->syncModels();

	// For this example, test User creation
	$newUser = $abyss->instanceFromModel(User::getModel()); // Fetch blank User class
	$newUser->save(); // This will insert it into the DB
	var_dump($newUser->id); // Will be the new user ID

	// Can modify and save it again
	$newUser->name = "nox" . rand(1,100);
	$newUser->save();