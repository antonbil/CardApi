CREATE TABLE commercial (title STRING PRIMARY KEY  NOT NULL , firstline STRING, description STRING, picture BLOB);
CREATE TABLE game (gamenumber STRING PRIMARY KEY,status STRING,starter STRING,tokenplayer STRING,deckontable STRING,restcards STRING,winner STRING);
CREATE TABLE gamemove (game STRING,player STRING,time STRING,cardsin STRING,cardsout STRING,PRIMARY KEY (game, player,time));
CREATE TABLE gameuser (player STRING,game STRING,status STRING,cards STRING,ordernr INTEGER,PRIMARY KEY (player, game));
CREATE TABLE player (ip STRING PRIMARY KEY,id STRING,name STRING,status STRING, password STRING);
CREATE TABLE test (player STRING, game STRING, status STRING, cards STRING, ordernr INTEGER, PRIMARY KEY (player, game));
