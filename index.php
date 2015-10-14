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

global $GAME_STATE;
$GAME_STATE = ['initiated', 'running','ended'];
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
	$status=$GLOBALS['GAME_STATE'][0];//'initiated';
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
    $findgames=$db->gameuser->where("status", $GLOBALS['GAME_STATE'][0]);
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
//returns list of ip for all players who have applied for a game
$app->get('/askdatastartinggame/:ip/:gamenr',function ($ip, $gamenr) use ($app, $db) { 
});


//returns name of ipplayer if ip is known to system, otherwise -1
$app->get('/getplayerinfo/:ip/:ipplayer',function ($ip, $ipplayer) use ($app, $db) { 
});

//returns 1 if game still starting, and not full, and ip has not applied for this game yet. Otherwise 0.
$app->get('/applyforgame/:ip/:gamenr',function ($ip, $gamenr) use ($app, $db) { 
});

//returns 1 if game is started, 0 if ip has not initiated game or no players yet
$app->get('/startgame/:ip/:gamenr',function ($ip, $gamenr) use ($app, $db) { 
});
//returns the ip which has the token for the game, 0 if game not started yet etc.
$app->get('/gettoken/:ip/:gamenr',function ($ip, $gamenr) use ($app, $db) { 
});
//returns the cards a player has (array of cardnumbers) game is still playing, 0 otherwise 
$app->get('/gethand/:ip/:gamenr',function ($ip, $gamenr) use ($app, $db) { 
});
//returns cardnumber which player gets if player has token, 0 otherwise
$app->get('/getcard/:ip/:gamenr',function ($ip, $gamenr) use ($app, $db) { 
});
//returns 1 if ip has token, 0 otherwise.
$app->get('/pushcard/:ip/:gamenr/:cardnumber',function ($ip, $gamenr, $cardnumber) use ($app, $db) { 
});
//-getexchange(ip,ipplayer,gamenr) get
//return cardnumber last pushed of player
$app->get('/getexchange/:ip/:gamenr/:ipplayer',function ($ip, $gamenr, $ipplayer) use ($app, $db) { 
});
//-getdeckontable(ip,gamenr) get
//returns list of cardnumbers lying open to see
$app->get('/getdeckontable/:ip/:gamenr',function ($ip, $gamenr) use ($app, $db) { 
});
//-swapcards(ip,gamenr) post
//returns list of cards lying on table if player has token, otherwise error
$app->get('/swapcards/:ip/:gamenr',function ($ip, $gamenr) use ($app, $db) { 
});
//-offerpass(ip,gamenr) post
//returns 1 if player has token, error otherwise
$app->get('/offerpass/:ip/:gamenr',function ($ip, $gamenr) use ($app, $db) { 
});
//-claimwin(ip,gamenr) post
//returns 1 if player has won, and has token. error otherwise
$app->get('/claimwin/:ip/:gamenr',function ($ip, $gamenr) use ($app, $db) { 
});
//-getstategame(ip,gamenr) get
//returns one of: initiated,started,ended
$app->get('/getstategame/:ip/:gamenr',function ($ip, $gamenr) use ($app, $db) { 
});
//-getresultgame(ip,gamenr, ipplayer) get
//returns list of cards for ipplayer if game is ended.
$app->get('/getresultgame/:ip/:gamenr:/ipplayer',function ($ip, $gamenr, $ipplayer) use ($app, $db) { 
});
//-getwinnergame(ip,gamenr) get
//returns ip of winning player if game is ended
$app->get('/getwinnergame/:ip/:gamenr',function ($ip, $gamenr) use ($app, $db) { 
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
