<?php
    define("TIMERESET",30);
    define("CUANTOS_DIGITOS_NUMERO_TOTP",6);

    function getTOTPNumbers($secret){//Aletorio y en la db y de 40 caracteres (sha1 len result)
        $times = array(str_pad(dechex(floor(time()/TIMERESET)),16, "0",STR_PAD_LEFT),str_pad(dechex(floor(time()/TIMERESET) - 1),16, "0",STR_PAD_LEFT), str_pad(dechex(floor(time()/TIMERESET) + 1),16, "0",STR_PAD_LEFT));
    
        $encryptedTimes = [];
        foreach($times as $time){
            $encryptedTimes[] = hash_hmac("sha1",hex2bin($time), hex2bin($secret), true); //len de 20
        }

        $codesGenerated = [];
        foreach($encryptedTimes as $encryptedTime){
            $offset = $encryptedTime[strlen($encryptedTime) - 1];
            $offset =  ord($offset) & 0xf; //numero de los ultimos 4 bits (entre 0 y 15 (15 + 4 = 19 no se sale))

            $dynamicTruncation =  ((ord($encryptedTime[$offset]) & 0x7f) << 24) | ((ord($encryptedTime[$offset + 1]) & 0xff) << 16) | ((ord($encryptedTime[$offset + 2]) & 0xff) << 8) | (ord($encryptedTime[$offset + 3]) & 0xff);
            $codesGenerated[] =  str_pad($dynamicTruncation % 10**CUANTOS_DIGITOS_NUMERO_TOTP,"0",STR_PAD_LEFT);
        }

        return $codesGenerated;
    }
?>