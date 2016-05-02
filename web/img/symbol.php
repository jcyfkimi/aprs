<html><head><meta http-equiv="Content-Type" content="text/html; charset=gb2312" />
        <title>APRS 图标</title>

APRS图标由两个字符控制，分别叫table和symbol。<p>
一般来说，table设置为/即可。<br>
symbol可以按照下表左侧字符设置:

<?php

$table="/\\2DEGIRY";

echo "<table>";
echo "<tr>";
echo "<th>table</th>";
for ($i=0;$i<strlen($table);$i++) {
	echo "<th>".substr($table,$i,1)."</th>\n";
}
echo"</tr>";

for($j=0;$j<94;$j++) {
	echo "<tr>";
	echo "<td>".chr($j+33)."</td>";
	for ($i=0;$i<strlen($table);$i++) {
		echo "<td><img src=".bin2hex(substr($table,$i,1).chr($j+33)).".png></td>\n";
	}
	echo "</tr>";
}

echo "</table>\n";

?>
