<?php

	namespace Nox\ORM\Interfaces;

	interface ModelInstance{
		public static function getModel(): MySQLModelInterface;
	}