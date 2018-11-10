<?php
	$servername = "localhost";
	$username = "root";
	$password = "1";
	$dbName = "test";
	$dbty = "mysql:host=$servername;dbN=$dbName";
	try
	{
		$conn = new PDO($dbty, $username, $password);
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$sql = "create database myfirst";
		$conn->query($sql);
		$sql = "use myfirst";
		$conn->query($sql);
		$sql = "create table school(
			name varchar(50),
			year date,
			class int
		)";
		$conn->query($sql);	
	}
	catch(PDOException $err)
	{
		echo "database or table is exist .".$err->getMessage();
		echo "\r\n";	
		$sql = "use myfirst";
		$conn->query($sql);
		$sql = "insert into school values ('vv','19931018','25')";
		echo $sql;
		$conn->query($sql);	
		$sql = "select * from school";
		$data = $conn->query($sql);
		while($row = $data->fetch())
		{
			echo $row["name"]." ";	
			echo $row["year"]." ";
			echo $row["class"]." ";
			echo "\r\n";
		}
		

	}

	$conn =null;