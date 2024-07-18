import { Command } from 'ckeditor5/src/core';

export default class HelpCommand extends Command {

  execute() {
    window.open('https://www.drupal.org/project/issues/ai?categories=All', '_blank');
  }

}
