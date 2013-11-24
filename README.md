mify - the simple url-shortening library
========================================

### About

This small library was created by me, primairly to use with my domain ([http://mify.me]). The thought behind mify is that it should be fairly easy to use and implement on existing sites.

Currently there's no real examples out there, but I'll work on that, but basically what you need to do is something like this:

1. Include class.mify.php, class.url.php, klogger.php into your file.
2. Configure your webserver to handle the rewrites that mify currently is using. Examples for nginx:
	* `rewrite ^/done/(.*)$ /index.php?pu=$1 last;`
	* `rewrite ^/stats/(.*)$ /index.php?stats=$1 last;`
	* `rewrite ^/error/([0-9]*)$ /index.php?e=$1 last;`
	* `rewrite ^/u/(.*)$ /index.php?u=$1 last;`
3. Initialize mify like this: `$mify = new mify("siteURL", "dbServer", "dbUser", "dbPassword", "database", debug (true/false);`
4. Add this before any headers are sent out: `$mify->parseRequest();`
5. Make sure that your code handles the different get-requests, as seen above.
6. Your form's submit-button must be named `mifySubmit`
7. The error-codes currently present are these:
	* 100: Missing database-connection
	* 101: A non-valid url was submitted
	* 102: Was unable to add the url to the database (don't as me how)
	* 201: Invalid url-id was submitted
	* 202: Error while processing the request (aka everything is broken)

As there's no further documentation available at the moment, check the actual functions to see how they're used.