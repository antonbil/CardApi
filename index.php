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
ordernr INTEGER,
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
cardsin STRING,
cardsout STRING,
 * PRIMARY KEY (game, player,time));');*/

require "notorm/NotORM.php";
require 'vendor/slim/slim/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
/*
 * 
 */
class CardApi extends \Slim\Slim 
{
	private $db;
	const INITIATED = 0;
	const RUNNING = 1;
	const ENDED = 2;
	private static $GAME_STATE = ['initiated', 'running','ended'];
	function gameState($i){
		return self::$GAME_STATE[$i];
	}
	const PLAYING = 0;
	const PASS = 1;
	private static $PLAYER_STATE = ['playing', 'pass'];
	function playerState($i){
		return self::$PLAYER_STATE[$i];
	}
	/*
	return result as JSON-object
	*/
	function returnResult($result){
	    $this->response()->header("Content-Type", "application/json");
		echo json_encode($result);
	}
	
	function returnError($error){
		$result=array("error"=>$error);
	    $this->response()->header("Content-Type", "application/json");
		echo json_encode($result);
	}
	
	function getDB()
	{
		return $this->db;
	}
	/*
	 * initialize db to be used for storage in app
	 */
	function initDB()
	{
		$pdo = new PDO('sqlite:mysqlitedb.db');
		$this->db = new NotORM($pdo);
		//return $db;
	}
   function __construct() {
       parent::__construct();
       $this->initDB();
   }
   /*
    * remove cardnumber from cards
    * return new cards
    * if cardnumber not part of cards return false
    */
   function removeFrom($cardnumber,$cards){
   	$arrcards=json_decode($cards);
	$nr=count($arrcards);
    for ($i = 0; $i < count($arrcards); ++$i) {
        if ($arrcards[$i]==$cardnumber){$nr=$i;};
    }
	if ($nr==count($arrcards))return false;
	array_splice($arrcards, $nr, 1);
   	return json_encode($arrcards);
   }
   function addTo($cardnumber,$cards){
   	$arrcards=json_decode($cards);
	if (!is_int ($cardnumber))$cardnumber=intval($cardnumber);
	$arrcards[]=$cardnumber;
   	return json_encode($arrcards);
   }
   
   function nextMove($gamenr,$nr){
	$findgame=$this->getDB()->game->where(array("gamenumber"=>$gamenr))->fetch();
	$nr2=$this->getDB()->gameuser->where(array("game" => $gamenr))->max("ordernr");
	$nr++;
	if ($nr>$nr2)$nr=1;
	$nr=strval($nr);
	if($this->checkForEndGame($gamenr,$nr)){$this->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("status"=>$this->gameState(CardApi::ENDED)));};
	$result = $this->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("tokenplayer"=>$nr));
   }
   /*
    * returns true if current move is winning
    * if winning status of game is updated in db
    */
   function checkForWinning($gamenr,$cards){
   	$result = false;
   	$arrcards=json_encode($cards);
   	//if player has 31, then end game
   	if ($this->getValue($arrcards)==31){
		$this->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("status"=>$app->gameState(CardApi::ENDED)));
		$result=true;
	}
	return $result;
   }
   function checkForEndGame($gamenr,$nr){
   	//if player has passed in previous, then end game
		$finduser=$this->getDB()->gameuser->where(array("game" => $gamenr, "ordernr" => $nr))->fetch();
		return ($finduser["status"]==$this->playerState(CardApi::PASS));
   	   }
   /*
    * input: array of card-numbers
    * retturns: value for total of cards
    */
   function getValue($cards){
   	$cardvalue=array();
	for ($i=0;$i<count($cards);$i++){
		$cardvalue[]=$this->getCard($cards[$i]);
	}
	$val=0;
	for ($i=0;$i<4;$i++){
		$hval=0;
		for ($j=0;$j<count($cardvalue);$j++){
			if($cardvalue[$j][0]==$i)$hval=$hval+$cardvalue[$j][1];
		}
		if ($hval>$val)$val=$hval;
	}
	if ($val<31)
	if(count($cards)>0){
		$equal=true;
		for ($i=1;$i<count($cardvalue);$i++)
			if (!($cardvalue[0][1]==$cardvalue[$i][1])) $equal=false;
		if ($equal) $val=30.5;
	}
	return $val;
   }
   /*
    * input: card-number
    * returns: deck of card, and value in points
    */
   function getCard($card) {
   	$fl=floor(($card-1) / 13);
	$rm=(($card-1) % 13)+1;
	if ($rm>10) $rm=10;
	if ($rm==1) $rm=11;
	return array($fl,$rm);
   }
   
   function addMove($game,$player,$time,$cardsin,$cardsout){
	$newmoveuser=array(
		"game" => $game,
		"player" => $player,
		"time" => $time,
		"cardsin" => $cardsin,
		"cardsout" => $cardsout
	);
	return $this->getDB()->gamemove->insert($newmoveuser);
   }
   
   /*
    * check if player has token for this game
    * If so, return player , game-instance and token
    * if not, generate error messages, and return false;
    */
