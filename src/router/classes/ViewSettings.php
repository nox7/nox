<?php

	namespace Nox\Router;

	class ViewSettings{
		public string $viewsFolder = "";
		public string $layoutsFolder = "";

		public function setViewsFolder(string $directoryPath): void{
			$this->viewsFolder = $directoryPath;
		}

		public function setLayoutsFolder(string $directoryPath): void{
			$this->layoutsFolder = $directoryPath;
		}

		public function getViewFilePath(string $fileName): string{
			return sprintf("%s/%s", $this->viewsFolder, $fileName);
		}
	}
