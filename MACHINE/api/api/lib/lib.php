<?php
    date_default_timezone_set('UTC');
    require_once("/var/www/html/conn.php");

    function sendError($error){
        if($error !== ""){
            echo json_encode(array("code" => "error","message" => $error));
            die();
        }
    }

    function sendMsg($msg){
        if($error !== ""){
            echo json_encode(array("code" => "ok","message" => $msg));
            die();
        }
    }

    //Me gustaria quitar esto
    function validateIfPostFieldExists($field){
        if(!isset($_POST[$field])){
            sendError($field." field not found");
        }
    }

    function validateArray($data){
        if(!is_array($data)){
            sendError("bad array is not an array");
        }
        if(empty($data)){
            sendError("empty array");
        }
    }

    function validateField($userData){
        if(!is_string($userData)){
            sendError("This should be a string");
        }
        if (!preg_match("/^[0-9a-zA-Z_ -]*$/",$userData)) {
            sendError("Only letters,numbers, '_' and '-'");
        }
    }

    function validateDescription($userData){
        if(!is_string($userData)){
            sendError("This should be a string");
        }
        if (!preg_match("/^[0-9a-zA-ZáéíóúÁÉÍÓÚñÑ _.,!?;:'\"()-]*$/u",$userData)) {
            sendError("Only letters, numbers, spaces and basic punctuation");
        }
    }

    function validateDate($userDataDate){
        $dt = parseDate($userDataDate);
        if(!$dt || $dt->format('Y-m-d H:i:s') !== $userDataDate){
            sendError("Bad format for a Date time");
        }
    }
    
    function validateNumeric($userDataNumber){
        if(!is_numeric($userDataNumber)){
            sendError("Bad format for a number");
        }
    }

    function validateInteger($userDataNumber){
        if(!is_int($userDataNumber)){
            sendError("Bad format for a integer");
        }
    }

    function validateBoolean($userDataBool){
        if(parseBoolean($userDataBool) === NULL){
            sendError("Bad format for a boolean");
        }
    }

    function parseDate($userDataDate){
        return DateTime::createFromFormat('Y-m-d H:i:s', $userDataDate);
    }

    function parseBoolean($userDataBool){
        return filter_var($userDataBool, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    function emptyJson($jsonData){
        if(empty($jsonData)){
            sendError("Json not found");
        }
    }

    function emptyField($list, $name){
        if(!array_key_exists($name, $list)){
            sendError($name." not found");
        }
    }

    function checkIfUserExists($username){
        global $conn;
        
        $stm = $conn->prepare("SELECT username FROM USERS WHERE username = :username LIMIT 1");
        $stm->execute(array(":username" => $username));
        $user = $stm->fetchAll();
        if(!empty($user)){
            sendError("Username taken");
        }   
    }

    function checkUserPassword($username,$password){
        global $conn;

        $stm = $conn->prepare("SELECT salt FROM USERS where username = :username limit 1");
        $stm->execute(array(":username" => $username));
        $salt = $stm->fetchAll();
        
        if(empty($salt)){
            sendError("Login Failed"); //si salta es que o no hay salt o no hay usuario
        }
        $salt = $salt[0]["salt"];

        [$password,] = hasingPassword($password,$salt);
        $stm = $conn->prepare("SELECT id_user,username,password,totpSecret,isCreator,isAdmin FROM USERS WHERE username = :username and password = :password LIMIT 1");
        $stm->execute(array(":username" => $username,":password" => $password));
        $user = $stm->fetchAll();
        
        if(empty($user)){
            sendError("Login Failed: user");
        }

        return $user[0];
    }

    function hasingPassword($password,$salt=null){
        $salt = ($salt === null)? random_bytes(8) : $salt; 
        $password = $salt . $password;
        return [sha1($password),$salt];
    }

    function sendTOTPIfNeeded($secretTOTP){
        if($secretTOTP === null){
            return;
        }

        if(!isset($_POST["totp"]) || !is_numeric($_POST["totp"])){
            sendError("need totp field");
        }

        require_once("TOTP/main.php");
        $totpUserNumber = $_POST["totp"];
        $possibleNumbers = getTOTPNumbers($secretTOTP);
        $valid = FALSE;
        foreach($possibleNumbers as $number){
            if($number === $totpUserNumber){
                $valid = TRUE;
                break;
            }
        }
        if(!$valid){
            sendError("totp failed");
        }

    }

    function storeUser($username,$password){
        global $conn;
        [$password,$salt] = hasingPassword($password);
        $stm = $conn->prepare("INSERT INTO USERS (username,password,salt) VALUES (:username,:password,:salt)");
        $stm->execute(array(":username" => $username, ":password" => $password, ":salt" => $salt));
        return $conn->lastInsertId();
    }

    function storeTOTPSecret($username){
        global $conn;
        $secretTOTP = random_bytes(40);
        $secretTOTPencoded = bin2hex($secretTOTP);
        $stm = $conn->prepare("UPDATE USERS SET totpSecret = :secretTOTP WHERE username = :username");
        $stm->execute(array(":secretTOTP" => $secretTOTPencoded, ":username" => $username));
    }

    function custom_base32_encode($input) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $output = '';
        // Convert input bytes to a bit string
        foreach (str_split($input) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        // Group bits into 5-bit chunks and map to alphabet
        for ($i = 0; $i < strlen($bits); $i += 5) {
            $chunk = substr($bits, $i, 5);
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0'); // Pad the last chunk
            }
            $index = bindec($chunk);
            $output .= $alphabet[$index];
        }
        return $output;
    }
?>