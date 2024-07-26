import AIUI from './aiui';
import { Plugin } from 'ckeditor5/src/core';

export default class AiCKEditor extends Plugin {
  static get requires() {
    return [AIUI];
  }
}
