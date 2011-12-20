<?php
/**
 * Created: 20.12.2011
 */

/**** Include/Require Section Start ****/
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions_navpanel.php');
/**** Include/Require Section End   ****/

/**** Default values Section Start  ****/
error_reporting(E_ALL & ~E_NOTICE);
if (!can_administer('canadminsettings'))
	header('Location: /');
/**** Default values Section End    ****/

/**** $_GET Section Start           ****/
/**** $_GET Section End             ****/

/**** $_POST Section Start          ****/
/**** $_POST Section End            ****/

/**** Default Section Start         ****/
/**** Default Section End           ****/

/**** Template Section Start        ****/
print_cp_header();
can_administer();
construct_nav_spacer();

require_once(DIR.'/raid/class.raid.php');
define('RAID_PERM', 'admin', true);

$planner = new RaidPlanner($db);

$planner->getPlannerTemplate('test_admin', DIR . '/raid/templates/admin.html');

define('NO_CP_COPYRIGHT', true);
unset($DEVDEBUG);
print_cp_footer();
/**** Template Section End          ****/

/**** Functions Section Start       ****/
/**** Functions Section End         ****/
?>
