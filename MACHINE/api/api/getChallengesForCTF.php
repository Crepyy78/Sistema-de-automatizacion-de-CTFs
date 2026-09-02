<?php
    require_once("lib/sessions.php");
    require_once("lib/lib.php");
    require_once("cors.php");
    
    if($_SERVER["REQUEST_METHOD"] === "GET"){
        checkSessionCreator();

        global $conn;
        $stm = $conn->prepare("SELECT name,description,category,points,difficulty,author FROM CHALLENGES");
        $stm->execute();
        $allChallenges = $stm->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(array("code" => "ok", "challenges" => $allChallenges));
    }

    if($_SERVER["REQUEST_METHOD"] === "POST"){ //Estrictamente deberia de ir en get, pero por facilidad se hace en POST
        checkSessionCreator();
        
        $challenges = json_decode(file_get_contents('php://input'), true);
        checkPostJson($challenges);
        validateField($challenges["type"]);
        
        if($challenges["type"] !== "simple" and $challenges["type"] !== "complex" and $challenges["type"] !== "selected"){
            echo json_encode(array("code" => "error","message" => "error requesting challenges"));
            die();
        }

        global $conn;
        if($challenges["type"] === "simple"){
            validateInteger($challenges["challenges"]);

            $numberOfChallenges = $challenges["challenges"];
            checkNumberChallenges($numberOfChallenges);
            $stm = $conn->prepare("SELECT name,description,category,points,difficulty,author FROM CHALLENGES");
            $stm->execute();
            $allChallenges = $stm->fetchAll(PDO::FETCH_ASSOC);

            $returnChallenges = chooseRandomChallenges($numberOfChallenges,count($allChallenges),$allChallenges);
            
            echo json_encode(array("code" => "ok", "challenges" => $returnChallenges));

        }elseif($challenges["type"] === "complex"){
            validateArray($challenges["challenges"]);
            
            $stm = $conn->prepare("SELECT DISTINCT category FROM CHALLENGES");
            $stm->execute();
            $validCategories = $stm->fetchAll(PDO::FETCH_ASSOC);
            $validCategories = array_column($validCategories, "category");

            $returnChallenges = array();
            foreach($challenges["challenges"] as $category){
                validateArray($category);
                validateField($category[0]);
                validateInteger($category[1]);

                $categoryName = $category[0];
                $numberOfChallenges = $category[1];
                checkNumberChallenges($numberOfChallenges);

                if(!in_array($categoryName,$validCategories,true)){
                    echo json_encode(array("code" => "error","message" => "Category not found"));
                    die();
                }

                $stm = $conn->prepare("SELECT name,description,category,points,difficulty,author FROM CHALLENGES WHERE category = :cat");
                $stm->execute(array(":cat" => $categoryName));
                $allChallengesCategory = $stm->fetchAll(PDO::FETCH_ASSOC);

                $returnChallenges["$categoryName"] = chooseRandomChallenges($numberOfChallenges,count($allChallengesCategory),$allChallengesCategory);

            }

            echo json_encode(array("code" => "ok", "challenges" => $returnChallenges));

        }elseif($challenges["type"] === "selected"){
            validateArray($challenges["challenges"]);
            foreach($challenges["challenges"] as $challengesNames){
                validateField($challengesNames);
            }
            $in  = str_repeat('?,', count($challenges["challenges"]) - 1) . '?';
            $sql = "SELECT name,description,category,points,difficulty,author FROM CHALLENGES WHERE name IN ($in)";

            $stm = $conn->prepare($sql);
            $stm->execute($challenges["challenges"]);
            $challenges = $stm->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(array("code" => "ok", "challenges" => $challenges));
        }
    }

    if($_SERVER["REQUEST_METHOD"] === "PUT"){
        echo json_encode(array("message" => "does not support PUT Petition"));
    }

    if($_SERVER["REQUEST_METHOD"] === "DELETE"){   
        echo json_encode(array("message" => "does not support DELETE Petition"));
    }

    function checkPostJson($challenges){
        emptyJson($challenges);
        emptyField($challenges,"type");
        emptyField($challenges,"challenges");
    }

    function checkNumberChallenges($numChallenge){             
        if($numChallenge <= 0){
            echo json_encode(array("code" => "error","message" => "You should request some challenges..."));
            die();
        }
    }

    function chooseRandomChallenges($numberUser,$numberDB,$allChallengesCategory){
        if($numberUser > $numberDB){
            $numberUser = $numberDB;
        }

        $randomNumbersSequence = range(0,$numberDB);
        shuffle($allChallengesCategory);
        for($challenge = 0; $challenge < $numberUser;$challenge = $challenge + 1){
            $returnChallenges[]  = $allChallengesCategory[$randomNumbersSequence[$challenge]];
        }

        return $returnChallenges;
    }
?>