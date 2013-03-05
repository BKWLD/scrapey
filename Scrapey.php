<?php

// Load dependencies
require_once(Bundle::path('scrapey').'vendor/opengraph/OpenGraph.php');
require_once(Bundle::path('scrapey').'vendor/bkwld-php-library/File.php');
use BKWLD\Utils\File;

class Scrapey {
	
	/**
	 * Go info about a URL.  The response is an object that has:
	 * - title
	 * - images - An array of absolute paths or URLs
	 * - description
	 * ... and any other og tags like site_name, url, type, etc
	 * 
	 * If no data can be found, false is returned
	 */
	static public function lookup($url) {
		
		// This can take awhile
		set_time_limit(30);
		
		// Require the URL to include the protocol
		if (!preg_match('#^http#', $url)) throw new Exception('Include protocol: '.$url);
		
		// Look for Open Graph tags as well as standard meta tags.  This call
		// will get regular title and description metas.
		$graph = OpenGraph::fetch($url);
		if (!$graph) return false;
		
		// Make a new object from the response.  Put image in an array so the resposne
		// is the same whether we have to scrape for images or not.
		$response = new stdClass;
		foreach($graph as $key => $val) {
			if ($key == 'image') $response->images = array(self::parse_ref($val, $url));
			else $response->$key = $val;
		}
		
		// If no images were fetched, scrape the page for images
		if (empty($response->images)) {
			$response->images = self::scrape_images($url);
		}

		// Download images to the local filesystem
		if (Config::has('scrapey::scrapey.download_dir') && !empty($response->images)) {
			$response->images = self::download_images($response->images);
		}

		// Return findings
		return $response;
	}
	
	/**
	 * Get all the image tags on a URL
	 */
	static private function scrape_images($url) {
		
		// Get the source of the page
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		$html = curl_exec($ch);
		curl_close($ch);
		
		// Collect all the img tags
		preg_match_all("#<\s*img[^>]*src=(?:'|\")?([^'\"\s]*)(?:'|\")?[^>]*>#i", $html, $matches);
		if (empty($matches)) return array();
		$imgs = $matches[1];
		
		// Only support the most common image formats
		$imgs = array_filter($imgs, function($img) {
			return preg_match('#(jpg|jpeg|gif|png)$#i', $img);
		});
		
		// Prepend domain and url to all images
		$imgs = array_map(function($img) use ($url) {
			return Scrapey::parse_ref($img, $url);
		}, $imgs);
		
		// Return massaged set of images
		return $imgs;
	}
	
	/**
	 * Get a full URL to an image.  This is public so it can be executed by the
	 * anonymouse function that is created in array_map() above.  In PHP 5.4
	 * Closure::bind() could be used.
	 * @param $img A reference to an image
	 * @param $url The URL that was being looked up
	 */
	static public function parse_ref($img, $url) {
	
		// Current protocol
		preg_match('#^(https?)://[^/]+#i', $url, $matches);
		$protocol = $matches[1];
		$protocol_and_domain = $matches[0];
		
		// Do nothing if url has protocol
		if (preg_match('#^http#i', $img)) return $img;
		
		// If a wildcard protocol (i.e. //domain.com/path/...), match the current protocol
		if (preg_match('#^//#', $img)) return $protocol . ':' . $img;
		
		// If a relative path, append the full url
		if (preg_match('#^[^/]#', $img)) return $url . $img;
		
		// If an absolute path, append the protocol and domain
		if (preg_match('#^/#', $img)) return $protocol_and_domain . $img;
		
		// If it hasn't been catched yet, it's some new condition I haven't accounted for
		throw new Exception('Unaccounted for ref: '.$img);
		
	}
	
	/**
	 * Download all the images to the local filesystem
	 */
	static private function download_images($imgs) {
		$dir = Config::get('scrapey::scrapey.download_dir');
		
		// Limit the number of images that get downloaded
		$imgs = array_slice($imgs, 0, Config::get('scrapey::scrapey.max_imgs'));
		
		// Loop through imgs
		$imgs = array_map(function($img) use ($dir) {
			
			// Figure out where to store the image			
			$dst = File::make_sub_dirs($dir);
			$file = uniqid().'.'.strtolower(pathinfo($img, PATHINFO_EXTENSION));
			$dst .= $file;
			
			// Download the image
			$fp = fopen($dst, 'w');
			$ch = curl_init($img);
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			if (!curl_exec($ch)) throw new Exception('File download error: '.$img.' -> '.$dst);
			curl_close($ch);
			fclose($fp);
			
			// Update the path
			return File::public_path($dst);
			
		}, $imgs);
		
		// Return the images
		return $imgs;
		
	}
}
