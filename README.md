# Scrapey

Get info about a URL such as you get when sharing a link on Facebook.  It looks at normal meta tags, open graph meta tags, and scrapes images from the page.

## Usage

	$response = Scrapey::lookup(Input::get('http://www.imdb.com/title/tt0122690'));

Response looks like:

	stdClass Object(
		[url] => http://www.imdb.com/title/tt0122690/
		[images] => Array
			(
				[0] => /uploads/09/07/512d5dd66e26b.jpg
			)

		[type] => video.movie
		[title] => Ronin (1998)
		[site_name] => IMDb
		[description] => Directed by John Frankenheimer.  With Robert De Niro, Jean Reno, Natascha McElhone, Stellan Skarsgård. A freelancing former US intelligence agent tries to track down a mysterious package that is wanted by both the Irish and the Russians.
	)
	
Note, by default it will localize images it finds to your webserver.

## Thanks

I'm using https://github.com/scottmac/opengraph for the parsing of meta tags.