import AIUI from './aiui';
import NetworkStatus from './status/status';
import AiRequest from "./api/request";
import { Plugin } from 'ckeditor5/src/core';
import {ContextualBalloon} from 'ckeditor5/src/ui';

export default class AiCKEditor extends Plugin {
  static get requires() {
    return [AIUI, NetworkStatus, AiRequest, ContextualBalloon];
  }
}
