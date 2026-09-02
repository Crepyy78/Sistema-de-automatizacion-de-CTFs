<?php
    require_once("lib/sessions.php");
    require_once("lib/lib.php");
    require_once("cors.php");
    if($_SERVER["REQUEST_METHOD"] === "GET"){   
        checkSession();
        $id_user = $_SESSION["id"];

        $stm = $conn->prepare("SELECT UE.points as points "
                            .",E.name as name,E.description as description,E.startDate as startDate "
                            .",E.endDate as endDate FROM USERS_EVENTS UE " 
                            . "JOIN EVENTS E ON E.id_event = UE.id_event "
                            . "WHERE UE.id_user = :id_user");
        $stm->execute(array(":id_user" => $id_user));

        $joinedCtf = $stm->fetchAll();

        foreach($joinedCtf as $ctf){
            $arrayCtf[] = array("name" => $ctf["name"],
                                "description" => $ctf["description"],
                                "startDate" => $ctf["startDate"],
                                "endDate" => $ctf["endDate"],
                                "points" => $ctf["points"]);
        }

        echo json_encode($arrayCtf);
    }
    if($_SERVER["REQUEST_METHOD"] === "POST"){
        checkSessionCreator();
        $ctfData = json_decode(file_get_contents('php://input'), true);
        checkPostJson($ctfData);
        validatePostJsonData($ctfData);

        $nameCTF = $ctfData["name"];
        $descriptionCTF = $ctfData["description"];
        $startDateCTF = parseDate($ctfData["startDate"]);
        $endDateCTF = parseDate($ctfData["endDate"]);
        $peoplePerTeamCTF = intval($ctfData["peoplePerTeam"]);

        if($startDateCTF >= $endDateCTF){
            sendError("dates in reverse order");
        }
        
        if(new DateTime("now") >= $startDateCTF){
            sendError("start date alredy passed");
        }

        global $conn;
        $stm = $conn->prepare("SELECT name FROM EVENTS");
        $stm->execute();
        $namesCTFs = $stm->fetchAll();
        foreach($namesCTFs as $name){
            if($name["name"] === $nameCTF){
                sendError("Name alredy taken");
            }
        }

        $stm = $conn->prepare("INSERT INTO EVENTS (name, startDate, endDate, description, maxNumbersPerTeam)"
                              ."VALUES (:name, :startDate, :endDate, :description, :maxNumbersPerTeam)");
        $stm->execute(array(":name" => $nameCTF,
                            ":startDate" => $startDateCTF->format('Y-m-d H:i:s'),
                            ":endDate" => $endDateCTF->format('Y-m-d H:i:s'),
                            ":description" => $descriptionCTF,
                            ":maxNumbersPerTeam" => $peoplePerTeamCTF));
        
        $userId = $_SESSION["id"];
        $eventId = $conn->lastInsertId();

        $stm = $conn->prepare("INSERT INTO USERS_EVENTS (id_user, id_event, isMantainer) " 
                              ."VALUES (:id_user, :id_event, true)");
        $stm->execute(array(":id_user" => $userId, ":id_event" => $eventId)); 

        echo json_encode(array("code" => "ok", "message" => "added event correctly"));
    }
    if($_SERVER["REQUEST_METHOD"] === "PUT"){
        checkSession();
        $ctfChanges = json_decode(file_get_contents('php://input'), true);
        emptyJson($ctfChanges);
        emptyField($ctfChanges,"name");
        emptyField($ctfChanges,"changes");
        validateField($ctfChanges["name"]);
        validateArray($ctfChanges,"changes");
        $ctfName = $ctfChanges["name"];
        $changes = $ctfChanges["changes"];
        isUserMantainer($ctfName);

        $itStarted   = isEventStartedAndPublic($ctfName);

        $allowedFields = [
            "name"              => ["validator" => "validateField",   "allowedIfStarted" => false],
            "startDate"         => ["validator" => "validateDate",    "allowedIfStarted" => false],
            "endDate"           => ["validator" => "validateDate",    "allowedIfStarted" => true],
            "description"       => ["validator" => "validateField",   "allowedIfStarted" => true],
            "maxNumbersPerTeam" => ["validator" => "validateNumeric", "allowedIfStarted" => false],
            "isPublic"          => ["validator" => "validateBoolean", "allowedIfStarted" => false],
        ];

        $setClauses = [];
        $params = [];

        foreach ($allowedFields as $field => $rules) {
            if (!isset($changes[$field]) || ($itStarted && !$rules["allowedIfStarted"])) continue;

            $validator = $rules["validator"];
            $validator($changes[$field]);

            $value = $changes[$field];
            if ($field === "isPublic") {
                $value = parseBoolean($value) ? 1 : 0;
            }

            $setClauses[] = "$field = :$field";
            $params[$field] = $value;
        }

        if (empty($setClauses)) {
            sendIfError("no changes detected");
        }

        $sql = "UPDATE EVENTS SET " . implode(", ", $setClauses) . " WHERE name = :originalName";
        $params["originalName"] = $ctfName;
        
        $stm = $conn->prepare($sql);
        $stm->execute($params);

        echo json_encode(array("code" => "ok", "message" => "modified event correctly"));
    }
    
    if($_SERVER["REQUEST_METHOD"] === "DELETE"){
        checkSession();
        $ctfName = json_decode(file_get_contents('php://input'), true);
        emptyJson($ctfName);
        emptyField($ctfName,"name");
        validateField($ctfName["name"]);
        $ctfName = $ctfName["name"];

        isUserMantainer($ctfName);
        
        global $conn;
        $stm = $conn->prepare("DELETE FROM EVENTS WHERE name = :nameCTF");
        $stm->execute(array(":nameCTF" => $ctfName));

        echo json_encode(array("code" => "ok", "message" => "deleted event correctly"));
    }


    function checkPostJson($ctfData){
        emptyJson($ctfData);
        emptyField($ctfData,"name");
        emptyField($ctfData,"startDate");
        emptyField($ctfData,"endDate");
        emptyField($ctfData,"description");
        emptyField($ctfData,"peoplePerTeam");
    }

    function validatePostJsonData($ctfData){
        validateField($ctfData["name"]);
        validateField($ctfData["description"]);
        validateDate($ctfData["startDate"]);
        validateDate($ctfData["endDate"]);
        validateNumeric($ctfData["peoplePerTeam"]);
    }

    function isUserMantainer($ctfName){
        global $conn;
        $stm = $conn->prepare("SELECT UE.isMantainer as mantainer FROM USERS_EVENTS UE "
                             ."JOIN EVENTS E ON E.id_event = UE.id_event "
                             ."WHERE UE.id_user = :id_user AND E.name = :ctfName LIMIT 1");
        $stm->execute(array(":id_user" => $_SESSION["id"], ":ctfName" => $ctfName));
        $isMantainer = $stm->fetchAll();
        if(count($isMantainer) === 0){
            sendError("not part of this event");
        }
        if($isMantainer[0]["mantainer"] === 0){
            sendError("not a mantainer of this event");
        }
    }

    function isEventStartedAndPublic($ctfName){
        global $conn;
        $stm = $conn->prepare("SELECT startDate, endDate, isPublic FROM EVENTS WHERE name = :name LIMIT 1");
        $stm->execute(array(":name" => $ctfName));
        $datesAndPublicCTF = $stm->fetch();
        $startDateCTF = parseDate($datesAndPublicCTF["startDate"]);
        $endDateCTF   = parseDate($datesAndPublicCTF["endDate"]);
        $isPublic = $datesAndPublicCTF["isPublic"];

        if(new DateTime("now") >= $endDateCTF){
            sendError("Event alredy ended");
        }
        if(new DateTime("now") >= $startDateCTF && $isPublic){
            return true;
        }
        return false;
    }
?>