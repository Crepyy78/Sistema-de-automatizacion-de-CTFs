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
        emptyField($data,"name");
        emptyField($data,"flag");
        validateField($data["eventName"]);
        validateField($data["name"]);

        if(trim($data["flag"]) === ""){
            sendError("Invalid flag");
        }

        $eventName = $data["eventName"];
        $challengeDisplayName = $data["name"];
        $submittedFlag = $data["flag"];

        global $conn;

        $stm = $conn->prepare("SELECT id_event, endDate FROM EVENTS WHERE name = :name LIMIT 1");
        $stm->execute(array(":name" => $eventName));
        $event = $stm->fetchAll();
        if(empty($event)){
            sendError("Event not found. ¿Name is correct?");
        }
        $idEvent = $event[0]["id_event"];

        if(new DateTime("now") >= parseDate($event[0]["endDate"])){
            sendError("Event already ended");
        }

        $stm = $conn->prepare("SELECT CU.id_challenge as id_challenge, CU.points as points, C.name as originalName "
                             ."FROM CHALLENGES_USING CU "
                             ."JOIN CHALLENGES_EVENT CE ON CE.id_challengeU = CU.id_challengeU "
                             ."JOIN CHALLENGES C ON C.id_challenge = CU.id_challenge "
                             ."WHERE CE.id_event = :idEvent AND CU.name = :name LIMIT 1");
        $stm->execute(array(":idEvent" => $idEvent, ":name" => $challengeDisplayName));
        $usingRow = $stm->fetchAll();
        if(empty($usingRow)){
            sendError("Challenge not found in this event");
        }
        $usingRow = $usingRow[0];
        $idChallenge = $usingRow["id_challenge"];
        $points = $usingRow["points"];
        $originalName = $usingRow["originalName"];

        $stm = $conn->prepare("SELECT flag FROM USER_CONTAINERES WHERE id_user = :idUser AND id_event = :idEvent AND challengeName = :challengeName LIMIT 1");
        $stm->execute(array(":idUser" => $idUser, ":idEvent" => $idEvent, ":challengeName" => $originalName));
        $containerData = $stm->fetchAll();
        if(empty($containerData)){
            sendError("No active container for this challenge in this event");
        }

        if(!hash_equals($containerData[0]["flag"], $submittedFlag)){
            sendError("Incorrect flag");
        }

        $stm = $conn->prepare("SELECT ET.id_teams as id_teams FROM EVENTS_TEAMS ET JOIN USERS_TEAMS UT ON UT.id_teams = ET.id_teams "
                             ."WHERE UT.id_user = :idUser AND ET.id_event = :idEvent LIMIT 1");
        $stm->execute(array(":idUser" => $idUser, ":idEvent" => $idEvent));
        $teamEntry = $stm->fetchAll();
        if(empty($teamEntry)){
            sendError("Only a team entered in this event can submit flags");
        }
        $idTeams = $teamEntry[0]["id_teams"];

        $stm = $conn->prepare("SELECT status FROM TEAMS_CHALLENGES_EVENT WHERE id_teams = :idTeams AND id_challenge = :idChallenge AND id_event = :idEvent LIMIT 1");
        $stm->execute(array(":idTeams" => $idTeams, ":idChallenge" => $idChallenge, ":idEvent" => $idEvent));
        $progress = $stm->fetchAll();

        if(!empty($progress) && $progress[0]["status"] === "done"){
            sendError("Your team already completed this challenge");
        }


        $conn->beginTransaction();

        try{

            $stm = $conn->prepare("UPDATE TEAMS_CHALLENGES_EVENT SET status = 'done' WHERE id_teams = :idTeams AND id_challenge = :idChallenge AND id_event = :idEvent AND status != 'done'");
            $stm->execute(array(":idTeams" => $idTeams, ":idChallenge" => $idChallenge, ":idEvent" => $idEvent));

            if($stm->rowCount() === 0){
                $conn->rollBack();
                sendError("Your team already completed this challenge");
            }

            $stm = $conn->prepare("UPDATE EVENTS_TEAMS SET points = points + :points, lastChallengeDate = NOW() WHERE id_teams = :idTeams AND id_event = :idEvent");
            $stm->execute(array(":points" => $points, ":idTeams" => $idTeams, ":idEvent" => $idEvent));

            $stm = $conn->prepare("UPDATE TEAMS SET points = points + :points WHERE id_teams = :idTeams");
            $stm->execute(array(":points" => $points, ":idTeams" => $idTeams));

            $stm = $conn->prepare("UPDATE USERS SET points = points + :points WHERE id_user = :idUser");
            $stm->execute(array(":points" => $points, ":idUser" => $idUser));
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            sendError($e->getMessage());
        }
        echo json_encode(array("code" => "ok", "message" => "Correct flag! +".$points." points"));
    }

?>
