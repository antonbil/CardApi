<?php
// //created september 2015 by anton bil
//db structure:
/*$db = new SQLite3('mysqlitedb.db');*/
/*game(gamenumber,status,starter,tokenplayer,deckontable,restcards,winner)
gameuser(usernr,gamenumber,ordernr,status,password)
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
paasword STRING,
status STRING);');
//gamemove(game,player,cards,time)
$db->exec('CREATE TABLE IF NOT EXISTS
gamemove (game STRING,
player STRING,
time STRING,
cardsin STRING,
cardsout STRING,
PRIMARY KEY (game, player,time));');
CREATE  TABLE "main"."commercial" ("title" STRING PRIMARY KEY  NOT NULL , "firstline" STRING, "description" STRING, "picture" BLOB)
 */

require "notorm/NotORM.php";
//http://www.notorm.com/#api
require 'vendor/slim/slim/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
/*
 * 
 */
class CardApi extends \Slim\Slim 
{
	const ADMINPASSWORD = "CardApi";
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
	}/*
	 * constructor for class
	 * initializes db.
	 */
   function __construct() {
       parent::__construct();
       $this->initDB();
   }
	/*
	return result as JSON-object
	*/
	function returnResult($result){
	    $this->response()->header("Content-Type", "application/json");
		echo json_encode($result);
	}
	/*
	 * send back error message in JSON
	 */
	function returnError($error){
		$result=array("error"=>$error);
	    $this->response()->header("Content-Type", "application/json");
		echo json_encode($result);
	}
	
	/*
	 * getter for db
	 */
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
   /*
    * add card to set of cards
    */
   function addTo($cardnumber,$cards){
   	$arrcards=json_decode($cards);
	if (!is_int ($cardnumber))$cardnumber=intval($cardnumber);
	$arrcards[]=$cardnumber;
   	return json_encode($arrcards);
   }
   
   /*
    * sets up state for next move
    * if game is ended game endstate is set up.
    */
   function nextMove($gamenr,$nr){
	$findgame=$this->getDB()->game->where(array("gamenumber"=>$gamenr))->fetch();
	$nr2=$this->getDB()->gameuser->where(array("game" => $gamenr))->max("ordernr");
	$nr++;
	if ($nr>$nr2)$nr=1;
	$nr=strval($nr);
	if($this->checkForEndGame($gamenr,$nr)){
		$this->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("status"=>$this->gameState(CardApi::ENDED)));
		$this->getWinner($gamenr);
	};
	$result = $this->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("tokenplayer"=>$nr));
   }
   /*
    * returns true if current move is winning
    * if winning status of game is updated in db
    */
   function checkForWinning($gamenr,$cards){
   	$result = false;
   	$arrcards=json_decode($cards);
   	//if player has 31, then end game
   	if ($this->getValue($arrcards)==31){
		$this->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("status"=>$this->gameState(CardApi::ENDED)));
		$this->getWinner($gamenr);
		$result=true;
	}
	return $result;
   }
   /*
    * check if game is ended.
    * if player has passed, and gets token, then game is ended.
    */
   function checkForEndGame($gamenr,$nr){
   	//if player has passed in previous, then end game
		$finduser=$this->getDB()->gameuser->where(array("game" => $gamenr, "ordernr" => $nr))->fetch();
		return ($finduser["status"]==$this->playerState(CardApi::PASS));
   	   }
   /*
    * input: array of card-numbers
    * returns: value for total of cards
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
			if (!($cardvalue[0][2]==$cardvalue[$i][2])) $equal=false;
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
	//$rv = cardnumber inside harten/klaver/etc
	$rv=(($card-1) % 13)+1;
	//$rm = value of card when summed up
	$rm=$rv;
	if ($rm>10) $rm=10;
	if ($rm==1) $rm=11;
	return array($fl,$rm,$rv,$card);
   }
    
    /*
    * calculates the winner if game is ended
    */
   function getWinner($gamenr){
   		//get all players
    $findusers=$this->getDB()->gameuser->where("game", $gamenr);
	$userwin=null;
	$max=0;
	$values=array();
    foreach ($findusers as $user) {
    	$cards=$user["cards"];
		$val=$this->getValue(json_decode($cards));
		if ($val>$max){
			$max=$val;
			$userwin=$user;
		}
		$values[]=array("player"=>$user["player"],"value"=>$val);
	}
	$ip1=$userwin["player"];
	$this->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("winner"=>$ip1));
	return array("winner"=>$ip1,"winningvalue"=>$max,"data"=>$values); 
	
   }
   /*
    * add move to gamemove table
    */
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
	function checkgameplayertoken($ip, $gamenr,$status,$checkToken){
		$findgame=$this->getDB()->game->where(array("gamenumber"=>$gamenr,"status"=>$status))->fetch();
		$result="0";
	    if ($findgame){
	     	$token=$findgame["tokenplayer"];
			if($checkToken)
			$finduser=$this->getDB()->gameuser->where(array("game" => $gamenr, "player" => $ip, "ordernr" => $token))->fetch();
			else
			$finduser=$this->getDB()->gameuser->where(array("game" => $gamenr, "player" => $ip))->fetch();
			if($checkToken)
				if (!($token==$finduser["ordernr"])){$this->returnError("player $ip does not have token");return false;}
		} else {$this->returnError("$gamenr not defined or not $status");return false;}
		return array("findgame"=>$findgame,"finduser"=>$finduser,"token"=>$token);
	}
	/*
	 * check if player has token for game that is running
	 */
	function checkgameplayertokenRunning($ip, $gamenr){
		return $this->checkgameplayertoken($ip, $gamenr,$this->gameState(CardApi::RUNNING),true);
	}
	/*
	 * check if password belongs to player with ip
	 */
	function identifyPlayer($ip){
		$password=$this->request()->post('password');
		$identifyplayer=$this->getDB()->player->where(array("ip"=>$ip,"password"=>$password));
		if (count($identifyplayer)==0){$this->returnError("player $ip unknown or password incorrect");return;}
		return true;
	}
	function identifyAdmin(){
		$password=$this->request()->post('password');
		//var_dump($password);
		$identifyplayer=$this->getDB()->player->where(array("ip"=>"admin","password"=>$password));
		$error=false;
		if (count($identifyplayer)==0){
			//no admin-password set yet
			//check for default admin-password
			$identifyplayer=$this->getDB()->player->where(array("ip"=>"admin"));
			if (count($identifyplayer)==0){
			if (!($password==CardApi::ADMINPASSWORD))
				$error=true;
			} else $error=true;//admin with wrong password
		}
		if ($error){$this->returnError("player admin unknown or password ($password) incorrect");}
		return !$error;
	}
	function createTable($pdo,$table,$fields){
		$query="DROP TABLE IF EXISTS $table;CREATE TABLE $table ($fields);";
		return $pdo->exec($query);
	}
}//end class CardApi

