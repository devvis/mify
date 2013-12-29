<?php

class mify {
	# Error codes redirected to by the class
	# ?e=100 - Database error, probably not connected?
	# ?e=101 - Non-valid URL submitted
	# ?e=201 - Invalid ID submitted
	# ?e=202 - Error while processing the request
	
	# TODO:
	# 1) Add validation for custom urls, should only contain a-z A-Z 0-9 and _
	
	public $formValidation;
	public $dbErrorPage;
	public $debug;
	public $siteURL;
	public $db;
	
	private $log;
	private $urlHelp;
	
	private $formValue;
	
	public function __construct($url, $host, $username, $password, $database, $debug) {
		if(!isset($url) || !isset($host) || !isset($username) || !isset($database) || !isset($debug)) {
			throw new Exception("Missing parameter in the constructor. Aborting.", 0);
		}
		$this->debug = $debug;

		# init the logging
		if(isset($this->debug) && $this->debug == true) {
			$this->log = new KLogger("log/", KLogger::DEBUG);
		}
		else {
			$this->log = new KLogger("log/", KLogger::INFO);
		}
		$this->urlHelp = new urlHelper();
		
		$this->siteURL = $url;
		
		
		
		if($this->formValidation == true) {
			$this->generateFormValue();
		}
		
		if(!isset($this->dbErrorPage)) {
			$this->dbErrorPage = "maint.php";
		}
		
		$this->connectToDB($host, $username, $password, $database);
		
		
	}
	
