<?php
    require_once("cors.php");
    require_once("lib/lib.php");
    require_once("lib/sessions.php");

    if($_SERVER["REQUEST_METHOD"] === "GET"){
        echo json_encode(array("message" => "does not support GET Petition"));
    }

    if($_SERVER["REQUEST_METHOD"] === "POST"){
        validateIfPostFieldExists('username');
        validateIfPostFieldExists('password');
        $username =  $_POST['username'];
        $password = $_POST['password'];
        validateField($username);
        validateField($password);
        
        $user = checkUserPassword($username,$password);

        sendTOTPIfNeeded($user["totpSecret"]);

        setParamsCookie();
        session_start();
        $_SESSION["id"] = $user["id_user"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["isCreator"] = $user["isCreator"];
        $_SESSION["isAdmin"] = $user["isAdmin"];
        echo json_encode(array("code" => "ok", "message" => "Login succsesfull"));
    }

    if($_SERVER["REQUEST_METHOD"] === "PUT"){
        echo json_encode(array("message" => "does not support PUT Petition"));
    }

    if($_SERVER["REQUEST_METHOD"] === "DELETE"){
        checkSession();
        deleteSession();
        echo json_encode(array("code" => "ok", "message" => "LogOut succsesfully"));
    }
?>