<?php
	// this file shows the bbcode buttons if there is a bbcode library used
	
	// check for bbcode library
	if ($site->bbcode_lib_available())
	{
		echo '<script type="text/javascript" src="' . baseaddress() . 'bbcode_buttons.js"></script>' . "\n";
		echo '<div>';
		$site->write_self_closing_tag('input type="button" name="bold" value="b" '
									  . 'style="font-weight: bold;" '
									  . 'onclick="' . "insert('[b]', '[/b]'" . ')"');
		$site->write_self_closing_tag('input type="button" name="italic" value="i" '
									  . 'style="font-style: italic;" '
									  . 'onclick="' . "insert('[i]', '[/i]'" . ')"');
		$site->write_self_closing_tag('input type="button" name="underline" value="u" '
									  . 'style="text-decoration: underline;" '
									  . 'onclick="' . "insert('[u]', '[/u]'" . ')"');
		$site->write_self_closing_tag('input type="button" name="img" value="img" '
									  . 'onclick="' . "insert('[img]', '[/img]'" . ')"');
		$site->write_self_closing_tag('input type="button" name="url" value="url" '
									  . 'onclick="' . "insert('[url]', '[/url]'" . ')"');		
		echo '</div>' . "\n";
	}
?>