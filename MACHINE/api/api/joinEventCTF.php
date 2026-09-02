<?php
    require_once("lib/sessions.php");
    require_once("lib/lib.php");
    require_once("cors.php");

    if($_SERVER["REQUEST_METHOD"] === "POST"){
        checkSession();
        $idUser = $_SESSION["id"];

        $data = json_decode(file_get_contents('php://input'), true);
        emptyJson($data);
        emptyField($data,"eventName");
        validateField($data["eventName"]);

        $eventName = $data["eventName"];
        $teamName = isset($data["teamName"]) ? trim($data["teamName"]) : "";

        global $conn;

        $stm = $conn->prepare("SELECT id_event, isPublic, maxNumbersPerTeam FROM EVENTS WHERE name = :name LIMIT 1");
        $stm->execute(array(":name" => $eventName));
        $event = $stm->fetchAll();
        if(empty($event)){
            sendError("Event not found. ¿Name is correct?");
        }
        $event = $event[0];
        $idEvent = $event["id_event"];

        if(!$event["isPublic"]){
            sendError("This event isn't open for teams to join");
        }

        if($teamName !== ""){
            validateField($teamName);
            $stm = $conn->prepare("SELECT id_teams FROM TEAMS WHERE name = :name AND leader = :idUser LIMIT 1");
            $stm->execute(array(":name" => $teamName, ":idUser" => $idUser));
            $team = $stm->fetchAll();
            if(empty($team)){
                sendError("Not a leader of this team");
            }
        } else {
            $stm = $conn->prepare("SELECT id_teams FROM TEAMS WHERE leader = :idUser AND isPersonal = TRUE LIMIT 1");
            $stm->execute(array(":idUser" => $idUser));
            $team = $stm->fetchAll();
            if(empty($team)){
                sendError("No personal team found");
            }
        }
        $idTeams = $team[0]["id_teams"];

        $stm = $conn->prepare("SELECT id_teams FROM EVENTS_TEAMS WHERE id_teams = :idTeams AND id_event = :idEvent LIMIT 1");
        $stm->execute(array(":idTeams" => $idTeams, ":idEvent" => $idEvent));
        $existing = $stm->fetchAll();
        if(!empty($existing)){
            sendError("Team already entered in this event");
        }

        $stm = $conn->prepare("SELECT COUNT(*) FROM USERS_TEAMS WHERE id_teams = :idTeams");
        $stm->execute(array(":idTeams" => $idTeams));
        $teamSize = (int)$stm->fetchColumn();

        if($teamSize > $event["maxNumbersPerTeam"]){
            sendError("Team is too big for this event (max ".$event["maxNumbersPerTeam"].")");
        }

        $stm = $conn->prepare("INSERT INTO EVENTS_TEAMS(id_teams, id_event) VALUES (:idTeams, :idEvent)");
        $stm->execute(array(":idTeams" => $idTeams, ":idEvent" => $idEvent));

        $stm = $conn->prepare("INSERT IGNORE INTO TEAMS_CHALLENGES_EVENT (id_teams, id_challenge, id_event, status) "
                             ."SELECT DISTINCT :idTeams, CU.id_challenge, :idEvent, 'not done' "
                             ."FROM CHALLENGES_USING CU JOIN CHALLENGES_EVENT CE ON CE.id_challengeU = CU.id_challengeU "
                             ."WHERE CE.id_event = :idEvent2");
        $stm->execute(array(":idTeams" => $idTeams, ":idEvent" => $idEvent, ":idEvent2" => $idEvent));

        sendMsg("Team entered the event");
    }

?>
