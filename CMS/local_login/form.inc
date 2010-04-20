<?php
	require_once realpath('../CMS/siteinfo.php');
	echo '<form action="' . baseaddress() . 'Login/'. '" method="post">' . "\n";
?>
Name:<br>
<input type="Text" name="loginname" value="" maxlength="300"><br>
Passwort:<br>
<input type="password" name="pw" value="" maxlength="300"><br>
<input type="hidden" name="module" value="local" maxlength="300"><br>
<input type="submit" value="Login">
</form>
