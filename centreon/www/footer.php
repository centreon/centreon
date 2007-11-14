<?php
/** 
Oreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
Developped by : Julien Mathis - Romain Le Merlus

The Software is provided to you AS IS and WITH ALL FAULTS.
OREON makes no representation and gives no warranty whatsoever,
whether express or implied, and without limitation, with regard to the quality,
safety, contents, performance, merchantability, non-infringement or suitability for
any particular or intended purpose of the Software found on the OREON web site.
In no event will OREON be liable for any direct, indirect, punitive, special,
incidental or consequential damages however they may arise and even if OREON has
been previously advised of the possibility of such damages.

For information : contact@oreon-project.org
*/
	if (!isset($oreon))
		exit;
?>
		<div id="footer">
		<table cellpadding="0" cellspacing="0" height="1" width='100%'>
			<tr>
				<td id="footerline1"></td>
			</tr>
			<tr>
				<td id="footerline2"></td>
			</tr>
		</table>
		<table cellpadding='0' cellspacing='0' width='100%' border='0'>
			<tr>
				<td align='center' class='copyRight'>
					Generated in <?php$time_end = microtime_float(); $now = $time_end - $time_start; print round($now,3) . $lang["time_sec"]; ?><br />
					Copyright &copy; 1999-2007 Nagios - <a href="http://www.nagios.org/contact/">Ethan Galstad</a> | Copyright &copy; 2004-2007 <a href="mailto:infos@centreon-nsm.org">Centreon</a><br>
					All Rights Reserved<br />
				</td>
			</tr>
			<tr>
				<td align="center" style="padding-top:5px;"><div class='footer'>
				<a href='http://www.w3c.org'><img src="<?phpecho $skin; ?>Images/footer/colophon_css.png"
				          height="15" width="80" alt="Valid CSS"
				          title="Oreon was built with valid CSS." />
				<a href='http://www.php.net'><img src="<?phpecho $skin; ?>Images/footer/button-php.gif"
				          height="15" width="80" alt="Powered By PHP"
				          title="Powered By PHP." /></a>
				<a href='http://sourceforge.net/donate/index.php?group_id=140316'><img src="<?phpecho $skin; ?>Images/footer/button-donate.gif"
				          height="15" width="80" alt="Donate"
				          title="Donate" /></a>
				<a href='http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt'><img src="<?phpecho $skin; ?>Images/footer/button-gpl.gif"
				          height="15" width="80" alt="GPL Licenced"
				          title="GPL Licenced" /></a>
				</div>
				</td>
			</tr>
		</table>
		</div>
</body>
</html>