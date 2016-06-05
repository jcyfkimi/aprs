<?php
$qt=$_REQUEST['qt'];
$x=intval($_REQUEST['x']);
$y=intval($_REQUEST['y']);
$z=intval($_REQUEST['z']);
$styles=$_REQUEST['styles'];
$udt=intval($_REQUEST['udt']);
$scaler=intval($_REQUEST['scaler']);

chdir("/usr/src/aprs/web/baidu/cache");

$dir=$qt."/".$scaler."/".$x."/".$y;
$file = $dir."/".$z.".".$styles.".png";

if(file_exists($file))
	header("Location: cache/".$qt."/".$scaler."/".$x."/".$y."/".$z.".".$styles.".png");

if(!file_exists($dir))
	 mkdir($dir,0777,true);

$img = file_get_contents("http://online3.map.bdimg.com/tile/?qt=".$qt."&x=".$x."&y=".$y."&z=".$z."&styles=".$styles."&scaler=".$scaler."&udt=".$udt);

file_put_contents($file,$img);

header("Location: cache/".$qt."/".$scaler."/".$x."/".$y."/".$z.".".$styles.".png");
exit(0);
?>
