<?php

	namespace Nox\ORM;

	interface MySQLModelInterface{
		public function getInstanceName(): string;
		public function getName(): string;
		public function getColumns(): array;
	}
