<?php
	require_once 'DB.class.php';
	$db = new DB();
	
	echo empty(null);
	
	/*
		$columns = array("plip", "plap");
		$c = is_array($columns) ? implode(', ',$columns) : trim($columns);
		echo $c;
		
		testWhere();
		testWhere("");
		testWhere("id = 2 AND plip = plop");
		testWhere(array('id'=>125));
		
		function testWhere($where = null) {
		$req = new Request();
		echo "<h3>TEST DE LA METHODE WHERE </h3>";
		echo 'Syntaxe : ';
		print_r($where);
		echo '<br /><br />';
		echo '<br />SQL : "'.$req->where($where)->whereSql.'"<br />';
		echo '<pre>';
		print_r($req->whereAssoc);
		echo '</pre>';	
		}
	*/
	print '<pre>';
	/**
		* Database Helper Function templates
	*/
	/*
		select(table name, where clause as associative array)
		insert(table name, data as associative array, mandatory column names as array)
		update(table name, column names as associative array, where clause as associative array, mandatory columns as array)
		delete(table name, where clause as array)
	*/
	echo "<h1>With Builder</h1>";
	
	
	print_r($db->read("customers_php as c")->where("c.id > :id_min")->params(array(":id_min" => 290))->exec());
	print_r($db->insert("customers_php")->val(array('name' => 'Miguel', 'email'=>'ipi@angularcode.com'))->reqCol(array('name', 'email'))->exec());
	print_r($db->update("customers_php")->setVal(array('name' => 'Monica'))->where(array('name' => 'Miguel'))->exec());
	print_r($db->delete("customers_php")->where(array('name' => 'Monica'))->exec());
	
	/*
		$rows = $db->select("*","customers_php",array('id'=>171));
		print_r($rows);
		print_r(json_encode($rows,JSON_NUMERIC_CHECK));
		
		
		
		$rows = $db->insert("customers_php",array('name' => 'Ipsita Sahoo', 'email'=>'ipi@angularcode.com'), array('name', 'email'));
		print_r($rows);
		print_r(json_encode($rows,JSON_NUMERIC_CHECK));
		
		
		
		$rows = $db->update("customers_php",array('name' => 'Manou Sahoo', 'email'=>'email'),"id < :id_max AND id > :id_min", array('name', 'email'), array(":id_min" => 260, ":id_max" => 266));
		print_r($rows);
		print_r(json_encode($rows,JSON_NUMERIC_CHECK));
		
		
		
		$rows = $db->delete("customers_php", array('name' => 'Ipsita Sahoo', 'id'=>'254'));
		print_r($rows);
		print_r(json_encode($rows,JSON_NUMERIC_CHECK));
	*/
	echo "<h1>Without Builder</h1>";
	
	$rows = $db->select(array("v.name as n", "v.id as i"),"customers_php as v");
	print_r($rows);
	print_r(json_encode($rows,JSON_NUMERIC_CHECK));
	
	$rows = $db->select("*","customers_php",array('id'=>171));
	print_r($rows);
	print_r(json_encode($rows,JSON_NUMERIC_CHECK));
	
	
	
	$rows = $db->insert("customers_php",array('name' => 'Ipsita Sahoo', 'email'=>'ipi@angularcode.com'), array('name', 'email'));
	print_r($rows);
	print_r(json_encode($rows,JSON_NUMERIC_CHECK));
	
	
	
	$rows = $db->update("customers_php",array('name' => 'Manou Sahoo', 'email'=>'email'),"id < :id_max AND id > :id_min", array('name', 'email'), array(":id_min" => 260, ":id_max" => 266));
	print_r($rows);
	print_r(json_encode($rows,JSON_NUMERIC_CHECK));
	
	
	
	$rows = $db->delete("customers_php", array('name' => 'Ipsita Sahoo', 'id'=>'254'));
	print_r($rows);
	print_r(json_encode($rows,JSON_NUMERIC_CHECK));
	
	
	print '</pre>';
?>