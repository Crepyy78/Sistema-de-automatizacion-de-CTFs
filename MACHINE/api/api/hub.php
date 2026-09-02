<?php
    require_once("cors.php");
    require_once("lib/lib.php");
    require_once("lib/sessions.php");

    if($_SERVER["REQUEST_METHOD"] === "GET"){
        checkSession();
        global $conn;

        $stm = $conn->prepare("SELECT E.name as name, E.startDate as startDate, E.endDate as endDate, "
                             ."E.isPublic as isPublic, COALESCE(UE.isMantainer, 0) as isMantainer "
                             ."FROM EVENTS E "
                             ."LEFT JOIN USERS_EVENTS UE ON UE.id_event = E.id_event AND UE.id_user = :userId "
                             ."WHERE E.isPublic = TRUE OR UE.id_user = :userId");
        $stm->execute(array(":userId" => $_SESSION["id"]));
        $publicEvents= $stm->fetchAll();

        $arrayEvents = [];
        foreach($publicEvents as $event){
            $arrayEvents[] = array("name" => $event["name"], "startDate" => $event["startDate"],
                                "endDate" => $event["endDate"], "isPublic" => (bool)$event["isPublic"],
                                "isMantainer" => (bool)$event["isMantainer"]);
        }
        echo json_encode(array("code" => "ok","events" => $arrayEvents));
    }

    if($_SERVER["REQUEST_METHOD"] === "POST"){
        echo json_encode(array("message" => "does not support POST Petition"));
    }

    if($_SERVER["REQUEST_METHOD"] === "PUT"){
        echo json_encode(array("message" => "does not support PUT Petition"));
    }

    if($_SERVER["REQUEST_METHOD"] === "DELETE"){
        echo json_encode(array("message" => "does not support DELETE Petition"));
    }
?>