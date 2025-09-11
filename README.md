for starting:
1. composer update
2. if you wanna work on 8.1:
   "php": "^8.1",
        "illuminate/database": "^10.0@dev",
        "illuminate/events": "^10.0@dev"
3. but on 8.2 or higher use:
   "php": "^8.2",
        "illuminate/database": "^13.0@dev",
        "illuminate/events": "^13.0@dev"
4. in db.php you need to change - 'database' => 'mvc','username' => 'root', 'password' => ''