// create new Slim instance
$app = new CardApi();

//start services
// add new Routes 
//identify(ip,naam)
//test with curl:
//curl  -X POST http://127.0.0.1/anton/cardapi/identify/myip/anton
//of
//curl -H "Content-Type: application/json" -X POST -d '{"username":"xyz","password":"xyz"}' http://127.0.0.1/anton/cardapi/players/myip/add/hans
$app->post('/players/:ip/add/:naam',function ($ip,$naam) use ($app) {   
    $findplayer=$app->getDB()->player->where("ip", $ip);
	$password=$app->request()->post('password');
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
	  "password" => $password
	);
	
	$result = $app->getDB()->player->insert($newplayer);
        $players=$newplayer;
    }
    $app->returnResult(array(
            "player" => $players));
   $db=null;
});
//validate if player with ip has password
$app->post('/players/:ip/validate',function ($ip) use ($app) {   
	if (!$app->identifyPlayer($ip)) return;
    $app->returnResult(array(
            "result" => "ok"));
   $db=null;
});
//curl -X POST   --data "password=p3" http://192.168.2.8/CardApi/games/n132/initiate
$app->post('/games/:ip/initiate' ,function ($ip) use ($app) {
	if (!$app->identifyPlayer($ip)) return;
	$nr=$app->getDB()->game->max("gamenumber");
	$status=$app->gameState(CardApi::INITIATED);//'initiated';
	$cards=array();
	for ($j=0;$j<4;$j++){
	  $cards[]=$j*13+1;//cards 2-6 are not dealt.
	  for ($i=6;$i<13;$i++)$cards[]=$i+$j*13+1;
	}
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
	$app->returnResult(array(
            "result" => $result));
	});
