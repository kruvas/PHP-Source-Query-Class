<?php
class SourceQuery
{
	/********************************************************************************
	 * Class written by xPaw <xpaw.crannk.de>
	 *
	 * GitHub: https://github.com/xPaw/PHP-Source-Query-Class
	 *
	 ********************************************************************************
	 * INFORMATION:
	 * - If server is HLTV, bots == spectators (You can know that by comparing 'Dedicated' == 'p')
	 * - GetPlayers() for HLTV returns players on game server, and their time will be always 1 second
	 * - Source RCON uses TCP, so it requires different connection and protocol for it
	 ********************************************************************************/
	
	protected $Resource;
	protected $Connected;
	protected $RconPassword;
	protected $RconChallenge;
	protected $Challenge;
	protected $IsSource;
	protected $RconId;
	
	public function __destruct( )
	{
		$this->Disconnect( );
	}
	
	public function Connect( $Ip, $Port = 27015, $Password = "" )
	{
		$this->Disconnect( );
		$this->RconPassword  = $Password;
		$this->RconChallenge = 0;
		$this->Challenge     = 0;
		$this->IsSource      = 0;
		$this->RconId        = 0;
		
		if( ( $this->Resource = @FSockOpen( 'udp://' . GetHostByName( $Ip ), (int)$Port ) ) )
		{
			$this->Connected = true;
			Socket_Set_TimeOut( $this->Resource, 3 );
			
		/*	if( !$this->Ping( ) ) // TODO: Ping does not work in TF2
			{
				$this->Disconnect( );
				
				throw new SQueryException( "This server is not responding to Source Query Protocol." );
			}	*/
		}
		else
		{
			throw new SQueryException( "Can't connect to the server." );
		}
		
		return $this->Connected;
	}
	
	public function Disconnect( )
	{
		if( $this->Connected )
		{
			$this->Connected = false;
			
			FClose( $this->Resource );
		}
	}
	
	private function Ping( )
	{
		$this->WriteData( 'i' );
		$Type = $this->ReadData( );
		
		if( $Type && $Type[ 4 ] == 'j' )
		{
			$this->IsSource = ( $Type[ 5 ] == '0' );
			
			return true;
		}
		
		return false;
	}
	
