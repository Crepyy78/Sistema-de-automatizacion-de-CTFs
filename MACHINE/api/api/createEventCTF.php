<?php
    require_once("lib/sessions.php");
    require_once("lib/lib.php");
    require_once("cors.php");

    if($_SERVER["REQUEST_METHOD"] === "GET"){
        checkSession();
        $idUser = $_SESSION["id"];

        if(!isset($_GET["eventName"])){
            sendError("need the parameter eventName");
        }

        validateField($_GET["eventName"]);
        $stm = $conn->prepare("SELECT id_event, startDate FROM EVENTS WHERE name = :name LIMIT 1");
        $stm->execute(array(":name" => $_GET["eventName"]));
        $event = $stm->fetchAll();
        if(empty($event)){
            sendError("Event not found. ¿Name is correct?");
        }
        $event = $event[0];
        $idEvent = $event["id_event"];

        $stm = $conn->prepare("SELECT isMantainer FROM USERS_EVENTS WHERE id_user = :idUser AND id_event = :idEvent LIMIT 1");
        $stm->execute(array(":idUser" => $idUser, ":idEvent" => $idEvent));
        $mantainerRow = $stm->fetchAll();
        $isMantainer = !empty($mantainerRow) && $mantainerRow[0]["isMantainer"];

        $idTeams = null;
        if(!$isMantainer){
            $stm = $conn->prepare("SELECT ET.id_teams FROM EVENTS_TEAMS ET JOIN USERS_TEAMS UT ON UT.id_teams = ET.id_teams "
                                 ."WHERE UT.id_user = :idUser AND ET.id_event = :idEvent LIMIT 1");
            $stm->execute(array(":idUser" => $idUser, ":idEvent" => $idEvent));
            $teamEntry = $stm->fetchAll();

            if(empty($teamEntry)){
                sendError("You must enter this event first");
            }
            $idTeams = $teamEntry[0]["id_teams"];

            $startDate = parseDate($event["startDate"]);
            if(new DateTime("now") < $startDate){
                sendError("Challenges are not available until the event starts");
            }
        }

        $stm = $conn->prepare("SELECT CU.name as name, CU.description as description, CU.category as category, "
                             ."CU.points as points, CU.difficulty as difficulty, TCE.status as status "
                             ."FROM CHALLENGES_USING CU JOIN CHALLENGES_EVENT CE ON CU.id_challengeU = CE.id_challengeU "
                             ."LEFT JOIN TEAMS_CHALLENGES_EVENT TCE ON TCE.id_challenge = CU.id_challenge "
                             ."AND TCE.id_event = CE.id_event AND TCE.id_teams = :idTeams "
                             ."WHERE CE.id_event = :idEvent");
        $stm->execute(array(":idEvent" => $idEvent, ":idTeams" => $idTeams));
        $challenges = $stm->fetchAll(PDO::FETCH_ASSOC);

        foreach($challenges as &$challenge){
            $challenge["solved"] = ($challenge["status"] === "done");
            unset($challenge["status"]);
        }
        unset($challenge);

        echo json_encode(array("code" => "ok", "challenges" => $challenges));
    }

    if($_SERVER["REQUEST_METHOD"] === "POST"){
        checkSessionCreator();
        $idUser = $_SESSION["id"];

        $evenAndChallengesData = json_decode(file_get_contents('php://input'), true);
        emptyJson($evenAndChallengesData);
        emptyField($evenAndChallengesData,"eventName");
        emptyField($evenAndChallengesData,"challenges");
        validateField($evenAndChallengesData["eventName"]);
        validateArray($evenAndChallengesData["challenges"]);
        foreach($evenAndChallengesData["challenges"] as $challengesNames){
            validateField($challengesNames);
        }

        $stm = $conn->prepare("SELECT id_event, isPublic FROM EVENTS WHERE name = :name LIMIT 1");
        $stm->execute(array(":name" => $evenAndChallengesData["eventName"]));
        $event = $stm->fetchAll();
        if(empty($event)){
            sendError("Event not found. ¿Name is correct?");
        }
        $event = $event[0];
        $idEvent = $event["id_event"];

        if($event["isPublic"]){
            sendError("Can't add challenges to a public event");
        }

        $stm = $conn->prepare("SELECT UE.isMantainer as isMantainer FROM USERS_EVENTS UE WHERE UE.id_user = :idUser AND UE.id_event = :idEvent LIMIT 1");
        $stm->execute(array(":idUser" => $idUser, ":idEvent" => $idEvent));
        $isMantainer = $stm->fetchAll();
        if(empty($isMantainer) || !$isMantainer[0]["isMantainer"]){
            sendError("Not a mantainer in this event");
        }

        foreach($evenAndChallengesData["challenges"] as $challengesNames){
            $stm = $conn->prepare("SELECT CU.id_challengeU as id_challengeU FROM CHALLENGES_USING CU "
                                 ."JOIN CHALLENGES_EVENT CE ON CE.id_challengeU = CU.id_challengeU "
                                 ."WHERE CE.id_event = :idEvent AND CU.name = :name LIMIT 1");
            $stm->execute(array(":idEvent" => $idEvent, ":name" => $challengesNames));
            $existing = $stm->fetchAll();
            if(!empty($existing)){
                sendError("Challenge already in this event");
            }

            $stm = $conn->prepare("SELECT * FROM CHALLENGES WHERE name = :name LIMIT 1");
            $stm->execute(array(":name" => $challengesNames));
            $challData = $stm->fetchAll();

            if(empty($challData)){
                sendError("Challenge Not Found");
            }
            $challData = $challData[0];

            $stm = $conn->prepare("SELECT COALESCE(MAX(id_challengeU), 0) + 1 FROM CHALLENGES_USING");
            $stm->execute();
            $idChallengeU = (int)$stm->fetchColumn();

            $stm = $conn->prepare("INSERT INTO CHALLENGES_USING(id_challengeU, id_challenge, name, description, category, points, difficulty, author) "
                                  ."VALUES (:idChallengeU, :idChallenge, :name, :description, :category, :points, :difficulty, :author)");
            $stm->execute(array(
                ":idChallengeU" => $idChallengeU,
                ":idChallenge"  => $challData["id_challenge"],
                ":name"         => $challData["name"],
                ":description"  => $challData["description"],
                ":category"     => $challData["category"],
                ":points"       => $challData["points"],
                ":difficulty"   => $challData["difficulty"],
                ":author"       => $challData["author"],
            ));

            $stm = $conn->prepare("INSERT INTO CHALLENGES_EVENT(id_challengeU, id_event) VALUES(:idChallengeU,:idEvent)");
            $stm->execute(array(":idChallengeU" => $idChallengeU, ":idEvent" => $idEvent));

            $stm = $conn->prepare("INSERT IGNORE INTO TEAMS_CHALLENGES_EVENT (id_teams, id_challenge, id_event, status) "
                                 ."SELECT DISTINCT ET.id_teams, :idChallenge, :idEvent, 'not done' "
                                 ."FROM EVENTS_TEAMS ET WHERE ET.id_event = :idEvent2");
            $stm->execute(array(":idChallenge" => $challData["id_challenge"], ":idEvent" => $idEvent, ":idEvent2" => $idEvent));
        }

        sendMsg("CTF Created");

    }

        if($_SERVER["REQUEST_METHOD"] === "PUT"){
        checkSession();
        $idUser = $_SESSION["id"];

        $data = json_decode(file_get_contents('php://input'), true);
        emptyJson($data);
        emptyField($data,"eventName");
        emptyField($data,"challengeName");
        emptyField($data,"changes");
        validateField($data["eventName"]);
        validateField($data["challengeName"]);
        validateArray($data["changes"]);

        $eventName = $data["eventName"];
        $challengeName = $data["challengeName"];
        $changes = $data["changes"];

        $stm = $conn->prepare("SELECT id_event, isPublic FROM EVENTS WHERE name = :name LIMIT 1");
        $stm->execute(array(":name" => $eventName));
        $event = $stm->fetchAll();
        if(empty($event)){
            sendError("Event not found. ¿Name is correct?");
        }
        $event = $event[0];
        $idEvent = $event["id_event"];

        if($event["isPublic"]){
            sendError("Can't edit challenges on a public event");
        }

        $stm = $conn->prepare("SELECT UE.isMantainer as isMantainer FROM USERS_EVENTS UE WHERE UE.id_user = :idUser AND UE.id_event = :idEvent LIMIT 1");
        $stm->execute(array(":idUser" => $idUser, ":idEvent" => $idEvent));
        $isMantainer = $stm->fetchAll();
        if(empty($isMantainer) || !$isMantainer[0]["isMantainer"]){
            sendError("Not a mantainer in this event");
        }

        $stm = $conn->prepare("SELECT CU.id_challengeU as id_challengeU FROM CHALLENGES_USING CU "
                             ."JOIN CHALLENGES_EVENT CE ON CE.id_challengeU = CU.id_challengeU "
                             ."WHERE CE.id_event = :idEvent AND CU.name = :challengeName LIMIT 1");
        $stm->execute(array(":idEvent" => $idEvent, ":challengeName" => $challengeName));
        $challengeU = $stm->fetchAll();
        if(empty($challengeU)){
            sendError("Challenge not found in this event");
        }
        $idChallengeU = $challengeU[0]["id_challengeU"];

        $allowedFields = [
            "name"        => "validateField",
            "description" => "validateDescription",
            "category"    => "validateField",
            "points"      => "validateNumeric",
            "difficulty"  => "validateField",
            "author"      => "validateField",
        ];

        $setClauses = [];
        $params = [];

        foreach($allowedFields as $field => $validator){
            if(!isset($changes[$field])) continue;
            $validator($changes[$field]);

            $setClauses[] = "$field = :$field";
            $params[$field] = $changes[$field];
        }

        if(empty($setClauses)){
            sendError("no changes detected");
        }

        $params["idChallengeU"] = $idChallengeU;
        $sql = "UPDATE CHALLENGES_USING SET " . implode(", ", $setClauses) . " WHERE id_challengeU = :idChallengeU";
        $stm = $conn->prepare($sql);
        $stm->execute($params);

        sendMsg("Challenge updated");
    }

    if($_SERVER["REQUEST_METHOD"] === "DELETE"){
        checkSessionCreator();
        $idUser = $_SESSION["id"];

        $data = json_decode(file_get_contents('php://input'), true);
        emptyJson($data);
        emptyField($data,"eventName");
        emptyField($data,"challengeName");
        validateField($data["eventName"]);
        validateField($data["challengeName"]);

        $eventName = $data["eventName"];
        $challengeName = $data["challengeName"];

        $stm = $conn->prepare("SELECT id_event, isPublic FROM EVENTS WHERE name = :name LIMIT 1");
        $stm->execute(array(":name" => $eventName));
        $event = $stm->fetchAll();
        if(empty($event)){
            sendError("Event not found. ¿Name is correct?");
        }
        $event = $event[0];
        $idEvent = $event["id_event"];

        if($event["isPublic"]){
            sendError("Can't remove challenges from a public event");
        }

        $stm = $conn->prepare("SELECT UE.isMantainer as isMantainer FROM USERS_EVENTS UE WHERE UE.id_user = :idUser AND UE.id_event = :idEvent LIMIT 1");
        $stm->execute(array(":idUser" => $idUser, ":idEvent" => $idEvent));
        $isMantainer = $stm->fetchAll();
        if(empty($isMantainer) || !$isMantainer[0]["isMantainer"]){
            sendError("Not a mantainer in this event");
        }

        $stm = $conn->prepare("SELECT CU.id_challengeU as id_challengeU FROM CHALLENGES_USING CU "
                             ."JOIN CHALLENGES_EVENT CE ON CE.id_challengeU = CU.id_challengeU "
                             ."WHERE CE.id_event = :idEvent AND CU.name = :challengeName LIMIT 1");
        $stm->execute(array(":idEvent" => $idEvent, ":challengeName" => $challengeName));
        $challengeU = $stm->fetchAll();
        if(empty($challengeU)){
            sendError("Challenge not found in this event");
        }
        $idChallengeU = $challengeU[0]["id_challengeU"];

        $stm = $conn->prepare("DELETE FROM CHALLENGES_USING WHERE id_challengeU = :idChallengeU");
        $stm->execute(array(":idChallengeU" => $idChallengeU));

        sendMsg("Challenge removed from event");
    }

?>