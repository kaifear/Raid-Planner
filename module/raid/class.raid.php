<?php
/*
 * class RaidPlanner
 * Created: 20.12.2011
 */

class RaidPlanner
{
	private $actions = array(
	                         'roster',
	                         'guilds'
	                         );
	private $connect;
	private $guildList = array();
	private $guild_list = array();
	private $guild_list_table = array();
	private $roster_list = array();
	private $undecided = false;
	private $approved = false;
	private $limit = 100;
	private $action = null;
	private $units = array();
	
	/**
	 * function __construct
	 * Created 20.12.2011
	 * @result void;
	 */
	public function __construct($db)
	{
		$this->connect = $db;
		if (isset($_GET['raid_action']) && in_array($_GET['raid_action'], $this->actions))
			$this->action = $_GET['raid_action'];
		elseif (RAID_PERM == 'admin')
			exit(header('Location: index.php?do=home'));
	}
	
	/**
	 * function getPlannerTemplateVB
	 * Created 20.12.2011
	 * @result void;
	 */
	public function getPlannerTemplateVB($template)
	{
		$raid = vB_Template::create($template);
		$raid->register_page_templates();
		
		return $raid->render();
	}
	
	/**
	 * function getPlannerTemplate
	 * Created 20.12.2011
	 * @param String $id Индекс шаблона;
	 * @param String $path Путь к  шаблону;
	 * @result void;
	 */
	public function getPlannerTemplate($id, $path)
	{
		require_once('class.template.php');
		
		$tpl = new template;
		
		$tpl->LoadFile($id, $path);
		
		$parse_if = array(
		                  'is_guild'        => $this->action == 'guilds',
		                  'is_roster'       => $this->action == 'roster',
		                  'is_glist'        => count($this->guild_list) > 0,
		                  'is_undecided'    => $this->undecided,
		                  'is_approved'     => $this->approved
		                  );
		$tpl->parseIf($id, $parse_if);
		
		$tpl->parseLoop($id, 'glist', $this->guild_list);
		$tpl->parseLoop($id, 'g_list', $this->guild_list_table);
		
		foreach ($this->units as $gid => $data)
			$tpl->parseLoop($id, 'unit_' . $gid, $data);
		
		foreach ($this->units as $gid => $data) {
			$raid = $firm = array();
			foreach ($data as $key => $unit)
				if ($unit['TYPE'] == 'raid')
					$raid[] = $unit;
				else
					$firm[] = $unit;
			$tpl->parseLoop($id, 'runit_' . $gid, $raid);
			$tpl->parseLoop($id, 'funit_' . $gid, $firm);
		}
		
		$tpl->PrintFile($id);
	}
	
	/**
	 * function createUnit
	 * Created 29.12.2011
	 * @param String $name;
	 * @result void;
	 */
	public function createUnit($action, $name, $gid = null)
	{
		if ($action == 'create_guild')
			$query = sprintf('INSERT INTO %sraid_guilds (name) VALUE ("%s")', TABLE_PREFIX, $name);
		elseif (intval($gid) > 0 ) {
			$action = str_replace('create_', null, $action);
			$query = sprintf('INSERT INTO %sraid_unit (unit_type, name, gid) VALUE ("%s", "%s", "%d")', TABLE_PREFIX, $action, $name, $gid);
		}
		else
			return;
		$this->connect->query_write($query);
	}
	
	/**
	 * function createList
	 * Created 29.12.2011
	 * @result void;
	 */
	public function createList()
	{
		$function = sprintf('create%sList', ucfirst($this->action));
		call_user_func(array($this, $function));
	}
	
	/**
	 * function userList
	 * Created 04.01.2012
	 * @param Integer $gid;
	 * @result void;
	 */
	public function userList($gid)
	{
		switch ($gid) {
			case -1:
				$this->undecidedUsers();
				break;
			case 0:
				$this->approvedUsers();
				break;
			default:
				$gid = intval($gid);
				if ($gid > 0)
					$this->guildUsers($gid);
				else
					header('HTTP/1.1 404 Not Found');
		}
	}
	
	/**
	 * function approveUsers
	 * Created 04.01.2012
	 * @param Array $users;
	 * @result void;
	 */
	public function approveUsers($users)
	{
		if (!is_array($users))
			$users = array();
		
		foreach ($users as $id => $result) {
			if (!in_array($result, array('approve', 'denied'))) continue;
			
			$query = sprintf('UPDATE %suser SET raid_approve="%s" WHERE userid="%d"', TABLE_PREFIX, $result, $id);
			$this->connect->query_write($query);
		}
	}
	