//curl -X GET http://127.0.0.1/anton/cardapi/games/myip/starting
$app->get('/games/:ip/starting',function ($ip) use ($app) { 
    $findgames=$app->getDB()->game->where("status", $app->gameState(CardApi::INITIATED));
    if (count($findgames)>0){
     $games = array();
     foreach ($findgames as $game) {
        $games[]  = array(
            "id" => $game["gamenumber"],
            "player" => $game["starter"]
        );
      }

      $app->returnResult(array(
            "games" => $games));
    } else $app->returnError("no games defined yet");return;
});
$app->get('/games/:ip/all',function ($ip) use ($app) { 
//game (gamenumber ,status,starter
    $findgames=$app->getDB()->game();
    if (count($findgames)>0){
     $games = array();
     foreach ($findgames as $game) {
        $games[]  = array(
            "starter" => $game["starter"],
            "gamenumber" => $game["gamenumber"],
            "status" => $game["status"]
        );
      }

      $app->returnResult(array(
            "games" => $games));
    } else $app->returnError("no games defined yet");return;
});
//returns list of ip for all players who have applied for a game
//curl -X GET http://127.0.0.1/anton/cardapi/initiategame/myip2
$app->get('/games/:ip/:gamenr/players',function ($ip, $gamenr) use ($app) {
	$findplayer=$app->getDB()->gameuser->where("game", $gamenr);
	if (count($findplayer)==0){$app->returnError("no player for game $gamenr");return;}
	//TODO: if sqlite, findplayer is in order of addition
	//if mysql, they are ordered by name
	$players=array();
	foreach ($findplayer as $player) {
    	$players[]=$player["player"];//gameuser (player ,game,status,cards,ordernr INTEGER,

	}
	$app->returnResult(array(
            "players" => $players)); 
});

//returns all players with ordernr for one game
$app->get('/games/:ip/:gamenr/playersadv',function ($ip, $gamenr) use ($app) {
	$findplayer=$app->getDB()->gameuser->where("game", $gamenr);
	if (count($findplayer)==0){$app->returnError("no player for game $gamenr");return;}
	$players=array();
	foreach ($findplayer as $player) {
    	$players[]=array(//gameuser (player ,game,status,cards,ordernr INTEGER,
            "ordernr" => $player["ordernr"],
            "player" => $player["player"]
	  );

	}
	$app->returnResult(array(
            "players" => $players)); 
});


//returns name of ipplayer if ip is known to system, otherwise error
$app->get('/players/:ip/:ipplayer/name',function ($ip, $ipplayer) use ($app) {
	$findplayer=$app->getDB()->player->where("ip", $ipplayer);
	if (count($findplayer)==0){$app->returnError("no player $ipplayer");return;}
    foreach ($findplayer as $player) {
    	$result=$player["name"];
	}
	$app->returnResult(array(
            "name" => $result)); 
	 
});

