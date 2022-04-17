<?php

	namespace Nox\ORM\Interfaces;

	interface MySQLModelInterface{
		public function getDatabaseName(): string;
		public function getInstanceName(): string;
		public function getName(): string;
		public function getColumns(): array;
	}
