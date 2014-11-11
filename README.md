# README #

This README would normally document whatever steps are necessary to get your application up and running.

### What is this repository for? ###

* craw data from ptt(web based)
* save data into mysql DB

### How do I get set up? ###

* Import DB schema "ptt_crawler.sql"
* Change DB configurations in "Database.php"
* Run the crawler.php with CLI
* Command usage: 
```
#!php

php crawler.php {Board Name}

```