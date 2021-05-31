<?php

	namespace Nox\Router;

	class FileBuffer{

		private ?string $filePath = null;
		private string $result = "";

		public function __construct(string $filePath){
			$this->filePath = $filePath;
		}

		/**
		* Buffers a file into the $result property
		*/
		public function buffer(): void{
			ob_start();
			include $this->filePath;
			$this->result = ob_get_contents();
			ob_end_clean();
		}

		/**
		* Gets the result of the buffer
		*/
		public function getResult(): string{
			return $this->result;
		}

	}
