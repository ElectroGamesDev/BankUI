# Information 
 - This plugin allows players to store their money in a bank.
# Features 
 - Withdraw Money into Bank
 - Deposit Money from Bank
 - Transfer Money to Other Players Bank Accounts
 - Transaction Log
 - Daily Interest
 - Configurable Interest Rates
 - Admins Can Manage Other Players Bank With "/bank {PlayerName}"
 - Customizable Messages
 - Automatic Backup System
 - Database Migration
 - MySQL and SQLite Support
 - Developer API
 - Bedrock Economy Support
 - ScoreHud Support
# Command
- Player Commands
 - /bank

- Admin Commands
 - /bank {PlayerName} 
 - /bank migrate - DO NOT USE THIS UNLESS YOU KNOW WHAT YOU ARE DOING!
 - /bank backup {save | load | restore} - Becareful when loading a backup, you will lose all data since the last saved backup unless you restore it but DO NOT take the risk !
# Important
- This plugin Requires Bedrock Economy!
# ScoreHud Support
- ScoreHud Tag: ```{bankui.money}```
# Images
![Bank5](https://user-images.githubusercontent.com/34932094/124204221-37c3c280-daa4-11eb-826f-8c6511cf9649.png)
![Bank2](https://user-images.githubusercontent.com/34932094/122729370-b7e55f00-d23e-11eb-8aa6-1d8e8b47e70f.PNG)
![Bank3](https://user-images.githubusercontent.com/34932094/122729371-b7e55f00-d23e-11eb-8a94-ee292bab50f8.PNG)
![Bank4](https://user-images.githubusercontent.com/34932094/122729372-b7e55f00-d23e-11eb-9a8c-f44571718108.PNG)
![Bank6](https://user-images.githubusercontent.com/34932094/124215248-48cafe80-dab9-11eb-930d-df1b113a7d3d.PNG)
![admn](https://user-images.githubusercontent.com/34932094/141248349-65d9629c-2e30-42d3-aa4a-d05909c5908e.PNG)
# Permissions
- bankui.cmd
- bankui.admin
- bankui.admin.backup (Required to use /bank backup)
- bankui.admin.migrate (Required to use /bank migrate)
# Developer API
- You can give/take/set/get/save players money/transactions using our API.

- Add Money:
```BankUI::getInstance()->addMoney($playerName, $amount);```
- Take Money:
```BankUI::getInstance()->takeMoney($playerName, $amount);```
- Set Money:
```BankUI::getInstance()->setMoney($playerName, $amount);```
- Get Money:
```BankUI::getInstance()->>getMoney($playerName)->onCompletion(function(float $money): void{```
    ```// Code (use $money)```
```}, static fn() => null);```
- Add Economy Money:
```BankUI::getInstance()->addEconomyMoney($playerName, $amount);```
- Take Economy Money:
```BankUI::getInstance()->takeEconomyMoney($playerName, $amount);```
- Add Transaction:
```BankUI::getInstance()->addTransaction($playerName, $transaction);```
- Get Transactions:
```BankUI::getInstance()->>getTransactions($playerName)->onCompletion(function(string $transactions): void{``
    ```// Code (use $transactions)```
```}, static fn() => null);```
- Check If Account Exists:
```BankUI::getInstance()->>accountExists($playerName)->onCompletion(function(bool $exists): void{```
    ```// Code (use $exists)```
```}, static fn() => null);```
- Set Transactions:
```BankUI::getInstance()->setTransaction($playerName, $transactions);```
- Save Data:
```BankUI::getInstance()->saveData($player);```
- Save All Online Players Data:
```BankUI::getInstance()->saveAllData();```
- Backup Data - REQUIRES BACKUP ENABLED:
```BankUI::getInstance()->backupData();```
- Load Backup - REQUIRES BACKUP ENABLED:
```BankUI::getInstance()->loadBackup();```
- Restore Backup - REQUIRES BACKUP ENABLED:
```BankUI::getInstance()->restoreBackup();```
- Migrate Database (Only "SQLite", "MySQL", and "SQL" is supported. "SQL" will migrate the database from/to the current database type in use and you should Save All before using this. Make sure you know what your doing as you can lose all of your data if not used correctly.):
```BankUI::getInstance()->migrateDatabase($migrateFrom, $migrateTo);```
# Config
```
# If true, players will get daily interest for the money in their bank
enable-interest: true

# Interst Rates is in percentage so if interst-rates = 50, it means 50% Interest Rates, if it is set at 1, it means 1% interest rates. (It is recommended to keep this low)
interest-rates: 1

# Backup System - You may want to set this to false if you think you or your staff may accidentally restore a backup and lose your data
enable-backups: true 

# "enabled-backups" must be true for automatic backups. 
enable-automatic-backups: true

# "enable-automatic-backups" must be true for this. This is how often your databases get automatically backed up. This is in minutes so 60 = 60 minutes. This number should be between 720-1440 (12-24 hours) DO NOT go less
# than 60 (1 hour) as this could cause lag for a few seconds if you have had alot of unique players join yuour server.
automatic-backups-time: 720

database:
  # The database type. "sqlite" and "mysql" are supported.
  type: sqlite

  # Edit these settings only if you choose "sqlite".
  sqlite:
    # The file name of the database in the plugin data folder.
    # You can also put an absolute path here.
    file: players.sqlite
  # Edit these settings only if you choose "mysql".
  mysql:
    host: 127.0.0.1
    # Avoid using the "root" user for security reasons.
    username: root
    password: ""
    schema: your_schema
  # The maximum number of simultaneous SQL queries
  # Recommended: 1 for sqlite, 2 for MySQL. You may want to further increase this value if your MySQL connection is very slow.
  worker-limit: 1
  
# "enable-backups" must be enabled for backups to work.
backup-database:
  # The database type. "sqlite" and "mysql" are supported. This should be set to "mysql" for backups to be most useful although you can use "sqlite" if you do not have a MySQL database.
  type: sqlite

  # Edit these settings only if you choose "sqlite".
  sqlite:
    # The file name of the database in the plugin data folder.
    # You can also put an absolute path here.
    file: backup.sqlite
  # Edit these settings only if you choose "mysql".
  mysql:
    host: 127.0.0.1
    # Avoid using the "root" user for security reasons.
    username: root
    password: ""
    schema: your_schema
  # The maximum number of simultaneous SQL queries
  # Recommended: 1 for sqlite, 2 for MySQL. You may want to further increase this value if your MySQL connection is very slow.
  worker-limit: 1
  
# DO NOT TOUCH
config-ver: 2
```
# Credits
- Icon from www.flaticon.com
