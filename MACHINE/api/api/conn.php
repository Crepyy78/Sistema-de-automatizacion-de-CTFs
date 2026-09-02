<?php
    $servername = "mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=apiDB";
    $username = "basicUser";
    $password = "LOL";
try{
    $conn = new PDO($servername, $username, $password);
  } catch (PDOException $e) {
    echo 'db conexion failed API: ' . $e->getMessage();
}
?>