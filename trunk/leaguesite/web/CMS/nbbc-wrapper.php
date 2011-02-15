<?php
	class wrapper
	{
		function Parse($string)
		{
			global $config;
			global $site;
			
			require_once((dirname(__FILE__)) . '/nbbc-1.4.4/nbbc.php');
			$setup = new BBCode;
			
			if (!isset($config))
			{
				$setup->SetSmileyURL(baseaddress() . 'smileys');
			} else
			{
				$setup->SetSmileyURL($config->value('baseaddress') . 'smileys');
			}
			// $setup->SetEnableSmileys(false);
			
			return $setup->Parse($string);
		}
    }
?>