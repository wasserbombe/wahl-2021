<?php
    $api_url = "https://wahl-2021.eu/stats/api.php";
    $parties = ["spd","linke","cdu","afd","gruene","fdp","csu"];
    $views = [
        'tweets-per-day' => array(),
        'tweets-per-hour' => array(),
        'current-hashtags' => array(),
        'hashtags-over-time' => array(),
        'tweets-per-hour-per-search' => array(),
        'current-hashtags-by-party' => array(
            'party' => $parties
        ),
        'current-hashtags-by-party-candidate' => array(
            'party' => $parties
        ),
        'current-hashtags-by-party-account' => array(
            'party' => $parties
        ),
        'current-domains-by-party' => array(
            'party' => $parties
        ),
    ];

    foreach ($views as $view => $config){
        if (sizeof($config)){
            foreach ($config as $param => $values){
                foreach ($values as $value){
                    file_get_contents($api_url.'?forceLive=1&view='.$view.'&'.$param."=".$value); 
                }
            }
        } else {
            file_get_contents($api_url.'?forceLive=1&view='.$view); 
        }
        
    }
?>