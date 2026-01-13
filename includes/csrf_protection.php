<?php
//TODO
//VALIDATE VALUES OF POST BODYPARAMETERS


//Create a session variable with a given key and value
function storeToken($tokenName,$tokenValue){
	if (isset($_SESSION)){
		$_SESSION['latest_valid_csrf_token_name']=$tokenName;
		$_SESSION[$tokenName]=$tokenValue;
	}
}

//Unset session variable given the key
function unsetToken($tokenName){
	unset($_SESSION['latest_valid_csrf_token_name']);
	unset($_SESSION[$tokenName]);
}

//Return session variable value given the key
//if not found return false
function getSessionVariable($key){
	if (isset($_SESSION[$key])){
		return $_SESSION[$key];
	}
	else {  return false; }
}

function getLastValidTokenName(){
	return $_SESSION['latest_valid_csrf_token_name'] ?? null;
}

//Generate the CSRF token, 128 bits using a cryptographically secure random bit generator
//converting the resuls as hexadecimal string
function generateCSRFToken($uniqueFormName){
	$token = sodium_bin2hex(random_bytes(32));
	storeToken($uniqueFormName,$token);
	return $token;
}

//Given a CSRF name, it retrieves the value from the session and compares is with the given one
function validateCSRFToken(string $tokenName,string $token,bool $deleteToken=true){

	//using is_string to check the user input $_POST['CSRFtoken'] and $POST['CSRFName']
	if(!is_string($tokenName)||!is_string($token)){
		return false;
	}
	//Fetching from session the token associated to CSRFname
	$storedToken = getSessionVariable($tokenName);
	//checking if the storedtoken is found and is a string
	if ($storedToken===false||!is_string($token)) {
        return false;
	}
	
    //Using hash_equals to compare string, ensure no side channel attacks based on time
	$result = hash_equals($token, $storedToken);
    //Destroy the token, ensuring no reuse

	if($deleteToken)
		unsetToken($tokenName);
	
	return $result;
}

//Retrieve the forms, and replace it injection the hidden fields
//containing the CSRF name and CSRF token
function csrfguardReplaceForms($htmlForm){	
	//"/<form(.*?)>(.*?)<\\/form>/is" --> first version captures all form
	//"/<form(?![^>]*\bnocsrf\b)(.*?)>(.*?)<\/form>/is" captures all form, which are not class csrf
	$count=preg_match_all("/<form(?![^>]*\bnocsrf\b)(.*?)>(.*?)<\/form>/is",$htmlForm,$matches,PREG_SET_ORDER);
	if (is_array($matches)){	
		
		$tokenName=getLastValidTokenName();

		if($tokenName===null){
			//if no previuos csrf token was generated , generate a new one
			//this prevents to generate valid tokens, that will be stored in the
			//session and never used, for example if the user refresh the page
			//Ensuring that just one token is used for a request

			//creates a unique name using a cryptographically secure random generator
			$tokenName="CSRFGuard_" . sodium_bin2hex(random_bytes(16));
			$token=generateCSRFToken($tokenName);

		}else{
			//case a valid token has been alredy generated and not used
			$token=getSessionVariable($tokenName);
		}

		foreach ($matches as $m){  		
			$htmlForm=str_replace($m[0],
				"<form{$m[1]}>
                    <input type='hidden' name='CSRFName' value='{$tokenName}' />
                    <input type='hidden' name='CSRFToken' value='{$token}' />
                {$m[2]}</form>",$htmlForm);
		}
	}
	return $htmlForm;
}

//Utility function that retrieves the data from the page, inject the csrf protection and then print the new page
function injectCSRFGuard(){
	$data=ob_get_clean();
	$data=csrfguardReplaceForms($data);
	echo $data;
}

//Function that starts the csrf protection
function csrf_protection_start($log){  
    //check for request body parameters
	if (count($_POST)){
        //if no CSRF field found assumend invalid request
		if ( !isset($_POST['CSRFName']) or !isset($_POST['CSRFToken']) ){

            $log->warning('Probable invalid request, CSRF parameters missing', 
                ['IP_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Not available',
                 'User' => $_SESSION['username'] ?? 'Not logged in',
                 'Origin' => $_SERVER['HTTP_ORIGIN'] ?? 'Not available',
                 'Referer'       => $_SERVER['HTTP_REFERER'] ?? 'Not available',
                 'Forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Not available',
                 'Request_uri' => $_SERVER['REQUEST_URI'] ?? 'Not available',
                ]);
				
			//user re-direction
			http_response_code(400);
			#header("Location: user_dashboard.php");
			exit;
		}
        //get the CSRF fields 
		$name =$_POST['CSRFName'];
		$token = $_POST['CSRFToken'];
        //Check
		if (!validateCSRFToken($name, $token)){   
            //check failed
		
            //403 forbidden
            $log->warning('Probable invalid request, CSRF validation failed', 
                ['IP_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Not available',
                 'User' => $_SESSION['username'] ?? 'Not logged in',
                 'Origin' => $_SERVER['HTTP_ORIGIN'] ?? 'Not available',
                 'Referer'       => $_SERVER['HTTP_REFERER'] ?? 'Not available',
                 'Forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Not available',
                 'Request_uri' => $_SERVER['REQUEST_URI'] ?? 'Not available'
                ]);	
            //user re-direction
			http_response_code(400);
			header("Location: login.php");
			exit;
		}
	}
    //register everything in the buffer, meaning that copy what has been printed on the page
	ob_start();
	//ensuring that the function csrfguard is called
	register_shutdown_function("injectCSRFGuard");	
}

function read_only_requests_csrf_protection_start($log){  
    //check for request body parameters
	if (count($_POST)){
        //if no CSRF field found assumend invalid request
		if ( !isset($_POST['CSRFName']) or !isset($_POST['CSRFToken']) ){

            $log->warning('Probable invalid request, CSRF parameters missing', 
                ['IP_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Not available',
                 'User' => hash_log_data($_SESSION['email']) ?? 'Not logged in',
                 'Origin' => $_SERVER['HTTP_ORIGIN'] ?? 'Not available',
                 'Referer'       => $_SERVER['HTTP_REFERER'] ?? 'Not available',
                 'Forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Not available',
                 'Request_uri' => $_SERVER['REQUEST_URI'] ?? 'Not available',
                ]);
				
			//user re-direction
			http_response_code(400);
			header("Location: user_dashboard.php");
			exit;
		}
        //get the CSRF fields 
		$name =$_POST['CSRFName'];
		$token = $_POST['CSRFToken'];
        //Check
		if (!validateCSRFToken($name, $token,false)){   
            //check failed
            //403 forbidden
            $log->warning('Probable invalid request, CSRF validation failed', 
                ['IP_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Not available',
                 'User' => hash_log_data($_SESSION['email']) ?? 'Not logged in',
                 'Origin' => $_SERVER['HTTP_ORIGIN'] ?? 'Not available',
                 'Referer'       => $_SERVER['HTTP_REFERER'] ?? 'Not available',
                 'Forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Not available',
                 'Request_uri' => $_SERVER['REQUEST_URI'] ?? 'Not available'
                ]);	
            //user re-direction
			http_response_code(400);
			header("Location: user_dashboard.php");
			exit;
		}
	}
}

//csrf_protection_start($logConcern);

?>