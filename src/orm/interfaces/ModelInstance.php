<?php

	namespace Nox\ORM;

	interface ModelInstance{
		public static function getModel(): MySQLModelInterface;
		public function save(): void;
		public function delete(): void;
	}