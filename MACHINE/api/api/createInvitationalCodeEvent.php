<?php
    require_once("lib/sessions.php");
    require_once("lib/lib.php");
    require_once("cors.php");

    if($_SERVER["REQUEST_METHOD"] === "POST"){
        checkSession();
        $idUser = $_SESSION["id"];

        $eventName = json_decode(file_get_contents('php://input'), true);
        emptyJson($eventName);
        emptyField($eventName,"eventName");
        validateField($eventName["eventName"]);

        global $conn;

        $stm = $conn->prepare("SELECT E.id_event FROM EVENTS E JOIN USERS_EVENTS UE ON UE.id_event = E.id_event "
                             ."WHERE UE.id_user = :idUser AND E.name = :name AND UE.isMantainer = TRUE LIMIT 1");
        $stm->execute(array(":idUser" => $idUser, ":name" => $eventName["eventName"]));
        $eventData = $stm->fetchAll();

        if(empty($eventData)){
            sendError("Not a mantainer for this event");
        }

        $code = bin2hex(random_bytes(12));
        $stm = $conn->prepare("UPDATE EVENTS SET invitanionalCode = :code, codeExpireDate = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id_event = :idEvent");
        $stm->execute(array(":code" => $code, ":idEvent" => $eventData[0]["id_event"]));

        sendMsg("Invitanional code created for the next 24h, code: ".$code);
    }

?>
