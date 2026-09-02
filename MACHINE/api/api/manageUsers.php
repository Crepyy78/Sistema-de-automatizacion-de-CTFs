<?php
    require_once("cors.php");
    require_once("lib/lib.php");
    require_once("lib/sessions.php");

    if($_SERVER["REQUEST_METHOD"] === "GET"){
        checkSessionAdmin();
        global $conn;

        $stm = $conn->prepare("SELECT username, isCreator, isAdmin FROM USERS WHERE id_user != :id");
        $stm->execute(array(":id" => $_SESSION["id"]));
        $users= $stm->fetchAll();
        
        $arrayUsers = [];
        foreach($users as $user){
            $arrayUsers[] = array("username" => $user["username"], "isCreator" => $user["isCreator"], "isAdmin" => $user["isAdmin"]);
        }
        echo json_encode(array("code" => "ok","users" => $arrayUsers));
    }

    if($_SERVER["REQUEST_METHOD"] === "POST"){
        echo json_encode(array("message" => "does not support POST Petition"));
    }
    
    if($_SERVER["REQUEST_METHOD"] === "PUT"){
        checkSessionAdmin();
        global $conn;

        $data = json_decode(file_get_contents("php://input"), true);

        if(isset($data["newUsername"])){
            if(!isset($data["username"])){
                echo json_encode(array("code" => "error", "message" => "Missing parameters"));
                return;
            }
            validateField($data["username"]);
            validateField($data["newUsername"]);

            $old = $data["username"];
            $new = trim($data["newUsername"]);
            if($new === ""){
                echo json_encode(array("code" => "error", "message" => "Invalid new username"));
                return;
            }
            if($new === $old){
                echo json_encode(array("code" => "ok", "message" => "No change"));
                return;
            }
            $check = $conn->prepare("SELECT COUNT(*) FROM USERS WHERE username = ?");
            $check->execute([$new]);
            if($check->fetchColumn() > 0){
                echo json_encode(array("code" => "error", "message" => "Username already exists"));
                return;
            }
            $stm = $conn->prepare("UPDATE USERS SET username = ? WHERE username = ?");
            $stm->execute([$new, $old]);
            echo json_encode(array("code" => "ok", "message" => "Username updated"));
            return;
        }

        if(isset($data["isCreator"])){
            if(!isset($data["username"])){
                echo json_encode(array("code" => "error", "message" => "Missing parameters"));
                return;
            }
            validateField($data["username"]);
            validateBoolean($data["isCreator"]);

            $stm = $conn->prepare("UPDATE USERS SET isCreator = ? WHERE username = ?");
            $stm->execute([(int)$data["isCreator"], $data["username"]]);
            echo json_encode(array("code" => "ok", "message" => "User updated"));
            return;
        }

        if(isset($data["isAdmin"])){
            if(!isset($data["username"])){
                echo json_encode(array("code" => "error", "message" => "Missing parameters"));
                return;
            }
            validateField($data["username"]);
            validateBoolean($data["isAdmin"]);

            $stm = $conn->prepare("UPDATE USERS SET isAdmin = ? WHERE username = ?");
            $stm->execute([(int)$data["isAdmin"], $data["username"]]);
            echo json_encode(array("code" => "ok", "message" => "User updated"));
            return;
        }

        echo json_encode(array("code" => "error", "message" => "Missing parameters"));
    }

    if($_SERVER["REQUEST_METHOD"] === "DELETE"){
        checkSessionAdmin();
        global $conn;

        $data = json_decode(file_get_contents("php://input"), true);

        validateField($data["username"]);

        $stm = $conn->prepare("DELETE FROM USERS WHERE username = :user");
        $stm->execute(array(":user" => $data["username"]));

        deleteSession();

        sendMsg("User deleted succsesfully");
    }
?>