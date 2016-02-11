<?php


if (isset($_SERVER['REMOTE_ADDR']))  {
	echo "not for web\n";
	exit(0);
}

function process($fn, $p) {
	echo $fn; echo $p; echo "\n";
	$imgs = imagecreatefrompng($fn);
	for($i=0;$i<6;$i++)
	for($j=0;$j<16;$j++){
		$destimage=imagecreatetruecolor(24,24);

  imagealphablending($destimage, false);
            $colorTransparent = imagecolorallocatealpha($destimage, 0, 0, 0, 127);
            imagefill($destimage, 0, 0, $colorTransparent);
            imagesavealpha($destimage, true);

		imagecopy($destimage,$imgs,0,0,$j*24,$i*24,24,24);
		$n = $p.chr($i*16+$j+33);
		$n=bin2hex($n);
		imagepng($destimage,$n.".png");
	}
}

process("aprs-symbols-24-0.png","/");
process("aprs-symbols-24-1.png","\\");

?>
