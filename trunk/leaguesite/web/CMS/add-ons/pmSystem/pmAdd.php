<?php
	class pmSystemAddPM extends pmSystemPM
	{
		function __construct()
		{
			global $tmpl;
			
			
			$tmpl->assign('New PM');
			$tmpl->display('PMAdd');
		}
	}
?>
