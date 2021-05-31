<?php

	namespace Nox\ORM;

	class Pager{

		public function __construct(
			public int $pageNumber = 1,
			public int $limit = 1,
		){

		}

	}