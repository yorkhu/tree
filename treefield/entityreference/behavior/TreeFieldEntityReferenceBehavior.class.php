<?php

/**
 * Extend an entityreference field to support tree-like structures.
 */
class TreeFieldEntityReferenceBehavior extends EntityReference_BehaviorHandler_Abstract {
  public function __construct($behavior, array $field, array $instance = NULL) {
    parent::__construct($behavior, $field, $instance);

    // @todo: Currently hardcoded. We should have two implementation here:
    // one for field_sql_storage fields and one for other fields.
    $this->storage = new Tree_Storage_SQL_Field(Database::getConnection(), $this->field);
    // @todo: Currently hardcoded, make it onfigurable.
    $this->provider = new Tree_Provider_NestedSet($this->storage);

    // Create the schema of the provider.
    if ($this->provider instanceof Tree_Provider_SQL) {
      $provider_schema = $this->provider->schema();
      foreach ($provider_schema as $table_name => $table_schema) {
        if (!db_table_exists($table_name)) {
          db_create_table($table_name, $table_schema);
        }
      }
    }

    // @todo: this behavior will not work if the target type is different then
    // the instance entity type, find a nice way to warn from this.
    if ($instance && ($instance['entity_type'] != $field['settings']['target_type'])) {
      drupal_set_message(t('The tree behavior can only work properly if the field is attached to the same entity type as the target type.'), 'error');
    }
  }

  public function schema_alter(&$schema, $field) {
    $schema['columns']['weight'] = array(
      'description' => 'The weight of the entity among its siblings',
      'type' => 'int',
      'not null' => FALSE,
      'default' => 0,
    );
  }

  public function property_info_alter(&$info, $entity_type, $field, $instance, $field_type) {
    $property = &$info[$entity_type]['bundles'][$instance['bundle']]['properties'][$field['field_name']];

    $property = array(
      'getter callback' => '_treefield_metadata_field_verbatim_get',
      'setter callback' => 'entity_metadata_field_verbatim_set',

      'property info' => array(),
    );

    $properties = &$property['property info'];

    $properties['parent'] = array(
      'type' => $field['settings']['target_type'],
      'label' => t('Direct parent'),
      'getter callback' => '_treefield_metadata_router_get',
      'field_name' => $field['field_name'],
      'behavior' => $this->plugin['name'],
    );
    $properties['ancestors'] = array(
      'type' => 'list<' . $field['settings']['target_type'] . '>',
      'label' => t('All the ancestors'),
      'getter callback' => '_treefield_metadata_router_get',
      'field_name' => $field['field_name'],
      'behavior' => $this->plugin['name'],
    );
    $properties['children'] = array(
      'type' => 'list<' . $field['settings']['target_type'] . '>',
      'label' => t('Children'),
      'getter callback' => '_treefield_metadata_router_get',
      'field_name' => $field['field_name'],
      'behavior' => $this->plugin['name'],
    );
    $properties['siblings'] = array(
      'type' => 'list<' . $field['settings']['target_type'] . '>',
      'label' => t('Siblings'),
      'getter callback' => '_treefield_metadata_router_get',
      'field_name' => $field['field_name'],
      'behavior' => $this->plugin['name'],
    );
  }

  public function views_data_alter(&$data, $field) {
    if ($this->provider instanceof Tree_Provider_SQL) {
      $this->provider->views_data_alter($data);
    }
  }

  public function presave($entity_type, $entity, $field, $instance, $langcode, &$items) {
    if (count($items)) {
      list($id, , ) = entity_extract_ids($entity_type, $entity);
      $item = $this->storage->itemFromFieldData($id, $items[0]);
      $this->provider->preSave($item);
    }
  }

  public function insert($entity_type, $entity, $field, $instance, $langcode, &$items) {
    if (count($items)) {
      list($id, , ) = entity_extract_ids($entity_type, $entity);
      $item = $this->storage->itemFromFieldData($id, $items[0]);
      $this->provider->postInsert($item);
    }
  }

  public function update($entity_type, $entity, $field, $instance, $langcode, &$items) {
    if (count($items)) {
      list($id, , ) = entity_extract_ids($entity_type, $entity);
      $item = $this->storage->itemFromFieldData($id, $items[0]);
      $this->provider->postUpdate($item);
    }
  }

  protected function metadata_prepare_item($item, $info) {
    // First, get the wrapper of the field.
    $field_wrapper = $info['parent'];
    $field_info = $field_wrapper->info();
    // Then, move one level up to the entity wrapper.
    $entity_wrapper = $field_info['parent'];
    // Fetch the identifier of the entity.
    return $this->storage->itemFromFieldData($entity_wrapper->value(array('identifier' => TRUE)), $item);
  }

  public function metadata_parent_get($item, $options, $name, $type, $info) {
    $item = $this->metadata_prepare_item($item, $info);
    $items = $this->provider->parentOf($item)->execute();
    print_r($item);
    $parent = reset($items);
    if ($parent) {
      return $parent->id;
    }
  }

  public function metadata_ancestors_get($item, $options, $name, $type, $info) {
    $item = $this->metadata_prepare_item($item, $info);
    $ancestor_ids = array();
    foreach ($this->provider->ancestorsOf($item)->execute() as $ancestor) {
      $ancestor_ids[] = $ancestor->id;
    }
    return $ancestor_ids;
  }

  public function metadata_children_get($item, $options, $name, $type, $info) {
    $item = $this->metadata_prepare_item($item, $info);
    $children_ids = array();
    foreach ($this->provider->childrenOf($item)->execute() as $child) {
      $children_ids[] = $child->id;
    }
    return $children_ids;
  }

  public function metadata_siblings_get($item, $options, $name, $type, $info) {
    $item = $this->metadata_prepare_item($item, $info);
    $sibling_ids = array();
    foreach ($provider->siblingsOf($item)->execute() as $sibling) {
      $sibling_ids[] = $sibling->id;
    }
    return $sibling_ids;
  }
}
