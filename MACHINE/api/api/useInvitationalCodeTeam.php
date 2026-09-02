<?php
    require_once("lib/sessions.php");
    require_once("lib/lib.php");
    require_once("cors.php");

    if($_SERVER["REQUEST_METHOD"] === "POST"){
        checkSession();
        $idUser = $_SESSION["id"];

        $teamCode = json_decode(file_get_contents('php://input'), true);   
        emptyJson($teamCode);
        emptyField($teamCode,"teamCode"); 
        validateField($teamCode["teamCode"]);

        global $conn;
        
        $stm = $conn->prepare("SELECT * FROM TEAMS WHERE invitanionalCode = :teamCode AND codeExpireDate > NOW()");
        $stm->execute(array(":teamCode" => $teamCode["teamCode"]));
        $teamData = $stm->fetchAll();

        if(empty($teamData)){
            sendError("No valid team with that code");
        }

        $stm = $conn->prepare("SELECT id_user FROM USERS_TEAMS WHERE id_teams = :idTeams AND id_user = :idUser");
        $stm->execute(array(":idTeams" => $teamData[0]["id_teams"], ":idUser" => $idUser));
        $teamDataUsers = $stm->fetchAll();

        if(!empty($teamDataUsers)){
            sendError("Alredy in the team");
        }

        $stm = $conn->prepare("INSERT INTO USERS_TEAMS(id_teams, id_user) VALUES (:idTeams,:idUser)");
        $stm->execute(array(":idTeams" => $teamData[0]["id_teams"], ":idUser" => $idUser));

        sendMsg("Added to the team");
    }
?>