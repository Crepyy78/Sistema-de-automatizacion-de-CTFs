<?php
    require_once("lib/sessions.php");
    require_once("lib/lib.php");
    require_once("cors.php");

    if($_SERVER["REQUEST_METHOD"] === "DELETE"){
        checkSession();
        $teamData = json_decode(file_get_contents('php://input'), true);
        emptyJson($teamData);
        emptyField($teamData,"name");
        emptyField($teamData,"teamName");

        validateField($teamData["name"]);
        validateField($teamData["teamName"]);

        $name = $teamData["name"];
        $teamName = $teamData["teamName"];

        global $conn;

        $stm = $conn->prepare("SELECT T.id_teams as id_teams, T.leader as leader, U.username as leaderUsername FROM TEAMS T JOIN USERS U ON U.id_user = T.leader WHERE T.name = :teamName");
        $stm->execute(array(":teamName" => $teamName));
        $teamData = $stm->fetchAll();

        if(empty($teamData)){
            sendError("No team with that name");
        }
        $team = $teamData[0];

        if($team["leaderUsername"] === $name){
            sendError("Cannot remove the team leader");
        }

        if($team["leader"] !== $_SESSION["id"]){
            sendError("Only the team leader can remove members");
        }

        $stm = $conn->prepare("SELECT UT.id_user as id_user FROM USERS_TEAMS UT JOIN USERS U ON U.id_user = UT.id_user WHERE UT.id_teams = :idTeams AND U.username = :name");
        $stm->execute(array(":idTeams" => $team["id_teams"], ":name" => $name));
        $memberData = $stm->fetchAll();

        if(empty($memberData)){
            sendError("User is not in that team");
        }

        $stm = $conn->prepare("DELETE FROM USERS_TEAMS WHERE id_teams = :idTeams AND id_user = :idUser");
        $stm->execute(array(":idTeams" => $team["id_teams"], ":idUser" => $memberData[0]["id_user"]));

        sendMsg("User removed from the team");
    }

?>
