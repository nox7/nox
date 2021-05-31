<?php

	namespace Nox\ORM\Interfaces;

	interface ModelInstance{
		public static function getModel(): MySQLModelInterface;
		public function save(): void;
		public function delete(): void;
	}