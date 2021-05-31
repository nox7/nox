<?php

	namespace Nox\ORM\Interfaces;

	interface MySQLModelInterface{
		public function getInstanceName(): string;
		public function getName(): string;
		public function getColumns(): array;
	}
