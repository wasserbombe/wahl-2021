<?php
    include __DIR__.'/../config/config.php';

    $res = array(
        "data" => array(),
        "code" => 400,
        "error" => array(),
        "request" => $_REQUEST
    );

    function cachedDBQuery($sql){
        global $DB_TWT; 

        $cache_fn = __DIR__.'/cache/stats_sql_'.md5($sql).'.json'; 
        if (file_exists($cache_fn) && filemtime($cache_fn) > time()-60*60*1){
            $raw = file_get_contents($cache_fn); 
            $res = json_decode($raw, true); 
        } else {
            $res = $DB_TWT->query($sql)->fetchAll(); 
            foreach ($res as $r => $row){
                foreach ($row as $i => $val){
                    if (is_numeric($val)) $res[$r][$i] = doubleval($val); 
                }
            }
            file_put_contents($cache_fn, json_encode($res)); 
        }

        return $res; 
    }

    if (isset($_REQUEST["view"]) && !empty($_REQUEST["view"])){
        if ($_REQUEST["view"] == "tweets-per-day"){
            $data = cachedDBQuery("SELECT 
                                        LEFT(t.`created_at`, 10) AS 'date',
                                        COUNT(*) AS 'tweets',
                                        COUNT(DISTINCT t.`user_id`) AS 'users',
                                        SUM(u.`followers_count`) AS 'reach'
                                    FROM tweets t
                                    JOIN users_latest ul ON t.`user_id` = ul.`user_id`
                                    JOIN users u ON ul.`user_id` = u.`id` AND ul.`date` = u.`date`
                                    WHERE t.`created_at` > '2021-07-01'
                                    GROUP BY LEFT(t.`created_at`, 10);");
            $res["data"] = $data; 
            $res["code"] = 200; 
        } elseif ($_REQUEST["view"] == "tweets-per-hour"){
            $data = cachedDBQuery("SELECT 
                                        LEFT(t.`created_at`, 13) AS 'hour',
                                        COUNT(*) AS 'tweets',
                                        COUNT(DISTINCT t.`user_id`) AS 'users',
                                        SUM(u.`followers_count`) AS 'reach'
                                    FROM tweets t
                                    JOIN users_latest ul ON t.`user_id` = ul.`user_id`
                                    JOIN users u ON ul.`user_id` = u.`id` AND ul.`date` = u.`date`
                                    WHERE t.`created_at` > '2021-07-01'
                                    GROUP BY LEFT(t.`created_at`, 13);");
            $res["data"] = $data; 
            $res["code"] = 200; 
        } elseif ($_REQUEST["view"] == "current-hashtags"){
            $data = cachedDBQuery("SELECT 
                                        ht.`hashtag`, 
                                        COUNT(*) AS 'tweets'
                                    FROM hashtag2tweet ht
                                    JOIN tweets t ON t.`id` = ht.`tweet_id`
                                    WHERE t.`created_at_ts` > UNIX_TIMESTAMP()-60*60*24*7 AND LOWER(ht.`hashtag`) NOT IN (SELECT sw.`word` FROM stopwords sw WHERE sw.`lang` = 'de')
                                    GROUP BY ht.`hashtag`
                                    ORDER BY COUNT(*) DESC;");
            $res["data"] = $data; 
            $res["code"] = 200; 
        } else {
            $res["code"] = 404; 
            $res["error"] = array("msg" => "Requested view not found.");
        }
    } else {
        $res["code"] = 400; 
        $res["error"] = array("msg" => "Parameter 'view' is required.");
    }

    http_response_code($res["code"]);
    header('Content-Type: application/json');
	header('Pragma: no-cache');
	header('Expires: Fri, 01 Jan 1990 00:00:00 GMT');
	header('Cache-Control: no-cache, no-store, must-revalidate');
	echo json_encode($res, JSON_PRETTY_PRINT);
?>