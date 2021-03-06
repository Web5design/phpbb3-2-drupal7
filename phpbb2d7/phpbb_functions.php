<?php
/* helper functions for phpBB conversion */


function phpbb_cleanBBCode($text,$bbuid=null) {

    // remove the bb UID thingee
    if (!is_null($bbuid)) {
        $text = preg_replace("/:$bbuid/", '', $text);
    } else {
        throw new Exception("No bbcode_uid found!");
    }

    // straight from phpBB code itself
    $match = array(
            '#<!\-\- e \-\-><a href="mailto:(.*?)">.*?</a><!\-\- e \-\->#',
            '#<!\-\- l \-\-><a (?:class="[\w-]+" )?href="(.*?)(?:(&amp;|\?)sid=[0-9a-f]{32})?">.*?</a><!\-\- l \-\->#',
            '#<!\-\- ([mw]) \-\-><a (?:class="[\w-]+" )?href="(.*?)">.*?</a><!\-\- \1 \-\->#',
            '#<!\-\- s(.*?) \-\-><img src="\{SMILIES_PATH\}\/.*? \/><!\-\- s\1 \-\->#',
            '#<!\-\- .*? \-\->#s',
            '#<.*?>#s',
    );
    $replace = array('$1', '$1', '$2', '$1', '', '');
    $text = preg_replace($match, $replace, $text);

    $text = phpbb_ConvertToUTF8($text);
    return $text;
}

function phpbb_ConvertToUTF8($text) {
    return utf8_encode(html_entity_decode($text, ENT_QUOTES, 'utf-8'));
}

function convertForumById($forumID,&$bar) {
    $forumList = phpbb_DB::getDB()->phpBB_getForumsByParent($forumID);
    $weight = 10;
    foreach ($forumList as $forum) {
        $forum->weight = $weight;
        $bar->message ='Writing ' .$forum->forum_name. ' '.$forum->forum_desc;
        $bar->next();
        dpl_addForum($forum);
        $weight += 10;
        if($forum->subforums > 0) {
            convertForumById($forum->forum_id, $bar);
        }
    }
}

function convertTopic($topic,$counter,$topicCount) {
    $node = dpl_addTopic($topic);
    $commentCount = phpbb_DB::getDB()->Count('phpbb_posts','topic_id = '.$topic->topic_id);
    $bar = new progressbar();
    $bar->start($commentCount);
    $first = true;
    for ($commentloop=0;$commentloop<$commentCount;$commentloop+=STEPSIZE) {
        $posts = phpbb_DB::getDB()->phpBB_getPostsByTopic($topic,$commentloop);
        foreach($posts as $post) {
            if ($first) {
                $node->is_new = false;
                $node->body[$node->language][] = array(
                        'summary' => '','value' => phpbb_cleanBBCode($post->post_text,$post->bbcode_uid),
                        'format'=>DRUPAL_FORMAT);
                node_save($node);
                $first = false;
            } else {
                dpl_addComment($post,$node->nid);
            }
            $bar->message("adding topic $counter of $topicCount nid ".$node->nid);
            $bar->next();
        }
     
    }
    $bar->finish();

}
?>
