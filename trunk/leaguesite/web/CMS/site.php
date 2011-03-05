<?php
	include('classes/smarty/Smarty.class.php');
	
	// this file is supposed to load and init all common classes
	class site
	{
		function __construct()
		{
			global $tmplHelper;
			global $config;
			global $db;
			global $user;
			global $tmpl;
			
			// setup session
			ini_set ('session.use_trans_sid', 0);
			ini_set ('session.name', 'SID');
			ini_set('session.gc_maxlifetime', '7200');
			session_start();
			
			// database connectivity
			include dirname(__FILE__) . '/classes/config.php';
			$config = new config();
			
			// database connectivity
			include dirname(__FILE__) . '/classes/db.php';
			$db = new database();
			
			// user information
			require dirname(__FILE__) . '/classes/user.php';
			$user = new user();
			
			// template builder
			// do not init it, as db information is needed
			// to find out what template should be used
/* 				include('classes/smarty/Smarty.class.php'); */
			$smarty = new Smarty();
			$tmpl = new tmpl($smarty);
/*
			} else
			{
				require dirname(__FILE__) . '/classes/tmpl.php';
				$tmpl = new template();
			}	
*/
		}
		
		function magic_quotes_on()
		{
			if (function_exists('get_magic_quotes_gpc') && (get_magic_quotes_gpc() === 1))
			{
				return true;
			}
			
			return false;
		}
		
		function basename()
		{
			$path = (pathinfo(realpath('./')));
			$name = $path['basename'];
			return $name;
		}
		
		function setKey($randomkey_name)
		{
			// this should be good enough as all we need is something that can not be guessed without many tries
			return $_SESSION[$randomkey_name] = rand(0, getrandmax());
		}
		
		function validateKey($key, $value)
		{
			if (isset($_SESSION[$key]))
			{
				$randomkeysmatch = (strcmp(html_entity_decode(urldecode($_SESSION[$key])), $value) === 0);
				
				// invalidate key & value to prevent allowing sending stuff more than once
				if (!(strcmp($value, '') === 0))
				{
					unset ($_SESSION[$key]);
					unset ($_SESSION[$value]);
				} else
				{
					// never return true on empty key & value combination
					return false;
				}
				
				return $randomkeysmatch;
			}
			
			return false;
		}
	}
	
	class tmpl extends Smarty
	{
		private $templateFile;
		private $engine;
		
		function __construct($engine)
		{
			$this->engine = ($engine);
			$this->debugging = false;
			$engine->caching = true;
			$engine->cache_lifetime = 120;
		}
		
		function getEngine()
		{
			return $this->engine;
		}
		
		function existsTemplate($theme, $template)
		{
			return file_exists(dirname(dirname(dirname(__FILE__))) . '/themes/'
							   . $theme . '/templates/' . $template . '.tmpl.html');
		}
		
		
		function findTemplate(&$template, &$path)
		{
			// split the path in $template into an array
			// overwrite $template with the last part of the array
			// forget the last part of the array
			// append the pieces of the array to $path
			$pathinfo = explode('/', $template);
			
			if (count($pathinfo) > 0)
			{
				$template = $pathinfo[count($pathinfo) -1];
				unset($pathinfo[count($pathinfo) -1]);
				
				$path .= '/' . htmlspecialchars(implode('/', $pathinfo));
			}
		}
		
		function noTemplateFound()
		{
			global $db;
			
			$db->logError('FATAL ERROR: Template not found, request URI: ' . $_SERVER['REQUEST_URI']);
			parent::setTemplate('NoPerm');
/* 			$this->done('Template file not found. This is an installation problem.'); */
			die();
		}
		
		public function display()
		{
			parent::display($this->templateFile . '.html.tmpl');
		}
		
		function setTemplate($template, $customTheme='')
		{
			global $config;
			global $user;
			global $tmpl;
			global $db;
			
			if (strcmp($customTheme, '') === 0)
			{
				$customTheme = $user->getStyle();
			}
			
			// extract possible file paths out of $template and append it to $themeFolder
			$themeFolder = dirname(dirname(__FILE__)) .'/themes/' . htmlspecialchars($customTheme);
			$this->findTemplate($template, $themeFolder);
			
			// init template system
			parent::setTemplateDir($themeFolder . 'templates');
			parent::setCompileDir($themeFolder . 'templates_c');
			parent::setCacheDir($themeFolder . 'cache');
			parent::setConfigDir($themeFolder . 'config');
			
//			$this->tpl = new HTML_Template_IT($themeFolder);
			
			// fallback if template specified is empty
			if (strcmp($template, '') === 0)
			{
				$template = 'NoPerm';
			}
			
//			$this->tpl->loadTemplatefile($template . '.tmpl.html', true, true);
			
			if (!file_exists($themeFolder . 'templates/' . $template . '.html.tmpl'))
			{
				if ($config->value('debugSQL'))
				{
					echo 'Tried to use template: ' . $themeFolder . 'templates/' . $template . '.html.tmpl but failed: file does not exist.';
				}
				return false;
			} elseif ($config->value('debugSQL'))
			{
				// debug output used template
//				$this->addMSG('Used template: ' . $themeFolder
//							  . $template . '.tmpl.html' . $this->return_self_closing_tag('br'));
			}
			
			$this->templateFile = $template;
			return true;
		}
	}
	
	// add a few functions to global namespace
	// only add very frequently used functions
	
	// shortcut for utf-8 aware htmlentities
	function htmlent($string)
	{
		return htmlentities($string, ENT_COMPAT, 'UTF-8');
	}
	
	function htmlent_decode($string)
	{
		return html_entity_decode($string, ENT_COMPAT, 'UTF-8');
	}
?>