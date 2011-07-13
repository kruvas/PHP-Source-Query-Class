<?php
	echo '<pre>';
	
	require 'SourceQuery.class.php';
	
	$Query = new SourceQuery( );
	
	try
	{
		$Query->Connect( 'localhost', 27015 );
		
		$Query->SetRconPassword( 'w00t_rcon' );
		echo $Query->Rcon( 'version' );
		
		print_r( $Query->GetInfo( ) );
		print_r( $Query->GetPlayers( ) );
		print_r( $Query->GetRules( ) );
		
		$Query->Disconnect( );
	}
	catch( SQueryException $e )
	{
		$Query->Disconnect( );
		
		echo "Error: " . $e->getMessage( );
	}
