<?php
	require_once __DIR__ . "/../Utils/FileSystem.php";

	use Nox\Utils\FileSystem;

	$exampleDirectory = __DIR__ . "/../../example";
	FileSystem::copyDirectory($exampleDirectory, getcwd());