	/**
	 * function inviteUsers
	 * Created 04.01.2012
	 * @param Array $users;
	 * @result void;
	 */
	public function inviteUsers($users)
	{
		if (!is_array($users))
			$users = array();
		
		foreach ($users as $id => $result) {
			if ($result == 0)
				continue;
			elseif ($result == -1)
				$query = sprintf('UPDATE %suser SET raid_approve="denied" WHERE userid="%d"', TABLE_PREFIX, $id);
			else
				$query = sprintf('INSERT INTO %sraid_roster (gid, uid) VALUES ("%d", "%d")', TABLE_PREFIX, $result, $id);
			
			$this->connect->query_write($query);
		}
	}
	
	/**
	 * function changeUsers
	 * Created 04.01.2012
	 * @param Array $users;
	 * @param Integer $gid;
	 * @result void;
	 */
	public function changeUsers($users, $gid, $raids, $firms)
	{
		if (!is_array($users))
			$users = array();
		if (!is_array($raids))
			$raids = array();
		if (!is_array($firms))
			$firms = array();
		
		$deleted_users = array();
		foreach ($users as $id => $result) {
			if ($result == 0)
				continue;
			elseif ($result == -1) {
				$query = sprintf('DELETE FROM %sraid_roster WHERE uid="%d"', TABLE_PREFIX, $id);
				$this->connect->query_write($query);
				$query = sprintf('UPDATE %suser SET raid_approve="denied" WHERE userid="%d"', TABLE_PREFIX, $id);
				$deleted_users[] = $id;
			}
			elseif ($result == $gid) {
				$query = sprintf('DELETE FROM %sraid_roster WHERE uid="%d"', TABLE_PREFIX, $id);
				$deleted_users[] = $id;
			}
			else
				$query = sprintf('UPDATE %sraid_roster SET gid="%d" WHERE uid="%d"', TABLE_PREFIX, $result, $id);
			
			$this->connect->query_write($query);
		}
		
		foreach ($raids as $id => $raid) {
			if (in_array($id, $deleted_users) || $raid == 0) continue;
			
			$query = sprintf('UPDATE %sraid_roster SET rid="%d" WHERE uid="%d"', TABLE_PREFIX,
			                 isset($_POST['rid']) && isset($_POST['rid'][$id]) && $raid != $_POST['rid'][$id] ? $raid : 0,
			                 $id);
			$this->connect->query_write($query);
		}
		
		foreach ($firms as $id => $firm) {
			if (in_array($id, $deleted_users) || (is_numeric($firm) && $firm == 0)) continue;
			
			$query = sprintf('UPDATE %sraid_roster SET fid=%s WHERE uid="%d"', TABLE_PREFIX,
			                ($firm == 'not' || $firm > 0) && isset($_POST['fid'][$id]) && $firm != $_POST['fid'][$id] ? sprintf('"%d"', $firm) : 'NULL',
			                 $id);
			$this->connect->query_write($query);
		}
	}
	
	/**
	 * function createGuildsList
	 * Created 29.12.2011
	 * @result void;
	 */
	private function createGuildsList()
	{
		$query = sprintf('SELECT RG.id, RG.name, COUNT(RU.id) units FROM %1$sraid_guilds RG LEFT JOIN %1$sraid_unit RU ON RG.id=RU.gid GROUP BY RU.gid, RG.id ORDER BY RG.id', TABLE_PREFIX);
		$result = $this->connect->query_write($query);
		while (list($gid, $gname, $units) = $this->connect->fetch_row($result)) {
			$this->guild_list[] = array(
			                            'GID'           => $gid,
			                            'GUILD_NAME'    => $gname,
			                            'DISPLAY'       => empty($units) ? ' style="display:none;"' : null,
			                            );
			$this->guildList[$gid] = $gname;
		}
		foreach ($this->guildList as $gid => $gname) {
			$query = sprintf('SELECT id, name, unit_type FROM %sraid_unit WHERE gid="%d"', TABLE_PREFIX, $gid);
			$result = $this->connect->query_write($query);
			$this->units[$gid] = array();
			while (list($unit_id, $unit_name, $unit_type) = $this->connect->fetch_row($result))
				$this->units[$gid][] = array(
				                             'UNIT_NAME'    => $unit_name,
				                             'UID'          => $unit_id,
				                             'TYPE'         => $unit_type,
				                             'UNIT_TYPE'    => $unit_type == 'raid' ? 'Рейд' : 'Фирма'
				                             );
		}
	}
	
