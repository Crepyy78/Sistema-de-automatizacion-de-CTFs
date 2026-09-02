<?php
    require_once("lib/sessions.php");
    require_once("lib/lib.php");
    require_once("cors.php");

    if($_SERVER["REQUEST_METHOD"] === "GET"){
        checkSession();

        if(!isset($_GET["eventName"])){
            sendError("need the parameter eventName");
        }
        validateField($_GET["eventName"]);

        global $conn;

        $stm = $conn->prepare("SELECT id_event FROM EVENTS WHERE name = :name LIMIT 1");
        $stm->execute(array(":name" => $_GET["eventName"]));
        $event = $stm->fetchAll();
        if(empty($event)){
            sendError("Event not found. ¿Name is correct?");
        }
        $idEvent = $event[0]["id_event"];

        $stm = $conn->prepare("SELECT T.name as teamName, ET.points as points "
                             ."FROM EVENTS_TEAMS ET JOIN TEAMS T ON T.id_teams = ET.id_teams "
                             ."WHERE ET.id_event = :idEvent "
                             ."ORDER BY ET.points DESC, ET.lastChallengeDate ASC, T.name ASC");
        $stm->execute(array(":idEvent" => $idEvent));
        $rows = $stm->fetchAll(PDO::FETCH_ASSOC);

        $ranking = array();
        $rank = 0;
        foreach($rows as $row){
            $rank = $rank + 1;
            $ranking[] = array(
                "rank" => $rank,
                "teamName" => $row["teamName"],
                "points" => (int)$row["points"],
            );
        }

        echo json_encode(array("code" => "ok", "ranking" => $ranking));
    }

?>