	public function GetInfo( )
	{
		if( !$this->Connected )
		{
			return false;
		}
		
		$this->WriteData( 'TSource Engine Query' );
		$Buffer = $this->ReadData( );
		
		$Type = $this->_CutByte( $Buffer, 5 );
		$Type = $Type[ 4 ];
		
		if( $Type != 'I' )
		{
			if( $Type == 'm' ) // Old HL1 protocol, HLTV uses it
			{
				$Server[ 'Address' ]    = $this->_CutString( $Buffer );
				$Server[ 'HostName' ]   = $this->_CutString( $Buffer );
				$Server[ 'Map' ]        = $this->_CutString( $Buffer );
				$Server[ 'ModDir' ]     = $this->_CutString( $Buffer );
				$Server[ 'ModDesc' ]    = $this->_CutString( $Buffer );
				$Server[ 'Players' ]    = $this->_CutNumber( $Buffer );
				$Server[ 'MaxPlayers' ] = $this->_CutNumber( $Buffer );
				$Server[ 'Protocol' ]   = $this->_CutNumber( $Buffer );
				$Server[ 'Dedicated' ]  = $this->_CutByte( $Buffer );
				$Server[ 'Os' ]         = $this->_CutByte( $Buffer );
				$Server[ 'Password' ]   = $this->_CutNumber( $Buffer );
				$Server[ 'IsMod' ]      = $this->_CutNumber( $Buffer );
				
				if( $Server[ 'IsMod' ] ) // TODO: Needs testing
				{
					$Mod[ 'Url' ]        = $this->_CutString( $Buffer );
					$Mod[ 'Download' ]   = $this->_CutString( $Buffer );
					$this->_CutByte( $Buffer ); // NULL byte
					$Mod[ 'Version' ]    = $this->_CutNumber( $Buffer ); // TODO: long?
					$Mod[ 'Size' ]       = $this->_CutNumber( $Buffer ); // TODO: long?
					$Mod[ 'ServerSide' ] = $this->_CutNumber( $Buffer );
					$Mod[ 'CustomDLL' ]  = $this->_CutNumber( $Buffer );
				}
				
				$Server[ 'Secure' ]   = $this->_CutNumber( $Buffer );
				$Server[ 'Bots' ]     = $this->_CutNumber( $Buffer );
				
				if( isset( $Mod ) )
				{
					$Server[ 'Mod' ] = $Mod;
				}
				
				return $Server;
			}
			
			return false;
		}
		
		$Server[ 'Protocol' ]   = $this->_CutNumber( $Buffer );
		$Server[ 'HostName' ]   = $this->_CutString( $Buffer );
		$Server[ 'Map' ]        = $this->_CutString( $Buffer );
		$Server[ 'ModDir' ]     = $this->_CutString( $Buffer );
		$Server[ 'ModDesc' ]    = $this->_CutString( $Buffer );
		$Server[ 'AppID' ]      = $this->_UnPack( 'S', $this->_CutByte( $Buffer, 2 ) );
		$Server[ 'Players' ]    = $this->_CutNumber( $Buffer );
		$Server[ 'MaxPlayers' ] = $this->_CutNumber( $Buffer );
		$Server[ 'Bots' ]       = $this->_CutNumber( $Buffer );
		$Server[ 'Dedicated' ]  = $this->_CutByte( $Buffer );
		$Server[ 'Os' ]         = $this->_CutByte( $Buffer );
		$Server[ 'Password' ]   = $this->_CutNumber( $Buffer );
		$Server[ 'Secure' ]     = $this->_CutNumber( $Buffer );
		
		if( $Server[ 'AppID' ] == 2400 ) // The Ship
		{
			$Server[ 'GameMode' ]     = $this->_CutNumber( $Buffer );
			$Server[ 'WitnessCount' ] = $this->_CutNumber( $Buffer );
			$Server[ 'WitnessTime' ]  = $this->_CutNumber( $Buffer );
		}
		
		$Server[ 'Version' ] = $this->_CutString( $Buffer );
		
		// EXTRA DATA FLAGS
		$Flags = $this->_CutNumber( $Buffer );
		
		if( $Flags & 0x80 ) // The server's game port #
		{
			$Server[ 'EDF' ][ 'GamePort' ] = $this->_UnPack( 'S', $this->_CutByte( $Buffer, 2 ) );
		}
		
		if( $Flags & 0x10 ) // The server's SteamID
		{
			// TODO: long long ...
			
			$this->_CutByte( $Buffer, 8 );
		}
		
		if( $Flags & 0x40 ) // The spectator port # and then the spectator server name
		{
			$Server[ 'EDF' ][ 'SpecPort' ] = $this->_UnPack( 'S', $this->_CutByte( $Buffer, 2 ) );
			$Server[ 'EDF' ][ 'SpecName' ] = $this->_CutString( $Buffer );
		}
		
		if( $Flags & 0x20 ) // The game tag data string for the server
		{
			$Server[ 'EDF' ][ 'GameTags' ] = $this->_CutString( $Buffer );
		}
		
		/*if( $Flags & 0x01 ) // The Steam Application ID again + several 0x00 bytes
			$this->_UnPack( 'S', $this->_CutByte( $Buffer, 2 ) );
		*/
		
		return $Server;
	}
	
