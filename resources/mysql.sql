-- #!mysql
-- #{init
-- #  {table
-- #    {creation
CREATE TABLE IF NOT EXISTS BankUI (
    id int unsigned auto_increment,
    Player varchar(255),
    Money float,
    Transactions varchar(255),
    primary key (id),
    unique key `Player`(Player)
);
-- #    }
-- #    {drop
DROP TABLE IF EXISTS BankUI
-- #    }
-- #	  {createaccount
-- #      :playername string
-- #      :money float
-- #      :transactions string
INSERT IGNORE INTO BankUI(
  Player,
  Money,
  Transactions
) VALUES (
  :playername,
  :money,
  :transactions
);
-- #	}
-- #    {getall
SELECT * FROM BankUI
-- #    }
-- #    {addmoney
-- #      :playername string
-- #      :money float
UPDATE BankUI SET
    Money = Money + :money
    WHERE Player = :playername;
-- #    }
-- #    {takemoney
-- #      :playername string
-- #      :money float
UPDATE BankUI SET
    Money = Money - :money
    WHERE Player = :playername;
-- #    }
-- #    {setmoney
-- #      :playername string
-- #      :money float
UPDATE BankUI SET
    Money = :money
    WHERE Player = :playername;
-- #    }
-- #    {getmoney
-- #      :playername string
SELECT Money FROM BankUI where Player = :playername
-- #    }
-- #    {playerexists
-- #      :playername string
SELECT * FROM BankUI where Player = :playername
-- #    }
-- #    {addtransaction
-- #      :playername string
-- #      :transaction string
UPDATE BankUI SET
    Transactions = Transactions + :transaction
    WHERE Player = :playername;
-- #    }
-- #    {settransactions
-- #      :playername string
-- #      :transactions string
UPDATE BankUI SET
    Transactions = :transactions
    WHERE Player = :playername;
-- #    }
-- #    {gettransactions
-- #      :playername string
SELECT Transactions FROM BankUI where Player = :playername
-- #    }
-- #  }
-- #}