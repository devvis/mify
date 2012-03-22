<?php

class mify {
	# Error codes redirected to by the class
	# ?e=100 - Database error, probably not connected?
	# ?e=101 - Non-valid URL submitted
	# ?e=201 - Invalid ID submitted
	# ?e=202 - Error while processing the request
	
	
	public $formValidation;
	public $dbErrorPage;
	public $debug;
	
	private $log;
	private $urlHelp;
	
	private $siteURL;
	private $formValue;
	private $db;
	
	public function __construct($url, $host, $username, $password, $database) {
		#if(session_status() == PHP_SESSION_DISABLED) {
		#	session_start();
		#}

		# init the logging
		if(isset($this->debug) && $this->debug == true) {
			$this->log = new KLogger("log/", KLogger::DEBUG);			
		}
		else {
			$this->log = new KLogger("log/", KLogger::INFO);
		}
		$this->urlHelp = new urlHelper();
		
		if(!isset($url) || !isset($host) || !isset($username) || !isset($database)) {
			throw new Exception("Missing parameter in the constructor. Aborting.", 0);
		}
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
	
		
	private function generateFormValue() {
		# Generates a unique value to be used in the form
		# so that we're sure the request is coming from the site itself
		$this->formValue = uniqid("mify_", true);
		$_SESSION['mifyVal'] = $this->formValue;
	}
	
	public function getUrl() {
		# Returns the site url
		return $this->siteURL;
	}
	
	public function getFormValue() {
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
	}
	
	public function parseRequest() {
		# Returns true on any request, otherwise false
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
	
	private function parseURLRequest() {
		# This handles the acutal redirection for the shortened URL
		
		try {
			$q = $this->db->prepare("SELECT urls.url FROM urls INNER JOIN `customurl` ON customurl.urlID = urls.id WHERE customurl.customURL = :cURL");
			$q->bindParam(":cURL", $_GET['u']);
			$q->execute();
			$data = $q->fetch();
		}
		catch(PDOException $e) {
			$this->log->logInfo("Something went wrong when querying for the custom url {$_GET['u']} - {$e}");
		}
		
		
		if($data[0] != false) {
			# The provided id isn't a number, but a custom URL so lets just do the redirection right away.
			$this->log->logDebug("Redirecting to {$data['url']}");
			header("HTTP/1.1 301 Moved Permanently");
			header("Location: {$data['url']}"); # Should we use URL-encode here?
			die;
		}
		
		# Reset to avoid unavoidable shitstorm
		$query = "";
		$data = "";
		
		$urlID = $this->baseToInt($_GET['u']);
		
		if(!preg_match("/^[0-9]+$/", $urlID)) {
			$this->log->logInfo("Invalid ID submitted - {$urlID}");
			header("Location:{$this->siteURL}?e=201");
			die;
		}
		# assuming everything's good, moving on.
		
		$query = $this->db->prepare("SELECT `url` FROM urls WHERE `id` = :id");
		$query->bindParam(":id", $urlID);
		$query->execute();
		
		$data = $query->fetch();
		
		if(!isset($data['url'])) {
			$this->log->logWarn("The database did not return any URL for ID {$_GET['u']}");
			header("Location:{$this->siteURL}?e=202");
			die;
		}
		else {
			$this->log->logDebug("Redirecting to {$data['url']}");
			header("HTTP/1.1 301 Moved Permanently");
			header("Location: {$data['url']}"); # Should we use URL-encode here?
			die;
		}
	}
	
	private function postURL() {
		# This function handles the submission of urls to the database
		# Returns the URL-id on success, redirects to following error-messages upon errors:
		# 100 - Missing the connection to the database - will actually never happen due to obvious reasons (stuck in infinite loop etc)
		# 101 - Invalid URL
		# 102 - Could not add the URL to the database
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
				header("Location:{$this->siteURL}?e=101");
				die;
			}
			
			
			
			
			#$q = $this->db->prepare("SELECT `id` FROM urls WHERE `hash` = MD5(:url)"); # We try and look up the url through an hash instead of the full url, should result in better performance
			
			$q = $this->db->prepare("SELECT urls.url, customurl.customURL FROM `urls` LEFT JOIN `customurl` ON urls.id = customurl.urlID WHERE urls.hash = MD5(:url)");
			$q->bindParam(":url", $url);
			$q->execute();
			$data = $q->fetch();
			
			if(isset($data['id']) && $data['id'] > 0) {
				# we got the record already, let's just generate the url from here (no need to insert it into the db since it's already there..)
				if($data['customURL'] == NULL) {
					# We got a custom url for this one
					# Not sure if we're going to display that, we could just use the normal one since
					# this user probably won't like the custom one that exists
					echo $data['customURL'];
				}
				else {
					echo $this->intToBase($data['id']);
					#header("Location:{$this->siteURL}?url="
				}
			}
			else {
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
					header("Location:{$this->siteURL}?=102");
				}
				if($useCustomURL == true) {
					try {
						$this->db->beginTransaction();
						$c = $this->db->prepare("INSERT INTO `customurl` SET `urlID` = :id, `customURL` = :cURL");
						$c->bindParam(":id", $id[0], PDO::PARAM_INT);
						$c->bindParam(":cURL", $cURL);
						$c->execute();
						$this->db->commit();
					}
					catch(PDOException $e) {
						$this->db->rollBack();
						$this->log->logAlert("Failed to add the custom URL to database. URL: {$cURL} - Exception {$e}");
						header("Location:{$this->siteURL}?=102");
					}
				}
				# now $id[0] contains the id of the url in the db
				# just generate the url from here
				if($useCustomURL == true) {
					echo $cURL;
				}
				else {
					echo $this->intToBase($id[0]);
				}
			}
		}
		else {
			$this->log->logEmerg("Missing database-connection. - ".var_dump($this->db));
			header("Location:{$this->siteURL}{$this->dbErrorPage}");
			die; ## TODO: Fixa bättre hantering för tappade/icke-existerande db-c0nns
		}
	}


	private function intToBase($i) {
		return base_convert($i, 10, 36);
	}
	private function baseToInt($i) {
		return base_convert($i, 36, 10);
	}
}
