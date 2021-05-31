<?php
	namespace Nox\Router\Interfaces;

	interface RouteAttribute{
		public function getPassed(): bool;
	}