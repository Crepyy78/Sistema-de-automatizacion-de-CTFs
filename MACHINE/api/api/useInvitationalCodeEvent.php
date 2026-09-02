<?php
    require_once("lib/sessions.php");
    require_once("lib/lib.php");
    require_once("cors.php");

    if($_SERVER["REQUEST_METHOD"] === "POST"){
        checkSession();
        $idUser = $_SESSION["id"];

        $eventCode = json_decode(file_get_contents('php://input'), true);
        emptyJson($eventCode);
        emptyField($eventCode,"eventCode");
        validateField($eventCode["eventCode"]);

        global $conn;

        $stm = $conn->prepare("SELECT * FROM EVENTS WHERE invitanionalCode = :eventCode AND codeExpireDate > NOW()");
        $stm->execute(array(":eventCode" => $eventCode["eventCode"]));
        $eventData = $stm->fetchAll();

        if(empty($eventData)){
            sendError("No valid event with that code");
        }

        $stm = $conn->prepare("SELECT id_user FROM USERS_EVENTS WHERE id_event = :idEvent AND id_user = :idUser");
        $stm->execute(array(":idEvent" => $eventData[0]["id_event"], ":idUser" => $idUser));
        $eventDataUsers = $stm->fetchAll();

        if(!empty($eventDataUsers)){
            sendError("Alredy in the event");
        }

        $stm = $conn->prepare("INSERT INTO USERS_EVENTS(id_event, id_user, isMantainer) VALUES (:idEvent,:idUser, TRUE)");
        $stm->execute(array(":idEvent" => $eventData[0]["id_event"], ":idUser" => $idUser));

        sendMsg("Added to the event as mantainer");
    }
?>
