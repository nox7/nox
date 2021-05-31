<?php
	namespace Nox\RenderEngine;

	require_once __DIR__ . "/exceptions/LayoutDoesNotExist.php";
	require_once __DIR__ . "/Parser.php";
	require_once __DIR__ . "/../router/classes/ViewSettings.php";

	use Nox\Router\ViewSettings;

	class Renderer{

		public ?string $fileLocation;
		public static ?ViewSettings $viewSettings;

		/**
		 * Retrieves the rendered result of the view file.
		 * @param string $viewFileName
		 * @param array $viewScope Array variable injected into the view to transfer data from the controller to the view file
		 * @return string
		 * @throws LayoutDoesNotExist
		 * @throws ParseError
		 */
		public static function renderView(string $viewFileName, array $viewScope = []): string{
			$fileLocation = sprintf("%s/%s", self::$viewSettings->viewsFolder, $viewFileName);
			$parser = new Parser($fileLocation, $viewScope);
			$parser->parse();

			$layoutFileName = $parser->directives['@Layout'];
			$layoutFilePath = sprintf("%s/%s", self::$viewSettings->layoutsFolder, $layoutFileName);

			if (!realpath($layoutFilePath)){
				throw new LayoutDoesNotExist(
					sprintf(
						"The layout %s does not exist in the folder %s",
						$layoutFileName,
						self::$viewSettings->layoutsFolder
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
