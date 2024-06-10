<?php

namespace Drupal\ai\Utility;

/**
 * Utility class for value case manipulation.
 */
class StringUtility {

  /**
   * Convert snake_case names used in models configuration into CamelCase.
   *
   * @param string $input
   *   String in snake_case.
   *
   * @return string
   *   String in PascalCase.
   */
  public static function snakeToPascal(string $input): string {
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $input)));
  }

  /**
   * Convert PascalCase names used in Enum into SnakeCase used in settings.
   *
   * @param string $input
   *   String in PascalCase.
   *
   * @return string
   *   String in snake_case.
   */
  public static function pascalToSnake(string $input): string {
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
  }

}