function checkgameplayertoken($ip, $gamenr,$status){
	$findgame=$this->getDB()->game->where(array("gamenumber"=>$gamenr,"status"=>$status))->fetch();
	$result="0";
    if ($findgame){
     	$token=$findgame["tokenplayer"];
		$finduser=$this->getDB()->gameuser->where(array("game" => $gamenr, "player" => $ip, "ordernr" => $token))->fetch();
		if (!($token==$finduser["ordernr"])){$this->returnError("player $ip does not have token");return false;}
	} else {$this->returnError("$gamenr not defined or not $status");return false;}
	return array("findgame"=>$findgame,"finduser"=>$finduser,"token"=>$token);
}}
function checkgameplayertokenRunning($ip, $gamenr){
	checkgameplayertoken($ip, $gamenr,$this->gameState(CardApi::RUNNING));
}

// create new Slim instance
$app = new CardApi();

// add new Routes 
//identify(ip,naam)
//test with curl:
//curl  -X POST http://127.0.0.1/anton/cardapi/identify/myip/anton
//of
//curl -H "Content-Type: application/json" -X POST -d '{"username":"xyz","password":"xyz"}' http://127.0.0.1/anton/cardapi/identify/myip/hans
$app->post('/identify/:ip/:naam',function ($ip,$naam) use ($app) {   
    $findplayer=$app->getDB()->player->where("ip", $ip);
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
	$result = $app->getDB()->player->insert($newplayer);
        $players=$result;
    }
    $app->returnResult($players);
   $db=null;
});

$app->post('/initiategame/:ip' ,function ($ip) use ($app) {
	$nr=$app->getDB()->game->max("gamenumber");
	$status=$app->gameState(CardApi::INITIATED);//'initiated';
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
	$result = $app->getDB()->game->insert($newgame);
	//gameuser(usernr,gamenumber,ordernr,status)
	$newgameuser=array(
		"game" => $nr+1,
		"player" => $ip,
		"ordernr" => 1,
		"status" => $status,
		"cards" => json_encode(array())
	);
	$result2 = $app->getDB()->gameuser->insert($newgameuser);
	$app->returnResult($result);
	});
//curl -X POST http://127.0.0.1/anton/cardapi/initiategame/myip2
$app->get('/askstartinggames/:ip',function ($ip) use ($app) { 
    $findgames=$app->getDB()->gameuser->where("status", $app->gameState(CardApi::INITIATED));
    if (count($findgames)>0){
     $games = array();
     foreach ($findgames as $game) {
        $games[]  = array(
            "id" => $game["game"],
            "player" => $game["player"]
        );
      }

      $app->returnResult($games);
    }
});
//returns list of ip for all players who have applied for a game
$app->get('/askdatastartinggame/:ip/:gamenr',function ($ip, $gamenr) use ($app) { 
});


//returns name of ipplayer if ip is known to system, otherwise -1
$app->get('/getplayerinfo/:ip/:ipplayer',function ($ip, $ipplayer) use ($app) { 
});

