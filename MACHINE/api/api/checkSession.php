<?php
    require_once("cors.php");
    session_start();
    if (isset($_SESSION['id'])) {
        echo json_encode(array('loggedIn' => true, 'isCreator' => $_SESSION["isCreator"], 'isAdmin' => $_SESSION["isAdmin"], 'username' => $_SESSION["username"]));
    } else {
        echo json_encode(array('loggedIn' => false));
    }
?>  