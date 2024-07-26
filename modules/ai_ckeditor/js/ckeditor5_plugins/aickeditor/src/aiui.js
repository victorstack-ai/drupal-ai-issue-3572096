/**
 * @file registers the Ai Completion button and binds functionality to it.
 */

import {Plugin} from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import icon from '../../../../icons/robot.svg';

export default class Aiui extends Plugin {

  init() {
    const editor = this.editor;
    const options = this.editor.config.get('ai_ckeditor_ai');
    if (!options) {
      return;
    }

    const {dialogURL, openDialog, dialogSettings = {}} = options;
    if (!dialogURL || typeof openDialog !== 'function') {
      return;
    }

    editor.ui.componentFactory.add('aickeditor', (locale) => {
      const buttonView = new ButtonView(locale);

      buttonView.set({
        label: Drupal.t('AI Assistant'),
        icon: icon,
        tooltip: true,
        class: 'ai-dropdown',
        withText: true,
      });

      // Bind the state of the button to the command.
      //buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

      this.listenTo(buttonView, 'execute', () => {
        const selection = editor.model.document.selection;
        const range = selection.getFirstRange();
        let selectedText = '';

        for (const item of range.getItems()) {
          if (typeof item.data !== undefined) {
            selectedText += item.data + ' ';
          }
        }

        const url = new URL(dialogURL, document.baseURI);
        if (selectedText.length > 0) {
          url.searchParams.append('selected_text', selectedText);
        }
        // Since we can't attach an editor instance to the dialog, we need to
        // pass the key for the configuration in the query.
        url.searchParams.append('editor_key', editor.sourceElement.dataset.editorActiveTextFormat);
        openDialog(
          url.toString(),
          ({attributes}) => {
            const model = this.editor.model;
            model.change(writer => {
              const selection = model.document.selection;
              const insertPosition = selection.getFirstPosition();

              // If the insert position is a selection, remove the selection.
              if (selection.hasOwnRange) {
                const range = selection.getFirstRange();
                writer.remove(range);
              }

              if (typeof attributes.returnsHtml != 'undefined' && attributes.returnsHtml) {

                // Covert the value to html and insert it.
                const viewFragment = this.editor.data.processor.toView(attributes.value);
                const modelFragment = this.editor.data.toModel(viewFragment);
                writer.insert(modelFragment, insertPosition);
              }
              else {

                // Insert the value as plain text.
                // const textNode = writer.createText(attributes.value);
                // writer.insert(insertPosition, textNode);
                editor.model.insertContent(
                  writer.createText(attributes.value)
                );
              }
            });
          },
          dialogSettings,
        );
      });

      return buttonView;
    });
  }
}
