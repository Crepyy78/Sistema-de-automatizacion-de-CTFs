<?php
    require_once("lib/sessions.php");
    require_once("lib/lib.php");
    require_once("cors.php");

    set_time_limit(0);

    if($_SERVER["REQUEST_METHOD"] === "GET"){ //estado del contenedor activo del usuario
        checkSession();
        $idUser = $_SESSION["id"];

        global $conn;

        if(!empty($_SESSION["isAdmin"]) && isset($_GET["all"]) && $_GET["all"] === "1"){
            $stm = $conn->prepare("SELECT UC.instanceId as instanceId, UC.port as port, UC.status as status, "
                                 ."E.name as eventName, CU.name as name, U.username as username "
                                 ."FROM USER_CONTAINERES UC "
                                 ."JOIN EVENTS E ON E.id_event = UC.id_event "
                                 ."JOIN USERS U ON U.id_user = UC.id_user "
                                 ."JOIN CHALLENGES C ON C.name = UC.challengeName "
                                 ."JOIN CHALLENGES_USING CU ON CU.id_challenge = C.id_challenge "
                                 ."JOIN CHALLENGES_EVENT CE ON CE.id_challengeU = CU.id_challengeU AND CE.id_event = UC.id_event "
                                 ."ORDER BY U.username ASC");
            $stm->execute();
            $containers = $stm->fetchAll();

            echo json_encode(array("code" => "ok", "containers" => $containers));
            die();
        }

        $stm = $conn->prepare("SELECT UC.instanceId as instanceId, UC.port as port, UC.status as status, "
                             ."E.name as eventName, CU.name as name "
                             ."FROM USER_CONTAINERES UC "
                             ."JOIN EVENTS E ON E.id_event = UC.id_event "
                             ."JOIN CHALLENGES C ON C.name = UC.challengeName "
                             ."JOIN CHALLENGES_USING CU ON CU.id_challenge = C.id_challenge "
                             ."JOIN CHALLENGES_EVENT CE ON CE.id_challengeU = CU.id_challengeU AND CE.id_event = UC.id_event "
                             ."WHERE UC.id_user = :idUser LIMIT 1");
        $stm->execute(array(":idUser" => $idUser));
        $container = $stm->fetchAll();

        if(empty($container)){
            echo json_encode(array("code" => "ok", "container" => null));
            die();
        }

        echo json_encode(array("code" => "ok", "container" => $container[0]));
        die();
    }

    if($_SERVER["REQUEST_METHOD"] === "POST"){ //inicializar reto
        checkSession();
        $idUser = $_SESSION["id"];

        $data = json_decode(file_get_contents('php://input'), true);
        checkPostJson($data);
        validateField($data["eventName"]);
        validateField($data["name"]);

        $eventName = $data["eventName"];
        $challengeDisplayName = $data["name"];

        global $conn;

        $stm = $conn->prepare("SELECT id_event, startDate, endDate FROM EVENTS WHERE name = :name LIMIT 1");
        $stm->execute(array(":name" => $eventName));
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

        if(!$isMantainer){
            $stm = $conn->prepare("SELECT ET.id_teams FROM EVENTS_TEAMS ET JOIN USERS_TEAMS UT ON UT.id_teams = ET.id_teams "
                                 ."WHERE UT.id_user = :idUser AND ET.id_event = :idEvent LIMIT 1");
            $stm->execute(array(":idUser" => $idUser, ":idEvent" => $idEvent));
            $teamEntry = $stm->fetchAll();
            if(empty($teamEntry)){
                sendError("You must enter this event first");
            }

            $now = new DateTime("now");
            if($now < parseDate($event["startDate"])){
                sendError("Event hasn't started yet");
            }
            if($now >= parseDate($event["endDate"])){
                sendError("Event already ended");
            }
        }

        $stm = $conn->prepare("SELECT CU.id_challenge as id_challenge FROM CHALLENGES_USING CU "
                             ."JOIN CHALLENGES_EVENT CE ON CE.id_challengeU = CU.id_challengeU "
                             ."WHERE CE.id_event = :idEvent AND CU.name = :name LIMIT 1");
        $stm->execute(array(":idEvent" => $idEvent, ":name" => $challengeDisplayName));
        $usingRow = $stm->fetchAll();
        if(empty($usingRow)){
            sendError("Challenge not found in this event");
        }

        $stm = $conn->prepare("SELECT * FROM CHALLENGES WHERE id_challenge = :idChallenge LIMIT 1");
        $stm->execute(array(":idChallenge" => $usingRow[0]["id_challenge"]));
        $challengeData = $stm->fetchAll();
        if(empty($challengeData)){
            sendError("challenge not found");
        }
        $challengeData = $challengeData[0];
        $challengeName = $challengeData["name"];

        $stm = $conn->prepare("SELECT * FROM USER_CONTAINERES WHERE id_user = :idUser LIMIT 1");
        $stm->execute(array(":idUser" => $idUser));
        $containerData = $stm->fetchAll();

        if(!empty($containerData)){
            sendError("One container running");
        }

        $randomID = bin2hex(random_bytes(20));
        #$challengeForUser = $userName."-".$randomID;
        $challengeForUser = $randomID;

        $lock = fopen('/var/lock/' . $challengeName . '.lock', 'c');
        if (!flock($lock, LOCK_EX)) {
            sendError('Start challenge later');
        }
        try {
            exec("/var/www/data/challenges/".$challengeName."/prepare.bash '".$challengeForUser."' 0 2>&1", $prepareOutput, $prepareCode);
            if($prepareCode !== 0){
                sendError("prepare.bash failed: ".implode("\n", $prepareOutput));
            }
            $flag = trim(file_get_contents("../data/challenges/".$challengeName."/flags/_".$challengeForUser."_flag.txt"));
            exec("/var/www/data/challenges/".$challengeName."/start.bash '".$challengeForUser."' 2>&1", $startOutput, $startCode);
            if($startCode !== 0){
                sendError("start.bash failed: ".implode("\n", $startOutput));
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        exec("docker compose -f '/var/www/data/challenges/".$challengeName."/docker-compose.yml' -p '".$challengeForUser."' port machine 54475",$IpAndPort, $returnCode);
        if ($returnCode !== 0 || empty($IpAndPort)) {
            sendError('Could not determine challenge port');
        }
        $port = (int) explode(":",$IpAndPort[0])[1];

        $stm = $conn->prepare("INSERT INTO USER_CONTAINERES(id_user, id_event, challengeName, port, flag, instanceId, status) VALUES (:idUser, :idEvent, :challengeName, :port, :flag, :instanceId, 'running')");
        $stm->execute(array(":idUser" => $idUser, ":idEvent" => $idEvent, ":challengeName" => $challengeName, ":port" => $port, ":flag" => $flag, ":instanceId" => $challengeForUser));

        echo json_encode(array("code" => "ok", "message" => "Challenged open on Port: " .$port." with instance: ".$challengeForUser));
        die();


    }

    if($_SERVER["REQUEST_METHOD"] === "PUT"){ //parar / reaunudar
        checkSession();
        $idUser = $_SESSION["id"];

        $data = json_decode(file_get_contents('php://input'), true);
        emptyJson($data);
        emptyField($data,"option");
        emptyField($data,"instanceId");
        validateField($data["option"]);
        validateField($data["instanceId"]);

        if($data["option"] !== "stop" && $data["option"] !== "continue"){
            sendError("Invalid option");
        }

        $instanceId = $data["instanceId"];

        global $conn;
        $stm = $conn->prepare("SELECT * FROM USER_CONTAINERES WHERE id_user = :idUser and instanceId = :instanceId LIMIT 1");
        $stm->execute(array(":idUser" => $idUser, ":instanceId" => $instanceId));
        $containerData = $stm->fetchAll();

        if(empty($containerData)){
            sendError("container not found");
        }
        $containerData = $containerData[0];
        $challengeName = $containerData["challengeName"];

        $newPort = -1;
        if($data["option"] === "stop"){
            exec("/var/www/data/challenges/".$challengeName."/stop.bash '".$instanceId."'");
            $stm = $conn->prepare("UPDATE USER_CONTAINERES SET status = 'pause' WHERE id_user = :idUser and instanceId = :instanceId");
            $stm->execute(array(":idUser" => $idUser, ":instanceId" => $instanceId));

        } elseif($data["option"] === "continue"){
            $stm = $conn->prepare("SELECT endDate FROM EVENTS WHERE id_event = :idEvent LIMIT 1");
            $stm->execute(array(":idEvent" => $containerData["id_event"]));
            $eventDates = $stm->fetchAll();
            if(!empty($eventDates) && new DateTime("now") >= parseDate($eventDates[0]["endDate"])){
                sendError("Event already ended");
            }

            $lock = fopen('/var/lock/' . $challengeName . '.lock', 'c');
            if (!flock($lock, LOCK_EX)) {
                sendError('Try again later');
            }
            try {
                exec("/var/www/data/challenges/".$challengeName."/start.bash '".$instanceId."'");
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }

            exec("docker compose -f '/var/www/data/challenges/".$challengeName."/docker-compose.yml' -p '".$instanceId."' port machine 54475",$IpAndPort, $returnCode);
            if ($returnCode !== 0 || empty($IpAndPort)) {
                sendError('Could not determine challenge port');
            }
            $newPort = (int) explode(":",$IpAndPort[0])[1];

            $stm = $conn->prepare("UPDATE USER_CONTAINERES SET status = 'running', port = :port WHERE id_user = :idUser and instanceId = :instanceId");
            $stm->execute(array(":port" => $newPort, ":idUser" => $idUser, ":instanceId" => $instanceId));
        }

        echo json_encode(array("code" => "ok", "message" => "Challenge succsessfullly changed in Port: ".$newPort));
        die();
    }

    if($_SERVER["REQUEST_METHOD"] === "DELETE"){ //borrrar
        checkSession();
        $idUser = $_SESSION["id"];
        $isAdmin = !empty($_SESSION["isAdmin"]);

        $data = json_decode(file_get_contents('php://input'), true);
        $targetInstanceId = (is_array($data) && !empty($data["instanceId"])) ? $data["instanceId"] : null;

        global $conn;

        if($targetInstanceId !== null){
            validateField($targetInstanceId);
            if($isAdmin){
                $stm = $conn->prepare("SELECT * FROM USER_CONTAINERES WHERE instanceId = :instanceId LIMIT 1");
                $stm->execute(array(":instanceId" => $targetInstanceId));
            } else {
                $stm = $conn->prepare("SELECT * FROM USER_CONTAINERES WHERE id_user = :idUser AND instanceId = :instanceId LIMIT 1");
                $stm->execute(array(":idUser" => $idUser, ":instanceId" => $targetInstanceId));
            }
            $containerData = $stm->fetchAll();
        } else {
            $stm = $conn->prepare("SELECT * FROM USER_CONTAINERES WHERE id_user = :idUser LIMIT 1");
            $stm->execute(array(":idUser" => $idUser));
            $containerData = $stm->fetchAll();
        }

        if(empty($containerData)){
            sendError("No container to delete");
        }

        $targetUserId = $containerData[0]["id_user"];
        $containerInstance =  $containerData[0]["instanceId"];
        $conatinerChallenegName =  $containerData[0]["challengeName"];
        exec("/var/www/data/challenges/".$conatinerChallenegName."/stop.bash '".$containerInstance."'",$output1,$returnCode1);
        exec("/var/www/data/challenges/".$conatinerChallenegName."/down.bash '".$containerInstance."'",$output2,$returnCode2);

        if($returnCode1 !== 0 || $returnCode2 !== 0){
            sendError("Try again later");
        }

        $stm = $conn->prepare("DELETE FROM USER_CONTAINERES WHERE id_user = :idUser AND instanceId = :instanceId");
        $stm->execute(array(":idUser" => $targetUserId, ":instanceId" => $containerInstance));

        echo json_encode(array("code" => "ok", "message" => "Conatiner deleted"));
        die();
    }

    function checkPostJson($challenge){
        emptyJson($challenge);
        emptyField($challenge,"name");
        emptyField($challenge,"eventName");
    }
?>
