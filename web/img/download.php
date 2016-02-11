<?php


function process($c) {
	echo $c; echo "\n";
	for($i=0;$i<96;$i++) {
		echo "i=".$i."\n";
		$n1 = bin2hex($c);
		$n2 = bin2hex(chr($i+33)); 
		echo "n1=".$n1.",n2=".$n2."\n";
		echo "curl http://d1dhsh1i77j8ju.cloudfront.net/s1/f24/".$c.$n2.$c.$n2.".png > ".$n1.$n2.".png\n";
	}
}

process("E");
process("2");

?>
