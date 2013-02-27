<?php namespace BKWLD\Utils;

// Utilities for dealing with files
class File {
	
	/**
	 * Moves a file from it's currently location to a new destination, being 
	 * careful to not overwrite anything there.  Only for uploaded files
	 * @param string $src Path to the current file
	 * @param string $dst Path to where you want it go
	 * @return mixed New path or false on error
	 */
	static public function move_uploaded_file_uniquely($src, $dst) {
		
		// Make sure it's an uploaded file
		if (!is_uploaded_file($src)) return false;
		
		// The file doesn't exist, so straight move it
		if (!file_exists($dst)) {
			move_uploaded_file($src, $dst);
			return $dst;
		}
		
		// Try different suffixes on the file until a match doesn't exist
		$dir = dirname($dst);
		$file = pathinfo($dst, PATHINFO_FILENAME);
		$i = 1;
		$ext = pathinfo($dst, PATHINFO_EXTENSION);
		while (file_exists($dst = $dir.'/'.$file.$i.'.'.$ext)) {
			$i++;
		}
		
		// Move the file and return the new path
		move_uploaded_file($src, $dst);
		return $dst;
		
	}

	/**
	 * Create a number of subdirectories witihin the provided folder. This
	 * is done to get around filesystem limitations when you create too many
	 * files in a given directory.  Also makes FTP and SSH listings faster.
	 * @param string $dir The directory to create sub directories in 
	 * @param number $depth How deep to make them
	 * @param number $length How many to make per depth
	 * @return mixed New path (with trailing slash) or false on error
	 */
	static public function make_sub_dirs($dir, $depth = 2, $length = 16) {
		
		// Make sure the destination is writeable
		if (!is_dir($dir) || !is_writable($dir)) return false;
		
		// Make sure the destination ends in a slash
		$dir = self::add_trailing_slash($dir);
		
		// Loop through the depth, making directories
		for ($i=0; $i<$depth; $i++) {
			$new_dir = str_pad(mt_rand(0, $length - 1), strlen($length), '0', STR_PAD_LEFT);
			$dir .= $new_dir.'/'; // Update our directory path
			if (is_dir($dir)) continue; // This directory already exists, go to next depth
			if (!mkdir($dir, 0755)) return false; // Make the dir or return false if error
		}
		
		// Return new path
		return $dir;
	}
	
	/**
	 * Combine a bunch of comon operations on uploaded files into a single
	 * command: Simplify the filename, make it unique, and store it in a nested
	 * directory
	 * @param mixed $src Path to the uploaded file or FILES array
	 * @param string $dst Directory of where to save the final file
	 * @return mixed New path or false on error
	 */
	static public function organize_uploaded_file($src, $dst_dir) {
		
		// Make sure the destination ends in a slash
		$dst_dir = self::add_trailing_slash($dst_dir);
		
		// If $src is a FILES array, get the tmp and real filenames out
		if (is_array($src)) {
			$filename = $src['name'];
			$src = $src['tmp_name'];
						
		// Otherwise, use the filename of $src for the destination path
		} else {
			$filename = basename($src);
		}
		
		// Make sure it's an uploaded file
		if (!is_uploaded_file($src)) return false;
		
		// Make nested sub directories
		if (!($dst_dir = self::make_sub_dirs($dst_dir))) return false;
		
		// Make the file a safe filename
		$filename = preg_replace('/[^a-z0-9-_.]/', '', strtolower($filename));
		
		// Move the file out of it's current directory, into the target destination
		if (!($path = self::move_uploaded_file_uniquely($src, $dst_dir.$filename))) return false;
		
		// Return the final path
		return $path;
	}
	
	/**
	 * Add the trailing slash onto a directory name
	 * @param string $dir The path to a directory
	 * @return string The slash added to the name
	 */
	static public function add_trailing_slash($dir) {
		if (substr($dir, -1, 1) != '/') $dir .= '/';
		return $dir;
	}
	
	/**
	 * Remove the document root from a path.  Like to get the embed path to an
	 * image given it's `realpath`.  I prefer to save this in the database rather than
	 * the absolute path because it makes migrating data between enviornments easier.
	 * @param string $path The absolute path in the filesystem
	 * @return string The converted, web friendly relative path
	 */
	static public function public_path($path) {
		
		// Require a DOCUMENT_ROOT.  It could be missing when executed from the command line
		if (empty($_SERVER['DOCUMENT_ROOT'])) throw new \Exception('DOCUMENT_ROOT not defined');
		
		// If document root isn't in the path, this probably isn't an absolute path
		if (strpos($path, $_SERVER['DOCUMENT_ROOT']) === false) return $path;
		
		// Remove the document root from the string
		return str_replace($_SERVER['DOCUMENT_ROOT'], '', $path);
	}
	
	/**
	 * Set the appropriate headers to trigger a file download.  I took this from:
	 * http://davidwalsh.name/php-force-download
	 * @param string $path The absolute path to the file
	 */
	static public function download($path) {
		
		// Required for IE
		if(ini_get('zlib.output_compression')) { ini_set('zlib.output_compression', 'Off');  }

		// Get the file mime type using the file extension
		switch(strtolower(substr(strrchr($path, '.'), 1))) {
			case 'pdf': $mime = 'application/pdf'; break;
			case 'zip': $mime = 'application/zip'; break;
			case 'jpeg':
			case 'jpg': $mime = 'image/jpg'; break;
			case 'gif': $mime = 'image/gif'; break;
			case 'png': $mime = 'image/png'; break;
			default: $mime = 'application/force-download';
		}
		
		// Set headers and output it
		header('Pragma: public');   // required
		header('Expires: 0');    // no cache
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Last-Modified: '.gmdate ('D, d M Y H:i:s', filemtime ($path)).' GMT');
		header('Cache-Control: private',false);
		header('Content-Type: '.$mime);
		header('Content-Disposition: attachment; filename="'.basename($path).'"');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: '.filesize($path));  // provide file size
		header('Connection: close');
		readfile($path);    // push it out
		exit();
		
	}
	
}