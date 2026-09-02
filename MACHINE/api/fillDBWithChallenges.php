<?php
    require_once("/var/www/html/conn.php");

    exec("ls /var/www/data/challenges",$challenges);
    global $conn;
    foreach($challenges as $challenge){
        $infoChallenge = [];
        exec("cat /var/www/data/challenges/".$challenge."/info/info.md",$infoChallenge);
        $name = $challenge;
        $description = "";
        $i = 2;
        while(!str_starts_with($infoChallenge[$i],"Level: ")){
            $description = $description.$infoChallenge[$i]."\n";
            $i += 1;
        }
        $difficulty = str_replace("Level: ","",$infoChallenge[$i]);
        $category = str_replace("# ","",$infoChallenge[$i + 2]);
        $author = str_replace("By: @","",$infoChallenge[$i + 4]);

        $stm = $conn->prepare("INSERT INTO CHALLENGES (name,description,category,points,difficulty,author) VALUES (:name,:desc,:cat,0,:diff,:auth)");
        $stm->execute(array(":name" => $name, ":desc" => $description, ":cat" => $category, ":diff" => $difficulty, ":auth" => $author));
    }
?>