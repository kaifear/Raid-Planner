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
	exit(header('Location: /'));

require_once(DIR.'/raid/class.raid.php');
define('RAID_PERM', 'admin', true);

$planner = new RaidPlanner($db);
/**** Default values Section End    ****/

/**** $_GET Section Start           ****/
/**** $_GET Section End             ****/

/**** $_POST Section Start          ****/
if (isset($_POST['action']))
	switch ($_POST['action']) {
		case 'create_guild':
			$guild_name = trim(htmlspecialchars($_POST['new_guild'], ENT_QUOTES, 'cp1251'));
			$planner->createGuild($guild_name);
			exit(header('Location: raid_admin.php?raid_action=' . $planner->action));
		case 'delete_guild':
			$gid = intval($_POST['form_gid']);
			if ($gid > 0) {
				$query = sprintf('DELETE FROM %sraid_guilds WHERE id="%d"', TABLE_PREFIX, $gid);
				$db->query_write($query);
				$query = sprintf('DELETE FROM %sraid_roster WHERE gid="%d"', TABLE_PREFIX, $gid);
				$db->query_write($query);
			}
			exit(header('Location: raid_admin.php?raid_action=' . $planner->action));
		case 'save_guild':
			$gid = intval($_POST['form_gid']);
			if ($gid > 0) {
				$gname = trim(htmlspecialchars($_POST['form_name'], ENT_QUOTES, 'cp1251'));
				$query = sprintf('UPDATE %sraid_guilds SET name="%s" WHERE id="%d"', TABLE_PREFIX, $gname, $gid);
				$db->query_write($query);
			}
			exit(header('Location: raid_admin.php?raid_action=' . $planner->action));
		default:
			
	}
/**** $_POST Section End            ****/

/**** Default Section Start         ****/
$planner->createList();
/**** Default Section End           ****/

/**** Template Section Start        ****/
print_cp_header();
can_administer();
construct_nav_spacer();

$planner->getPlannerTemplate('test_admin', DIR . '/raid/templates/admin.html');

define('NO_CP_COPYRIGHT', true);
unset($DEVDEBUG);
print_cp_footer();
/**** Template Section End          ****/

/**** Functions Section Start       ****/
/**** Functions Section End         ****/
?>
