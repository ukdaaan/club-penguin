<?php
include "penguinelite.php";
$cp = new penguinelite("config.xml");
$cp->init();

while(true){
	$cp->loopFunction();
}

?>