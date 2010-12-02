<?php

class phpbb_DB {
    private static $mysql;
    private static $instance = false;


    public static function getDB() {
        if(!self::$instance) {
            self::$instance = new phpbb_DB();
        }
        return self::$instance;
    }

    public function __construct() {
        if (!$this->mysql = new mysqli(PHPBB_DB_host,PHPBB_DB_username,PHPBB_DB_password,PHPBB_DB_database)) {
            throw new Exception('No SQL connection');
        } elseif(mysqli_connect_errno()) {
            throw new Exception('Wrong SQL creditials?');
        }
    }

    public function getRow($query) {
        if (!$result = $this->mysql->query($query)) {
            throw new Exception($this->mysql->error);
        } else {
            return $result->fetch_object();
        }
    }


    public function getRows($query) {
        if (!$result = $this->mysql->query($query)) {
            throw new Exception($this->mysql->error);
        } else {
            $return = array();
            while($obj = $result->fetch_object()) {
                $return[] = $obj;
            }
            if (count($return)>0) {
                return $return;
            }
        }
    }

    public function count($table,$where=NULL) {
        $qry = "SELECT count(*) as counter FROM {$table}";
        if (!is_null($where)) $qry .= " WHERE {$where}";
        return $this->getRow($qry)->counter;
    }

    public function phpbb_getActiveUsers($start,$end) {
        $qry = "SELECT u.username as name,u.user_email as mail,u.user_id,u.user_regdate as created,
                   u.user_lastvisit as login, u.user_avatar as picture
            FROM phpbb_users u
            WHERE u.user_id NOT IN ( SELECT ban_userid FROM phpbb_banlist )
            AND user_type in(0,3)
            AND u.user_lastvisit >= 0
            ORDER BY user_id
            LIMIT $start,$end";
        return $this->getRows($qry);
    }

    public function phpBB_getForumsByParent($parent) {
        $qry = "SELECT forum_id, forum_name, forum_desc, parent_id, forum_desc_uid as uid,
                    (SELECT count(*) FROM phpbb_forums AS sf WHERE sf.parent_id = phpbb_forums.forum_id) AS subforums
                     FROM phpbb_forums
                     WHERE phpbb_forums.parent_id = {$parent}
            ORDER BY (left_id)";
        return $this->getRows($qry);
    }

    public function phpBB_getTopics($start,$end) { // poster 1 = anonymous, meestal deleted, en  moved_id > 0 is verplaatst topicje
        $qry = "SELECT topic_title,topic_poster,topic_time,phpbb_topics.forum_id,topic_status,phpbb_topics.topic_id,
                   topic_first_poster_name as username
            FROM phpbb_topics
            WHERE topic_poster > 1 AND topic_moved_id =0 ORDER BY phpbb_topics.topic_id
            LIMIT $start,$end";
        return $this->getRows($qry);
    }

    public function phpBB_getPostsByTopic($topic) {
        $qry = "SELECT post_id, poster_id,poster_ip, post_time,post_edit_time bbcode_uid,post_text,post_subject
            FROM phpbb_posts
            WHERE topic_id = ".$topic->topic_id."
            ORDER BY post_time";
        return $this->getRows($qry);

    }
}
?>
