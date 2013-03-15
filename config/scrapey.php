<?php return array(
	
	// If defined, download the local images to this directory
	'download_dir' => path('public').'uploads',
	
	// The maximum number of images to download
	'max_imgs' => 6,
	
	// If there is an open graph image, still scrape additional ones?
	'always_scrape_imgs' => true,
	
	// Set a minimum size for the images that are shown.  This requires
	// a download_dir to be set.
	'minimum_size' => '100x100',
	
);