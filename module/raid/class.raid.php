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
	private $roster_list = array();
	
	public $action = null;
	
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
		                  'is_guild'    => $this->action == 'guilds',
		                  'is_roster'   => $this->action == 'roster',
		                  'is_glist'    => count($this->guild_list) > 0,
		                  );
		$tpl->parseIf($id, $parse_if);
		
		$tpl->parseLoop($id, 'glist', $this->guild_list);
		
		$tpl->PrintFile($id);
	}
	
	/**
	 * function createGuild
	 * Created 29.12.2011
	 * @param String $guild_name;
	 * @result void;
	 */
	public function createGuild($guild_name)
	{
		$query = sprintf('INSERT INTO %sraid_guilds (name) VALUE ("%s")', TABLE_PREFIX, $guild_name);
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
	 * function createGuildsList
	 * Created 29.12.2011
	 * @result void;
	 */
	private function createGuildsList()
	{
		$query = sprintf('SELECT * FROM %sraid_guilds ORDER BY id', TABLE_PREFIX);
		$result = $this->connect->query_write($query);
		while (list($gid, $gname) = $this->connect->fetch_row($result)) {
			$this->guild_list[] = array(
			                            'GID'           => $gid,
			                            'GUILD_NAME'    => $gname
			                            );
			$this->guildList[$gid] = $gname;
		}
	}
	
	/**
	 * function createRosterList
	 * Created 29.12.2011
	 * @result void;
	 */
	private function createRosterList()
	{
		//$undecided = $approved = $guilds = array();
		$this->createGuildsList();
		$query = sprintf('SELECT COUNT(U.userid), RG.gid, U.raid_approve FROM %1$suser U LEFT JOIN %1$sraid_roster RG ON U.userid=RG.uid WHERE U.raid_approve!="denied" GROUP BY RG.gid, U.raid_approve ORDER BY RG.gid', TABLE_PREFIX);
		$result = $this->connect->query_write($query);
		while (list($count_users, $guild, $approve) = $this->connect->fetch_row($result)) {
			if (!isset($this->guildList[$guild])) {
			//	$data = $approve == 'none' ? &$undecided : &$approved;
			//	$data[] = array(
			//					'NAME'=> $count_users
			//					);
			}
			else {
				
			}
		}
	}
}
?>
