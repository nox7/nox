<?php
	namespace Nox\Router\Interfaces;

	use Nox\Router\AttributeResponse;

	interface RouteAttribute{
		public function getAttributeResponse(): AttributeResponse;
	}