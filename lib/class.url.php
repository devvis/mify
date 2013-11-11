<?php
class urlHelper extends mify {
	# Helper-class that extends upon mify
	# Here you'll find various functions that handles
	# url-verification and similar stuff
	# Splitted those up to avoid a bigass class with all
	# the functions inside it.
	
	public function __construct() {
		return true;
	}
	
	protected function verifyURL($url) {
		if(preg_match("^(http|https)\://[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,4}(\/\S*)?$^", $url) && $this->curlCheck($url)) {
			return true;
		}
		else {
			return false;
		}
	}
	
	protected static function curlCheck($url) {
		$matches = null;
		$p = parse_url($url);

		if($p == 0) {
			return false;
		}

		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)');
		curl_setopt($c, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($c, CURLOPT_TIMEOUT, 20);
		curl_setopt($c, CURLOPT_NOBODY, true);
		curl_setopt($c, CURLOPT_HEADER, true);

		if($p['scheme'] == "https"){
			curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 1);
			curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
		}
		 
		$res = curl_exec($c);
		curl_close($c);

		if(preg_match('/HTTP\/1\.\d+\s+(\d+)/', $res, $matches)) {
			$code = intval($matches[1]);
		}
		else {
			return false;
		}
		return (($code >= 200) && ($code < 400));
			
	}
}