	public function GetPlayers( )
	{
		if( !$this->Connected )
		{
			return false;
		}
		
		$Challenge = $this->GetChallenge( );
		
		if( !$Challenge )
		{
			return false;
		}
		
		$this->WriteData( 'U' . $Challenge );
		$Buffer = $this->ReadData( );
		
		if( $this->_CutByte( $Buffer, 5 ) != "\xFF\xFF\xFF\xFFD" )
		{
			return false;
		}
		
		$Count = $this->_CutNumber( $Buffer );
		
		if( $Count <= 0 ) // No players
		{
			return false;
		}
		
		for( $i = 0; $i < $Count; $i++ )
		{
			$this->_CutByte( $Buffer ); // player id, but always equals to 0 (tested on HL1)
			
			$Players[ $i ][ 'Name' ]    = $this->_CutString( $Buffer );
			$Players[ $i ][ 'Frags' ]   = $this->_UnPack( 'L', $this->_CutByte( $Buffer, 4 ) );
			$Time                       = (int)$this->_UnPack( 'f', $this->_CutByte( $Buffer, 4 ) );
			$Players[ $i ][ 'IntTime' ] = $Time;
			$Players[ $i ][ 'Time' ]    = GMDate( ( $Time > 3600 ? "H:i:s" : "i:s" ), $Time );
		}
		
		return $Players;
	}
	
	public function GetRules( )
	{
		if( !$this->Connected )
		{
			return false;
		}
		
		$Challenge = $this->GetChallenge( );
		
		if( !$Challenge )
		{
			return false;
		}
		
		$this->WriteData( 'V' . $Challenge );
		$Buffer = $this->ReadData( );
		
		if( $this->_CutByte( $Buffer, 5 ) != "\xFF\xFF\xFF\xFFE" )
		{
			return false;
		}
		
		$Count = $this->_UnPack( 'S', $this->_CutByte( $Buffer, 2 ) );
		
		if( $Count <= 0 ) // Can this even happen?
		{
			return false;
		}
		
		$Rules = Array( );
		
		for( $i = 0; $i < $Count; $i++ )
		{
			$Rules[ $this->_CutString( $Buffer ) ] = $this->_CutString( $Buffer );
		}
		
		return $Rules;
	}
	
	private function GetChallenge( $IsRcon = false )
	{
		if( $IsRcon )
		{
			if( $this->RconChallenge )
			{
				return $this->RconChallenge;
			}
			
			$this->WriteData( 'challenge rcon' );
			$Data = $this->ReadData( );
			
			if( $this->_CutByte( $Data, 5 ) != "\xFF\xFF\xFF\xFFc" )
			{
				return false;
			}
			
			// TODO: Check is RTrim is needed here
			return ( $this->RconChallenge = RTrim( SubStr( $Data, 14 ) ) );
		}
		else if( $this->Challenge )
		{
			return $this->Challenge;
		}
		
		$this->WriteData( "\x55\xFF\xFF\xFF\xFF" );
		$Data = $this->ReadData( );
		
		// TODO: \x55 on HLTV just returns server info, breaking other packets...
		
		if( $this->_CutByte( $Data, 5 ) != "\xFF\xFF\xFF\xFFA" ) // dproto/hltv will return 'D' not 'A' here
		{
			return false;
		}
		
		return ( $this->Challenge = $Data );
	}
	
	// ==========================================================
	// RCON
	public function RconCareless( $Command )
	{
		if( $this->IsSource || !$this->Connected || !$this->RconPassword || !$this->GetChallenge( true ) )
		{
			return false;
		}
		
		return $this->WriteData( 'rcon ' . $this->RconChallenge . ' "' . $this->RconPassword . '" ' . $Command );
	}
	
	public function Rcon( $Command )
	{
		if( $this->IsSource )
		{
			throw new SQueryException( "Source RCON Protocol is not supported yet." );
		}
		else if( !$this->Connected || !$this->RconPassword || !$this->GetChallenge( true ) )
		{
			return false;
		}
		
		$this->WriteData( 'rcon ' . $this->RconChallenge . ' "' . $this->RconPassword . '" ' . $Command );
		
		Socket_Set_TimeOut( $this->Resource, 1 );
		
		$Buffer = "";
		
		while( $Type = FRead( $this->Resource, 5 ) )
		{
			if( Ord( $Type[ 0 ] ) == 254 ) // More than one datagram
			{
				$Data = SubStr( $this->_ReadSplitPackets( 3 ), 4 );
			}
			else
			{
				$Status = Socket_Get_Status( $this->Resource );
				$Data   = FRead( $this->Resource, $Status[ 'unread_bytes' ] );
			}
			
			$Buffer .= RTrim( $Data, "\0" );
		}
		
		Socket_Set_TimeOut( $this->Resource, 3 );
		
		return $Buffer;
	}
	
