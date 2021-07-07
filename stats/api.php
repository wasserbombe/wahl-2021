<?php
    include __DIR__.'/../config/config.php';

    $res = array(
        "data" => array(),
        "code" => 400,
        "error" => array(),
        "request" => $_REQUEST
    );

    function cachedDBQuery($sql){
        global $DB_TWT, $_REQUEST; 

        $cache_fn = __DIR__.'/cache/stats_sql_'.md5($sql).'.json'; 
        if (!isset($_REQUEST["forceLive"]) && file_exists($cache_fn) && filemtime($cache_fn) > time()-60*30){
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
        } elseif ($_REQUEST["view"] == "tweets-per-hour-per-search"){
            $data = cachedDBQuery("SELECT 
                                        LEFT(t.`created_at`, 13) AS 'hour',
                                        s.`id` as 'id',
                                        s.`q` as 'name',
                                        COUNT(*) AS 'tweets'
                                    FROM tweets t
                                    JOIN users_latest ul ON t.`user_id` = ul.`user_id`
                                    JOIN users u ON ul.`user_id` = u.`id` AND ul.`date` = u.`date`
                                    JOIN searches_tweets st ON t.`id` = st.`tweet_id`
                                    JOIN searches s ON s.`id` = st.`search_id`
                                    WHERE t.`created_at` > '2021-07-01' AND s.type = 'search'
                                    GROUP BY LEFT(t.`created_at`, 13), s.`id`;");
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
        } elseif ($_REQUEST["view"] == "current-hashtags-by-party"){
            if (isset($_REQUEST["party"]) && !empty($_REQUEST["party"])){
                $data = cachedDBQuery("SELECT 
                                            ht.`hashtag`, 
                                            COUNT(*) AS 'tweets'
                                        FROM hashtag2tweet ht
                                        JOIN tweets t ON t.`id` = ht.`tweet_id`
                                        JOIN searches_tweets st ON t.`id` = st.`tweet_id`
                                        JOIN searches s ON s.`id` = st.`search_id`
                                        WHERE s.`party` = ".$DB_TWT->prep($_REQUEST["party"])." AND t.`created_at_ts` > UNIX_TIMESTAMP()-60*60*24*7 AND LOWER(ht.`hashtag`) NOT IN (SELECT sw.`word` FROM stopwords sw WHERE sw.`lang` = 'de')
                                        GROUP BY ht.`hashtag`
                                        ORDER BY COUNT(*) DESC;");
                $res["data"] = $data; 
                $res["code"] = 200; 
            }  else {
                $res["code"] = 400; 
                $res["error"] = array("msg" => "Parameter 'party' is required.");
            }
        } elseif ($_REQUEST["view"] == "current-hashtags-by-party-candidate"){
            if (isset($_REQUEST["party"]) && !empty($_REQUEST["party"])){
                $data = cachedDBQuery("SELECT 
                                            ht.`hashtag`, 
                                            COUNT(*) AS 'tweets'
                                        FROM hashtag2tweet ht
                                        JOIN tweets t ON t.`id` = ht.`tweet_id`
                                        JOIN searches_tweets st ON t.`id` = st.`tweet_id`
                                        JOIN searches s ON s.`id` = st.`search_id`
                                        WHERE s.`party` = ".$DB_TWT->prep($_REQUEST["party"])." AND s.type = 'account' AND s.role = 'candidate' AND t.`created_at_ts` > UNIX_TIMESTAMP()-60*60*24*7 AND LOWER(ht.`hashtag`) NOT IN (SELECT sw.`word` FROM stopwords sw WHERE sw.`lang` = 'de')
                                        GROUP BY ht.`hashtag`
                                        ORDER BY COUNT(*) DESC;");
                $res["data"] = $data; 
                $res["code"] = 200; 
            }  else {
                $res["code"] = 400; 
                $res["error"] = array("msg" => "Parameter 'party' is required.");
            }
        } elseif ($_REQUEST["view"] == "current-hashtags-by-party-account"){
            if (isset($_REQUEST["party"]) && !empty($_REQUEST["party"])){
                $data = cachedDBQuery("SELECT 
                                            ht.`hashtag`, 
                                            COUNT(*) AS 'tweets'
                                        FROM hashtag2tweet ht
                                        JOIN tweets t ON t.`id` = ht.`tweet_id`
                                        JOIN searches_tweets st ON t.`id` = st.`tweet_id`
                                        JOIN searches s ON s.`id` = st.`search_id`
                                        WHERE s.`party` = ".$DB_TWT->prep($_REQUEST["party"])." AND s.type = 'account' AND s.role = 'party' AND t.`created_at_ts` > UNIX_TIMESTAMP()-60*60*24*7 AND LOWER(ht.`hashtag`) NOT IN (SELECT sw.`word` FROM stopwords sw WHERE sw.`lang` = 'de')
                                        GROUP BY ht.`hashtag`
                                        ORDER BY COUNT(*) DESC;");
                $res["data"] = $data; 
                $res["code"] = 200; 
            }  else {
                $res["code"] = 400; 
                $res["error"] = array("msg" => "Parameter 'party' is required.");
            }
        } elseif ($_REQUEST["view"] == "hashtags-over-time"){
            $data = cachedDBQuery("SELECT IF(!ISNULL(th.topic), th.topic, t1.`hashtag`) AS 'hashtag', t1.date, SUM(IFNULL(t2.count, 0)) AS 'tweets'
                                    FROM (
                                        SELECT DISTINCT LOWER(ht.`hashtag`) AS 'hashtag', dates.date
                                        FROM (SELECT DISTINCT t.`created_at_date` AS 'date' FROM tweets t WHERE t.`created_at_date` >= '2021-07-01') dates
                                        JOIN hashtag2tweet ht 
                                        LEFT JOIN stopwords sw ON sw.`word` = LOWER(ht.`hashtag`)
                                        WHERE ISNULL(sw.`id`)
                                        ORDER BY LOWER(ht.`hashtag`), dates.date
                                    ) t1
                                    LEFT JOIN (
                                        SELECT t.`created_at_date` AS 'date', LOWER(ht.`hashtag`) AS 'hashtag', COUNT(*) AS 'count'
                                        FROM hashtag2tweet ht
                                        JOIN tweets t ON t.`id` = ht.`tweet_id`
                                        WHERE t.`created_at_date` >= '2021-07-01'
                                        GROUP BY t.`created_at_date`, LOWER(ht.`hashtag`)
                                    ) t2 ON t1.hashtag = t2.hashtag AND t1.date = t2.date
                                    LEFT JOIN topics_hashtags th ON th.`hashtag` = LOWER(t1.hashtag)
                                    WHERE t1.hashtag IN (
                                        SELECT hashtags_avg.hashtag
                                        FROM (
                                            SELECT count_lookup.hashtag, AVG(count_lookup.count) AS 'count_avg'
                                            FROM (
                                                SELECT t1.`hashtag`, t1.date, IFNULL(t2.count, 0) AS 'count'
                                                FROM (
                                                    SELECT DISTINCT LOWER(ht.`hashtag`) AS 'hashtag', dates.date
                                                    FROM (SELECT DISTINCT t.`created_at_date` AS 'date' FROM tweets t WHERE t.`created_at_date` >= '2021-07-01') dates
                                                    JOIN hashtag2tweet ht 
                                                    LEFT JOIN stopwords sw ON sw.`word` = LOWER(ht.`hashtag`)
                                                    WHERE ISNULL(sw.`id`)
                                                    ORDER BY LOWER(ht.`hashtag`), dates.date
                                                ) t1
                                                LEFT JOIN (
                                                    SELECT t.`created_at_date` AS 'date', LOWER(ht.`hashtag`) AS 'hashtag', COUNT(*) AS 'count'
                                                    FROM hashtag2tweet ht
                                                    JOIN tweets t ON t.`id` = ht.`tweet_id`
                                                    WHERE t.`created_at_date` >= '2021-07-01'
                                                    GROUP BY t.`created_at_date`, LOWER(ht.`hashtag`)
                                                ) t2 ON t1.hashtag = t2.hashtag AND t1.date = t2.date
                                            ) count_lookup
                                            GROUP BY count_lookup.hashtag
                                        ) hashtags_avg
                                        WHERE hashtags_avg.count_avg > 3
                                    )
                                    GROUP BY IF(!ISNULL(th.topic), th.topic, t1.`hashtag`), t1.date");
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