<?php

/**
 * A implementation of the SQL-joinable storage for fields stored in field_sql_storage.
 */
class Tree_Storage_SQL_Field extends Tree_Storage_SQL {
  public function __construct(DatabaseConnection $database, array $field) {
    $this->database = $database;
    $this->field = $field;

    $this->tableName = key($this->field['storage']['details']['sql'][FIELD_LOAD_CURRENT]);
    $this->columnMap = array(
      'id' => 'entity_id',
      'parent' => $this->field['storage']['details']['sql'][FIELD_LOAD_CURRENT][$this->tableName]['target_id'],
      'weight' => $this->field['storage']['details']['sql'][FIELD_LOAD_CURRENT][$this->tableName]['weight'],
    );
  }

  public function getDatabase() {
    return $this->database;
  }

  public function getColumnMap() {
    return $this->columnMap;
  }

  public function getTableName() {
    return $this->tableName;
  }

  function query() {
    return new Tree_Storage_SQL_Field_Query($this->database, $this->tableName, $this->columnMap);
  }

  public function itemFromFieldData($entity_id, array $field_data) {
    $row = array(
      $this->columnMap['id'] => $entity_id,
      $this->columnMap['parent'] => $field_data['target_id'],
      $this->columnMap['weight'] => $field_data['weight'],
    );
    return new Tree_Storage_SQL_Item($row, $this->columnMap);
  }
}

class Tree_Storage_SQL_Field_Query extends Tree_Storage_SQL_Query {
  function __construct(DatabaseConnection $database, $table_name, $entity_type, array $column_map) {
    $this->query = $database->select($table_name, 't', array('fetch' => PDO::FETCH_ASSOC));
    $this->query->fields('t');

    // Join the field table to the entity table so that the missing IDs
    // appear in the tree.
    $this->query->leftJoin($entity_table, 'e', 'e.' . $entity_info['entity keys']['id'] . ' = t.entity_type');

    $this->columnMap = $column_map;
  }

}