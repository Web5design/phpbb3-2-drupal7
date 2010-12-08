<?php
/*
 * Converts data from phpBB3 into drupal 7 data
 *
 * see readme.txt for more information
 *
*/
ini_set('memory_limit','512m'); // just in case ;)

define('USERS',  FALSE);  # convert users (cached to disk)
define('FORUMS', FALSE);  # convert forums (cached to disk)
define('POSTS',  FALSE);  # convert posts
define('SYNC' ,  TRUE);  # sybc comment counters


define('NL', "\n"); // newline
define('STEPSIZE', 400); // Number of objects to receive per cycle from DB

define('THREADS',4);

define('DRUPAL_FORMAT' , 'plain_text'); // drupal input filter mode
define('DRUPAL_ROOT', getcwd());

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

echo NL.'Here we go, starting conversion at '.date("H:i:s").NL;


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
        $users = phpbb_DB::getDB()->phpbb_getActiveUsers($start);
        foreach($users as $newuser) {
            dpl_addUser($newuser);
            $bar->message ='User: '.$newuser->name;
            $bar->next();
        }
    }
    $bar->finish();
    $i = count($cache['users']);
    file_put_contents('cacheddata', serialize($cache));
    echo NL."Done, cached {$i} users, elapsed time ".format_interval(timer_read('phpbbconversion')/1000).NL;
} else {
    echo NL."Fetched users from cache".NL;
    global $cache;
    $cache = unserialize(file_get_contents('cacheddata'));
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
    file_put_contents('cacheddata', serialize($cache));
}

if (POSTS) {
    $comment_maintain_node_statistics = variable_get('comment_maintain_node_statistics',true);
    $topicCount = phpbb_DB::getDB()->Count('phpbb_topics','topic_poster > 1 AND topic_moved_id =0');
    echo NL."Converting $topicCount topics".NL.NL;
    variable_set('comment_maintain_node_statistics',false);
    $counter = 1;
    for($topicloop=0;$topicloop<$topicCount;$topicloop+=STEPSIZE) {
        $topics = phpbb_DB::getDB()->phpBB_getTopics($topicloop);
        foreach ($topics as $topic) {
           convertTopic($topic,$counter,$topicCount);
           $counter++;
        }
    }
    variable_set('comment_maintain_node_statistics',$comment_maintain_node_statistics);
    echo NL."Done converting topics, elapsed time ".format_interval(timer_read('phpbbconversion')/1000).NL;
}


if (SYNC) {
    variable_set('comment_maintain_node_statistics',true);
    $counter = $topics = db_select('node','node')->fields('node', array('nid'))->condition('type', 'forum')->countQuery()->execute()->fetchCol();
    $bar = new progressbar();
    $bar->start($counter[0]);
        $topics = db_select('node','node')->fields('node', array('nid'))->condition('type', 'forum')->execute()->fetchCol();
        foreach($topics as $topic) {
            $bar->message("Syncing topic with node id ".$topic);
            db_insert('node_comment_statistics')->fields(array("nid" => $topic))->execute();
            _comment_update_node_statistics($topic);
            $bar->next();
        }
    $bar->finish();
}


echo NL."Done.. Time needed ".format_interval(timer_read('phpbbconversion')/1000).NL.NL;

if($trackerDisabled) {
    module_enable(array('tracker'));
}

// save cache for next run




