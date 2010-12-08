<?php
static $cache;

function dpl_addForum($forum) {
    global $cache;
    $term = new stdClass();
    $term->name =  phpbb_ConvertToUTF8($forum->forum_name);
    $term->description = phpbb_cleanBBCode($forum->forum_desc,$forum->uid);
    $term->weight = $forum->weight;
    if ($forum->parent_id > 0) {
        $term->parent[0] = $cache['forums'][$forum->parent_id];
    } else {
        $term->parent[0] = 0;
    }
    $term->vid = variable_get('forum_nav_vocabulary', '');
    $tid = taxonomy_get_term_by_name($term->name);
    if (!$tid) {
        taxonomy_term_save(&$term);
        $cache["forums"][$forum->forum_id] = $term->tid;
        if ($forum->subforums > 0) {
            $containers = variable_get('forum_containers', array());
            $containers[] = $tid[0];
            variable_set('forum_containers', $containers);
        }
    } else {
        $tid = array_keys($tid);
        $cache["forums"][$forum->forum_id] = $tid[0];
    }
}

function dpl_addUser($newuser) {
    global $cache;
    $newuser->name = phpbb_ConvertToUTF8($newuser->name);
    $drupalUser = user_load_by_name($newuser->name);
    if (!$drupalUser) {
        $drupalUser = new stdClass();
        $drupalUser->is_new = true;
        $userData['name'] = $newuser->name;
        $userData["mail"] = $newuser->mail;
        $userData['roles'][2] = 'authenticated user';
        $userData['created'] = $newuser->created;
        $userData['access']  = $newuser->login;
        $userData['picture'] = $newuser->picture;
        $userData['status'] = 1;
        $userData['password'] = $newuser->user_password;
        $userData['init'] = $newuser->mail;
        $drupalUser = user_save($drupalUser,$userData);
    }
    $cache['users'][$newuser->user_id] = array('uid' => $drupalUser->uid,'name' => $userData['name']);
}


function dpl_addTopic($topic) {

    global $cache;
    $object = new stdClass();
    $object->forum_tid =  $cache["forums"][$topic->forum_id];
    $object->timestamp = $topic->topic_time;
    $object->uid       = $cache['users'][$topic->topic_poster]['uid'];
    $object->name      = $cache['users'][$topic->topic_poster]['name'];
    $object->title     = phpbb_ConvertToUTF8($topic->topic_title);
    $object->type      = 'forum';
    $object->language  = 'und';
    $object->revision  = 0;
    $object->comment   = $topic->topic_status;
    $object->created   = $object->timestamp;
    $object->taxonomy_forums[$object->language][] = array('tid' => $object->forum_tid);
    $object->icon = '';
    $object->status = 1;
    $object->comment = ($topic->topic_status == 0)?2:0;  // 2 = open, 0 = closed
    $object->is_new = TRUE;
    $object->validated = TRUE;
    if (!$object->forum_tid) throw new Exception('No forum TID found for '.$object->title);
    node_save(&$object);
    return $object;
}

function dpl_addComment($post,$nodeid) {
    global $cache;
    $comment = new stdClass();

    $userid = ($cache['users'][$post->poster_id]['uid'] !="")?$cache['users'][$post->poster_id]['uid']:'-1';
    $name =   user_load($userid)->name;
    $comment->pid = NULL;
    $comment->cid = NULL;
    $comment->uid = $userid;
    $comment->name    = $name;
    $comment->nid = $nodeid;

    $comment->validated = true;
    $comment->subject = phpbb_ConvertToUTF8( $post->post_subject);
    $comment->node_type = 'comment_node_forum';
    $comment->language  = 'und';
    $comment->hostname = $post->poster_ip;
    $comment->created = $post->post_time;
    $comment->status  = COMMENT_PUBLISHED;
    $comment->is_anonymous = FALSE;
    $comment->op = 'Save';
    $comment->comment_body[$comment->language][] = array('value' => phpbb_cleanBBCode($post->post_text,$post->bbcode_uid),
            'format' => DRUPAL_FORMAT);
    comment_save(&$comment);
    db_update('comment')->fields(array('hostname'=>$post->poster_ip))->condition('cid', $comment->cid)->execute();
}


?>
