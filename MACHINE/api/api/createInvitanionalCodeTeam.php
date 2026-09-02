<?php
    require_once("lib/sessions.php");
    require_once("lib/lib.php");
    require_once("cors.php");

    if($_SERVER["REQUEST_METHOD"] === "POST"){
        checkSession();
        $idUser = $_SESSION["id"];

        $teamName = json_decode(file_get_contents('php://input'), true);   
        emptyJson($teamName);
        emptyField($teamName,"teamName"); 
        validateField($teamName["teamName"]);

        global $conn;
        
        $stm = $conn->prepare("SELECT * FROM TEAMS WHERE leader = :idUser AND name = :name AND isPersonal != TRUE LIMIT 1");
        $stm->execute(array(":idUser" => $idUser, ":name" => $teamName["teamName"]));
        $teamData = $stm->fetchAll();

        if(empty($teamData)){
            sendError("Not a leader for this team");
        }

        $code = bin2hex(random_bytes(12));
        $stm = $conn->prepare("UPDATE TEAMS SET invitanionalCode = :code, codeExpireDate = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE leader = :idUser AND name = :name");
        $stm->execute(array(":code" => $code, ":idUser" => $idUser, ":name" => $teamName["teamName"]));

        sendMsg("Invitanional code created for the next 24h, code: ".$code);
    }

?>