//returns 1 if game still starting, and not full, and ip has not applied for this game yet. Otherwise 0.
$app->post('/applyforgame/:ip/:gamenr',function ($ip, $gamenr) use ($app) {
	
	//check if user exists
    $findplayer=$app->getDB()->player->where(array("ip" => $ip));
	if (!(count($findplayer)>0)){$app->returnError("no player with $ip");return;}
	//check if game already initated
    $findusers=$app->getDB()->gameuser->where(array("game" => $gamenr));
	if (!(count($findusers)>0)){$app->returnError("game $gamenr not initiated yet");return;}
	//check if user not already applied for this game
    $findusers=$app->getDB()->gameuser->where(array("game" => $gamenr, "player" => $ip))->fetch();
	if ((count($findusers)>1)){$app->returnError("$ip already part of game $gamenr");return;}
	$nr=$app->getDB()->gameuser->max("ordernr");
	$status=$app->gameState(CardApi::PLAYING);//'initiated';
		$newgameuser=array(
		"game" => $gamenr,
		"player" => $ip,
		"ordernr" => $nr+1,
		"status" => $status,
		"cards" => json_encode(array())
	);
	$result2 = $app->getDB()->gameuser->insert($newgameuser);
	$app->returnResult(1); 
});

//returns 1 if game is started, 0 if ip has not initiated game or no players yet
$app->post('/startgame/:ip/:gamenr',function ($ip, $gamenr) use ($app) {
	$gameplayer=$app->checkgameplayertoken($ip, $gamenr,$app->gameState(CardApi::INITIATED));
	if (!$gameplayer)return;
	if (!($gameplayer["token"]==1)){$app->returnError("$ip has not initiated game $gamenr");return;}
	//get all players
    $findusers=$app->getDB()->gameuser->where("game", $gamenr);
		//for all players: give them three cards
		$cards=$gameplayer["findgame"]["restcards"];
		//var_dump($findusers);
    foreach ($findusers as $user) {
      //for ($i=0;$i<count($findusers);$i++) {
          //$user=$findusers[$i];
		  $playercards=array();
		  for ($j=1;$j<4;$j++){
		  	$mycards=json_decode($cards);
			  $number=rand ( 0 , count($mycards)-1 );
			  $cardnumber=$mycards[$number];
			  $playercards[]=$cardnumber;
		  	$cards=$app->removeFrom($cardnumber,$cards);
		  }
		  //save $playercards to db
		  $app->getDB()->gameuser->insert_update(array("player"=>$user["player"],"game"=>$user["game"]), array(), array("cards"=>json_encode($playercards)));
	   }
	  //give the table cards, and save it in game
	  $deckcards=array();
	  for ($j=1;$j<4;$j++){
	  	  $mycards=json_decode($cards);
		  $number=rand ( 0 , count($mycards)-1 );
		  $cardnumber=$mycards[$number];
		  $deckcards[]=$cardnumber;
	  	  $cards=$app->removeFrom($cardnumber,$cards);
	  }
	  //set token = 1 in game, and status=running
	  $result=$app->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("deckontable"=>json_encode($deckcards),"restcards"=>$cards,"tokenplayer"=>1,
		"status"=>$app->gameState(CardApi::RUNNING)));
	  $app->returnResult(1);
});
//returns the ip which has the token for the game, 0 if game not started yet etc.
$app->get('/gettoken/:ip/:gamenr',function ($ip, $gamenr) use ($app) { 
});
//returns the cards a player has (array of cardnumbers) game is still playing, 0 otherwise 
$app->get('/gethand/:ip/:gamenr',function ($ip, $gamenr) use ($app) { 
});
/*//returns cardnumber which player gets if player has token, 0 otherwise
$app->get('/getcard/:ip/:gamenr',function ($ip, $gamenr) use ($app) { 
});*/
//returns 1 if ip has token, 0 otherwise.
//swaps $cardnumberin for $cardnumberout
$app->post('/exchangecard/:ip/:gamenr/:cardnumberin/:carnumberout',function ($ip, $gamenr, $cardnumberin,$cardnumberout) use ($app) {
	$gameplayer=$app->checkgameplayertokenRunning($ip, $gamenr);
	if (!$gameplayer)return;
	
	$cards=$gameplayer["finduser"]["cards"];
	$cards=$app->removeFrom($cardnumberout,$cards);
	if (!$cards){$app->returnError("$cardnumberout not part of cards of player");return;}
	$cardsontable=$app->removeFrom($cardnumberin,$gameplayer["findgame"]["deckontable"]);
	if (!$cardsontable){$app->returnError("$cardnumberin not part of deckontable");return;}
	$cards=$app->addTo($cardnumberin,$cards);
	$cardsontable=$app->addTo($cardnumberout,$cardsontable);
	$app->getDB()->gameuser->insert_update(array("player"=>$gameplayer["finduser"]["player"],"game"=>$gameplayer["finduser"]["game"]), array(), array("cards"=>$cards));
	$app->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("deckontable"=>$cardsontable));
	if (!$app->checkForWinning($gamenr,$cards))
		$app->nextMove($gamenr,$gameplayer["token"]);
	$app->addMove($gamenr,$ip,date('Y-m-d H:i:s'),json_encode(array(intval ($cardnumberin))),json_encode(array(intval ($cardnumberout))));
	$result = 1;
    $app->returnResult($result);
	 
});
//-getexchange(ip,ipplayer,gamenr) get
//return cardnumber last pushed of player
$app->get('/getexchange/:ip/:gamenr/:ipplayer',function ($ip, $gamenr, $ipplayer) use ($app) { 
});
//-getdeckontable(ip,gamenr) get
//returns list of cardnumbers lying open to see
$app->get('/getdeckontable/:ip/:gamenr',function ($ip, $gamenr) use ($app) { 
});
//-swapcards(ip,gamenr) post
//returns list of cards lying on table if player has token, otherwise error
$app->post('/swapcards/:ip/:gamenr',function ($ip, $gamenr) use ($app) {
	$result="0";
	$gameplayer=$app->checkgameplayertokenRunning($ip, $gamenr);
	if (!$gameplayer)return;
	
	$cardsontable=$gameplayer["finduser"]["cards"];
	$cards=$gameplayer["findgame"]["deckontable"];
	$app->getDB()->gameuser->insert_update(array("player"=>$gameplayer["finduser"]["player"],"game"=>$gameplayer["finduser"]["game"]), array(), array("cards"=>$cards,"status"=>$app->playerState(CardApi::PASS)));
	$app->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("deckontable"=>$cardsontable));
	if (!$app->checkForWinning($gamenr,$cards))
		$app->nextMove($gamenr,$gameplayer["token"]);
	$app->addMove($gamenr,$ip,date('Y-m-d H:i:s'),$cards,$cardsontable);
	$result=$cards;
    $app->returnResult($result);
	 
});
//-offerpass(ip,gamenr) post
//returns 1 if player has token, error otherwise
$app->post('/offerpass/:ip/:gamenr',function ($ip, $gamenr) use ($app) {
	$result="0";
	$gameplayer=$app->checkgameplayertokenRunning($ip, $gamenr);
	if (!$gameplayer)return;
	$app->getDB()->gameuser->insert_update(array("player"=>$gameplayer["finduser"]["player"],"game"=>$gameplayer["finduser"]["game"]), array(), array("status"=>$app->playerState(CardApi::PASS)));
	//cards not changed, so always continue with next move
	$app->nextMove($gamenr,$gameplayer["token"]);
	$app->addMove($gamenr,$ip,date('Y-m-d H:i:s'),json_encode(array()),json_encode(array()));
	$result=1;
    $app->returnResult($result);
	 
});
//-claimwin(ip,gamenr) post
//returns 1 if player has won, and has token. error otherwise
$app->get('/claimwin/:ip/:gamenr',function ($ip, $gamenr) use ($app) { 
});
//-getstategame(ip,gamenr) get
//returns one of: initiated,started,ended
$app->get('/getstategame/:ip/:gamenr',function ($ip, $gamenr) use ($app) { 
});
//-getresultgame(ip,gamenr, ipplayer) get
//returns list of cards for ipplayer if game is ended.
$app->get('/getresultgame/:ip/:gamenr:/ipplayer',function ($ip, $gamenr, $ipplayer) use ($app) { 
});
//-getwinnergame(ip,gamenr) get
//returns ip of winning player if game is ended
$app->get('/getwinnergame/:ip/:gamenr',function ($ip, $gamenr) use ($app) {
	$findgame=$app->getDB()->game->where("gamenumber",$gamenr);
    $winner = array("winner" => "none");
    if (count($findgame)>0){
     foreach ($findgame as $game) {
     	if ($game["status"]==$app->gameState(CardApi::ENDED))
        $winner  = array(
            "winner" => $game["winner"]
        );
      }

    }
    $app->returnResult($winner);
});

$app->get('/getvalue/:cards',function ($cards) use ($app) {
	$parts = explode(",", $cards);
	$val=$app->getValue($parts);
	$app->returnResult($val);
});

// run the Slim app
$app->run();


?> 
