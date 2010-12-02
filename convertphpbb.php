<?php
/*
 * Converts data from phpBB3 into drupal 7 data
 *
 * see readme.txt for more information
 *
*/

define('USERS',  false);  # cache/convert users
define('FORUMS', TRUE);  # cache/convert forums
define('POSTS',  TRUE);  # cache/convert posts

define('NL', "\n"); // newline
define('STEPSIZE', 400); // Number of objects to receive per cycle from DB

define('DRUPAL_FORMAT' , 'filtered_html'); // drupal inpuut filter mode
define('DRUPAL_ROOT', getcwd());



echo DRUPAL_ROOT;

// connection to phpBB

define('PHPBB_DB_host','127.0.0.1');
define('PHPBB_DB_database','dpz_forum');
define('PHPBB_DB_username','root');
define('PHPBB_DB_password','');


include_once DRUPAL_ROOT . '/includes/bootstrap.inc';
timer_start('phpbbconversion');



drupal_override_server_variables();
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

require('phpbb2d7/phpbb_db.php');
require('phpbb2d7/phpbb_functions.php');
require('phpbb2d7/drupal_functions.php');
require('phpbb2d7/progressbar.php');

echo NL.'Starting conversion'.NL;


$modules = module_list(true);
// check requirements
$diff = array_diff(array("forum",'taxonomy','comment','user'),$modules) ;
if (count($diff)>0) {
    $mods = format_plural(count($diff), 'module', 'modules');
    $diff = ucwords(implode(", ", $diff));
    echo "You need to enable {$diff} {$mods} before starting the conversion".NL;
    exit();
}

// disable tracker, saves a lot of unneeded queries ;)
if (in_array('tracker', $modules)) {
    module_disable(array('tracker'));
    $trackerDisabled = true;
}

if (USERS) {
    $bar = new progressbar();
    $userCount = phpbb_DB::getDB()->Count('phpbb_users u','u.user_id NOT IN (SELECT ban_userid FROM phpbb_banlist) AND user_type in(0,3) AND u.user_lastvisit >= 0');
    $bar->start($userCount);
    for($i=0;$i<$userCount;$i+=STEPSIZE) {
        $start = $i;
        $users = phpbb_DB::getDB()->phpbb_getActiveUsers($start,STEPSIZE);
        foreach($users as $newuser) {
            dpl_addUser($newuser);
            $bar->message ='User: '.$newuser->name;
            $bar->next();
        }
    }
    $bar->finish();
    $i = count($cache['users']);
    echo NL."Done, cached {$i} users, elapsed time ".format_interval(timer_read('phpbbconversion')/1000).NL;
} else {
    echo NL."Fetched users from cache".NL;
    $cache = unserialize(file_get_contents('cachedusers.txt'));
    foreach ($cache["users"] as $drupalid => $phpbbid) {
        $newCache["users"][$drupalid]["uid"] = $phpbbid;
    }
    $cache = $newCache;
}



if (FORUMS) {
    echo NL."Importing forums".NL;
    variable_del('forum_containers');
    $totalForums = phpbb_DB::getDB()->getRows("select count(*) as total from phpbb_forums");
    $bar = new progressbar();
    $bar->start($totalForums[0]->total);
    convertForumById(0,$bar);
    echo NL."Done, elapsed time ".format_interval(timer_read('phpbbconversion')/1000).NL;
    $bar->finish();
}

if (POSTS) {
    $comment_maintain_node_statistics = variable_get('comment_maintain_node_statistics',true);
    variable_set('comment_maintain_node_statistics',false);
    $topicCount = phpbb_DB::getDB()->Count('phpbb_topics','topic_poster > 1 AND topic_moved_id =0');
    echo NL."Converting $topicCount topics".NL.NL;

    $counter = 1;
    for($i=0;$i<$topicCount;$i+=STEPSIZE) {
        $topics = phpbb_DB::getDB()->phpBB_getTopics($i,STEPSIZE);
        foreach ($topics as $topic) {
            $nodeId = dpl_addTopic($topic);
            $posts = phpbb_DB::getDB()->phpBB_getPostsByTopic($topic);

            $bar = new progressbar();
            $bar->start(count($posts));
            foreach($posts as $post) {
                $bar->message("topic $counter of $topicCount adding posts");
                dpl_addComment($post,$nodeId);
                $bar->next();
            }
            $bar->finish();
            $counter++;
        }
    }
    variable_set('comment_maintain_node_statistics',$comment_maintain_node_statistics);
}
echo NL."Done converting topics, elapsed time ".format_interval(timer_read('phpbbconversion')/1000).NL;
echo NL."Done.. Time needed ".format_interval(timer_read('phpbbconversion')/1000).NL.NL;

if($trackerDisabled) {
    module_enable(array('tracker'));
}

// save cache for next run
file_put_contents('cacheddata', serialize($cache));



