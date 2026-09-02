<?php
    require_once("lib/sessions.php");
    require_once("lib/lib.php");
    require_once("cors.php");

    if($_SERVER["REQUEST_METHOD"] === "GET"){
        checkSession();

        global $conn;

        $stm = $conn->prepare("SELECT username, points FROM USERS ORDER BY points DESC, username ASC");
        $stm->execute();
        $userRows = $stm->fetchAll(PDO::FETCH_ASSOC);

        $users = array();
        $rank = 0;
        foreach($userRows as $row){
            $rank++;
            $users[] = array("rank" => $rank, "username" => $row["username"], "points" => (int)$row["points"]);
        }

        $stm = $conn->prepare("SELECT name as teamName, points FROM TEAMS WHERE isPersonal != TRUE ORDER BY points DESC, name ASC");
        $stm->execute();
        $teamRows = $stm->fetchAll(PDO::FETCH_ASSOC);

        $teams = array();
        $rank = 0;
        foreach($teamRows as $row){
            $rank = $rank + 1;
            $teams[] = array("rank" => $rank, "teamName" => $row["teamName"], "points" => (int)$row["points"]);
        }

        echo json_encode(array("code" => "ok", "users" => $users, "teams" => $teams));
    }
?>
