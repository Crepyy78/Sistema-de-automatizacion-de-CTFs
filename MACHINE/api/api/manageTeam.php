<?php
    require_once("lib/sessions.php");
    require_once("lib/lib.php");
    require_once("cors.php");
    
    if($_SERVER["REQUEST_METHOD"] === "GET"){   
        checkSession();
        global $conn;

        if(isset($_GET["team"])){
            validateField($_GET["team"]);
            $teamName = $_GET["team"];

            $stm = $conn->prepare("SELECT U.username as name FROM USERS_TEAMS UT JOIN USERS U ON U.id_user = UT.id_user JOIN TEAMS T ON T.id_teams = UT.id_teams WHERE T.name = :teamName AND T.isPersonal != TRUE");
            $stm->execute(array(":teamName" => $teamName));
            $teams = $stm->fetchAll();

            if(empty($teams)){
                sendError("No team with that name");
            }

            $arrayUsersTeam = [];
            foreach($teams as $team){
                $arrayUsersTeam[] = array("name" => $team["name"]);
            }
            echo json_encode(array("code" => "ok","users" => $arrayUsersTeam));

        } else {
            $stm = $conn->prepare("SELECT T.name as name, T.description as description FROM USERS_TEAMS UT JOIN TEAMS T ON UT.id_teams = T.id_teams WHERE UT.id_user = :id AND T.isPersonal != TRUE");
            $stm->execute(array(":id" => $_SESSION["id"]));
            $teams = $stm->fetchAll();

            $arrayTeams = [];
            foreach($teams as $team){
                $arrayTeams[] = array("name" => $team["name"], "description" => $team["description"]);
            }
            echo json_encode(array("code" => "ok","teams" => $arrayTeams));
        }
    }

    if($_SERVER["REQUEST_METHOD"] === "POST"){   
        checkSession();
        global $conn;

        $teamData = json_decode(file_get_contents('php://input'), true);
        checkPostJson($teamData);
        validatePostJsonData($teamData);

        $teamName = $teamData["name"];
        $teamDescription = $teamData["description"];
        $leaderId = $_SESSION["id"];

        $stm = $conn->prepare("SELECT name FROM TEAMS");
        $stm->execute();
        $teamsNames = $stm->fetchAll();
        foreach($teamsNames as $name){
            if($name["name"] === $teamName){
                sendError("Name alredy taken");
            }
        }

        $conn->beginTransaction();

        try {
            $stm = $conn->prepare(
                "INSERT INTO TEAMS (name, description, leader)
                VALUES (:name, :description, :leader)"
            );

            $stm->execute([
                ":name" => $teamName,
                ":description" => $teamDescription,
                ":leader" => $leaderId
            ]);

            $teamId = $conn->lastInsertId();

            $stm = $conn->prepare(
                "INSERT INTO USERS_TEAMS (id_teams, id_user)
                VALUES (:idTeams, :idUser)"
            );

            $stm->execute([
                ":idTeams" => $teamId,
                ":idUser" => $leaderId
            ]);

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            sendError($e->getMessage());
        }

        echo json_encode(array("code" => "ok", "message" => "team added correctly"));
    }

    if($_SERVER["REQUEST_METHOD"] === "PUT"){   
        checkSession();
        global $conn;

        $teamData = json_decode(file_get_contents('php://input'), true);
        emptyJson($teamData);
        ##TODO
    }

    if($_SERVER["REQUEST_METHOD"] === "DELETE"){   
        checkSession();
        global $conn;

        $teamData = json_decode(file_get_contents('php://input'), true);
        emptyJson($teamData);
        emptyField($teamData,"name");
        validateField($teamData["name"]);
        $userId = $_SESSION["id"];
        $teamName = $teamData["name"];

        $stm = $conn->prepare("DELETE FROM TEAMS WHERE name = :teamName and leader = :leader and isPersonal != TRUE");
        $stm->execute(array(":teamName" => $teamName, ":leader" => $userId));        

        # Y Si falla que?
        echo json_encode(array("code" => "ok", "message" => "deleted team correctly"));
    }

    function checkPostJson($teamData){
        emptyJson($teamData);
        emptyField($teamData,"name");
        emptyField($teamData,"description");
    }

    function validatePostJsonData($teamData){
        validateField($teamData["name"]);
        validateField($teamData["description"]);
    }

?>