<?php
	namespace Nox\Router;

	class BaseController{
		static ?RequestHandler $requestHandler = null;

		/**
		 * @var array Parameters from the route that are captured using regular expressions
		 */
		public static array $requestParameters = [];

		/**
		 * @var bool Flag to output controller method return array values as JSON strings
		 */
		public static bool $outputArraysAsJSON = false;
	}