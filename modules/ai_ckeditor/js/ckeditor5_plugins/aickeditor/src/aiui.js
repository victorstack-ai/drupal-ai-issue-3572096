/**
 * @file registers the Ai Completion button and binds functionality to it.
 */

import {Plugin} from 'ckeditor5/src/core';
import { ButtonView, ViewModel } from 'ckeditor5/src/ui';
import {DropdownButtonView, addListToDropdown, createDropdown} from 'ckeditor5/src/ui';
import icon from '../../../../icons/robot.svg';
import { Collection } from 'ckeditor5/src/utils';
import AiDrupalDialog from "./dialog/AiDrupalDialog";

export default class Aiui extends Plugin {

  init() {
    const editor = this.editor;
    const config = this.editor.config;
    const options = config.get('ai_ckeditor_ai');

    editor.commands.add('AiDrupalDialog', new AiDrupalDialog(editor));

    if (!options) {
      return;
    }

    editor.ui.componentFactory.add('aickeditor', (locale) => {
      const items = new Collection();
      const buttonView = new ButtonView(locale);
      const config = this.editor.config.get('ai_ckeditor_ai');

      if (typeof config.plugins !== 'undefined') {
        Object.keys(config.plugins).forEach(function (plugin_id) {
          items.add({
            type: 'button',
            model: new ViewModel({
              isEnabled: config.plugins[plugin_id].enabled,
              label: config.plugins[plugin_id].meta.label,
              withText: true,
              command: 'AiDrupalDialog',
              group: 'ai_ckeditor_ai',
              plugin_id: plugin_id
            })
          });
        });
      }

      const dropdownView = createDropdown(locale, DropdownButtonView);

      // Create a dropdown with a list inside the panel.
      addListToDropdown(dropdownView, items);

      // Attach the dropdown menu to the dropdown button view.
      dropdownView.buttonView.set({
        label: 'AI Assistant',
        class: 'ai-dropdown',
        icon,
        tooltip: true,
        withText: true,
      });

      buttonView.set({
        label: Drupal.t('AI Assistant'),
        icon: icon,
        tooltip: true,
        class: 'ai-dropdown',
        withText: true,
      });

      this.listenTo(dropdownView, 'execute', (event) => {
        this.editor.execute(event.source.command, event.source.group, event.source.plugin_id, event.source.label);
      });

      return dropdownView;
    });
  }
}
