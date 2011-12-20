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
	private $action = null;
	
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
		
		$tpl->PrintFile($id);
	}
}
?>
