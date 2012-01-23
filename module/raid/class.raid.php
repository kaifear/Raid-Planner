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
	private $usersList = array();
	private $guild_list = array();
	private $guild_list_table = array();
	private $roster_list = array();
	private $undecided = false;
	private $approved = false;
	private $limit = 100;
	private $action = null;
	private $units = array();
	private $sort_types = array(
	                            'rank'  => 'RR.rank',
	                            'class' => 'RR.rank',
	                            'gname' => '',
	                            'fname' => 'U.username'
	                            );
	private $sort;
	private $type;
	private $ranks = array(
	                       'Рекрут',
	                       'Участник',
	                       'Ветеран',
	                       'Сержант',
	                       'Лейтенант',
	                       'Офицер',
	                       'Глава гильдии'
	                       );
	//id custom поля => id группы
	private $perms = array(
	                       5 => 10,
	                       7 => 11,
	                       8 => 12
	                       );
	//id custom поля для ника
	private $nick = 6;
	
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
		$data = $raid->render();
		
		//setlocale(LC_ALL, 'ru_RU.cp1251');
		
		require_once('raid/class.template.php');
		$tpl = new template;
		
		$tpl->loadTemplate('roster', $data);
		
		$this->createRosterList();
		
		$tpl->parseLoop('roster', 'glist', $this->guild_list);
		
		$this->createUsersList();
		
		$ranks = array();
		
		$tpl->parseIf('roster', array('show table' => count($this->usersList) > 0));
		
		foreach ($this->ranks as $index => $value)
			if (empty($this->usersList[$index]))
				unset($this->ranks[$index]);
		
		if ($this->sort == 'rank')
			if ($this->type == 'DESC')
				krsort($this->ranks);
			else
				ksort($this->ranks);
		
		if ($this->sort == 'rank')
			foreach ($this->ranks as $index => $rank)
				$ranks[] = array(
				                 'RANK_INDEX'   => $index,
				                 'RANK_NAME'    => $rank,
				                 'TYPE'         => 'Ранг',
				                 'DISPLAY'      => ''
				                 );
		elseif ($this->sort == 'class') {
			$class_list = array_keys($this->usersList);
			foreach ($class_list as $value)
				$ranks[] = array(
				                 'RANK_INDEX'   => $value,
				                 'RANK_NAME'    => $value,
				                 'TYPE'         => 'Класс',
				                 'DISPLAY'      => ''
				                 );
		}
		else
			$ranks[] = array(
			                 'RANK_INDEX'   => '',
			                 'RANK_NAME'    => '',
			                 'TYPE'         => '',
			                 'DISPLAY'      => ' style="display:none;"'
			                 );
		
		$tpl->parseLoop('roster', 'rlist', $ranks);
		
		foreach ($this->usersList as $rank => $data)
			$tpl->parseLoop('roster', 'userlist_' . $rank, $data);
		
		$tpl_parse = array(
		                   'SORT_TYPE_RANK'     => $this->sort == 'rank' && $this->type == 'DESC' ? 'asc' : 'desc',
		                   'SORT_TYPE_CLASS'    => $this->sort == 'class' && $this->type == 'ASC' ? 'desc' : 'asc',
		                   'SORT_TYPE_GNAME'    => $this->sort == 'gname' && $this->type == 'ASC' ? 'desc' : 'asc',
		                   'SORT_TYPE_FNAME'    => $this->sort == 'fname' && $this->type == 'ASC' ? 'desc' : 'asc'
		                   );
		
		return $tpl->fileToVar('roster', $tpl_parse);
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
			//$tpl->parseLoop($id, 'funit_' . $gid, $firm);
		}
		
		$ranks = array();
		foreach ($this->ranks as $index => $rank)
			$ranks[] = array(
			                 'RANK_INDEX'   => $index,
			                 'RANK_NAME'    => $rank
			                 );
		
		$tpl->parseLoop($id, 'rlist', $ranks);
		
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
	public function changeUsers($users, $gid, $raids, $firms, $ranks)
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
		
		//foreach ($firms as $id => $firm) {
		//	if (in_array($id, $deleted_users) || (is_numeric($firm) && $firm == 0)) continue;
		//	
		//	$query = sprintf('UPDATE %sraid_roster SET fid=%s WHERE uid="%d"', TABLE_PREFIX,
		//	                ($firm == 'not' || $firm > 0) && isset($_POST['fid'][$id]) && $firm != $_POST['fid'][$id] ? sprintf('"%d"', $firm) : 'NULL',
		//	                 $id);
		//	$this->connect->query_write($query);
		//}
		
		foreach ($ranks as $id => $rank) {
			if (!isset($_POST['rank_old']) || in_array($id, $deleted_users) || $_POST['rank_old'][$id] == $rank) continue;
			
			$query = sprintf('UPDATE %sraid_roster SET rank="%d" WHERE uid="%d"', TABLE_PREFIX, $rank, $id);
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
		global $vbulletin;
		$guild = intval($vbulletin->session->vars['raid_guild']);
		
		$query = sprintf('SELECT RG.id, RG.name, COUNT(RU.id) units FROM %1$sraid_guilds RG LEFT JOIN %1$sraid_unit RU ON RG.id=RU.gid GROUP BY RU.gid, RG.id ORDER BY RG.id', TABLE_PREFIX);
		$result = $this->connect->query_write($query);
		while (list($gid, $gname, $units) = $this->connect->fetch_row($result)) {
			$this->guild_list[] = array(
			                            'GID'           => $gid,
			                            'GUILD_NAME'    => $gname,
			                            'DISPLAY'       => empty($units) ? ' style="display:none;"' : null,
			                            'SELECTED'      => $gid == $guild ? ' selected' : ''
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
		while (list($count_users, $gid, $approve) = $this->connect->fetch_row($result))
			if (!isset($this->guildList[$gid])) {
				if ($approve == 'approve')
					$this->approved = true;
				elseif ($approve == 'none')
					$this->undecided = true;
			}
			else
				$this->guild_list_table[$gid] = array(
				                                        'GID'           => $gid,
				                                        'GUILD_NAME'    => $this->guildList[$gid]
				                                        );
		
		$this->guild_list_table = array_values($this->guild_list_table);
	}
	
	/**
	 * function createUsersList
	 * Created 17.01.2012
	 * @result void;
	 */
	private function createUsersList()
	{
		global $vbulletin;
		$guild = intval($vbulletin->session->vars['raid_guild']);
		if ($guild == 0) {
			$query = sprintf('SELECT raid_guild FROM session WHERE idhash="%s" AND raid_guild > 0 LIMIT 1', $vbulletin->session->vars['idhash']);
			$result = $this->connect->query_write($query);
			list($guild) = $this->connect->fetch_row($result);
			$guild = intval($guild);
			if ($guild > 0) {
				$query = sprintf('UPDATE %ssession SET raid_guild="%d" WHERE idhash="%s"', TABLE_PREFIX, $guild, $vbulletin->session->vars['idhash']);
				$this->connect->query_write($query);
			}
			//else {
			//	$query = sprintf('SELECT gid FROM %sraid_roster WHERE uid="%d"', TABLE_PREFIX, $vbulletin->userinfo['userid']);
			//	$result = $this->connect->query_write($query);
			//	
			//}
		}
		
		$this->sort_types['gname'] = 'UF.field' . $this->nick;
		
		$this->sort = isset($_GET['sort']) && array_key_exists($_GET['sort'], $this->sort_types) ? $_GET['sort'] : 'rank';
		$this->type = isset($_GET['type']) && in_array(strtolower($_GET['type']), array('asc', 'desc')) ? strtoupper($_GET['type']) : 'DESC';
		
		$search = sprintf('RR.gid="%d"', $guild);
		if (isset($_GET['search_gname'])){
			$search_name = htmlentities(trim($_GET['search_gname']), ENT_QUOTES, 'cp1251');
			if ($search_name != '') {
				$search = sprintf('UF.field%d LIKE "%%%s%%"', $this->nick, $this->connect->escape_string($search_name));
				$this->sort = 'gname';
			}
		}
		
		$query = sprintf('SELECT * FROM %1$suser U '.
		                 'INNER JOIN %1$sraid_roster RR ON RR.uid=U.userid '.
		                 'INNER JOIN %1$suserfield UF ON UF.userid=U.userid '.
		                 'WHERE U.raid_approve="approve" AND %2$s ORDER BY %3$s %4$s, U.lastactivity ASC',
		                 TABLE_PREFIX, $search, $this->sort_types[$this->sort],
		                 $this->sort != 'rank' ? $this->type : 'DESC');
		$result = $this->connect->query_write($query);
		$time = time();
		$hour = 60 * 60;
		$day = $hour * 24;
		while ($row = $this->connect->fetch_array($result)) {
			$last_seen = $time - intval($row['lastactivity']);
			$data = '';
			
			if ($last_seen > $day) {
				$days = ($last_seen - $last_seen % $day) / $day;
				$last_seen %= $day;
				
				$data .= sprintf('%d %s ', $days, $this->parse_time($days, array('день', 'дня', 'дней')));
			}
			
			if ($last_seen > $hour) {
				$hours = ($last_seen - $last_seen % $hour) / $hour;
				$last_seen %= $hour;
				
				$data .= sprintf('%d %s ', $hours, $this->parse_time($hours, array('час', 'часа', 'часов')));
			}
			
			if ($last_seen > 60) {
				$mins = ($last_seen - $last_seen % 60) / 60;
				$last_seen %= 60;
				
				$data .= sprintf('%d %s ', $mins, $this->parse_time($mins, array('минута', 'минуты', 'минут')));
			}
			
			$data .= sprintf('%d %s ', $last_seen, $this->parse_time($days, array('секунда', 'секунды', 'секунд')));
			
			$class = '';
			foreach ($this->perms as $field => $perm_index)
				if (is_member_of($row, $perm_index)) {
					if (empty($row['field' . $field])) {
						$query = sprintf('SELECT title FROM %susergroup WHERE usergroupid="%d"', TABLE_PREFIX, $perm_index);
						$res = $this->connect->query_write($query);
						list($class) = $this->connect->fetch_row($res);
					}
					else
						$class = $row['field' . $field];
					break;
				}
			
			if ($this->sort == 'rank')
				$array = &$this->usersList[$row['rank']];
			elseif ($this->sort == 'class')
				$array = &$this->usersList[$class];
			else
				$array = &$this->usersList[''];
			
			$array[] = array(
			                 'UID'         => $row['userid'],
			                 'USER_NAME'   => $row['username'],
			                 'LAST_SEEN'   => $data,
			                 'CLASS'       => $class,
			                 'GAME_NICK'   => $row['field' . $this->nick]
			                 );
		}
		
		if ($this->sort == 'class')
			if ($this->type == 'DESC')
				krsort($this->usersList);
			else
				ksort($this->usersList);
	}
	
	private function parse_time($time, $text)
	{
		if ($time % 10 == 1 && $time != 11)
			$text =  $text[0];
		elseif (in_array($time % 10, array(2, 3, 4)) && !in_array($time, array(12, 13, 14)))
			$text = $text[1];
		else
			$text = $text[2];
		
		return $text;
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
		$uquery = sprintf('SELECT U.userid, U.username, RR.rid, RR.fid, RR.rank FROM  %1$suser U LEFT JOIN %1$sraid_roster RR ON RR.uid=U.userid'.
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
		while (list($id, $user, $rid, $fid, $rank) = $this->connect->fetch_row($result)){
			$users['data'][$id]['user'] = $user;
			$users['data'][$id]['rid'] = intval($rid);
			$users['data'][$id]['rank'] = intval($rank);
			//$users['data'][$id]['fid'] = is_null($fid) ? $fid : intval($fid);
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
