<?php
  ini_set('mysql.connect_timeout', 300);
  ini_set('default_socket_timeout', 300);
  
  error_reporting(E_ALL);
  set_time_limit(0);
  define("SINGLE_PLAYER", 1);
  define("MULTI_PLAYER", 2);
  
  include "crumbs.php";
  include "config.php";
  $init = false;
  $count = 0;
  
  class penguinelite {
	  public $ip;
	  public $count;
	  public $port;
	  public $users = array();
	  public $mode;
	  public $config;
	  public $mysql;
	  public $bot;
	  public $BotName;
	  public $StartMsg;
	  public $games;
	  private $socket;
	  public function __construct($config = "config.xml") {
		  global $init, $count;
		  $count++;
		  $this->count = $count;
		  if ($init == false)
			  $this->createHeader();
		  else
			  $this->writeOutput("Starting next server...", "INFO");
		  $init = true;
		  $this->readConfig($config);
	  }
	  public function readConfig($file) {
		  echo "\n\n\n";
		  echo "|----------------------------------------------|\n";
		  echo "|        Reading Configuration Files           |\n";
		  echo "|----------------------------------------------|\n";
		  if (!file_exists($file))
			  $this->shutDown("Could not find $file. Does it exist?");
		  $this->config = simplexml_load_file($file) or $this->shutDown("$file has errors!");
		  $this->writeOutput("Running as " . $this->config->type . " server", "INFO");
		  $this->writeOutput("Successfully read config files");
	  }
	  public function init() {
		  $this->mysql = new mysql();
		  $err = false;
		  $this->writeOutput("Connecting to MySQL database...", "INFO");
		  $this->mysql->connect($this->config->mysql->host, $this->config->mysql->username, $this->config->mysql->password) or $err = true;
		  if ($err == true)
			  $this->shutDown("Could not connect to mysql. Reason: " . $this->mysql->getError());
		  $this->mysql->selectDB($this->config->mysql->dbname);
		  if ($err == true)
			  $this->shutDown("Could not select the database. Reason: " . $this->mysql->getError());
		  $this->bind((integer)$this->config->port, (string)$this->config->ip);
		  $this->writeOutput("We recommend using a while loop here to accept connections", "FINEST");
	  }
	  public function bind($port, $ip = "0") {
		  $this->socket = socket_create(AF_INET, SOCK_STREAM, 0) or $this->shutDown("Could not create socket. Please check php.ini to see if sockets are enabled!");
		  socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
		  socket_bind($this->socket, $ip, $port) or $this->shutDown("Could not bind to port. Make sure the port is over 1024 if you are using linux");
		  socket_listen($this->socket);
	  }
	  public function loopFunction() {
		  socket_set_block($this->socket);
		  $read = array();
		  $read[0] = $this->socket;
		  foreach ($this->users as $i=>&$user) {
			  if (!empty($user))
				  $read[] = &$user->sock;
			  if ($user->selfDestruct == true)
				  unset($this->users[$i]);
		  }
		  $ready = socket_select($read, $w = null, $e = null, $t = 0);
		  if (in_array($this->socket, $read)) {
			  if (count($this->users) <= 1000) {
				  $this->users[] = new CPUser(socket_accept($this->socket), $this);
				  $this->writeOutput("New Client Connected", "FINE");
			  } else if(count($this->users) >= 1000)
				  $this->writeOutput("Server is full", "INFO");
		  }
		  if ($ready-- <= 0)
			  return;
		  else {
			  foreach ($this->users as $index=>&$user) {
				  if (in_array($user->sock, $read)) {
					  $input = socket_read($user->sock, 65536);
					  if ($input == null) {
						  unset($this->users[$index]);
						  continue;
					  }
					  $x = explode(chr(0), $input);
					  array_pop($x);
					  foreach ($x as $input2){
						  $this->handleRawPacket($input2, $user);
					  }
				  }
			  }
		  }
	  }
	  public function doLogin(&$user, $packet) {
		  $username = $this->mysql->escape($this->stribet($packet, "<nick><![CDATA[", "]]"));
		  $password = $this->stribet($packet, "<pword><![CDATA[", "]]");
		  if($username != "" || $password != "" || $password != "e9800998ecf8427ed41d8cd98f00b204"){
          if ($this->mysql->getRows("SELECT * FROM {$this->config->mysql->userTableName} WHERE username='" . $username . "';") > 0) {
			  $dbv = $this->mysql->returnArray("SELECT * FROM {$this->config->mysql->userTableName} WHERE username='" . $this->mysql->escape($username) . "';");
		  if($this->config->type == "login"){
			   $hash = strtoupper($dbv[0]["password"]);
			   $hash = $this->encryptPassword($hash, $user->key);
		  } else {
			   $hash = $this->swapMD5(md5($dbv[0]["lkey"] . $user->key)) . $dbv[0]["lkey"];
		  }
		  if ($password == $hash) {
				  if ($dbv[0]["active"] != "0") {
					  if ($dbv[0]["ubdate"] != "PERMABANNED") {
						  if ($dbv[0]["ubdate"] < strtotime("NOW MDT")) {
							  if ($this->config->type == "login") {
							      global $server1IP, $server1Port, $server1Name, $server2IP, $server2Port, $server2Name;
								  $this->writeSocket($user, "%xt%gs%-1%" . $server1IP . ":" . $server1Port . ":" . $server1Name . ":3|" . $server2IP . ":" . $server2Port . ":" . $server2Name . ":2% 3;");
								  $this->writeSocket($user, "%xt%l%-1%" . $dbv[0]["id"] . "%" . md5(strrev($user->key)) . "%0%");
								  $this->mysql->query("UPDATE {$this->config->mysql->userTableName} SET lkey='" . md5(strrev($user->key)) . "' WHERE id='" . $dbv[0]["id"] . "';");
							  } else {
								  socket_getsockname($user->sock, $ip);
								  $user->id = $dbv[0]["id"];
								  $this->mysql->query("UPDATE {$this->config->mysql->userTableName} SET ips=ips + '\n" . $this->mysql->escape($ip) . "' WHERE id='" . $user->getID() . "';");
								  $user->resetDetails();
								  $user->sendPacket("%xt%l%-1%");
							      global $StartMsg;
					              if($StartMsg != null)
								  $this->sendPacket("%xt%lm%-1%http://ultimatecheatscp.info/content/loginmsg.swf?msg=$StartMsg%");
							  }
						  } else
							  $this->writeSocket($user, "%xt%e%-1%601%24%");
					  } else
						  $this->writeSocket($user, "%xt%e%-1%603%");
				  } else
					  $this->writeSocket($user, "%xt%e%-1%900%");
			  } else
				  $this->writeSocket($user, "%xt%e%-1%101%");
		  } else
			  $this->writeSocket($user, "%xt%e%-1%100%");
	  }
    }
	  public function encryptPassword($password, $key) {
		  return $this->swapMD5(md5($this->swapMD5($password) . $key . 'Y(02.>\'H}t":E1'));
	  }
	  public function swapMD5($func_md5) {
		  return substr($func_md5, 16, 16) . substr($func_md5, 0, 16);
	  }
	  public function handleRawPacket($packet, &$user) {
		  if (substr($packet, 0, 1) == "<")
			  $this->handleSysPacket($packet, $user);
		  elseif (substr($packet, 0, 1) == "%")
			  $this->handleXtPacket($packet, $user);
	  }
	  public function handleSysPacket($packet, &$user) {
		  if (stristr($packet, "<policy-file-request/>") > -1)
			  $this->writeSocket($user, "<cross-domain-policy><allow-access-from domain='*' to-ports='*' /></cross-domain-policy>");
		  if (stristr($packet, "<msg t='sys'><body action='verChk'") > -1)
			  $this->writeSocket($user, "<msg t='sys'><body action='apiOK' r='0'></body></msg>");
		  if (stristr($packet, "<msg t='sys'><body action='rndK' r='-1'></body></msg>") > -1){
			  $user->key = $this->generateRandomKey();
			  $this->writeSocket($user, "<msg t='sys'><body action='rndK' r='-1'><k>" . $user->key . "</k></body></msg>");
		  }
		  if (stristr($packet, "<msg t='sys'><body action='login' r='0'>") > -1)
			  $this->doLogin($user, $packet);
	  }
	  public function handleXtPacket($packet, &$user) {
		  $raw = explode("%", $packet);
		  $handler = $raw[2];
		  if ($handler == "s")
			  $this->handleStandardPacket($packet, $user);
		  if ($handler == "z")
			  $this->handleGamePacket($packet, $user);
	  }
	  public function getDefaultRoom(){
		  $rooms = array("100", "110", "111", "120", "121", "130", "200", "210", "220", "221", "230", "300", "310", "320", "330", "340", "400", "410", "411", "800", "801", "802", "804", "805", "806", "807", "808", "809");
		  return $rooms[array_rand($rooms)];
	  }
	  public function handleStandardPacket($packet, &$user) {
		  $raw = explode("%", $packet);
		  $cmd = $raw[3];
		  if ($cmd == "j#js") {
			  $lkey = $raw[6];
			  $res = $this->mysql->returnArray("SELECT * FROM {$this->config->mysql->userTableName} WHERE id='" . $user->getID() . "'");
			  if (count($res) > 0)
				  $user->sendPacket("%xt%js%-1%0%1%" . $res[0]["ismoderator"] . "%0%");
			  $this->mysql->query("UPDATE {$this->config->mysql->userTableName} SET lkey='' WHERE id='" . $user->getID() . "';");
		  }
		  if ($cmd == "j#jp"){
			  $user->sendPacket("%xt%jp%" . $raw[4] . "%" . $raw[5] . "%");
			  $user->joinRoom($raw[5], $raw[6], $raw[7]);
		  }
		  if ($cmd == "p#pg"){
			  $user->sendPacket("%xt%pg%" . $raw[4] . "%");
		  }
		  if ($cmd == "i#gi"){
			  $user->sendPacket("%xt%gps%-1%" . $user->getID() . "%9|10|11|14|20|183%");
			  $user->sendPacket("%xt%glr%-1%3555%");
			  $user->sendPacket("%xt%lp%-1%" . implode("|", $user->getDetails()) . "%" . $user->getCoins() . "%0%1440%" . rand(1200000000000, 1500000000000) . "%" . $user->getAge() . "%4%" . $user->getAge() . "% %7%");
			  $user->joinRoom($this->getDefaultRoom());
			  $user->sendPacket("%xt%gi%-1%" . implode("%", $user->getInventory()) . "%");
		  }
		  if ($cmd == "i#ai")
			  $user->addItem($raw[5]);
		  if ($cmd == "n#gn")
			  $user->sendPacket("%xt%gn%-1%");
		  if ($cmd == "l#mst")
			  $user->sendPacket("%xt%mst%-1%0%1");
		  if ($cmd == "l#mg")
			  $user->sendPacket("%xt%mg%-1%Safari|0|12|penguinelite|0|63%");
		  if ($cmd == "j#jr")
			  $user->joinRoom($raw[5], $raw[6], $raw[7]);
		  if ($cmd == "m#sm")
			  $user->speak($raw[6]);
		  if ($cmd == "o#k" && $this->isModerator){
			  foreach($this->users as &$suser){
				  if($suser->getID() == $raw[5]){
					   $suser->kick();
				  }
			  }
		  }
		 $h = explode("#", $cmd);
		  $h = $h[0];
		  if ($h == "s")
			  $this->handleUserSettingPacket($packet, $user);
		  if ($h == "u")
			  $this->handleUserSettingPacket($packet, $user);
		  if ($h == "f")
			  $this->handleEPFPacket($packet, $user);
		  if ($h == "b")
			  $this->handleBuddyPacket($packet, $user);
		  if ($h == "g")
			  $this->handleIglooPacket($packet, $user);
	  }
	  public function handleBuddyPacket($packet, &$user){
		  $raw = explode("%", $packet);
		  $cmd = $raw[3];
		  if ($cmd == "b#gb")
			  $user->sendPacket("%xt%gb%-1%" . $user->getBuddyStr());
		  if($cmd == "b#br")
			  $user->requestBuddy($raw[5]);
		  if($cmd == "b#ba")
			  $user->acceptBuddy($raw[5]);
		  if($cmd == "b#rb")
			  $user->removeBuddy($raw[5]);
		  if($cmd == "b#bf")
			  $user->findBuddy($raw[5]);
	  }
	  public function handleIglooPacket($packet, &$user){
		  $raw = explode("%", $packet);
		  $cmd = $raw[3];
		  $id = $raw[5];
		if($cmd == "g#gm") {
			$user->sendPacket("%xt%gm%"  .  $raw[4]  .  "%" . $raw[5] . "%" .  $user->getIGLOO()  . "%" . $user->getFURNITURE() . "%"  .  $user->getFLOOR()  .  "%");
		} elseif($cmd == "g#go") {
			$user->sendPacket("%xt%go%" . $raw[4] . "%1%");
		} elseif($cmd == "g#gf") {
			$furn = $user->getFURNITURE();
			$user->sendPacket("%xt%gf%" . $raw[4] . "%" . $user->getFURNITURE() ."%");
		} elseif($cmd == "g#af") {
			$user->sendPacket("%xt%af%" . $raw[4] . "%" . $raw[5] ."%" .  $user->getCoins()  . "%");
		} elseif($cmd == "g#au") {
			$user->changeIGLOO($raw[5]);
			$user->sendPacket("%xt%au%" . $raw[4] . "%" . $raw[5] ."%" .  $user->getCoins()  . "%");
		} elseif($cmd == "g#ag") {
			$user->changeFloor($raw[5]);
			$user->sendPacket("%xt%ag%" . $raw[4] . "%" . $raw[5] ."%" .  $user->getCoins()  . "%");
		} elseif($cmd == "g#um") {
			$user->sendPacket("%xt%um%" . $raw[4] . "%" . $raw[5] ."%");
		} elseif($cmd == "g#ur") {
			$furniture1 = str_replace("%xt%s%g#ur%" . $raw[4] . "%", "" , $packet);
			$furniture2 = str_replace("%", "," , $furniture1);
			$user->setFurniture($furniture2);
			$user->sendPacket("%xt%ur%" . $raw[4] . "%");
		}
	  
	  }
	  public function handleUserSettingPacket($packet, &$user) {
		  $raw = explode("%", $packet);
		  $cmd = $raw[3];
		  if ($cmd == "u#sp")
			  $user->setXY($raw[5], $raw[6]);
		  if ($cmd == "u#gp"){
			  $playerInfo = $this->mysql->returnArray("SELECT id, nickname, '1', colour, curhead, curface, curneck, curbody, curhands, curfeet, curflag, curphoto, rank * 146 FROM {$this->config->mysql->userTableName} WHERE id='" . $this->mysql->escape($raw[5]) . "';");
			  $playerInfo = $playerInfo[0];
			  $user->sendPacket("%xt%gp%-1%" . $raw[5] . "%" . implode("|", $playerInfo) . "%");
		  }
		  if ($cmd == "s#upc")
			  $user->setColour($raw[5]);
		  if ($cmd == "s#uph")
			  $user->setHead($raw[5]);
		  if ($cmd == "s#upf")
			  $user->setFace($raw[5]);
		  if ($cmd == "s#upn")
			  $user->setNeck($raw[5]);
		  if ($cmd == "s#upb")
			  $user->setBody($raw[5]);
		  if ($cmd == "s#upa")
			  $user->setHands($raw[5]);
		  if ($cmd == "s#upe")
			  $user->setFeet($raw[5]);
		  if ($cmd == "s#upp")
			  $user->setPhoto($raw[5]);
		  if ($cmd == "s#upl")
			  $user->setPin($raw[5]);
		  if ($cmd == "u#h")
			  $user->sendPacket("%xt%h%" . $raw[4] . "%");
		  if ($cmd == "u#sf")
			  $user->setFrame($raw[5]);
		  if($cmd == "u#sb")
			  $user->sendRoom("%xt%sb%-1%" . $user->getID() . "%" . $raw[5] . "%" . $raw[6] . "%");
		  if($cmd == "u#se")
			  $user->sendRoom("%xt%se%-1%" . $user->getID() . "%" . $raw[5] . "%");
		  if($cmd == "u#sa")
			  $user->setAction($raw[5]);
		  if($cmd == "u#ss")
			  $user->sendRoom("%xt%ss%-1%" . $user->getID() . "%" . $raw[5] . "%");
		  if($cmd == "u#sl")
			  $user->sendRoom("%xt%sl%-1%" . $user->getID() . "%" . $raw[5] . "%");
		  if($cmd == "u#sq")
			  $user->sendRoom("%xt%sq%-1%" . $user->getID() . "%" . $raw[5] . "%");
		  if($cmd == "u#sg")
			  $user->sendRoom("%xt%sg%-1%" . $user->getID() . "%" . $raw[5] . "%");
		  if($cmd == "u#sj")
			  $user->sendRoom("%xt%sj%-1%" . $user->getID() . "%" . $raw[5] . "%");
		  if($cmd == "u#sma")
			  $user->sendRoom("%xt%sma%-1%" . $user->getID() . "%" . $raw[5] . "%");
	  }
	  public function handleEPFPacket($packet, &$user) {
		  $raw = explode("%", $packet);
		  $cmd = $raw[3];
		  if ($cmd == "f#epfga")
			  $user->sendPacket("%xt%epfga%-1%1%");
		  if ($cmd == "f#epfgr")
			  $user->sendPacket("%xt%epfgr%-1%0%0%");
		  if ($cmd == "f#epfgf")
			  $user->sendPacket("%xt%epfgf%-1%0%");
	  }
	  public function handleGamePacket($packet, &$user){
		  $raw = explode("%", $packet);
		  $cmd = $raw[3];
		  $gameID = (int) $raw[4];
		  if($cmd == "m")
		  return $user->sendRoom("%xt%zm%" . $user->room . "%{$cmd[5]}%{$cmd[6]}%{$cmd[7]}%{$cmd[8]}%{$cmd[9]}%");
		  if($user->game != null){
			  $game = &$user->game;
			  $game->handlePacket($packet, $user);
		  } else if($gameID < 1000){
			  $this->writeOutput("Dojo Debug: " . $gameID . " " . $packet, "FINEST");
		  } else {
			  $this->writeOutput($user->getName() . " has just tried to send a packet to a game room that doesn't exist (" . $gameID . ")", "FINER");
		  }
	  }
	  public function writeSocket(&$user, $packet) {
		  if (@stristr($packet, strlen($packet) - 1, 1) != chr(0))
			  $packet = $packet . chr(0);
		  socket_write($user->sock, $packet, strlen($packet));
	  }
	  public function stribet($input, $left, $right) {
		  $pl = stripos($input, $left) + strlen($left);
		  $pr = stripos($input, $right, $pl);
		  return substr($input, $pl, $pr - $pl);
	  }
	  public function generateRandomKey($amount = 9) {
		  return "abc12345";
		  $keyset = "abcdefghijklmABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!\"�$%^&*()_+-=[]{}:@~;'#<>?|\\,./";
		  $randkey = "";
		  for ($i = 0; $i < $amount; $i++)
			  $randkey .= substr($keyset, rand(0, strlen($keyset) - 1), 1);
		  return $randkey;
	  }
	  public function __destruct() {
		  @socket_shutdown($this->socket);
	  }
	  public function shutDown($error) {
		  $this->writeOutput("System error. Terminating server", "CRITICAL");
		  $this->writeOutput($error, "CRITICAL");
		  $this->writeOutput("Server terminated.", "CRITICAL");
		  if ($this->socket != null)
			  $this->writeOutput("Closing ports", "INFO");
		  die();
	  }
	  private function createHeader() {
		  echo "\033[2J";
		  echo "\n  |----------------------------------------------|\n";
		  echo "  |     CPSP UK - The Open Source Private Server |\n";
		  echo "  |----------------------------------------------|\n";
		  echo "  |          Created by DJSMITH UK               |\n";
		  echo "  |           Original by Cooldude170 & ZKid     |\n";
		  echo "  |----------------------------------------------|\n";
		  echo "\n";
		  $this->writeOutput("Successfully loaded, welcome to CPPS UK - FINAL!");
		  echo "\n";
	  }
	  private function writeOutput($msg, $type = "INFO") {
		  echo date("H\:i\:s") . " - [$type] [" . $this->count . "]  > $msg\n";
	  }
	  public function handleCommand(&$user, $msg) {
		  if (function_exists("handleCommand") && substr($msg, 0, 1) == "!"){
			  handleCommand($user, $msg, $this);
		  }
	  }
	  public function sendPacket($packet) {
		  foreach ($this->users as $user)
			  $user->sendPacket($packet);
	  }
  }
  class CPUser {
	  public $selfDestruct;
	  public $sock;
	  public $parent;
	  public $inventory;
	  public $coins;
	  public $username;
	  public $email;
	  public $room;
	  public $lkey;
	  public $colour;
	  public $id;
	  public $head;
	  public $face;
	  public $neck;
	  public $body;
	  public $hands;
	  public $feet;
	  public $pin;
	  public $photo;
	  public $loggedin;
	  public $x;
	  public $y;
	  public $key;
	  public $rank;
	  public $igloo;
	  public $floor;
	  public $furniture;
	  public $frame;
	  public $buddies;
	  public $buddyRequests = array();
	  public $isModerator = false;
	  public $stamps = array();
	  public $muted = false;
	  public $game;
	  public $mail;
	  public function __construct($socket, &$parent) {
		  $this->sock = $socket;
		  $this->parent = $parent;
	  }
	  public function __destruct() {
		  $this->sendRoom("%xt%rp%-1%" . $this->getID() . "%");
	  }
	  public function getName() {
		  return $this->username;
	  }
	  public function getEmail() {
	      return $this->email;
	  }
	  public function getID() {
		  return $this->id;
	  }
	  public function getIGLOO() {
		  return $this->igloo;
	  }
	  public function getFLOOR() {
		  return $this->floor;
	  }
	  public function getFURNITURE() {
		  return $this->furniture;
	  }
	  public function getHead() {
		  return $this->head;
	  }
	  public function getFace() {
		  return $this->face;
	  }
	  public function getNeck() {
		  return $this->neck;
	  }
	  public function getBody() {
		  return $this->body;
	  }
	  public function getHands() {
		  return $this->hands;
	  }
	  public function getFeet() {
		  return $this->feet;
	  }
	  public function getPin() {
		  return $this->pin;
	  }
	  public function getPhoto() {
		  return $this->photo;
	  }
	  public function getColour() {
		  return $this->colour;
	  }
	  public function getAge() {
		  return $this->age;
	  }
	  public function getCoins() {
		  return $this->coins;
	  }
	  public function getX() {
		  return $this->x;
	  }
	  public function getY() {
		  return $this->y;
	  }
	  public function getInventory() {
		  return $this->inventory;
	  }
	  public function getFrame() {
		  return $this->frame;
	  }
	  public function setHead($id) {
		  $id = $this->parent->mysql->escape($id);
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET curhead='$id' WHERE id='" . $this->getID() . "';");
		  $this->sendRoom("%xt%uph%-1%{$this->getID()}%" . $id . "%");
		  $this->head = $id;
	  }
	  public function setFurniture($furn) {
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET furniture='$furn' WHERE id='" . $this->getID() . "';");
		  $this->furniture = $furn;
	  }
	  public function changeIGLOO($iglooid) {
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET igloo='" . $iglooid ."' WHERE id='" . $this->getID() . "';");
	  }	
	  public function changeFloor($floorid) {
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET floor='" . $floorid ."' WHERE id='" . $this->getID() . "';");
	  }	 
	  public function setFace($id) {
		  $id = $this->parent->mysql->escape($id);
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET curface='$id' WHERE id='" . $this->getID() . "';");
		  $this->sendRoom("%xt%upf%-1%{$this->getID()}%" . $id . "%");
		  $this->face = $id;
	  }
	  public function setNeck($id) {
		  $id = $this->parent->mysql->escape($id);
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET curneck='$id' WHERE id='" . $this->getID() . "';");
		  $this->sendRoom("%xt%upn%-1%{$this->getID()}%" . $id . "%");
		  $this->neck = $id;
	  }
	  public function setBody($id) {
		  $id = $this->parent->mysql->escape($id);
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET curbody='$id' WHERE id='" . $this->getID() . "';");
		  $this->sendRoom("%xt%upb%-1%{$this->getID()}%" . $id . "%");
		  $this->body = $id;
	  }
	  public function setHands($id) {
		  $id = $this->parent->mysql->escape($id);
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET curhands='$id' WHERE id='" . $this->getID() . "';");
		  $this->sendRoom("%xt%upa%-1%{$this->getID()}%" . $id . "%");
		  $this->hands = $id;
	  }
	  public function setFeet($id) {
		  $id = $this->parent->mysql->escape($id);
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET curfeet='$id' WHERE id='" . $this->getID() . "';");
		  $this->sendRoom("%xt%upe%-1%{$this->getID()}%" . $id . "%");
		  $this->feet = $id;
	  }
	  public function setPin($id) {
		  $id = $this->parent->mysql->escape($id);
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET curflag='$id' WHERE id='" . $this->getID() . "';");
		  $this->sendRoom("%xt%upl%-1%{$this->getID()}%" . $id . "%");
		  $this->pin = $id;
	  }
	  public function setPhoto($id) {
		  $id = $this->parent->mysql->escape($id);
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET curphoto='$id' WHERE id='" . $this->getID() . "';");
		  $this->sendRoom("%xt%upp%-1%{$this->getID()}%" . $id . "%");
		  $this->photo = $id;
	  }
	  public function setColour($id) {
		  $id = $this->parent->mysql->escape($id);
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET colour='$id' WHERE id='" . $this->getID() . "';");
		  $this->sendRoom("%xt%upc%-1%{$this->getID()}%" . $id . "%");
		  $this->colour = $id;
	  }
	  public function setCoins($coins) {
		  $coins = $this->parent->mysql->escape($coins);
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET coins='$coins' WHERE id='" . $this->getID() . "';");
		  $this->sendPacket("%xt%zo%-1%" . $coins . "%Open%CP%");
	  }
	  public function setXY($x, $y) {
		  $this->x = $x;
		  $this->y = $y;
		  $this->sendRoom("%xt%sp%-1%" . $this->getID() . "%$x%$y%");
	  }
	  public function setFrame($frame) {
		  $this->frame = $frame;
		  $this->sendRoom("%xt%sf%-1%" . $this->getID() . "%" . $frame . "%");
	  }
	  public function setAction($action) {
		  $this->frame = 1;
		  $this->sendRoom("%xt%sf%-1%" . $this->getID() . "%" . $action . "%");
	  }
	  public function speak($msg = "I need friends") {
		  $this->sendRoom("%xt%sm%-1%" . $this->getID() . "%" . htmlspecialchars($msg) . "%");
		  $this->parent->handleCommand($this, $msg);
		  	include("censor.php");
		    if(in_array($msg, $censored)){
		    $this->sendPacket("%xt%e%-1%610%Please do not use bad language. THIS IS NOT A BAN!%");
		}
	  }
	  public function resetDetails() {
		  $res = $this->parent->mysql->returnArray(" SELECT* FROM {$this->parent->config->mysql->userTableName} WHERE id='" . $this->getID() . "'");
		  $res = $res[0];
		  $this->username = $res["nickname"];
		  $this->head = $res["curhead"];
		  $this->face = $res["curface"];
		  $this->neck = $res["curneck"];
		  $this->body = $res["curbody"];
		  $this->hands = $res["curhands"];
		  $this->feet = $res["curfeet"];
		  $this->pin = $res["curflag"];
		  $this->photo = $res["curphoto"];
		  $this->colour = $res["colour"];
		  $this->age = round((strtotime("NOW") - strtotime($res['joindate'])) / (60 * 60 * 24));
		  $this->coins = $res["coins"];
		  $this->isModerator = $res["ismoderator"];
		  $this->inventory = explode(",", $res["items"]);
		  if($this->inventory[0] == "0")
			  array_shift($this->inventory);
		  $this->buddies = explode(",", $res["buddies"]);
		  $this->rank = $res["rank"];
		  $this->igloo = $res["igloo"];
		  $this->floor = $res["floor"];
		  $this->furniture = $res["furniture"];
	  }
	  public function getBuddyStr(){
	  $buddyStr = "";
		  foreach($this->buddies as $buddyID){
			  $buddyInfo = $this->parent->mysql->returnArray("SELECT * FROM {$this->parent->config->mysql->userTableName} WHERE id='" . $this->parent->mysql->escape($buddyID) . "';");
			  $buddyName = $buddyInfo[0]["nickname"];
			  $isOnline = false;
			  foreach($this->parent->users as &$user){
				  if($user->getID() == $buddyID){
					  $isOnline = true;
					  break;
				  }
			  }
			  $buddyStr .= "$buddyID|" . $buddyName . "|" . $isOnline . "%";
		  }
		  if($buddyStr == "")
			  $buddyStr = "%";
		  return $buddyStr;
	  }
	  public function requestBuddy($id){
		  $isOnline = false;
		  foreach($this->parent->users as &$user){
			  if($user->getID() == $id){
				  $isOnline = true;
				  break;
			  }
		  }
		  if($isOnline){
			  $user->buddyRequests[$this->getID()] = true;
			  $user->sendPacket("%xt%br%-1%" . $this->parent->mysql->escape($this->getID()) . "%" . $this->parent->mysql->escape($this->getName()) . "%");
		  }
	  }
	  public function acceptBuddy($id){
		  $isOnline = false;
		  foreach($this->parent->users as &$user){
			  if($user->getID() == $id){
				  $isOnline = true;
				  break;
			  }
		  }
		  if($isOnline == false){ return $this->kick(); }
		  if($this->buddyRequests[$id] != true){ return $this->kick(); }
		  unset($user->buddyRequests[$this->getID()]);
		  $this->buddies[$id] = $id;
		  $user->buddies[$this->getID()] = $this->getID();
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET buddies='" . $this->parent->mysql->escape(implode(",", $this->buddies)) . "' WHERE id='" . $this->getID() . "';");
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET buddies='" . $this->parent->mysql->escape(implode(",", $user->buddies)) . "' WHERE id='" . $user->getID() . "';");
		  $user->sendPacket("%xt%ba%-1%" . $this->getID() . "%" . $this->getName() . "%");
	  }
	  public function removeBuddy($id){
		  foreach($this->parent->users as &$user){
			  if($user->getID() == $id){
				  break;
			  }
		  }
		  unset($this->buddies[$id]);
		  unset($user->buddies[$id]);
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET buddies='" . $this->parent->mysql->escape(implode(",", $this->buddies)) . "' WHERE id='" . $this->getID() . "';");
		  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET buddies='" . $this->parent->mysql->escape(implode(",", $user->buddies)) . "' WHERE id='" . $user->getID() . "';");
		  $user->sendPacket("%xt%rb%-1%" . $this->getID() . "%" . $this->getName() . "%");
	  }
	  public function findBuddy($id){
		  foreach($this->parent->users as &$user){
			  if($user->getID() == $id){
				  break;
			  }
		  }
		  $this->sendPacket("%xt%bf%-1%" . $user->room . "%");
	  }
	  public function getRoomCount() {
		  $i = 0;
		  foreach ($this->parent->users as $user) {
			  if ($user->room == $this->room)
				  $i++;
		  }
		  return $i;
	  }
	  public function joinRoom($id = 100, $x = 330, $y = 300) {
//		  $this->resetDetails();
		  if($id > 899){
			  //Game Room
			  $this->room = $id;
			  $this->sendRoom("%xt%rp%" . $this->room . "%" . $this->getID() . "%");
			  $this->game = new ocpGame(SINGLE_PLAYER, $id);
			  $this->sendPacket("%xt%jg%" . $this->room . "%$id%"); //Single player game
			  return;
		  }
		  if ($this->getRoomCount() > 50)
			  $this->sendPacket("%xt%e%-1%210%");
		  else {
			  $this->sendRoom("%xt%rp%-1%" . $this->getID() . "%");
			  $this->x = $x;
			  $this->room = $id;
			  $this->y = $y;
			  $s = "%xt%jr%-1%$id%" . $this->getString() . "%";
			  global $BotName;
			  $s .= "0|" . $BotName . "|1|14|413|442|0|0|0|0|0|0|380|300|0|1|999%";
			  foreach ($this->getUserList() as $user)
				  $s .= $user->getString() . "%";
			  $this->sendPacket($s);
			  $this->sendRoom("%xt%ap%-1%" . $this->getString() . "%");
		  }
	  }
	  public function sendRoom($packet) {
		  foreach ($this->parent->users as $user) {
			  if ($user->room == $this->room)
				  $user->sendPacket($packet);
		  }
	  }
	  public function getUserList() {
		  $users = array();
		  foreach ($this->parent->users as &$user) {
			  if ($user->room == $this->room)
				  $users[] = $user;
		  }
		  return $users;
	  }
	  public function sendPacket($packet) {
		  if (@stristr($packet, strlen($packet) - 1, 1) != chr(0))
			  $packet = $packet . chr(0);
		  if(!socket_write($this->sock, $packet, strlen($packet))){
			  $this->selfDestruct = true;
		  }
	  }
	  public function getDetails() {
		  return array($this->getID(), $this->getName(), "1", $this->getColour(), $this->getHead(), $this->getFace(), $this->getNeck(), $this->getBody(), $this->getHands(), $this->getFeet(), $this->getPin(), $this->getPhoto(), $this->getX(), $this->getY(), $this->getFrame(), "1", $this->getRank() * 146);
	  }
	  public function getRank(){
		  return $this->rank;
	  }
	  public function getString() {
		  return implode("|", $this->getDetails());
	  }
	  public function getStamps(){
		  return $this->stamps;
	  }
	  public function addStamp($id){
		  $stamps = explode("|", $id);
		  foreach($stamps as $sid){
          mysql_query("UPDATE {$this->parent->config->mysql->userTableName} SET stamps='" . $stamps . "' WHERE id='" . $id . "';");
		  $this->stamps[$id] = "yes";
		  }
		  $this->sendPacket("%xt%aabs%" . $this->room . "%" . $id . "%");
		  $this->sendPacket("%xt%gmres%" . $this->room . "%" . $id . "%");
	  }
	  public function addItem($id) {
		  global $crumbs;
		  if ($crumbs[$id] == null)
			  $this->sendPacket("%xt%e%-1%402%");
		  elseif (in_array($id, $this->inventory))
			  $this->sendPacket("%xt%e%-1%400%");
		  elseif ($this->coins < $crumbs[$id]["cost"])
			  $this->sendPacket("%xt%e%-1%401%");
		  else {
			  $this->inventory[] = $id;
			  $this->coins = $this->coins - $crumbs[$id]["cost"];
			  $this->parent->mysql->query("UPDATE {$this->parent->config->mysql->userTableName} SET items='" . implode(",", $this->inventory) . "', coins='" . $this->getCoins() . "' WHERE id='" . $this->getID() . "';");
			  $this->sendPacket("%xt%ai%-1%" . $id . "%" . $this->getCoins() . "%");
		  }
	  }
	  public function timerKick($minutes, $from){
		  $this->sendPacket("%xt%tk%-1%$minutes%$from%");
	  }
	  public function kick(){
		  $this->sendPacket("%xt%e%-1%5%");
	  }
  }
    class ocpGame {
      public $type;
	  public $roomID;
	  public $server;
	  public function __construct($type, $roomID){
		  $this->type = $type;
		  $this->roomID = $roomID;
	  }
	  public function handlePacket($packet, &$user){
	      $raw = explode("%", $packet);
		  $cmd = $raw[3];
		  if((int)$this->type == 1){
			  //Single User
			  if($cmd == "zo")
				  $user->setCoins((int)$user->getCoins() + ((int) $raw[5] / 10));
		  } else if((int)$this->type == 2){
			  if($cmd == ""){ 	 }
		  } else {
			//Something's gone horribly wrong
			  echo "Somethings gone horribly wrong. Game type: " . $this->type . ", room ID: " . $this->roomID . "\n";
		  }
	  }
  }
  class MySQL {
	  public $host;
	  public $username;
	  public $password;
	  private $ref;
	  public function mysql() {
	  }
	  public function connect($host, $username, $password) {
		  $this->ref = @mysql_connect($host, $username, $password);
		  $this->host = $host;
		  $this->username = $username;
		  $this->password = $password;
		  if ($this->ref == false)
			  return false;
		  else
			  return true;
	  }
	  public function escape($string) {
		  $this->checkConnection();
		  return @mysql_real_escape_string($string, $this->ref);
	  }
	  public function getError() {
		  $this->checkConnection();
		  return mysql_error($this->ref);
	  }
	  public function selectDB($db) {
		  $this->checkConnection();
		  $newRes = @mysql_select_db($db, $this->ref);
		  if ($newRes == true)
			  return true;
		  else
			  return false;
	  }
	  public function query($query) {
		  $this->checkConnection();
		  return @mysql_query($query, $this->ref);
	  }
	  public function getRows($query) {
		  $this->checkConnection();
		  $result = $this->query($query);
		  return @mysql_num_rows($result);
	  }
	  public function returnArray($query) {
		  $this->checkConnection();
		  $result = $this->query($query);
		  
		  if (@mysql_num_rows($result) != 0) {
			  $arr = array();
			  while ($row = @mysql_fetch_assoc($result))
				  $arr[] = $row;
			  return $arr;
		  } else
			  return array();
	  }
	  public function checkConnection() {
		  @$this->connect($this->host, $this->username, $this->password);
	  }
	  public function disconnect(){
		  return @mysql_close($this->ref);
	  }
	  public function __destruct(){
		  $this->disconnect();
		  sleep(4);
	  }
  }
?>
