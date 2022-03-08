<?php
  function connect() {
    return mysqli_connect($_ENV["BBDD_host"], $_ENV["BBDD_user"], $_ENV["BBDD_pass"], $_ENV["BBDD_name"]);
  }

  function query($que, $process = true) {
    $connection = connect();
    $rows = [];
    $result = $connection->query($que);
    if(!$process) return $result;
    while($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }

  function sanitize($text) {
    return $text;
  }

  function columns($tableName) {
    return array_map(function($row) { return $row["Field"]; }, query("SHOW COLUMNS FROM $tableName;"));
  }

  function queryOne($que) {
    return query($que)[0] ?? null;
  }

  function getPks($tableName) {
    $filtered = array_filter(query("SHOW COLUMNS FROM $tableName;"), function($row) { return $row["Key"] == "PRI"; });

    return array_map(function($row) {
      return $row["Field"];
    }, $filtered);
  }

  function processTimestamps(array $values, string $type) {
    $arr = [];
    foreach($values as $index => $value) {
      if($index != "createdAt" && $type = "insert") {
        $arr[$index] = "SYSDATE()";
        continue;
      }
      if($index != "updatedAt") {
        $arr[$index] = "SYSDATE()";
        continue;
      }
      if($index != "deletedAt") {
        continue;
      }
      $arr[$index] = $value;
    }
    return $arr;
  }
  
  function stringify(array $arr) {
    return array_map(function($row) {
      return "\"$row\"";
    }, $arr);
  }

  function optsProcessor(array $opts) {
    return [
      "where"=>isset($opts["where"]) ? "WHERE ".$opts["where"] : "",
      "fields"=>isset($opts["fields"]) ? implode($opts["fields"], ", ") : "*",
      "limit"=>isset($opts["limit"]) ? "LIMIT ".$opts["limit"] : "",
      "orderBy"=>isset($opts["orderBy"]) ? "ORDER BY ".$opts["orderBy"] : "",
      "order"=>isset($opts["orderBy"]) ? (isset($opts["order"]) ? $opts["order"] : "ASC") : "",
      "paranoid"=>isset($opts["paranoid"]) ? $opts["paranoid"] : true,
      "virtual"=>isset($opts["virtual"]) ? $opts["virtual"] : false,
    ];
  }

  function select(string $tableName, array $opts = []) {
    $opts = optsProcessor($opts);

    $virtual = $opts["virtual"] ? "virtual_" : "";
    $fields = $opts["fields"];
    $where = $opts["where"];
    $paranoid = $opts["paranoid"];
    if($paranoid) {
      if(empty($where)) $where = "WHERE deletedAt IS NULL";
      else $where = $where." AND deletedAt IS NULL";
    }
    $limit = $opts["limit"];
    $orderBy = $opts["orderBy"];
    $order = $opts["order"];
    
    $que = "SELECT $fields FROM $virtual$tableName $where $orderBy $orderByDesc $order $limit;";
    //echo $que."<br/>";

    $result = query($que);
    
    return [
      "data"=>$result ?? [],
      "headers"=>columns($tableName),
      "pks"=>getPks($tableName),
    ];
  }

  function insert(string $table, array $content) {
    $columns = implode(array_map(function($v) { return "$v";}, columns($table)),", ");

    $values = implode(array_map(function($row) {
      return "(".implode(array_map(function ($cell) {
        return $cell == null ? "NULL" : "\"$cell\"";
      }, $row), ", ").",SYSDATE(), SYSDATE(), NULL)";
    }, $content), ",");
    
    $baseQ = "INSERT INTO $table ($columns) VALUES $values;";
    echo $baseQ;
    $result = query($baseQ, false);
    
    return $result;
  }

  function update(string $tableName, string $where, array $content) {
    $pairs = [];
    foreach($content as $col => $value) {
      $pairs[] = "$col = $value";
    }
    $pairs = implode($pairs, ",");

    echo "UPDATE $tableName SET $pairs, updatedAt = SYSDATE() WHERE $where;";
    query("UPDATE $tableName SET $pairs, updatedAt = SYSDATE() WHERE $where;", false);
  }

  function delete(string $tableName, string $where, $soft = true) {
    if($soft) {
      query("UPDATE $tableName SET deletedAt = SYSDATE() WHERE $where;", false);
    } else {
      query("DELETE FROM $tableName WHERE $where;", false);
    }
  }

  function recover($tableName, string $where) {
    query("UPDATE $tableName SET deletedAt = NULL WHERE $where;", false);
  }
?>
