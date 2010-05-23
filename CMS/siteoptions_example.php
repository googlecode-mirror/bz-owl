<?php
    function domain()
	{
		// return 'my.bzflag.org';
		return '192.168.1.10';
	}
	
	function basepath()
	{
		return '/~spiele/league_svn/ts/';
	}
    
    
    function xhtml_on()
    {
        return false;
    }
    
    function www_required()
    {
        return false;
    }
    
	class pw_secret
	{
		function mysqlpw_secret()
		{
			return 'MYSQL_PW19,43';
		}
		
		function mysqluser_secret()
		{
			return 'root';
		}
    }
?>