<?php
    function checkSession(){
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if(empty($_SESSION)){
            echo json_encode(array("code" => "privilege", "message" => "you can't do this, no session"));
            die();
        }        
    }

    function checkSessionAdmin(){
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if(empty($_SESSION) || $_SESSION["isAdmin"] != 1){
            echo json_encode(array("code" => "privilege", "message" => "you can't do this no privileges"));
            die();
        }
    }

    function checkSessionCreator(){
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if(empty($_SESSION) || $_SESSION["isCreator"] != 1){
            echo json_encode(array("code" => "privilege", "message" => "you can't do this no privileges"));
            die();
        }
    }

    function deleteSession(){
        session_unset();
        session_destroy();
        session_write_close();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => '/',
            'domain' => "",
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    function setParamsCookie(){
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None'
        ]);
    }
?>