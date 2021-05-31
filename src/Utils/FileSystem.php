<?php
	namespace Nox\Utils;

	class FileSystem{

		/**
		 * Recursively deletes a directory, all subfolder, and all files.
		 */
		public static function recursivelyDeleteDirectory(string $directoryPath): void{
			$contents = array_diff(scandir($directoryPath), ['.', '..']);
			$dir = opendir($directoryPath);
			while(false !== ( $file = readdir($dir)) ) {
				if (( $file != '.' ) && ( $file != '..' )) {
					$full = $directoryPath . '/' . $file;
					if (is_dir($full)) {
						self::recursivelyDeleteDirectory($full);
					}else{
						unlink($full);
					}
				}
			}
			closedir($dir);
			rmdir($directoryPath);
		}

		/**
		 * Copy a file, or recursively copy a folder and its contents
		 * @author      Aidan Lister <aidan@php.net>
		 * @version     1.0.1
		 * @link        http://aidanlister.com/2004/04/recursively-copying-directories-in-php/
		 * @param       string   $source    Source path
		 * @param       string   $dest      Destination path
		 * @param       int      $permissions New folder creation permissions
		 * @return      bool     Returns true on success, false on failure
		 */
		public static function copyDirectory(
			string $source,
			string $dest,
			int $permissions = 0755
		): bool{
			// Check for symlinks
			if (is_link($source)) {
				return symlink(readlink($source), $dest);
			}

			// Simple copy for a file
			if (is_file($source)) {
				return copy($source, $dest);
			}

			// Make destination directory
			if (!is_dir($dest)) {
				mkdir($dest, $permissions, true);
			}

			// Loop through the folder
			if (is_dir($source)) {
				$dir = dir($source);
				$sourceHash = self::hashDirectory($source);
				while (false !== $entry = $dir->read()) {
					// Skip pointers
					if ($entry == '.' || $entry == '..') {
						continue;
					}

					$sourcePath = sprintf("%s/%s", $source, $entry);

					if (is_dir($sourcePath)) {
						// Deep copy directories
						if ($sourceHash != self::hashDirectory($source . "/" . $entry)) {
							self::copyDirectory("$source/$entry", "$dest/$entry", $permissions);
						} else {
							var_dump(sprintf("Ignoring %s", $source . "/" . $entry));
						}
					}else{
						// It's a file. Copy it
						copy($sourcePath, sprintf("%s/%s", $dest, $entry));
					}
				}

				// Clean up
				$dir->close();
			}

			return true;
		}

		/**
		 * In case of coping a directory inside itself, there is a need to hash check the directory otherwise and infinite loop of copying is generated
		 */
		public static function hashDirectory(string $directory): string{
			if (!is_dir($directory)){
				throw new \ValueError("$directory is not a directory");
			}

			$files = array();
			$dir = dir($directory);

			while (false !== ($file = $dir->read())){
				if ($file != '.' and $file != '..') {
					if (is_dir($directory . '/' . $file)){
						$files[] = self::hashDirectory($directory . '/' . $file);
					} else {
						$files[] = md5_file($directory . '/' . $file);
					}
				}
			}

			$dir->close();

			return md5(implode('', $files));
		}
	}