<?php
	namespace Nox\Router;

	use Nox\Nox;

	class BaseController{
		public static Nox $noxInstance;

		/**
		 * @var array Parameters from the route that are captured using regular expressions
		 */
		public static array $requestParameters = [];

		/**
		 * @var bool Flag to output controller method return array values as JSON strings
		 */
		public static bool $outputArraysAsJSON = false;

		/**
		 * @var array|null The parsed request body payload as an array - if it was processed. Helps
		 * in instances of non-PHP support rest request methods such as DELETE/PUT/PATCH.
		 */
		public static ?array $requestPayload = null;
	}