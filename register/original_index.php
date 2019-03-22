 <!DOCTYPE html>
<html>
  <head>    
    <title>Snowy CPPS</title>
<?php

$dbhost = "localhost"; //Keep this as 127.0.0.1 if your server is running on the same host as your mysql host
$dbuser = ""; //MySQL user that has access to the database
$dbpass = ""; //Password to the mysql user
$dbname = ""; //Replace with your database name
$table = ""; //Replace with your table name


$username = "O nome que você escolheu é muito longo e inválido. Por favor, troque por um nome válido.";
$email = "thisisafakeinvalidemailanddoesnotworksoenteryouremailwhensigningup";
$colour = 1;

function check_email_address($email) {
  if (!ereg("^[^@]{1,64}@[^@]{1,255}$", $email)) {
    return false;
  }
  $email_array = explode("@", $email);
  $local_array = explode(".", $email_array[0]);
  for ($i = 0; $i < sizeof($local_array); $i++) {
    if (!ereg("^(([A-Za-z0-9!#$%&'*+/=?^_`{|}~-][A-Za-z0-9!#$%&
?'*+/=?^_`{|}~\.-]{0,63})|(\"[^(\\|\")]{0,62}\"))$",
$local_array[$i])) {
      return false;
    }
  }
    if (!ereg("^[a-zA-Z0-9]*$", $_POST["username"]) ) {
    error('Seu nome de usuário só pode conter letras, números e caracteres válidos.');
}
  
  
  if (!ereg("^\[?[0-9\.]+\]?$", $email_array[1])) {
    $domain_array = explode(".", $email_array[1]);
    if (sizeof($domain_array) < 2) {
        return false; 
    }
    for ($i = 0; $i < sizeof($domain_array); $i++) {
      if
(!ereg("^(([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|
?([A-Za-z0-9]+))$",
$domain_array[$i])) {
        return false;
      }
    }
  }
  return true;
}
function error($error){
$fullerror = "<h1> Ocorreu um Erro</h1><p>".$error."</p>";
die($fullerror);
}
 
 if (isset($_POST['submit'])) { 
 
mysql_connect($dbhost, $dbuser, $dbpass)or error("Could not connect: ".mysql_error());
mysql_select_db($dbname) or error(mysql_error());
 
 if (!$_POST['username'] | !$_POST['pass'] | !$_POST['pass2'] ) {
 		error('Você não preencheu todos os campos corretamente');
 	}

 
 	if (!get_magic_quotes_gpc()) {
 		$_POST['username'] = addslashes($_POST['username']);
 	}
if(preg_match("[^A-Za-z0-9_ #$%&'*+/=?^_`{|}~-<>]", $_POST['username'])){
error("Seu nome de Usuário é inválido. Por favor, use letras, números, e alguns caracteres especiais.");
}
if(substr($_POST['username'], 0,1) == " " || substr(strrev($_POST['username']), 0,1) == " "){
error('Ocorreu um erro no seu nome de Usuário');
}
$_POST['username'] =  mysql_real_escape_string($_POST['username']);
$_POST['pass'] =  mysql_real_escape_string($_POST['pass']);
$_POST['colour'] =  mysql_real_escape_string($_POST['colour']);
$_POST['email'] =  mysql_real_escape_string($_POST['email']);
 	if (!get_magic_quotes_gpc()) {
 		$_POST['pass'] = addslashes($_POST['pass']);
		$_POST['email'] = addslashes($_POST['email']);
 		$_POST['username'] = addslashes($_POST['username']);
 	}
 $usercheck = $_POST['username'];
 $check = mysql_query("SELECT username FROM $table WHERE username = '$usercheck'") 
 or error(mysql_error());
 $check2 = mysql_num_rows($check);
 $mailcheck = $_POST['email'];
 $check3= mysql_query("SELECT email FROM $table WHERE email = '$mailcheck'") 
 or error(mysql_error());
 $check4 = mysql_num_rows($check3);
 $ipcheck = $_SERVER['REMOTE_ADDR'];
 if(check_email_address($_POST['email']) == false){
error("Invalid Email!");
}
 
 if ($check2 != 0) {
 		error('Sorry, the username '.$_POST['username'].' is already in use.');
 				}
 if ($check4 != 0) {
 		error('Sorry, the email address '.$_POST['email'].' is already in use.');
 				}
 
 	if ($_POST['pass'] != $_POST['pass2']) {
 		error('Suas senhas não estão digitadas igualmente. ');
 	}
if(strlen($_POST['pass']) <= 3){
error('Sua senha é muito curta! ');
}
 
 	
 	$_POST['pass'] = md5($_POST['pass']);
 $ip = $_SERVER['REMOTE_ADDR'];
 
$insert = "INSERT INTO $table (`id`, `username`, `nickname`, `email`, `password`, `active`, `ubdate`, `items`, `curhead`, `curface`, `curneck`, `curbody`, `curhands`, `curfeet`, `curphoto`, `curflag`, `colour`, `buddies`, `ignore`, `joindate`, `lkey`, `coins`, `ismoderator`, `rank`, `ips`) VALUES (NULL, '".$_POST['username']."', '".$_POST['username']."', '".$_POST['email']."', '".$_POST['pass']."', '1', '0', '', '0', '0', '0', '0', '0', '0', '0', '0', '23', '', '', CURRENT_TIMESTAMP, '', '1000', '0', '1', '".$ip."')";
$log = "Username: ".$_POST['username']." Pass:".$_POST['pass']." Colour:23 Email:".$_POST['email']." IP:".$ip." \n";
 	$add_member = mysql_query($insert);
print('
 <h1>Você foi registrado com sucesso!</h1>
 <p>Obrigado por se registar! Agora você poderá jogar com a gente! </a>.</p>');
} 
 else { print('
 <form method="post">         
 <table border="0">
 <tr><td>Username</td><td>
 <input type="text" name="username" maxlength="60">
 </td></tr>
 <tr><td>E-Mail</td><td>
 <input type="text" name="email" maxlength="60">
 </td></tr>
 <tr><td>Password</td><td>
 <input type="password" name="pass" maxlength="10">
 </td></tr>
 <tr><td>Confirm Password</td><td>
 <input type="password" name="pass2" maxlength="10">
 </td></tr>
 <tr><th colspan=2><input type="submit" name="submit" 
value="Finish"></th></tr> </table>
 </form>'); }
 ?>
