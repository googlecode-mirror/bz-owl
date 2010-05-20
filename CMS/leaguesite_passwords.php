// this is only a sample password file

// copy it to the path specified within siteinfo.php and
// edit the return values appropriately to get the site running

<?php
	class pw_secret
	{
		function mysqlpw_secret()
		{
			return 'your password here';
		}
		
		function mysqluser_secret()
		{
			return 'your database management system user account here';
		}
    }
?>