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
	
	function db_used_custom_name()
	{
		return 'ts-CMS';
	}
	
	function debug_sql_custom()
	{
		return true;
	}
	
	class pw_secret
	{
		function mysqlpw_secret()
		{
			return 'insert mysql user password here';
		}
		
		function mysqluser_secret()
		{
			return 'insert mysql user name here';
		}
	}
?>