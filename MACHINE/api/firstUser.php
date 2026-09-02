<?php
require_once("/var/www/html/lib/lib.php");

global $conn;
$username="admin";
$password="admin";
[$password,$salt] = hasingPassword($password);

$conn->beginTransaction();
try {
    $stm = $conn->prepare("INSERT INTO USERS (username,password,salt,isCreator,isAdmin) VALUES (:username,:password,:salt,TRUE,TRUE)");
    $stm->execute(array(":username" => $username, ":password" => $password, ":salt" => $salt));
    $userId = $conn->lastInsertId();

    $stm = $conn->prepare("INSERT INTO TEAMS (name, description, leader, isPersonal) VALUES (:name, :description, :leader, TRUE)");
    $stm->execute(array(":name" => $username, ":description" => "", ":leader" => $userId));
    $teamId = $conn->lastInsertId();

    $stm = $conn->prepare("INSERT INTO USERS_TEAMS (id_teams, id_user) VALUES (:idTeams, :idUser)");
    $stm->execute(array(":idTeams" => $teamId, ":idUser" => $userId));

    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
}

?>