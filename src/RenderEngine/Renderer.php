<?php
	namespace Nox\RenderEngine;

	require_once __DIR__ . "/Exceptions/LayoutDoesNotExist.php";
	require_once __DIR__ . "/Exceptions/ViewFileDoesNotExist.php";
	require_once __DIR__ . "/Parser.php";
	require_once __DIR__ . "/../Router/ViewSettings.php";

	use Nox\Nox;
	use Nox\RenderEngine\Exceptions\LayoutDoesNotExist;
	use Nox\RenderEngine\Exceptions\ParseError;
	use Nox\RenderEngine\Exceptions\ViewFileDoesNotExist;
	use Nox\Router\ViewSettings;

	class Renderer{

		public static Nox $noxInstance;

		/**
		 * Retrieves the rendered result of the view file.
		 * @param string $viewFileName
		 * @param array $viewScope Array variable injected into the view to transfer data from the controller to the view file
		 * @return string
		 * @throws LayoutDoesNotExist
		 * @throws ParseError
		 * @throws ViewFileDoesNotExist
		 */
		public static function renderView(string $viewFileName, array $viewScope = []): string{
			$viewsFolder = self::$noxInstance->getViewsDirectory();
			$layoutsFolder = self::$noxInstance->getLayoutsDirectory();
			$fileLocation = sprintf("%s/%s", $viewsFolder, $viewFileName);

			if (!realpath($fileLocation)){
				throw new ViewFileDoesNotExist(sprintf("No view file at file path: %s", $fileLocation));
			}

			$parser = new Parser($fileLocation, $viewScope);
			$parser->parse();

			$layoutFileName = $parser->directives['@Layout'];
			$layoutFilePath = sprintf("%s/%s", $layoutsFolder, $layoutFileName);

			if (!realpath($layoutFilePath)){
				throw new LayoutDoesNotExist(
					sprintf(
						"The layout %s does not exist in the directory %s",
						$layoutFileName,
						$layoutsFolder
					)
				);
			}

			$viewResult = "";
			$htmlBody = $parser->directives['@Body'];
			$htmlHead = $parser->directives['@Head'];
			ob_start();
			include $layoutFilePath;
			$viewResult = ob_get_contents();
			ob_end_clean();

			// Push the parsed view
			return $viewResult;
		}
	}
