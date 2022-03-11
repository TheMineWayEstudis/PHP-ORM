<?php
  function uuid(string $checkOnTable = "", string $field = "id") {
    while(true) {
       $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));

      // Si no se requiere check
      if($checkOnTable == "") return $id;

      // Se busca si ya hay un registro con este id
      $count = queryOne("SELECT COUNT($field) as \"count\" FROM $checkOnTable WHERE id = \"$id\";");
      
      if($count["count"] == 0) return $id;
    }
  }
?>
