<?php

namespace Drupal\search_api_ai\DataType;

use Drupal\search_api\DataType\DataTypePluginBase;
use Drupal\search_api_ai\DataType\EmbeddingsDataTypeInterface;

/**
 * Defines a base class from which other data type classes may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_data_type_info_alter(). The definition includes the following
 * keys:
 * - id: The unique, system-wide identifier of the data type class.
 * - label: The human-readable name of the data type class, translated.
 * - description: A human-readable description for the data type class,
 *   translated.
 * - fallback_type: (optional) The fallback data type for this data type. Needs
 *   to be one of the default data types defined in the Search API itself.
 *   Defaults to "string".
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @SearchApiDataType(
 *   id = "my_data_type",
 *   label = @Translation("My data type"),
 *   description = @Translation("Some information about my data type"),
 *   fallback_type = "string"
 * )
 * @endcode
 *
 * Search API comes with a couple of default data types. These have an extra
 * "default" property in the annotation. It is not allowed for custom data type
 * plugins to set this property.
 *
 * @see \Drupal\search_api\Annotation\SearchApiDataType
 * @see \Drupal\search_api\DataType\DataTypePluginManager
 * @see \Drupal\search_api\DataType\DataTypeInterface
 * @see plugin_api
 */
abstract class EmbeddingsDataTypePluginBase extends DataTypePluginBase implements EmbeddingsDataTypeInterface {

}
