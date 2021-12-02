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
# Command
- /bank
- /bank {PlayerName}
# Important
- This plugin Requires EconomyAPI by OneBone
# Images
![Bank5](https://user-images.githubusercontent.com/34932094/124204221-37c3c280-daa4-11eb-826f-8c6511cf9649.png)
![Bank2](https://user-images.githubusercontent.com/34932094/122729370-b7e55f00-d23e-11eb-8aa6-1d8e8b47e70f.PNG)
![Bank3](https://user-images.githubusercontent.com/34932094/122729371-b7e55f00-d23e-11eb-8a94-ee292bab50f8.PNG)
![Bank4](https://user-images.githubusercontent.com/34932094/122729372-b7e55f00-d23e-11eb-9a8c-f44571718108.PNG)
![Bank6](https://user-images.githubusercontent.com/34932094/124215248-48cafe80-dab9-11eb-930d-df1b113a7d3d.PNG)
![admn](https://user-images.githubusercontent.com/34932094/141248349-65d9629c-2e30-42d3-aa4a-d05909c5908e.PNG)
# Permissions
- bankui.admin
# Developer API
- You can give/take/set/get players money/transactions using functions.

- Add Money:
```BankUI::getInstance()->addMoney($playerName, $amount);```
- Take Money:
```BankUI::getInstance()->takeMoney($playerName, $amount);```
- Set Money:
```BankUI::getInstance()->setMoney($playerName, $amount);```
- Get Money:
```BankUI::getInstance()->getMoney($playerName);```
- Add Transaction:
```BankUI::getInstance()->addTransaction($playerName, $message);```
# Config
```
# DO NOT TOUCH
config-ver: 1

# If true, players will get daily interest for the money in their bank
enable-interest: = true

# Interst Rates is in percentage so if interst-rates = 50, it means 50% Interest Rates, if it is set at 1, it means 1% interest rates. (It is recommended to keep this low)
interest-rates: 1

# Timezones can be found at https://www.php.net/manual/en/timezones.php if you don't know what your doing, keep this at "America/Chicago" (OR IT WILL BREAK THE PLUGIN). Players will recieve their daily interest at 12pm in this timezone.
timezone: America/Chicago
```
# Credits
- Icon from www.flaticon.com
