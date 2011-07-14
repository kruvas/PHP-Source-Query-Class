PHP SourceQuery
===============

Description:
------------
This class was created to query servers that use 'Source Query' protocol, for games like HL1, HL2, The Ship, SiN 1, Rag Doll Kung Fu and more.

You can find protocol specifications here: http://developer.valvesoftware.com/wiki/Server_queries

Usage:
------
You can find easy example how to use it in `Test.php` file

Functions:
----------
Open connection to a server:
```php
Connect( $IP, $Port );
```

Close connection:
```php
Disconnect( );
```

Set rcon password for future use:
```php
SetRconPassword( $RconPassword );
```

Execute Rcon command:
```php
Rcon( $Command );
```