	// ==========================================================
	// DATA WORKERS
	private function WriteData( $Command )
	{
		$Command = "\xFF\xFF\xFF\xFF" . $Command . "\x00";
		
		return !!( !FWrite( $this->Resource, $Command, StrLen( $Command ) ) );
	}
	
	private function ReadData( )
	{
		$Data = FRead( $this->Resource, 1 );
		
		switch( Ord( $Data ) )
		{
			case 255: // Just one datagram
				$Status = Socket_Get_Status( $this->Resource );
				$Data  .= FRead( $this->Resource, $Status[ 'unread_bytes' ] );
				
				break;
			
			case 254: // More than one datagram
				$Data = $this->_ReadSplitPackets( 7 );
				break;
			
			case 0:
				return false;
		}
		
		if( $Data && $Data[ 4 ] == 'l' )
		{
			$Temp = RTrim( SubStr( $Data, 5, 42 ) );
			
			if( $Temp == "You have been banned from this server." )
			{
				throw new SQueryException( $Temp );
				
				return false;
			}
		}
		
		return $Data;
	}
	
	private function _ReadSplitPackets( $BytesToRead )
	{
		FRead( $this->Resource, $BytesToRead );
		
		// The 9th byte tells us the datagram id and the total number of datagrams
		$Data = FRead( $this->Resource, 1 );
		
		// We need to evaluate this in bits (so convert to binary)
		$Bits = SPrintF( "%08b", Ord( $Data ) );
		
		// The low bits denote the total number of datagrams (1-based)
		$Count = BinDec( SubStr( $Bits, -4 ) );
		
		// The high bits denote the current datagram id
		$x = BinDec( SubStr( $Bits, 0, 4 ) );
		
		// The rest is the datagram content.
		$Status = Socket_Get_Status( $this->Resource );
		$Datagrams[ $x ] = FRead( $this->Resource, $Status[ 'unread_bytes' ] );
		
		// Repeat this process for each datagram
		// We've already done the first one, so $i = 1 to start at the next
		for( $i = 1; $i < $Count; $i++ )
		{
			// Skip the header.
			FRead( $this->Resource, 8 );
			
			// Evaluate the 9th byte.
			$Data = FRead( $this->Resource, 1 );
			$x = BinDec( SubStr( SPrintF( "%08b", Ord( $Data ) ), 0, 4 ) );
			
			// Read the datagram content
			$Status = Socket_Get_Status( $this->Resource );
			$Datagrams[ $x ] = FRead( $this->Resource, $Status[ 'unread_bytes' ] );
		}
		
		$Data = "";
		for( $i = 0; $i < $Count; $i++ )
		{
			$Data .= $Datagrams[ $i ];
		}
		
		return $Data;
	}
	
	private function _CutNumber( &$Buffer )
	{
		return Ord( $this->_CutByte( $Buffer ) );
	}
	
	private function _CutByte( &$Buffer, $Length = 1 )
	{
		$String = SubStr( $Buffer, 0, $Length );
		$Buffer = SubStr( $Buffer, $Length );
		
		return $String;
	}
	
	private function _CutString( &$Buffer )
	{
		$Length = StrPos( $Buffer, "\x00" );
		
		if( $Length === FALSE )
		{
		//	$Length = StrLen( $Buffer );
			
			$String = $Buffer;
			$Buffer = "";
		}
		else
		{
			$String = $this->_CutByte( $Buffer, ++$Length );
		}
		
		return $String;
	}
	
	private function _UnPack( $Format, $Buffer )
	{
		List( , $Buffer ) = UnPack( $Format, $Buffer );
		
		return $Buffer;
	}
}

class SQueryException extends Exception
{
	// Isn't it funny to have empty class just to have fancy exception name
}