//returns 1 if game still starting, and not full, and ip has not applied for this game yet. Otherwise 0.
//player applies for game
$app->post('/games/:gamenr/apply/:ip',function ($ip, $gamenr) use ($app) {
	
	//check if user exists
	if (!$app->identifyPlayer($ip)) return;
	//check if game already initated
	$findgame=$app->getDB()->game->where("gamenumber",$gamenr);//CardApi::INITIATED
	$result="";
    if (count($findgame)>0){
     $error=false;
     foreach ($findgame as $game) {
     	if (!(CardApi::INITIATED==$game["status"])) $error=true;
	 }
	if ($error){$app->returnError("game $gamenr not defined");return;}
	} else {$app->returnError("game $gamenr not defined");return;}
    $findusers=$app->getDB()->gameuser->where(array("game" => $gamenr));
	if (!(count($findusers)>0)){$app->returnError("game $gamenr not initiated yet");return;}
	//check if user not already applied for this game
    $findusers=$app->getDB()->gameuser->where(array("game" => $gamenr, "player" => $ip))->fetch();
	if ((count($findusers)>1)){$app->returnError("$ip already part of game $gamenr");return;}
	$nr=$app->getDB()->gameuser->where(array("game" => $gamenr))->max("ordernr");
	if ($nr+1>6){$app->returnError("game $gamenr maximum number of players (6) exceeded");return;}//maximum (52-4*6)/3-1 players
	$status=$app->gameState(CardApi::PLAYING);//'initiated';
		$newgameuser=array(
		"game" => $gamenr,
		"player" => $ip,
		"ordernr" => $nr+1,
		"status" => $status,
		"cards" => json_encode(array())
	);
	$result2 = $app->getDB()->gameuser->insert($newgameuser);
	$app->returnResult(array(
            "result" => 1)); 
});
/*
	$findgame=$app->getDB()->game->where("gamenumber",$gamenr);//CardApi::INITIATED
	$mysterycard=0;
	if (count($findgame)>0){
	 foreach ($findgame as $game) {
	    $mysterycard=$game["mysterycard"];
	 }
	} else {$app->returnError("game $gamenr not defined");return;}
	if ($mysterycard==0){$app->returnError("no mysterycard found in game $gamenr");return;}
	
	$result=$app->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("mysterycard"=>$cardnumber));
*/
$app->post('/games/:ip/:gamenr/play/mysterycard/:cardnumber',function ($ip, $gamenr, $cardnumber) use ($app) {

	if (!$app->identifyPlayer($ip)) return;
	$gameplayer=$app->checkgameplayertokenRunning($ip, $gamenr);
	if (!$gameplayer)return;
	
	$cards=$gameplayer["finduser"]["cards"];
	$cards=$app->removeFrom($cardnumber,$cards);
	if (!$cards){$app->returnError("$cardnumber not part of cards of player");return;}
	//get mystery card
	$findgame=$app->getDB()->game->where("gamenumber",$gamenr);//CardApi::INITIATED
	$mysterycard=0;
	if (count($findgame)>0){
	 foreach ($findgame as $game) {
	    $mysterycard=$game["mysterycard"];
	 }
	} else {$app->returnError("game $gamenr not defined");return;}
	if ($mysterycard==0){$app->returnError("no mysterycard found in game $gamenr");return;}
	//store new mysterycard in db
	$result=$app->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("mysterycard"=>$cardnumber));
	//and now add old mysterycard to player card, and send it back
	$cards=$app->addTo($mysterycard,$cards);
	$app->getDB()->gameuser->insert_update(array("player"=>$gameplayer["finduser"]["player"],"game"=>$gameplayer["finduser"]["game"]), array(), array("cards"=>$cards));
	$winning="continue";
	if (!$app->checkForWinning($gamenr,$cards))
		$app->nextMove($gamenr,$gameplayer["token"]);
	else $winning="winning";
	$app->addMove($gamenr,$ip,date('Y-m-d H:i:s'),"[]","[]");
	$result = 1;
    $app->returnResult(array(
            "result" => $result,"mysterycard"=>$mysterycard,"cards"=>$cards,"winning"=>$winning,"value"=>$app->getValue(json_decode($cards))));
	 
});
//initiate mystery card.
//can only be initiated by the player who has initiated and started the game
//game needs to be running
$app->post('/games/:ip/:gamenr/initiatemysterycard',function ($ip, $gamenr) use ($app) {
	if (!$app->identifyPlayer($ip)) return;
	$gameplayer=$app->checkgameplayertoken($ip, $gamenr,$app->gameState(CardApi::RUNNING),true);
	if (!$gameplayer){$app->returnError("game $gamenr not running");return;}
	if (!($gameplayer["token"]==1)){$app->returnError("$ip has not initiated and started game $gamenr");return;}
	$cards=$gameplayer["findgame"]["restcards"];
	$cardnumber=0;
	$mycards=json_decode($cards);
	$number=rand ( 0 , count($mycards)-1 );
	$cardnumber=$mycards[$number];
	$playercards[]=$cardnumber;
	$cards=$app->removeFrom($cardnumber,$cards);
	$result=$app->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("mysterycard"=>$cardnumber));
	$app->returnResult(array(
	  "result" => 1));
});
//returns 1 if game is started, 0 if ip has not initiated game or no players yet
//player starts game
//curl -X POST  --data "password=p3" http://192.168.2.8/CardApi/games/n132/14/start
$app->post('/games/:ip/:gamenr/start',function ($ip, $gamenr) use ($app) {

	if (!$app->identifyPlayer($ip)) return;
	$gameplayer=$app->checkgameplayertoken($ip, $gamenr,$app->gameState(CardApi::INITIATED),true);
	if (!$gameplayer)return;
	if (!($gameplayer["token"]==1)){$app->returnError("$ip has not initiated game $gamenr");return;}
	//get all players
    $findusers=$app->getDB()->gameuser->where("game", $gamenr);
		//for all players: give them three cards
		$cards=$gameplayer["findgame"]["restcards"];
		//var_dump($findusers);
	  $startercards="";
	  $status=$app->gameState(CardApi::RUNNING);
	  foreach ($findusers as $user) {
		  $playercards=array();
		  for ($j=1;$j<4;$j++){
		  	$mycards=json_decode($cards);
			  $number=rand ( 0 , count($mycards)-1 );
			  $cardnumber=$mycards[$number];
			  $playercards[]=$cardnumber;
		  	$cards=$app->removeFrom($cardnumber,$cards);
		  }
		  //save $playercards to db
		  if($user["player"]==$ip) $startercards=json_encode($playercards);
		  $app->getDB()->gameuser->insert_update(array("player"=>$user["player"],"game"=>$user["game"]), array(), array("cards"=>json_encode($playercards)));
		  //check for every user if it is 31
		  if($app->checkForWinning($gamenr,json_encode($playercards))) $status=$app->gameState(CardApi::ENDED);
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
		"status"=>$status));
	  $app->checkForWinning($gamenr,$startercards);
	  $app->nextMove($gamenr,0);//check if there is a winner already
	  $app->returnResult(array(
            "result" => 1));
});
//returns the ip which has the token for the game, error if game not started yet etc.
//get player-id who has token for game
$app->get('/games/:ip/:gamenr/token',function ($ip, $gamenr) use ($app) {
	$findgame=$app->getDB()->game->where("gamenumber", $gamenr);
	if (count($findgame)==0){$app->returnError("no game $gamenr");return;}
    foreach ($findgame as $game) {
    	$result=$game["tokenplayer"];
	}
	$app->returnResult(array(
            "token" => $result)); 
});
//returns the cards a player has (array of cardnumbers) game is still playing, 0 otherwise 
$app->post('/games/:ip/:gamenr/getcards',function ($ip, $gamenr) use ($app) {
	
	if (!$app->identifyPlayer($ip)) return;
	$gameuser=$app->getDB()->gameuser->where(array("player"=>$ip,"game"=>$gamenr));
	if (!(count($gameuser)>0)){$app->returnError("player $ip not part of game $gamenr");return;}
	$cards="nothing";
    foreach ($gameuser as $user) {
    	$cards=$user["cards"];
	}
	$app->returnResult(array(
            "cards" => $cards));
			 
});
/*//returns cardnumber which player gets if player has token, 0 otherwise
$app->get('/getcard/:ip/:gamenr',function ($ip, $gamenr) use ($app) { 
});*/
//returns 1 if ip has token, 0 otherwise.
//swaps $cardnumberin for $cardnumberout
//move player: exchange one card for another
$app->post('/games/:ip/:gamenr/play/move/:cardnumberin/:carnumberout',function ($ip, $gamenr, $cardnumberin,$cardnumberout) use ($app) {

	if (!$app->identifyPlayer($ip)) return;
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
	$winning="continue";
	if (!$app->checkForWinning($gamenr,$cards))
		$app->nextMove($gamenr,$gameplayer["token"]);
	else $winning="winning";
	$app->addMove($gamenr,$ip,date('Y-m-d H:i:s'),json_encode(array(intval ($cardnumberin))),json_encode(array(intval ($cardnumberout))));
	$result = 1;
    $app->returnResult(array(
            "result" => $result,"cards"=>$cards,"winning"=>$winning,"value"=>$app->getValue(json_decode($cards))));
	 
});
//-getmoves(ip,ipplayer,gamenr) get
//return moves of player
$app->get('/games/:ip/:gamenr/moves/:ipplayer',function ($ip, $gamenr, $ipplayer) use ($app) {
	$findmoves=$app->getDB()->gamemove->where(array("game"=>$gamenr,"player"=>$ipplayer));
	if (count($findmoves)==0){$app->returnError("player $ipplayer not part of game $gamenr");return;}
	$moves=array();
    foreach ($findmoves as $move) {
    	$moves[]=array("cardsin"=>$move["cardsin"],"cardsout"=>$move["cardsout"]);
	}
	$app->returnResult(array(
            "moves" => $moves)); 
});
//-getdeckontable(ip,gamenr) get
//returns list of cardnumbers lying open to see
$app->get('/games/:ip/:gamenr/deck',function ($ip, $gamenr) use ($app) {
	$findgame=$app->getDB()->game->where(array("gamenumber"=>$gamenr));
	if (count($findgame)==0){$app->returnError("no game $gamenr");return;}
    foreach ($findgame as $game) {
    	$result=$game["deckontable"];
	}
	$app->returnResult(array(
            "cards" => $result)); 
});
//-swapcards(ip,gamenr) post
//returns list of cards lying on table if player has token, otherwise error
//move player: swap cards
$app->post('/games/:ip/:gamenr/play/swap',function ($ip, $gamenr) use ($app) {

	if (!$app->identifyPlayer($ip)) return;
	$result="0";
	$gameplayer=$app->checkgameplayertokenRunning($ip, $gamenr);
	if (!$gameplayer)return;
	
	$cardsontable=$gameplayer["finduser"]["cards"];
	$cards=$gameplayer["findgame"]["deckontable"];
	$app->getDB()->gameuser->insert_update(array("player"=>$gameplayer["finduser"]["player"],"game"=>$gameplayer["finduser"]["game"]), array(), array("cards"=>$cards,"status"=>$app->playerState(CardApi::PASS)));
	$app->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("deckontable"=>$cardsontable));
	$winning="continue";
	if (!$app->checkForWinning($gamenr,$cards))
		$app->nextMove($gamenr,$gameplayer["token"]);
	else $winning="winning";
	$app->addMove($gamenr,$ip,date('Y-m-d H:i:s'),$cards,$cardsontable);
	$result=$cards;
    $app->returnResult(array(
            "cards" => $result,"cards"=>$cards,"winning"=>$winning,"value"=>$app->getValue(json_decode($cards))));
	 
});
//-offerpass(ip,gamenr) post
//returns 1 if player has token, error otherwise
//move player: pass
$app->post('/games/:ip/:gamenr/play/pass',function ($ip, $gamenr) use ($app) {

	if (!$app->identifyPlayer($ip)) return;
	$result="0";
	$gameplayer=$app->checkgameplayertokenRunning($ip, $gamenr);
	if (!$gameplayer)return;
	//get number of players
	$players=$app->getDB()->gameuser->where(array("game" => $gamenr));
	$numberOfPlayers=count($players);
	//get number of moves
	$moves=$app->getDB()->gamemove->where(array("game"=>$gamenr));
	$numberOfMoves=count($moves);
	//if number of moves < number of players: illegal move
	if ($numberOfMoves<$numberOfPlayers){$app->returnError("number of moves ($numberOfMoves) must not be smaller than number of players ($numberOfPlayers)");return;}
	$app->getDB()->gameuser->insert_update(array("player"=>$gameplayer["finduser"]["player"],"game"=>$gameplayer["finduser"]["game"]), array(), array("status"=>$app->playerState(CardApi::PASS)));
	//cards not changed, so always continue with next move
	$app->nextMove($gamenr,$gameplayer["token"]);
	$app->addMove($gamenr,$ip,date('Y-m-d H:i:s'),json_encode(array()),json_encode(array()));
	$result=1;
    $app->returnResult(array(
            "result" => $result));
	 
});
//-claimwin(ip,gamenr) post
//returns 1 if player has won, and has token. error otherwise
//player can check if game is won. is same as getresultgame, except that this can only be claimed by a player of the game itself.
$app->post('/games/:ip/:gamenr/claimwin',function ($ip, $gamenr) use ($app) {
	if (!$app->identifyPlayer($ip)) return;
	$gameplayer=$app->checkgameplayertoken($ip, $gamenr,$app->gameState(CardApi::ENDED),true);
	if (!$gameplayer)return;
	$cards=$gameplayer["finduser"]["cards"];
	if (!$app->checkForWinning($gamenr,$cards)){$app->returnError("No 31, you only have $val points");return;}
	//get all players
	$app->returnResult($app->getWinner($gamenr));//checkForWinning($gamenr,$cards)
});
//get finalresults
//returns winner and results for each player if game is ended
$app->get('/games/:ip/:gamenr/finalresults',function ($ip, $gamenr) use ($app) {
	$findgame=$app->getDB()->game->
	  where(array("gamenumber" => $gamenr,"status"=>$app->gameState(CardApi::ENDED)));
	if (count($findgame)==0){$app->returnError("game $gamenr not ended or unknown ");return;}
	$findusers=$app->getDB()->gameuser->where("game", $gamenr);
	$userwin=null;
	$max=0;
	$players=array();
	foreach ($findusers as $user) {
	    $cards=$user["cards"];
	    $val=$app->getValue(json_decode($cards));
	    if ($val>$max){
		    $max=$val;
		    $userwin=$user;
	    }
	    $players[]=array("player"=>$user["player"],"cards"=>$cards,"handvalue"=>$val);
	}
	$app->returnResult(array("winner"=>$userwin,"results"=>$players));
	 
});
//-getstategame(ip,gamenr) get
//returns one of: initiated,started,ended
$app->get('/games/:ip/:gamenr/state',function ($ip, $gamenr) use ($app) {
	$findgame=$app->getDB()->game->where("gamenumber",$gamenr);//CardApi::INITIATED
	$result="";
    if (count($findgame)>0){
     foreach ($findgame as $game) {
     	$result=$game["status"];
	 }
	} else {$app->returnError("game $gamenr not defined");return;}
    $app->returnResult(array(
            "status" => $result));
});
//-getresultgame(ip,gamenr, ipplayer) get
//returns list of cards for ipplayer if game is ended.
$app->get('/games/:ip/:gamenr/cards/:ipplayer',function ($ip, $gamenr, $ipplayer) use ($app) {
	$gameplayer=$app->checkgameplayertoken($ipplayer, $gamenr,$app->gameState(CardApi::ENDED),false);
	if (!$gameplayer){return;};
	//echo "player cards can be shown!";
	$cards=$gameplayer["finduser"]["cards"];
	$app->returnResult($gameplayer["finduser"]);
	 
});
//-getwinnergame(ip,gamenr) get
//returns ip of winning player if game is ended
$app->get('/games/:ip/:gamenr/winner',function ($ip, $gamenr) use ($app) {
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

//getvalue
//returns value of card. in: cardnumber
$app->get('/cards/:cards/value',function ($cards) use ($app) {
	$parts = explode(",", $cards);
	$val=$app->getValue($parts);
	$app->returnResult(array(
            "value" => $val));
});
//commercials
//returns title, first line, description and png.
//end services
$app->get('/commercials/:title',function ($title) use ($app) {
	$commercials = $app->getDB()->commercial->where("title",$title);
	$result=array();
    if (count($commercials)>0){
     foreach ($commercials as $commercial) {
        $result  = array(
            "title" => $commercial["title"],
            "firstline" => $commercial["firstline"],
            "description" => $commercial["description"],
            "picture" => $commercial["picture"],
        );
      }

    } else {$app->returnError("no commercials with title $title defined");return;} 
    $app->returnResult(array(
            "commercial" => $result));
});
$app->get('/games/:ip/numberofmoves',function ($ip) use ($app) {
	$findmoves=$app->getDB()->gamemove->select("game");
	if (count($findmoves)==0){$app->returnError("no moves available");return;}
	$moves=array();
	foreach ($findmoves as $move) {
	  if(isset ($moves[$move["game"]]))
	    $moves[$move["game"]]=$moves[$move["game"]]+1;
	  else
	    $moves[$move["game"]]=1;//one occurrence at least
	}
	$app->returnResult(array(
            "result" => $moves)); 
});
$app->get('/games/:ip/gamewinners',function ($ip) use ($app) {
	$findgame=$app->getDB()->game;//CardApi::INITIATED
	$games=array();
	if (count($findgame)>0){
	  foreach ($findgame as $game) {
		$games[]=array(array("game"=>$game["gamenumber"]),array("winner"=>$game["winner"]));
	  }
	} else {$app->returnError("no games available");return;}
	$app->returnResult(array(
            "result" => $games));
});
//commercials
$app->get('/commercials',function () use ($app) {
    $commercials = $app->getDB()->commercial->select("title");
    $result=array();
    if (count($commercials)>0){
    	$result=array();
     foreach ($commercials as $commercial) {
        $result[]  = array(
            "title" => $commercial["title"]
        );
      }

    } else {$app->returnError("no commercials defined");return;} 
    $app->returnResult(array(
            "commercials" => $result));
});

//delete game
//deletes all items for a game
//available for user admin
//curl -X DELETE  --data "password=CardApi" http://127.0.0.1/anton/cardapi/players/1230
$app->delete('/players/:ip', function ($ip) use ($app)  {
    //Delete player identified by $id
	if (!$app->identifyAdmin()) return;
	$app->getDB()->gamemove->where("player",$ip)->delete();
	$app->getDB()->gameuser->where("player",$ip)->delete();
	$result=$app->getDB()->player->where("ip",$ip)->delete();
	$app->returnResult(array(
            "result" => $result));    
});
//curl -X POST  --data "password=CardApi" http://127.0.0.1/anton/cardapi/db/create
$app->post('/db/create', function () use ($app)  {
    //Delete player identified by $id
	if (!$app->identifyAdmin()) return;
		$pdo = new PDO('sqlite:mysqlitedb.db');
	//$file_db = new PDO('sqlite:mysqlitedb.db');
	$app->createTable($pdo,"player", "ip STRING PRIMARY KEY,id STRING,name STRING,status STRING, password STRING, datafield STRING");
	$app->createTable($pdo,"gameuser", "player STRING,game STRING,status STRING,cards STRING,ordernr INTEGER,PRIMARY KEY (player, game)");
	$app->createTable($pdo,"gamemove", "game STRING,player STRING,time STRING,cardsin STRING,cardsout STRING,PRIMARY KEY (game, player,time)");
	$app->createTable($pdo,"game", "gamenumber STRING PRIMARY KEY,status STRING,starter STRING,tokenplayer STRING,deckontable STRING,restcards STRING,winner STRING, mysterycard INTEGER");
	$app->createTable($pdo,"commercial", "title STRING PRIMARY KEY  NOT NULL , firstline STRING, description STRING, picture BLOB");
	   // Close file db connection
    $pdo = null;
	$app->returnResult(array(
            "result" => "1"));
});
//curl -X POST  --data "password=CardAPi&passwordplayer=CardAPiNew" http://127.0.0.1/anton/cardapi//players/userid/password
$app->post('/players/:ip/password', function ($ip) use ($app)  {
    //Delete player identified by $id
	if (!$app->identifyAdmin()) return;
	$passworduser=$app->request()->post('passwordplayer');
	//$user=$app->getDB()->player->where("ip",$ip);
	$result=$app->getDB()->player->insert_update(array("ip"=>$ip), array(), array("password"=>$passworduser));
	$app->returnResult(array(
            "result" => $result));    
});
$app->post('/commercials/add/:title', function ($title) use ($app)  {
	if (!$app->identifyAdmin()) return;
//$app->post('/players/:ip/add/:naam',function ($ip,$naam) use ($app) {   
    $findcommercial=$app->getDB()->commercial->where("title", $title);
    $firstline=$app->request()->post('firstline');
    $description=$app->request()->post('description');
    $picture=$app->request()->post('picture');
    if (count($findcommercial)>0){
     $commercials = array();
     foreach ($findcommercial as $commercial) {
        $commercials[]  = array(
            "title" => $commercial["title"],
            "firstline" => $commercial["firstline"],
            "description" => $commercial["description"],
            "picture" => $commercial["picture"]
        );
      }
     } else {
	$status='ok';
        $newcommercial=array(
            "title" => $title,
            "firstline" => $firstline,
            "description" => $description,
            "picture" => $picture,
	);
	
	$result = $app->getDB()->commercial->insert($newcommercial);
        $commercials=$newcommercial;
    }
    $app->returnResult(array(
            "commercial" => $commercials));
   $db=null;
});

//function made for convenience and testing purposes.
//forces then hand of the player equal to cards
$app->post('/players/:ip/forcegame/:gamenr/cards/:cards',function ($ip, $gamenr, $cards) use ($app) {
	if (!$app->identifyAdmin()) return;
	$app->getDB()->gameuser->insert_update(array("player"=>$ip,"game"=>$gamenr), array(), array("cards"=>$cards));
	$app->returnResult(array(
            "cards" => $cards)); 
	 
});

//function made for convenience and testing purposes.
//forces then hand of the player equal to cards
$app->post('/games/:gamenr/forcedeck/:cards',function ($gamenr, $cards) use ($app) {
	if (!$app->identifyAdmin()) return;
	$app->getDB()->game->insert_update(array("gamenumber"=>$gamenr), array(), array("deckontable"=>$cards));
	$app->returnResult(array(
            "cards" => $cards)); 
	 
});
//delete commercial with title
$app->post('/commercials/delete/:title', function ($title) use ($app)  {
	if (!$app->identifyAdmin()) return;
	$app->getDB()->commercial->where("title", $title)->delete();
	$app->returnResult(array(
            "result" => "","deleted" => $title)); 
});

// run the Slim app
$app->run();


?> 
