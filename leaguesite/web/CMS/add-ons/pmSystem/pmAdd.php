<?php
	class pmSystemAddPM extends pmSystemPM
	{
		function __construct()
		{
			global $tmpl;
			
			
			$tmpl->assign('title', 'New PM');
			$tmpl->display('PMAdd');
		}
	}
?>
