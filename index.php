<?php
// //created september 2015 by anton bil
//db structure:
/*$db = new SQLite3('mysqlitedb.db');*/
/*game(gamenumber,status,starter,tokenplayer,deckontable,restcards,winner)
gameuser(usernr,gamenumber,ordernr,status)
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

require 'vendor/slim/slim/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
// create new Slim instance
$app = new \Slim\Slim();
//propel is een alternatief voor notorm
//put voor nieuwe en update
//syncen van namen velden en api-parameters
$db=getDB();
// add new Route 
$app->get("/", function () {
    echo "<h1>Hello Slim World</h1>";
});//
//identify(ip,naam)
//curl  -X POST http://127.0.0.1/anton/cardapi/identify/myip/anton
//of
//curl -H "Content-Type: application/json" -X POST -d '{"username":"xyz","password":"xyz"}' http://127.0.0.1/anton/cardapi/identify/myip/hans
$app->post('/identify/:ip/:naam',function ($ip,$naam) use ($app, $db) {   
    $findplayer=$db->player->where("ip", $ip);
//var_dump($findplayer);
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
        $newplayer=array(
	  "id" => $id, // omit or null for auto_increment
	  "ip" => $ip,
	  "name" => $naam,
	  "status" => $status,
	);
	$result = $db->player->insert($newplayer);
        $players=$result;
    }
    returnResult($app,$players);
   $db=null;
});

$app->post('/initiategame/:ip',function ($ip) use ($app, $db) {  
    $nr=$db->game->max("gamenumber");
	$status='initiated';
        $cards=array();
        for ($i=1;$i<53;$i++)$cards[]=$i;
        $newgame=array(
	  "gamenumber" => $nr+1,
	  "starter" => $ip,
	  "tokenplayer" => 1,
	  "status" => $status,
          "restcards" => json_encode($cards),
          "deckontable" => json_encode(array())
	);
	$result = $db->game->insert($newgame);
        //gameuser(usernr,gamenumber,ordernr,status)
        $newgameuser=array(
	  "game" => $nr+1,
	  "player" => $ip,
	  "ordernr" => 1,
	  "status" => $status,
          "cards" => json_encode(array())
	);
	$result2 = $db->gameuser->insert($newgameuser);
    returnResult($app,$result);
});
//curl -X POST http://127.0.0.1/anton/cardapi/initiategame/myip2
$app->get('/askstartinggames/:ip',function ($ip) use ($app, $db) { 
    $findgames=$db->gameuser->where("player", $ip);
    if (count($findgames)>0){
     $games = array();
     foreach ($findgames as $game) {
        $games[]  = array(
            "id" => $game["game"],
            "player" => $game["player"]
        );
      }

    returnResult($app,$games);
      }
});
// run the Slim app
$app->run();

/*
return result as JSON-object
*/
function returnResult($app,$result){
    $app->response()->header("Content-Type", "application/json");
	echo json_encode($result);
}

function getDB()
{

require "notorm/NotORM.php";

$pdo = new PDO('sqlite:mysqlitedb.db');
$db = new NotORM($pdo);
return $db;
}
?> 
