## cardapi
created by Anton Bil

made for course ¨Agile App Development¨

api is meant to act as a means to play the card-game ¨31¨

uses libraries
-Slim for REST-services
-NotOrm for db access

uses sqlite-db to store data
-example: install sqlite3

requirements:
-PHP
-url rewriting

Most likely to be run within Apache server

-example .htaccess:
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]

# ALLOW USER BY IP
<Limit GET POST>
 order deny,allow
 deny from all
 allow from 192.168.2.
 allow from 81.207.236.65 #thuis-ip
 allow from 145.19. #school-ip
</Limit>
# PREVENT VIEWING OF .HTACCESS
<Files .htaccess>
 order allow,deny
 deny from all
</Files>

-create sqlite db
example session:
sudo sqlite3 mysqlitedb.db < mysqlitedb.sql
-change rights so that www-data can write to db
example:
sudo chmod 775 /var/www/html/CardApi
sudo chmod 664 /var/www/html/CardApi/mysqlitedb.sql
