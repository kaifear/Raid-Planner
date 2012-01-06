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
	exit(header('Location: index.php?do=home'));

require_once(DIR.'/raid/class.raid.php');
define('RAID_PERM', 'admin', true);

$planner = new RaidPlanner($db);
/**** Default values Section End    ****/

/**** $_GET Section Start           ****/
/**** $_GET Section End             ****/

/**** $_POST Section Start          ****/
if (isset($_POST['action']))
	switch ($_POST['action']) {
		case 'create_guild': case 'create_raid': case 'create_firm':
			$name = trim(htmlspecialchars($_POST['new'], ENT_QUOTES, 'cp1251'));
			if (!empty($name))
				$planner->createUnit($_POST['action'], $name, isset($_POST['guild']) ? intval($_POST['guild']) : null);
			exit(header('Location: raid_admin.php?raid_action=guilds'));
		case 'delete_guild':
			$gid = intval($_POST['form_id']);
			if ($gid > 0) {
				$query = sprintf('DELETE FROM %sraid_guilds WHERE id="%d"', TABLE_PREFIX, $gid);
				$db->query_write($query);
				$query = sprintf('DELETE FROM %sraid_roster WHERE gid="%d"', TABLE_PREFIX, $gid);
				$db->query_write($query);
				$query = sprintf('DELETE FROM %sraid_unit WHERE gid="%d"', TABLE_PREFIX, $gid);
				$db->query_write($query);
			}
			exit(header('Location: raid_admin.php?raid_action=guilds'));
		case 'delete_unit':
			$unit_id = intval($_POST['form_id']);
			if ($unit_id > 0) {
				$query = sprintf('DELETE FROM %sraid_unit WHERE id="%d"', TABLE_PREFIX, $unit_id);
				$db->query_write($query);
			}
			exit(header('Location: raid_admin.php?raid_action=guilds'));
		case 'save_guild':
			$gid = intval($_POST['form_id']);
			if ($gid > 0) {
				$gname = trim(htmlspecialchars($_POST['form_name'], ENT_QUOTES, 'cp1251'));
				$query = sprintf('UPDATE %sraid_guilds SET name="%s" WHERE id="%d"', TABLE_PREFIX, $gname, $gid);
				$db->query_write($query);
			}
			exit(header('Location: raid_admin.php?raid_action=guilds'));
		case 'save_unit':
			$unit_id = intval($_POST['form_id']);
			if ($unit_id > 0) {
				$uname = trim(htmlspecialchars($_POST['form_name'], ENT_QUOTES, 'cp1251'));
				$query = sprintf('UPDATE %sraid_unit SET name="%s" WHERE id="%d"', TABLE_PREFIX, $uname, $unit_id);
				$db->query_write($query);
			}
			exit(header('Location: raid_admin.php?raid_action=guilds'));
		case 'get_users':
			$planner->userList($_POST['gid']);
			exit;
		case 'approve_users':
			$planner->approveUsers($_POST['user']);
			exit(header('Location: raid_admin.php?raid_action=roster'));
		case 'invite_users':
			$planner->inviteUsers($_POST['user']);
			exit(header('Location: raid_admin.php?raid_action=roster'));
		case 'change_users':
			$planner->changeUsers($_POST['user'], intval($_POST['gid']), $_POST['user_raid'], $_POST['user_firm']);
			exit(header('Location: raid_admin.php?raid_action=roster'));
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
