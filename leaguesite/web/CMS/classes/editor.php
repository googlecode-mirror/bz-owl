<?php
	
	class editor
	{
		var $caller;
		
		function __construct($caller)
		{
			$this->caller = $caller;
			
						global $config;
			global $tmpl;
			
			$tmpl->setCurrentBlock('USER_NOTE');
			
			if ($config->value('bbcodeLibAvailable'))
			{
				$tmpl->setVariable('EDIT_MODE_NOTE', 'Keep in mind to use BBCode instead of HTML or XHTML.');
				$tmpl->parseCurrentBlock();
				
				include(dirname(dirname(__FILE__)) . '/bbcode_buttons.php');
				$bbcode = new bbcode_buttons();
				
				$buttons = $bbcode->showBBCodeButtons('staticContent');
				$tmpl->setCurrentBlock('STYLE_BUTTONS');
				foreach ($buttons as $button)
				{
					$tmpl->setVariable('BUTTONS_TO_FORMAT', $button);
					$tmpl->parseCurrentBlock();
				}
				
				// forget no longer needed variables
				unset($button);
				unset($buttons);
				unset($bbcode);
			} else
			{
				if ($config->value('useXhtml'))
				{
					$tmpl->setVariable('EDIT_MODE_NOTE', 'Keep in mind the home page currently uses XHTML, not HTML or BBCode.');
				} else
				{
					$tmpl->setVariable('EDIT_MODE_NOTE', 'Keep in mind the home page currently uses HTML, not XHTML or BBCode.');
				}
				$tmpl->parseCurrentBlock();
			}
			return;
		}
		
		
		function edit()
		{
			global $entry_edit_permission;
			global $randomKeyName;
			global $site;
			global $tmpl;
			global $user;
			
			
			if ($user->hasPermission($entry_edit_permission))
			{
				// initialise variables
				$confirmed = 0;
				$content = '';
				
				// set their values in case the POST variables are set
				if (isset($_POST['confirmationStep']))
				{
					$confirmed = intval($_POST['confirmationStep']);
				}
				if (isset($_POST['editPageAgain']) && strlen($_POST['editPageAgain']) > 0)
				{
					// user looked at preview but chose to edit the message again
					$confirmed = 0;
				}
				if (isset($_POST['staticContent']))
				{
					$content = htmlspecialchars_decode($_POST['staticContent'], ENT_COMPAT);
				}
				
				// sanity check variabless
				$test = $this->caller->sanityCheck($confirmed);
				switch ($test)
				{
						// use bbcode if available
					case (true && $confirmed === 1 && $config->value('bbcodeLibAvailable')):
						$this->caller->insertEditText(true);
						$tmpl->addMSG($tmpl->encodeBBCode($content));
						break;
						
						// else raw output
					case (true && $confirmed === 1 && !$config->value('bbcodeLibAvailable')):
						$this->caller->insertEditText(true);
						$tmpl->addMSG($content);
						break;
						
						// use this as guard to prevent selection of noperm or nokeymatch cases
					case (strlen($test) < 2):
						$this->caller->insertEditText(false);
						break;
						
					case 'noperm':
						$tmpl->addMSG('You need write permission to edit the content.');
						break;
						
					case 'nokeymatch':
						$this->caller->insertEditText(false);
						$tmpl->addMSG('The magic key does not match, it looks like you came from somewhere else or your session expired.');
						break;			
				}
				unset($test);
				
				
				// there is no step lower than 0
				if ($confirmed < 0)
				{
					$confirmed = 0;
				}
				
				// increase confirmation step by one so we get to the next level
				$tmpl->setCurrentBlock('PREVIEW_VALUE');
				if ($confirmed > 1)
				{
					$tmpl->setVariable('PREVIEW_VALUE_HERE', 1);
				} else
				{
					$tmpl->setVariable('PREVIEW_VALUE_HERE', $confirmed+1);
				}
				$tmpl->parseCurrentBlock();
				
				switch ($confirmed)
				{
					case 1:
						$tmpl->setCurrentBlock('FORM_BUTTON');
						$tmpl->setVariable('SUBMIT_BUTTON_TEXT', 'Write changes');
						$tmpl->parseCurrentBlock();
						// user may decide not to submit after seeing preview
						$tmpl->setCurrentBlock('EDIT_AGAIN');
						$tmpl->setVariable('EDIT_AGAIN_BUTTON_TEXT', 'Edit again');
						$tmpl->parseCurrentBlock();
						break;
						
					case 2:
						$this->writeContent($content, $page_title);
						$tmpl->addMSG('Changes written successfully.' . $tmpl->linebreaks("\n\n"));
						
					default:
						$tmpl->setCurrentBlock('FORM_BUTTON');
						$tmpl->setVariable('SUBMIT_BUTTON_TEXT', 'Preview');
						$tmpl->parseCurrentBlock();
				}
				
				
				$randomKeyName = $randomKeyName . microtime();
				// convert some special chars to underscores
				$randomKeyName = strtr($randomKeyName, array(' ' => '_', '.' => '_'));
				$randomkeyValue = $site->setKey($randomKeyName);
				$tmpl->setCurrentBlock('KEY');
				$tmpl->setVariable('KEY_NAME', $randomKeyName);
				$tmpl->setVariable('KEY_VALUE', urlencode($_SESSION[$randomKeyName]));
				$tmpl->parseCurrentBlock();
			}
		}	
	}
	
?>
