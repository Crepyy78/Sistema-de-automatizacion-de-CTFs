<?php
    require_once("cors.php");
    require_once("lib/lib.php");
    require_once("lib/sessions.php");

    if($_SERVER["REQUEST_METHOD"] === "GET"){
        echo json_encode(array("message" => "does not support GET Petition"));
    }

    if($_SERVER["REQUEST_METHOD"] === "POST"){
        validateIfPostFieldExists('username');
        validateIfPostFieldExists('password');
        $username =  $_POST['username'];
        $password = $_POST['password'];
        validateField($username);
        validateField($password);

        checkIfUserExists($username);

        global $conn;
        $stm = $conn->prepare("SELECT id_teams FROM TEAMS WHERE name = :name LIMIT 1");
        $stm->execute(array(":name" => $username));
        if(!empty($stm->fetchAll())){
            sendError("Username not available");
        }

        $conn->beginTransaction();
        try {
            $userId = storeUser($username,$password);

            $stm = $conn->prepare("INSERT INTO TEAMS (name, description, leader, isPersonal) VALUES (:name, :description, :leader, TRUE)");
            $stm->execute(array(":name" => $username, ":description" => "", ":leader" => $userId));
            $teamId = $conn->lastInsertId();

            $stm = $conn->prepare("INSERT INTO USERS_TEAMS (id_teams, id_user) VALUES (:idTeams, :idUser)");
            $stm->execute(array(":idTeams" => $teamId, ":idUser" => $userId));

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            sendError($e->getMessage());
        }

        $totp = null;
        if(isset($_POST["totp"]) && $_POST["totp"] === "y"){
            //Generar Clave de 40, guardarlo y mandarlo
            global $conn;
            storeTOTPSecret($username);
            $stm = $conn->prepare("SELECT totpSecret FROM USERS WHERE username = :username LIMIT 1");
            $stm->execute(array(":username" => $username));
            $secretTOTP = $stm->fetchAll();
            if(!empty($secretTOTP)){
                $totp = "otpauth://totp/C0nclave:".$username."?secret=" .custom_base32_encode(hex2bin($secretTOTP[0][0]))."&algorithm=SHA1&digits=6&period=30&issuer=C0nclave&lock=false";      
            }
        }

        $arrayToSend = array("code" => "ok");
        if($totp != null){
            $arrayToSend["KeyURI"] = $totp;
        }
        echo json_encode($arrayToSend);
    }

    if($_SERVER["REQUEST_METHOD"] === "PUT"){
        checkSession();
        global $conn;
        $id = $_SESSION["id"];
        $data = json_decode(file_get_contents("php://input"), true);
        if(isset($data["newUsername"])){
            validateField($data["newUsername"]);

            $new = trim($data["newUsername"]);
            if($new === ""){
                echo json_encode(array("code" => "error", "message" => "Invalid new username"));
                return;
            }

            $check = $conn->prepare("SELECT COUNT(*) FROM USERS WHERE username = ?");
            $check->execute([$new]);
            if($check->fetchColumn() > 0){
                sendError("Username already exists");
            }

            $stm = $conn->prepare("SELECT id_teams FROM TEAMS WHERE leader = :id AND isPersonal = TRUE LIMIT 1");
            $stm->execute([":id" => $id]);
            $personalTeam = $stm->fetchAll();
            $personalTeamId = !empty($personalTeam) ? $personalTeam[0]["id_teams"] : null;

            if($personalTeamId !== null){
                $checkTeam = $conn->prepare("SELECT COUNT(*) FROM TEAMS WHERE name = ? AND id_teams != ?");
                $checkTeam->execute([$new, $personalTeamId]);
            } else {
                $checkTeam = $conn->prepare("SELECT COUNT(*) FROM TEAMS WHERE name = ?");
                $checkTeam->execute([$new]);
            }
            if($checkTeam->fetchColumn() > 0){
                sendError("Username already exists");
            }

            $conn->beginTransaction();
            try {
                $stm = $conn->prepare("UPDATE USERS SET username = ? WHERE id_user = ?");
                $stm->execute([$new, $id]);

                if($personalTeamId !== null){
                    $stm = $conn->prepare("UPDATE TEAMS SET name = ? WHERE id_teams = ?");
                    $stm->execute([$new, $personalTeamId]);
                }

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollBack();
                sendError($e->getMessage());
            }

            $_SESSION["username"] = $new;
            sendMsg("Username updated");
        }

        if(isset($data["password"])){
            validateField($data["password"]);

            $password = $data["password"]; 
            [$password,$salt] = hasingPassword($password);

            $stm = $conn->prepare("UPDATE USERS SET password = ?, salt = ? WHERE id_user = ?");
            $stm->execute([$password, $salt, $id]);
            sendMsg("Password updated");
        }

        if(isset($data["totp"]) && $data["totp"] === "y"){
            storeTOTPSecret($_SESSION["username"]);
            $stm = $conn->prepare("SELECT totpSecret FROM USERS WHERE id_user = :id LIMIT 1");
            $stm->execute(array(":id" => $id));
            $secretTOTP = $stm->fetchAll();
            if(!empty($secretTOTP)){
                $totp = "otpauth://totp/C0nclave:".$_SESSION["username"]."?secret=" .custom_base32_encode(hex2bin($secretTOTP[0][0]))."&algorithm=SHA1&digits=6&period=30&issuer=C0nclave&lock=false";      
            }
                    
            $arrayToSend = array("code" => "ok");
            $arrayToSend["KeyURI"] = $totp;
            echo json_encode($arrayToSend);
            die();
        }

        sendError("Missing parameters");
    }


    if($_SERVER["REQUEST_METHOD"] === "DELETE"){
        checkSession();
        global $conn;

        $id = $_SESSION["id"];

        $stm = $conn->prepare("DELETE FROM TEAMS WHERE leader = :id AND isPersonal = TRUE");
        $stm->execute(array(":id" => $id));

        $stm = $conn->prepare("DELETE FROM USERS WHERE id_user = :id");
        $stm->execute(array(":id" => $id));

        deleteSession();

        echo json_encode(array("code" => "ok", "message" => "User deleted succsesfully"));
    }
?>