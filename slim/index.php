<?php
require 'vendor/slim/slim/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
// create new Slim instance
$app = new \Slim\Slim();
$db=getDB();
// add new Route 
$app->get("/", function () {
    echo "<h1>Hello Slim World</h1>";
});//
$app->get('/users',getUsers);
//identify(ip,naam)
$app->get('/identify/:ip/:naam',function ($ip,$naam) use ($app, $db) {   
    $findplayer=$db->player->where("ip", $ip);
    if (count($findplayer)>0){
     $players = array();
     foreach ($findplayer as $player) {
        $players[]  = array(
            "id" => $player["id"],
            "ip" => $player["ip"],
            "name" => $player["name"],
            "status" => $player["status"]
        );
      }
     } else {
	$id=$_SERVER['REMOTE_ADDR'];
	$status='ok';
	$result = $db->player->insert(array(
	  "id" => $id, // omit or null for auto_increment
	  "ip" => $ip,
	  "name" => $naam,
	  "status" => $status,
	));
        $players=$result;
    }
    $app->response()->header("Content-Type", "application/json");
    echo json_encode($players);
   $db=null;
});
$app->get('/askstartinggames/:ip',askStartingGames);
$app->get('/hello/:name', function ($name) {
    echo "Hello, $name";
});
// run the Slim app
$app->run();
function identify($ip,$naam){
   $db=getDB();//(ip,id,name,status)
    $players = array();
     foreach ($db->player() as $player) {
        $players[]  = array(
            "id" => $player["id"],
            "ip" => $player["ip"],
            "name" => $player["name"],
            "status" => $player["status"]
        );
    }
    $app->response()->header("Content-Type", "application/json");
    echo json_encode($players);
/*   $id='zomaarwat';
   $status='ok';
   $db->exec("insert into player (`ip`,`id`,`name`,`status`) VALUES (\"$ip\", \"$id\", \"$naam\",\"$status\")");*/
   $db=null;
}
function getUsers() {
$c = array("anton","jannie","wieneke","jelma");
echo '{"users": ' . json_encode($c) . '}';
}
function askStartingGames($ip) {
    echo "<h1>Hai $ip</h1>";
}

function getDB()
{
/*$db = new SQLite3('mysqlitedb.db');*/
/*game(gamenumber,status,starter,tokenplayer,deckontable,restcards,winner)
gameuser(usernr,gamenumber,status)
player(ip,id,name,status)
playercards(player,game,cards)
gamemove(game,player,cards,time)
*/
/*$db->exec('CREATE TABLE IF NOT EXISTS
game (gamenumber STRING PRIMARY KEY,
status STRING,
starter STRING,
tokenplayer STRING,
deckontable STRING,
restcards STRING,
winner STRING);');
//gameuser(usernr,gamenumber,status,cards)
// IF NOT EXISTS 
$db->exec('CREATE TABLE IF NOT EXISTS
gameuser (player STRING,
game STRING,
status STRING,
cards STRING,
PRIMARY KEY (player, game));');
//player(ip,id,name,status)
$db->exec('CREATE TABLE IF NOT EXISTS
player (ip STRING PRIMARY KEY,
id STRING,
name STRING,
status STRING);');
//gamemove(game,player,cards,time)
$db->exec('CREATE TABLE IF NOT EXISTS
gamemove (game STRING,
player STRING,
time STRING,
cards STRING,
PRIMARY KEY (game, player,time));');*/
require "notorm/NotORM.php";

$pdo = new PDO('sqlite:mysqlitedb.db');
$db = new NotORM($pdo);
return $db;
}
?> 