	/**
	 * function createRosterList
	 * Created 29.12.2011
	 * @result void;
	 */
	private function createRosterList()
	{
		$undecided = $approved = $guilds = array();
		$this->createGuildsList();
		$query = sprintf('SELECT COUNT(U.userid), RG.gid, U.raid_approve FROM %1$suser U LEFT JOIN %1$sraid_roster RG ON U.userid=RG.uid WHERE U.raid_approve!="denied" GROUP BY RG.gid, U.raid_approve ORDER BY RG.gid', TABLE_PREFIX);
		$result = $this->connect->query_write($query);
		while (list($count_users, $guild, $approve) = $this->connect->fetch_row($result))
			if (!isset($this->guildList[$guild])) {
				if ($approve == 'approve')
					$this->approved = true;
				elseif ($approve == 'none')
					$this->undecided = true;
			}
			else
				$this->guild_list_table[$guild] = array(
				                                        'GID'           => $guild,
				                                        'GUILD_NAME'    => $this->guildList[$guild],
				                                        'SELECTED'      => ''
				                                        );
		
		$this->guild_list_table = array_values($this->guild_list_table);
	}
	
	/**
	 * function undecidedUsers
	 * Created 04.01.2012
	 * @result void;
	 */
	private function undecidedUsers()
	{
		$tquery = sprintf('SELECT COUNT(*) FROM %suser WHERE raid_approve="none"', TABLE_PREFIX);
		$uquery = sprintf('SELECT userid, username FROM %suser WHERE raid_approve="none" ORDER BY userid LIMIT %d, %d',
		                  TABLE_PREFIX, $_POST['offset'], $this->limit);
		$this->getUsers($tquery, $uquery, -1);
	}
	
	/**
	 * function approvedUsers
	 * Created 04.01.2012
	 * @result void;
	 */
	private function approvedUsers()
	{
		$tquery = sprintf('SELECT COUNT(*) FROM %1$suser U LEFT JOIN %1$sraid_roster RR ON RR.uid=U.userid '.
		                  'WHERE U.raid_approve="approve" AND RR.uid IS NULL', TABLE_PREFIX);
		$uquery = sprintf('SELECT U.userid, U.username FROM  %1$suser U LEFT JOIN %1$sraid_roster RR ON RR.uid=U.userid'.
		                  ' WHERE U.raid_approve="approve" AND RR.uid IS NULL ORDER BY U.userid LIMIT %2$d, %3$d',
		                  TABLE_PREFIX, $_POST['offset'], $this->limit);
		$this->getUsers($tquery, $uquery, 0);
	}
	
	/**
	 * function guildUsers
	 * Created 04.01.2012
	 * @param Integer $gid
	 * @result void;
	 */
	private function guildUsers($gid)
	{
		$tquery = sprintf('SELECT COUNT(*) FROM %1$suser U LEFT JOIN %1$sraid_roster RR ON RR.uid=U.userid '.
		                  'WHERE U.raid_approve="approve" AND RR.gid="%2$d"', TABLE_PREFIX, $gid);
		$uquery = sprintf('SELECT U.userid, U.username, RR.rid, RR.fid FROM  %1$suser U LEFT JOIN %1$sraid_roster RR ON RR.uid=U.userid'.
		                  ' WHERE U.raid_approve="approve" AND RR.gid="%4$d" ORDER BY U.userid LIMIT %2$d, %3$d',
		                  TABLE_PREFIX, $_POST['offset'], $this->limit, $gid);
		$this->getUsers($tquery, $uquery, $gid);
	}
	
	/**
	 * function printUsers
	 * Created 04.01.2012
	 * @param Array $users;
	 * @result void;
	 */
	private function getUsers($tquery, $uquery, $gid)
	{
		$users = array();
		$result = $this->connect->query_write($tquery);
		list($users['total']) = $this->connect->fetch_row($result);
		$users['total'] = intval($users['total']);
		
		$users['offset'] = intval($_POST['offset']);
		
		$users['offset'] += $this->limit;
		$users['gid'] = $gid;
		
		$users['data'] = array();
		
		$result = $this->connect->query_write($uquery);
		while (list($id, $user, $rid, $fid) = $this->connect->fetch_row($result)){
			$users['data'][$id]['user'] = $user;
			$users['data'][$id]['rid'] = intval($rid);
			$users['data'][$id]['fid'] = is_null($fid) ? $fid : intval($fid);
		}
		
		$this->printUsers($users);
	}
	
	/**
	 * function printUsers
	 * Created 04.01.2012
	 * @param Array $users;
	 * @result void;
	 */
	private function printUsers($users)
	{
		echo json_encode($users);
	}
}
?>
