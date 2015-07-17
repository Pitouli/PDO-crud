<?php
	
	class PDOcrud extends PDO {
		
		function __construct($dsn, $db_username, $db_password) {
			parent::__construct($dsn, $db_username, $db_password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
			/*
				try {
				parent::__construct($dsn, $db_username, $db_password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
				} 
				catch (PDOException $e) {
				$response["status"] = "error";
				$response["message"] = 'Connection failed: ' . $e->getMessage();
				$response["data"] = null;
				exit;
				}
			*/
		}
		
		// Method to quickly execute a SELECT request
		//
		// $columns : the list of columns to return, as a string or as an array
		//		ex: "*"
		//		ex: "c.name as n, v.name as pseudo"
		//		ex: array("*")
		//		ex: array("c.name as n", "v.name as pseudo")
		// $table : the tables on which should be accomplished the request, as a string or as an array
		//		ex: "my_table"
		//		ex: "my_table as c"
		//		ex: "my_table as t1 LEFT JOIN my_other_table as t2 ON t1.id = t2.id"
		//		ex: array("my_table as t1", "my_other_table as t2")
		// $where : the WHERE restrictions, as a string or as an array
		// important : with the array, all the conditions are "AND"-linked, and the values are automatically protected
		// 				and are defined to be equal (=) to the column-value.
		//				With the string, you can complexify the WHERE condition, add some LIMIT or GROUP BY statements
		//				but it is very highly encouraged to not include User inputs, but to use parameters :param that you describe in the following parameters
		//				Using :parameters ensure security
		//		ex: array("id" => 125, "name" => "marius")
		//		ex: "id = :id OR name LIKE :name AND likes > :number_of_likes LIMIT 25 ORDER BY name DESC"
		// $params : it is used only if $where as been passed as a string. It defines in an array the value of the used :parameters with SQL protection
		//		ex: array(":id" => 125)
		//		ex: array("id" => 125, "name" => "%marius%", ":number_of_likes" => 657)
		// $orderBy : it is used only if $where as been passed as an array. It's the "ORDER BY" restriction, as a string
		// $limit : it is used only if $where as been passed as an array. It's the "LIMIT" restriction, as an integer, or as a string including the offset before the limit (ex: "5,10")
		// $offset : it is used only if $where as been passed as an array. It's the "OFFSET" restriction, as an integer
		//
		// The data returned is the table of results.
		//
		// $PDOcrud->select("*", "my_table"); // return all the columns of all the rows of my_table
		// $PDOcrud->select(array("pseudo", "password"), "my_table", array("id"=>125)); // return the pseudo and the password of the id 125 in my_table (note that it is protected form SQL injection)
		// $PDOcrud->select(
		//		"t1.pseudo as p, t2.name as n", 
		//		"table1 as t1 LEFT JOIN table2 as t2 ON t1.id = t2.p_id", 
		//		"t2.name LIKE :t2_name OR t1.age > :age", 
		//		array(":t2_name" => "Valentin", ":age" => 23)
		// );
		public function execSelect($columns, $tables, $where = array(), $params = null, $orderBy = null, $limit = null, $offset = null){
			
			$a = array();
			$w = "";
			
			$c = is_array($columns) ? implode(', ',$columns) : trim($columns);
			$t = is_array($tables) ? implode(', ',$tables) : trim($tables);
			
			if (is_array($where)) {
				$w = "1=1";
				foreach ($where as $key => $value) {
					$w .= " AND " .$key. " = :".strtr($key, '.-', '__');
					$a[":".strtr($key, '.-', '__')] = $value;
				}
				if (!empty($orderBy)) {
					$w += " ORDER BY ".$orderBy;
				}
				if (!empty($limit)) {
					$w += " LIMIT ".$limit;
				}
				if (!empty($offset) && is_int($offset)) {
					$w += " OFFSET ".$offset;
				}
			}
			else {
				$w = $where;
				$a = $params;
			}
			
			return $this->rawSelect("SELECT ".$c." FROM ".$t." WHERE ". $w, $a);
			
		}
		
		private function rawSelect($sql, $params = null) {
			
			$response = array();
			$response["status"] = "error";
			$response["message"] = "Unknown error";
			$response["data"] = null;
			
			try{
				$stmt = $this->prepare($sql);
				$stmt->execute($params);
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
				
				if(count($rows)<=0){
					$response["status"] = "warning";
					$response["message"] = "No data found.";
				}
				else{
					$response["status"] = "success";
					$response["message"] = "Data selected from database";
				}
				
				$response["data"] = $rows;
			}
			catch(PDOException $e){
				$response["status"] = "error";
				$response["message"] = "Select Failed: " .$e->getMessage();
				$response["data"] = null;
			}
			return $response;
		}
		
		public function select($tables = null) {
			if ($tables == null) {
				return new QueryBuilderSelect($this);				
			}
			else {
				$QB = new QueryBuilderSelect($this);
				return $QB->tables($tables);
			}
		}
		
		public function s($tables = null) { // "s" as "SELECT"
			return $this->select($tables);
		}		
		public function read($tables = null) {
			return $this->select($tables);
		}		
		public function r($tables = null) { // "r" as "READ"
			return $this->select($tables);
		}
		
		// Method to quickly execute an INSERT request
		//
		// $table : the tables on which should be accomplished the request, as a string 
		//		ex: "my_table"
		// $values : an associative array listing the name of the columns associated to the value to insert
		//		ex: array("firstname" => "pedro", "lastname" => "rodriguez", "age" => 213)
		// $requiredColumns : a facultative array of all the columns that are mandatory for the insertion
		//		ex: array("firstname", "lastname")
		//
		// The data returned is the Id of the inserted row
		//
		// $PDOcrud->insert(
		//		"my_table", 
		//		array("firstname" => "pedro", "lastname" => "rodriguez", "age" => 213)
		// ); // return all the columns of all the rows of my_table
		public function execInsert($table, $values, $requiredColumns = array()) {
			
			$responseVerif = $this->verifyRequiredParams($values, $requiredColumns);
			
			if ($responseVerif["status"] == "error") {
				return $responseVerif;
				exit;
			}
			else {
				
				$a = array();
				$c = "";
				$v = "";
				
				foreach ($values as $key => $value) {
					$c .= $key. ", ";
					$v .= ":".strtr($key, '.-', '__'). ", ";
					$a[":".strtr($key, '.-', '__')] = $value;
				}
				
				$c = rtrim($c,', ');
				$v = rtrim($v,', ');
				
				return $this->rawInsert("INSERT INTO $table($c) VALUES($v)", $a);
				
			}
		}
		
		private function rawInsert($sql, $params = null) {
			
			$response = array();
			$response["status"] = "error";
			$response["message"] = "Unknown error";
			$response["data"] = null;
			
			try{
				$stmt =  $this->prepare($sql);
				$stmt->execute($params);
				$affected_rows = $stmt->rowCount();
				$response["data"] = $this->lastInsertId();
				
				$response["status"] = "success";
				$response["message"] = $affected_rows." row inserted into database";
			}
			catch(PDOException $e){
				$response["status"] = "error";
				$response["message"] = "Insert Failed: " .$e->getMessage();
			}
			return $response;
		}
		
		public function insert($tables = null) {
			if ($tables == null) {
				return new QueryBuilderInsert($this);				
			}
			else {
				$QB = new QueryBuilderInsert($this);
				return $QB->tables($tables);
			}
		}
		
		public function i($tables = null) { // "i" as "INSERT"
			return $this->insert($tables);
		}
		public function create($tables = null) { 
			return $this->insert($tables);
		}
		public function c($tables = null) { // "c" as "CREATE"
			return $this->insert($tables);
		}
		
		// Method to quickly execute an UPDATE request
		//
		// $table : the tables on which should be accomplished the request, as a string
		//		ex: "my_table"
		// $where : the WHERE restrictions, as a string or as an array
		// important : with the array, all the conditions are "AND"-linked, and the values are automatically protected
		// 				and are defined to be equal (=) to the column-value.
		//				With the string, you can complexify the WHERE condition, add some LIMIT or GROUP BY statements
		//				but it is very highly encouraged to not include User inputs, but to use parameters :param that you describe in the last function parameter
		//				Using :parameters ensure security
		//		ex: array("id" => 125, "name" => "marius")
		//		ex: "id = :id OR name LIKE :name AND likes > :number_of_likes LIMIT 25 ORDER BY name DESC"
		// $requiredColumns : a facultative (unless you use the string version of the "where" parameter and then you should at least pass a null value) 
		//		array of all the columns that are mandatory for the insertion
		//		ex: array("firstname", "lastname")
		// $params : it is used only if $where as been passed as a string. It defines in an array the value of the used :parameters with SQL protection
		//		ex: array(":id" => 125)
		//		ex: array("id" => 125, "name" => "%marius%", ":number_of_likes" => 657)
		// $orderBy : it is used only if $where as been passed as an array. It's the "ORDER BY" restriction, as a string
		// $limit : it is used only if $where as been passed as an array. It's the "LIMIT" restriction, as an integer, or as a string including the offset before the limit (ex: "5,10")
		//
		// The data returned in case of success is the number of affected rows
		//
		// $PDOcrud->update(
		//		"my_table", 
		//		array("lastname" => "roger", "firstname" => "raoul"), 
		//		array("firstname" => "mickael"), 
		//		array("lastname")
		// ); // Update everyone with firstname "mickael" to now have the firstname "raoul" and the mandatory lastname "roger"
		public function execUpdate($table, $setValues, $where, $requiredColumns = array(), $params = null, $orderBy = null, $limit = null){ 
			
			$responseVerif = $this->verifyRequiredParams($setValues, $requiredColumns);
			if ($responseVerif["status"] == "error") {
				return $responseVerif;
				exit;
			}
			else {
				
				$a = array();
				$w = "";
				$c = "";
				
				if (is_array($where)) {
					$w = "1=1";
					foreach ($where as $key => $value) {
						$w .= " AND " .$key. " = :w_".strtr($key, '.-', '__');
						$a[":w_".strtr($key, '.-', '__')] = $value;
					}
					if (!empty($orderBy)) {
						$w += " ORDER BY ".$orderBy;
					}
					if (!empty($limit) && is_int($limit)) {
						$w += " LIMIT ".$limit;
					}
				}
				else {
					$w = $where;
					$a = $params;
					foreach ($params as $key => $value) {
						$a[$key] = $value;
					}
				}
				
				foreach ($setValues as $key => $value) {
					$c .= $key. " = :u_".strtr($key, '.-', '__').", ";
					$a[":u_".strtr($key, '.-', '__')] = $value;
				}
				
				$c = rtrim($c,", ");
				
				return $this->rawUpdate("UPDATE $table SET $c WHERE ".$w, $a);		
				
			}
		}
		
		private function rawUpdate($sql, $params = null){ 
			
			$response = array();
			$response["status"] = "error";
			$response["message"] = "Unknown error";
			$response["data"] = null;		
			
			try{
				$stmt = $this->prepare($sql);
				$stmt->execute($params);
				$affected_rows = $stmt->rowCount();
				
				if($affected_rows<=0){
					$response["status"] = "warning";
					$response["message"] = "No row updated";
				}
				else{
					$response["status"] = "success";
					$response["message"] = $affected_rows." row(s) updated in database";
				}
				
				$response["data"] = $affected_rows;
			}
			catch(PDOException $e){
				$response["status"] = "error";
				$response["message"] = "Update Failed: " .$e->getMessage();
			}
			
			return $response;
		}
		
		public function update($tables = null) {
			if ($tables == null) {
				return new QueryBuilderUpdate($this);				
			}
			else {
				$QB = new QueryBuilderUpdate($this);
				return $QB->tables($tables);
			}
		}
		
		public function u($tables = null) { // "u" as "UPDATE"
			return $this->update($tables);
		}
		
		// Method to quickly execute a DELETE request
		//
		// $table : the tables on which should be accomplished the request, as a string
		//		ex: "my_table"
		// $where : the WHERE restrictions, as a string or as an array
		// important : with the array, all the conditions are "AND"-linked, and the values are automatically protected
		// 				and are defined to be equal (=) to the column-value.
		//				With the string, you can complexify the WHERE condition, add some LIMIT or GROUP BY statements
		//				but it is very highly encouraged to not include User inputs, but to use parameters :param that you describe in the last function parameter
		//				Using :parameters ensure security
		//		ex: array("id" => 125, "name" => "marius")
		//		ex: "id = :id OR name LIKE :name AND likes > :number_of_likes LIMIT 25 ORDER BY name DESC"
		// $params : it is used only if $where as been passed as a string. It defines in an array the value of the used :parameters with SQL protection
		//		ex: array(":id" => 125)
		//		ex: array("id" => 125, "name" => "%marius%", ":number_of_likes" => 657)
		//
		// The data returned in case of success is the number of affected rows
		//
		// $PDOcrud->update(
		//		"my_table", 
		//		array("lastname" => "roger", "firstname" => "raoul"), 
		//		array("firstname" => "mickael"), 
		//		array("lastname")
		// ); // Update everyone with firstname "mickael" to now have the firstname "raoul" and the mandatory lastname "roger"		
		function execDelete($table, $where, $params = null){
			
			if(empty($where)) {
				$response = array();
				$response["status"] = "warning";
				$response["message"] = "Delete Failed: At least one condition is required";
				$response["data"] = null;
				
				return $response;
			}
			else{
				
				$a = array();
				$w = "";
				
				if (is_array($where)) {
					$w = "1=1";
					foreach ($where as $key => $value) {
						$w .= " AND " .$key. " = :w_".strtr($key, '.-', '__');
						$a[":w_".strtr($key, '.-', '__')] = $value;
					}
				}
				else {
					$w = $where;
					$a = $params;
				}
				
				return $this->rawDelete("DELETE FROM $table WHERE ".$w, $a);
			}
		}
		
		private function rawDelete($sql, $params = null){
			
			$response = array();
			$response["status"] = "error";
			$response["message"] = "Unknown error";
			$response["data"] = null;
			
			try{
				
				$stmt =  $this->prepare($sql);
				$stmt->execute($params);
				$affected_rows = $stmt->rowCount();
				
				if($affected_rows<=0){
					$response["status"] = "warning";
					$response["message"] = "No row deleted";
				}
				else{
					$response["status"] = "success";
					$response["message"] = $affected_rows." row(s) deleted from database";
				}
				
				$response["data"] = $affected_rows;
			}
			catch(PDOException $e){
				$response["status"] = "error";
				$response["message"] = "Delete Failed: " .$e->getMessage();
			}
			
			return $response;
		}
		
		public function delete($tables = null) {
			if ($tables == null) {
				return new QueryBuilderDelete($this);				
			}
			else {
				$QB = new QueryBuilderDelete($this);
				return $QB->tables($tables);
			}
		}
		
		public function d($tables = null) { // "d" as "DELETE"
			return $this->delete($tables);
		}
		
		public function preparedQuery($sql, $params = null) {
			
			// We check that there is only ONE request in the sql statement
			$sql = trim($sql);
			$lastChar = substr($sql, -1);
			$nbSemicolon = substr_count($sql, ';');
			$nbQuery = $lastChar == ';' ? $nbSemicolon : $nbSemicolon + 1;
			
			// If there is more than one query
			if ($nbQuery > 1) {
				
				$response = array();
				$response["status"] = "error";
				$response["message"] = "Query Failed: it must have one and only one query";
				$response["data"] = null;
				
				return $response;
			}
			else {
				$type = substr($sql, 0, 6); // We try to determine the type of the query
				
				if (strcasecmp($type, "SELECT") == 0) {
					return $this->rawSelect($sql, $params);
				}
				else if (strcasecmp($type, "INSERT") == 0){
					return $this->rawInsert($sql, $params);
				}
				else if (strcasecmp($type, "UPDATE") == 0){
					return $this->rawUpdate($sql, $params);
				}
				else if (strcasecmp($type, "DELETE") == 0){
					return $this->rawDelete($sql, $params);
				}
				else { // If we do not know the type of the query, we execute it without knowing the best response to give
					$response = array();
					$response["status"] = "error";
					$response["message"] = "Unknown error";
					$response["data"] = null;
					
					try{
						$stmt = $this->prepare($sql);
						$stmt->execute($params);
						$response["status"] = "warning";
						$response["message"] = "It could had work ; at least, the query has not raise an exception. That's all we know.";
					}
					catch(PDOException $e){
						$response["status"] = "error";
						$response["message"] = "Delete Failed: " .$e->getMessage();
					}
					
					return $response;					
				}
				
			}		
			
		}
		
		private function verifyRequiredParams($inArray, $requiredColumns) {
			
			$response = array();
			$response["status"] = "success";
			$response["message"] = null;
			$response["data"] = null;
			
			if (!empty($requiredColumns)) {
				
				$error = false;
				$errorColumns = "";
				
				foreach ($requiredColumns as $field) {
					if (!isset($inArray[$field]) || strlen(trim($inArray[$field])) <= 0) {
						$error = true;
						$errorColumns .= $field . ', ';
					}
				}
				
				if ($error) {
					$response["status"] = "error";
					$response["message"] = "Required field(s) " . rtrim($errorColumns, ', ') . " is missing or empty";
				}	
				
			}
			
			return $response;
		}
		
		public function toJson ($response) {
			return json_encode($response,JSON_NUMERIC_CHECK);
		}
		
	}
	
	abstract class QueryBuilder {
		
		protected $db;
		
		protected $tables;
		
		public function tables($p) { $this->tables = $p; return $this; }
		public function t($p) { $this->tables($p); return $this; }
		
		function __construct(PDOcrud &$pDb) {
			$this->db = $pDb;
		}
		
		abstract public function exec($json = false);
		public function e($json = false) { return $this->exec($json); }
	}
	
	class QueryBuilderSelect extends QueryBuilder {
		private $columns = "*";
		private $where = array();
		private $orderBy = null;
		private $limit = null;
		private $offset = null;
		private $params = null;
		
		private $onlyFirstRow = false;
		
		public function columns($p) { $this->columns = $p; return $this; }
		public function col($p) { $this->columns($p); return $this; }
		public function c($p) { $this->columns($p); return $this; }
		public function where($p) { $this->where = $p; return $this; }
		public function w($p) { $this->where($p); return $this; }
		public function orderBy($p) { $this->orderBy = $p; return $this; }
		public function ob($p) { $this->orderBy($p); return $this; }
		public function limit($p1, $p2 = null) { 
			if (empty($p2) && is_int($p1)) {
				$this->limit = (int) $p1;
			}
			else if (is_int($p1) && is_int($p2)) {
				$this->offset = (int) $p1;
				$this->limit = (int) $p2;
			}
			return $this; 
		}
		public function l($p1, $p2 = null) { $this->limit($p1, $p2); return $this; }
		public function offset($p) { 
			if (is_int($p)) {
				$this->offset = (int) $p;
			}
			return $this; 
		}
		public function o($p) { $this->offset($p); return $this; }
		public function params($p) { $this->params = $p; return $this; }
		public function p($p) { $this->params($p); return $this; }
		
		public function onlyFirstRow($p) { 
			if (is_bool($p)) {
				$this->onlyFirstRow = (bool) $p;
			}
			return $this; 
		}
		public function ofr($p) { $this->firstRow($p); return $this; }
		
		public function exec($json = false) {
			
			$response = array();
			$response["status"] = "error";
			$response["message"] = "Select failed for an unknown reason";
			$response["data"] = null;	
			
			if (!isset($this->tables) || empty($this->tables)) {
				$response["message"] = "Select failed: a table is mandatory";		
			}
			else {
				$response = $this->db->execSelect($this->columns, $this->tables, $this->where, $this->params, $this->orderBy, $this->limit, $this->offset);
			}
			if ($this->onlyFirstRow) {
				if (count($response['data']) == 0) {
					$response['data'] = null;
				}
				else {
					$response['data'] = $response['data'][0];
				}
			}
			if ($json) {
				$response = $this->db->toJson($response);
			}
			
			return $response;
		}
		public function fetchAll($json = false) { return $this->exec($json); }
		
		public function execFirstRow($json = false) {
			$this->onlyFirstRow(true);
			return $this->exec($json);
		}
		public function firstRow($json = false) { return $this->execFirstRow($json); }
		public function e1r($json = false) { return $this->execFirstRow($json); }
		public function fetch($json = false) { return $this->execFirstRow($json); }
	}
	class QueryBuilderInsert extends QueryBuilder {
		private $values = null;
		private $requiredColumns = array();
		
		public function values($p) { $this->values = $p; return $this; }
		public function val($p) { $this->values($p); return $this; }
		public function v($p) { $this->values($p); return $this; }
		public function requiredColumns($p) { $this->requiredColumns = $p; return $this; }
		public function reqCol($p) { $this->requiredColumns($p); return $this; }
		public function rC($p) { $this->requiredColumns($p); return $this; }
		
		public function exec($json = false) {
			
			$response = array();
			$response["status"] = "error";
			$response["message"] = "Insert failed for an unknown reason";
			$response["data"] = null;	
			
			if (!isset($this->tables) || empty($this->tables)) {
				$response["message"] = "Insert failed: a table is mandatory";			
			}
			else {
				$response = $this->db->execInsert($this->tables, $this->values, $this->requiredColumns);
			}
			if ($json) {
				$response = $this->db->toJson($response);
			}
			
			return $response;
		}		
	}
	class QueryBuilderUpdate extends QueryBuilder {
		private $setValues;
		private $where;
		private $requiredColumns = array();
		private $orderBy = null;
		private $limit = null;
		private $params = null;
		
		public function setValues($p) { $this->setValues = $p; return $this; }
		public function setVal($p) { $this->setValues($p); return $this; }
		public function sV($p) { $this->setValues($p); return $this; }
		public function requiredColumns($p) { $this->requiredColumns = $p; return $this; }
		public function reqCol($p) { $this->requiredColumns($p); return $this; }
		public function rC($p) { $this->requiredColumns($p); return $this; }
		public function where($p) { $this->where = $p; return $this; }
		public function w($p) { $this->where($p); return $this; }
		public function orderBy($p) { $this->orderBy = $p; return $this; }
		public function ob($p) { $this->orderBy($p); return $this; }
		public function limit($p) { 
			if (is_int($p)) {
				$this->limit = (int) $p;
			}
			return $this; 
		}
		public function l($p) { $this->limit($p); return $this; }
		public function params($p) { $this->params = $p; return $this; }
		public function p($p) { $this->params($p); return $this; }
		
		public function exec($json = false) {
			
			$response = array();
			$response["status"] = "error";
			$response["message"] = "Update failed for an unknown reason";
			$response["data"] = null;	
			
			if (!isset($this->tables) || empty($this->tables)) {
				$response["message"] = "Update failed: a table is mandatory";			
			}
			else if (!isset($this->setValues) || empty($this->setValues)) {
				$response["message"] = "Update failed: no value provided";			
			}
			else if (!isset($this->where) || empty($this->where)) {
				$response["message"] = "Update failed: no clause where provided";				
			}
			else {
				$response = $this->db->execUpdate($this->tables, $this->setValues, $this->where, $this->requiredColumns, $this->params, $this->orderBy, $this->limit);
			}
			if ($json) {
				$response = $this->db->toJson($response);
			}
			
			return $response;
		}		
	}
	class QueryBuilderDelete extends QueryBuilder {
		private $where = array();
		private $params = null;
		
		public function where($p) { $this->where = $p; return $this; }
		public function w($p) { $this->where($p); return $this; }
		public function params($p) { $this->params = $p; return $this; }
		public function p($p) { $this->params($p); return $this; }
		
		public function exec($json = false) {
			
			$response = array();
			$response["status"] = "error";
			$response["message"] = "Delete failed for an unknown reason";
			$response["data"] = null;	
			
			if (!isset($this->tables) || empty($this->tables)) {
				$response["message"] = "Delete failed: a table is mandatory";		
			}
			else if (!isset($this->where) || empty($this->where)) {
				$response["message"] = "Delete failed: no clause where provided";
			}
			else {
				$response = $this->db->execDelete($this->tables, $this->where, $this->params);
			}
			if ($json) {
				$response = $this->db->toJson($response);
			}
			
			return $response;
		}		
	}
	
?>
