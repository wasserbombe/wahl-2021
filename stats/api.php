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
                                    WHERE t.`created_at` > '2021-07-01' AND s.type = 'search' AND t.`lang` != 'pt'
                                    GROUP BY LEFT(t.`created_at`, 13), s.`id`
                                    ORDER BY LEFT(t.`created_at`, 13), s.`id`;");
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
        } elseif ($_REQUEST["view"] == "current-domains-by-party"){
            if (isset($_REQUEST["party"]) && !empty($_REQUEST["party"])){
                $data = cachedDBQuery("SELECT 
                                            REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(u.`expanded_url`, '/', 3), '://', -1), '/', 1), '?', 1), 'www.', '') AS domain,
                                            COUNT(*) AS 'tweets'
                                        FROM urls u
                                        JOIN urls_tweets ut ON ut.`url` = u.`url`
                                        JOIN tweets t ON t.`id` = ut.`tweet_id`
                                        JOIN searches_tweets st ON t.`id` = st.`tweet_id`
                                        JOIN searches s ON s.`id` = st.`search_id`
                                        WHERE s.`party` = ".$DB_TWT->prep($_REQUEST["party"])." AND t.`created_at_ts` > UNIX_TIMESTAMP()-60*60*24*7 AND REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(u.`expanded_url`, '/', 3), '://', -1), '/', 1), '?', 1), 'www.', '') NOT IN ('twitter.com','mesonet.agron.iastate.edu')
                                        GROUP BY REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(u.`expanded_url`, '/', 3), '://', -1), '/', 1), '?', 1), 'www.', '')
                                        ORDER BY COUNT(*) DESC;");
                $res["data"] = $data; 
                $res["code"] = 200; 
            }  else {
                $res["code"] = 400; 
                $res["error"] = array("msg" => "Parameter 'party' is required.");
            }
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
                                        -- ... not in stopwords
                                        LEFT JOIN stopwords sw ON sw.`word` = LOWER(ht.`hashtag`)
                                        WHERE ISNULL(sw.`id`)
                                        ORDER BY LOWER(ht.`hashtag`), dates.date
                                    ) t1
                                    LEFT JOIN (
                                        -- counts per hashtag and date
                                        SELECT t.`created_at_date` AS 'date', LOWER(ht.`hashtag`) AS 'hashtag', COUNT(*) AS 'count'
                                        FROM hashtag2tweet ht
                                        JOIN tweets t ON t.`id` = ht.`tweet_id`
                                        WHERE t.`created_at_date` >= '2021-07-01'
                                        GROUP BY t.`created_at_date`, LOWER(ht.`hashtag`)
                                    ) t2 ON t1.hashtag = t2.hashtag AND t1.date = t2.date
                                    -- if we have a topic for hashtag, use it instead
                                    LEFT JOIN topics_hashtags th ON th.`hashtag` = LOWER(t1.hashtag)
                                    -- only relevant ones
                                    WHERE t1.hashtag IN (
                                        -- filter hashtags based on average count
                                        SELECT hashtags_avg.hashtag
                                        FROM (
                                            -- calc count AVG for hashtags
                                            SELECT count_lookup.hashtag, AVG(count_lookup.count) AS 'count_avg'
                                            FROM (
                                                SELECT t1.`hashtag`, t1.date, IFNULL(t2.count, 0) AS 'count'
                                                FROM (
                                                    -- all hashtags, all days since 2021-07-01 combined
                                                    SELECT DISTINCT LOWER(ht.`hashtag`) AS 'hashtag', dates.date
                                                    FROM (SELECT DISTINCT t.`created_at_date` AS 'date' FROM tweets t WHERE t.`created_at_date` >= '2021-07-01') dates
                                                    JOIN hashtag2tweet ht 
                                                    LEFT JOIN stopwords sw ON sw.`word` = LOWER(ht.`hashtag`)
                                                    WHERE ISNULL(sw.`id`)
                                                    ORDER BY LOWER(ht.`hashtag`), dates.date
                                                ) t1
                                                LEFT JOIN (
                                                    -- counts per hashtag
                                                    SELECT t.`created_at_date` AS 'date', LOWER(ht.`hashtag`) AS 'hashtag', COUNT(*) AS 'count'
                                                    FROM hashtag2tweet ht
                                                    JOIN tweets t ON t.`id` = ht.`tweet_id`
                                                    WHERE t.`created_at_date` >= '2021-07-01' AND t.`lang` != 'pt'
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
        } elseif ($_REQUEST["view"] == "nodegraph-test"){
            $data = cachedDBQuery("SELECT *
            FROM (
                SELECT IFNULL(th1.`topic`, LOWER(ht1.`hashtag`)) AS 'topic_from', ht2.topic AS 'topic_to', COUNT(*) AS 'count'
                FROM hashtag2tweet ht1
                LEFT JOIN topics_hashtags th1 ON LOWER(ht1.`hashtag`) = th1.`hashtag`
                JOIN (
                    SELECT ht2.`tweet_id`, IFNULL(th2.`topic`, LOWER(ht2.`hashtag`)) AS 'topic'
                    FROM hashtag2tweet ht2
                    LEFT JOIN topics_hashtags th2 ON LOWER(ht2.`hashtag`) = th2.`hashtag`
                ) ht2 ON ht2.topic != IFNULL(th1.`topic`, LOWER(ht1.`hashtag`)) AND ht2.tweet_id = ht1.`tweet_id`
                GROUP BY IFNULL(th1.`topic`, LOWER(ht1.`hashtag`)), ht2.topic
            ) t1 
            WHERE t1.count > 5");
            $existing_nodes = array(); 
            $existing_links = array(); 
            $graphdata = array(
                "nodes" => array(),
                "links" => array()
            );
            foreach ($data as $row){
                if (!in_array($row["topic_from"], $existing_nodes)){
                    $existing_nodes[] = $row["topic_from"];
                    $graphdata["nodes"][] = array("id" => $row["topic_from"], "group" => 1);
                }
                if (!in_array($row["topic_to"], $existing_nodes)){
                    $existing_nodes[] = $row["topic_to"];
                    $graphdata["nodes"][] = array("id" => $row["topic_to"], "group" => 1);
                }
                $linkid = $row["topic_from"]."-".$row["topic_to"]; 
                $linkidAWR = $row["topic_to"]."-".$row["topic_from"]; 
                if (isset($existing_links[$linkidAWR])){
                    $existing_links[$linkidAWR]["value"] += $row["count"];
                } else {
                    $existing_links[$linkid] = array("source" => $row["topic_from"], "target" => $row["topic_to"], "value" => $row["count"]);
                }
            }
            foreach ($existing_links as $link){
                if ($link["value"] > 25)
                    $graphdata["links"][] = $link;
            }
            $res["data"] = $graphdata;
            $res["code"] = 200; 
        } elseif ($_REQUEST["view"] == "nodegraph-test2"){
            $data = cachedDBQuery("SELECT *
            FROM (
                SELECT IFNULL(th1.`topic`, LOWER(ht1.`hashtag`)) AS 'topic_from', ht2.topic AS 'topic_to', COUNT(*) AS 'count'
                FROM hashtag2tweet ht1
                LEFT JOIN topics_hashtags th1 ON LOWER(ht1.`hashtag`) = th1.`hashtag`
                JOIN (
                    SELECT ht2.`tweet_id`, IFNULL(th2.`topic`, LOWER(ht2.`hashtag`)) AS 'topic'
                    FROM hashtag2tweet ht2
                    LEFT JOIN topics_hashtags th2 ON LOWER(ht2.`hashtag`) = th2.`hashtag`
                ) ht2 ON ht2.topic != IFNULL(th1.`topic`, LOWER(ht1.`hashtag`)) AND ht2.tweet_id = ht1.`tweet_id`
                GROUP BY IFNULL(th1.`topic`, LOWER(ht1.`hashtag`)), ht2.topic
            ) t1 
            WHERE t1.count > 25");
            $graphdata = []; 
            foreach ($data as $row){
                $graphdata[] = array($row["topic_from"], $row["topic_to"]);
            }
            /*$existing_nodes = array(); 
            $existing_links = array(); 
            $graphdata = array(
                "nodes" => array(),
                "links" => array()
            );
            foreach ($data as $row){
                if (!in_array($row["topic_from"], $existing_nodes)){
                    $existing_nodes[] = $row["topic_from"];
                    $graphdata["nodes"][] = array("id" => $row["topic_from"], "group" => 1);
                }
                if (!in_array($row["topic_to"], $existing_nodes)){
                    $existing_nodes[] = $row["topic_to"];
                    $graphdata["nodes"][] = array("id" => $row["topic_to"], "group" => 1);
                }
                $linkid = $row["topic_from"]."-".$row["topic_to"]; 
                $linkidAWR = $row["topic_to"]."-".$row["topic_from"]; 
                if (isset($existing_links[$linkidAWR])){
                    $existing_links[$linkidAWR]["value"] += $row["count"];
                } else {
                    $existing_links[$linkid] = array("source" => $row["topic_from"], "target" => $row["topic_to"], "value" => $row["count"]);
                }
            }
            foreach ($existing_links as $link){
                if ($link["value"] > 25)
                    $graphdata["links"][] = $link;
            }*/
            $res["data"] = $graphdata;
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
	echo json_encode($res);
?>