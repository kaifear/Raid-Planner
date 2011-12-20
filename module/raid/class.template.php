<?php
class template
{
	private $start;
	private $end;
	private $tpls = array();
	
	public function __construct($start = '{', $end = '}')
	{
		$this->changeDelimeters($start, $end);
	}
	
	public function changeDelimeters($start = '{', $end = '}')
	{
		$this->start = "$start";
		$this->end   = "$end";
	}
	
	public function loadFile($id, $filename)
	{
		if (!file_exists($filename)) return;
		
		$content = file_get_contents($filename);
		
		$this->tpls[$id] = $content;
	}
	
	public function parseIf($id, $parse)
	{
		if (empty($this->tpls[$id])) return;
		
		if (is_array($parse))
			foreach ($parse as $if_key => $if_show) {
				$change = $if_show === true ? '$1' : '';
				$this->tpls[$id] = preg_replace(sprintf('#<if name="%1$s">(.*)</if name="%1$s">#Uis', $if_key),
				                                $change, $this->tpls[$id]);
			}
	}
	
	public function parseLoop($id, $loop_name, $parse)
	{
		if (empty($this->tpls[$id])) return;
		
		if (is_array($parse)) {
			preg_match_all(sprintf('#<loop name="%1$s">(.*)</loop name="%1$s">#Uis', $loop_name), $this->tpls[$id], $loops);
			for ($i = 0, $num = count($loops[0]); $i < $num; $i++) {
				$new = '';
				foreach ($parse as $loop)
					if (is_array($loop)) {
						$l = $loops[1][$i];
						foreach ($loop as $key => $value)
							$l = str_ireplace($this->start . $key . $this->end, $value, $l);
						$new .= $l;
					}
				$this->tpls[$id] = str_replace($loops[0][$i], $new, $this->tpls[$id]);
			}
		}
	}
	
	public function printFile($id, $values = array())
	{
		if (empty($this->tpls[$id])) return;
		
		if (!is_array($values)) $values = array();
		
		$this->changeVarGlobal($id, $values);
		echo $this->tpls[$id];
	}
	
	public function fileToVar($id, $values = array())
	{
		if (empty($this->tpls[$id])) return;
		
		if (!is_array($values)) $values = array();
		
		$this->changeVarGlobal($id, $values);
		return $this->tpls[$id];
	}
	
	private function changeVarGlobal($id, $values)
	{
		foreach ($values as $key => $value)
			$this->tpls[$id] = str_ireplace($this->start . $key . $this->end, $value, $this->tpls[$id]);
	}
}
?>