	private function connectToDB($host, $username, $password, $database) {
		try {
			$this->db = new PDO("mysql:host={$host};dbname={$database}", "{$username}", "{$password}", array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		}
		catch(PDOException $e) {
				$this->log->logEmerg("Database connection error: {$e}");
				header("Location:{$this->siteURL}{$this->dbErrorPage}");
				die;
		}
	}

	public function getUrl() {
		# Returns the site url
		return $this->siteURL;
	}
	
	public function getFormValue() {
		#### This function is not implemented yet. ####

		/*
		# Check if form-validation is used
		if($this->formValidation == false) {
			return;
		}
		else {
			if(!$_SESSION['mifyVal'] != $this->formValue) {
				throw new Exception("Error Processing Request", 1);
			}
			return $this->formValue;
		}
		*/
	}
	
	public function parseRequest() {
		# Returns true on any request, otherwise false
		# Actually doesn't return anything but a redirect if either post or get is set..

		if(isset($_POST['mifySubmit'])) {
			$this->postURL();
			return true;
		}
		elseif(isset($_GET['u'])) {
			$this->parseURLRequest();
			return true;
		}
		else {
			return false;
		}
	}

	public function getURLLink($id) {
		if(!isset($id)) {
			throw new Exception("Missing parameter, aborting.", 0);
		}

		return $this->siteURL . "u/{$id}";
	}

	public function getUrlStats($url) {
		# This function fetches the stats for the given url, returns an array
		# upon success, like this:
		# array(0 => http://site.url, 1 => *int* Total clicks, 2 => *int* Clicks last 7 days)
		# Returns error-message 301 if there's no url on that ID.
		if(!isset($url)) {
			throw new Exception("Missing parameter, aborting.", 0);
		}

		$iurl = $this->baseToInt($url); // fixes the url so that it's an int :D
		$this->verifyID($iurl);



		$q = $this->db->prepare("SELECT `clicks` FROM `urlclicks` WHERE `urlID` = :url");
		$q->bindParam(":url", $iurl, PDO::PARAM_INT);
		$q->execute();

		$urlClicks = $q->fetch();

		$q = $this->db->prepare("SELECT COUNT(`urlID`) FROM `urlstats` WHERE `timestamp` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND `urlID` = :url");
		$q->bindParam(":url", $iurl, PDO::PARAM_INT);
		$q->execute();

		$urlClicksDays = $q->fetch();

		if($urlClicks[0] < 1) {
			$urlClicks[0] = 0;
		}

		if($urlClicksDays[0] < 1) {
			$urlClicksDays[0] = 0;
		}

		return array(0 => "{$this->siteURL}u/{$url}", 1 => $urlClicks[0], 2 => $urlClicksDays[0]);
	}


	# URL-logging/stats functionality
	private function logUrlClick($url) {
		if(!isset($url)) {
			throw new Exception("Missing parameter, aborting.", 0);
		}

		$q = $this->db->prepare("SELECT `urlID` FROM `urlclicks` WHERE `urlID` = :url");
		//$q = $this->db->prepare("SELECT EXISTS(SELECT 1 FROM `urlclicks` WHERE `urlID` = :url)");
		$q->bindParam(":url", $url, PDO::PARAM_INT);
		$q->execute();

		$urlID = $q->fetch();

		$this->log->logInfo("urlID: {$urlID}");
	
		if($urlID > 0) {
			# currently already logging the given url
			$q = $this->db->prepare("UPDATE urlclicks SET clicks = clicks + 1 WHERE urlID = :urlID");
			$q->bindParam(":urlID", $url, PDO::PARAM_INT);
			$q->execute();
		}
		else {
			# currently not logging the given url
			$q = $this->db->prepare("INSERT INTO urlclicks SET clicks = 1, urlID = :urlID");
			$q->bindParam(":urlID", $url, PDO::PARAM_INT);
			$q->execute();
		}

		$q = $this->db->prepare("INSERT INTO urlstats SET urlID = :urlID");
		$q->bindParam(":urlID", $url, PDO::PARAM_INT);
		$q->execute();

		return true;
	}

	################################
	### INTERNAL FUNCTIONS BELOW ###
	################################

	private function postURL() {
		# This function handles the submission of urls to the database
		# Returns the URL-id on success, redirects to following error-messages upon errors:
		# 100 - Missing the connection to the database - will actually never happen due to obvious reasons (stuck in infinite loop etc)
		# 101 - Invalid URL
		# 102 - Could not add the URL to the database
		# 103 - The chosen custom url isn't available
		if($this->db == true) {
			$useCustomURL = false;
			$cURL = "";
			$url = htmlentities(trim($_POST['mifyURL']));
			
			if(isset($_POST['mifyCustom'])) {
				$cURL = htmlentities(trim($_POST['mifyCustom']));
				if($cURL != "") {
					$useCustomURL = true;
				}
			}
			
			if($this->urlHelp->verifyURL($url) == false) {
				## Check so that the url is valid and so, through curl and regexp!
				$this->log->logInfo("Seems like the url {$_POST['mifyURL']} is invalid.");
				header("Location:{$this->siteURL}error/101");
				die;
			}
			
			try {
				$this->db->beginTransaction();
				$q = $this->db->prepare("INSERT INTO `urls` SET `url` = :url, `hash` = MD5(:url)");
				$q->bindParam(":url", $url);
				$i = $this->db->prepare("SELECT LAST_INSERT_ID()");
				
				$q->execute();
				$i->execute();
				$this->db->commit();
				$id = $i->fetch();
			}
			catch(PDOException $e) {
				$this->db->rollBack();
				$this->log->logAlert("Failed to add the URL to database. URL: {$url} - Exception {$e}");
				header("Location:{$this->siteURL}error/102");
				die;
			}

				# now $id[0] contains the id of the url in the db
				# just generate the url from here
				$id = $this->intToBase($id[0]);
				header("Location:{$this->siteURL}done/{$id}");
				die;
		}
		else {
			$this->log->logEmerg("Missing database-connection. - ".var_dump($this->db));
			header("Location:{$this->siteURL}{$this->dbErrorPage}");
			die; // TODO: Probably fix better handeling of dropped db-connections
		}
	}

	private function parseURLRequest() {
		# This function handles the redirection for shortened URLs
		# Redirects the user upon success and redirects to following error-messages upon errors:
		# 201 - Missing url-entry for the given ID

		$urlID = $this->baseToInt($_GET['u']);
		$this->verifyID($urlID);

		# assuming everything's good, moving on.
		
		$query = $this->db->prepare("SELECT `url` FROM urls WHERE `id` = :id");
		$query->bindParam(":id", $urlID);
		$query->execute();
		
		$data = $query->fetch();
		
		if(!isset($data['url'])) {
			$this->log->logWarn("The database did not return any URL for ID {$_GET['u']}");
			header("Location:{$this->siteURL}error/201");
			die;
		}
		else {
			$this->log->logInfo("Redirecting to {$data['url']}");
			$this->logUrlClick($urlID);
			header("HTTP/1.1 301 Moved Permanently");
			header("Location: {$data['url']}"); # Should we use URL-encode here?
			die;
		}
	}

	private function intToBase($i) {
		# Converts from base10 to base36
		if(!isset($i)) {
			throw new Exception("Missing parameter, aborting.", 0);
		}

		return base_convert($i, 10, 36);
	}
	private function baseToInt($i) {
		# Converts from base36 to base10
		if(!isset($i)) {
			throw new Exception("Missing parameter, aborting.", 0);
		}

		return base_convert($i, 36, 10);
	}

	private function verifyID($urlID) {
		# Returns a 201-error if the given urlid is anything else but an int
		if(!isset($urlID)) {
			throw new Exception("Missing parameter, aborting.", 0);
		}

		if(!preg_match("/^[0-9]+$/", $urlID)) {
			$this->log->logInfo("Invalid ID submitted - {$urlID}");
			header("Location:{$this->siteURL}error/201");
			die;
		}
		else {
			return true;
		}
	}
			
	private function generateFormValue() {
		#### This function is not implemented yet. ####

		# Generates a unique value to be used in the form
		# so that we're sure the request is coming from the site itself

		/*
		$this->formValue = uniqid("mify_", true);
		$_SESSION['mifyVal'] = $this->formValue;
		*/
	}
	

# End of class
}
