/**
 * @file registers the Ai Completion button and binds functionality to it.
 */

import {Plugin} from 'ckeditor5/src/core';
import {DropdownButtonView, ViewModel, addListToDropdown, createDropdown} from 'ckeditor5/src/ui';
import icon from '../../../../icons/robot.svg';
import { Collection } from 'ckeditor5/src/utils';
import CompletionCommand from './completion/completioncommand';
import HelpCommand from "./help/helpcommand";
import TranslateCommand from "./translate/translatecommand";
import ToneCommand from './tone/tonecommand';
import SummarizeCommand from './summarize/summarizecommand';
import ReformatHTMLCommand from "./reformat_html/reformathtmlcommand";

export default class Aiui extends Plugin {

  init() {
    const editor = this.editor;
    const config = this.editor.config.get('ai_ckeditor');
    editor.commands.add('AiCompletionCommand', new CompletionCommand(editor, config.completion));
    editor.commands.add('AiTranslateCommand', new TranslateCommand(editor, config.completion));
    editor.commands.add('AiToneCommand', new ToneCommand(editor, config.completion));
    editor.commands.add('AiSummarizeCommand', new SummarizeCommand(editor, config.completion));
    editor.commands.add('AiHelpCommand', new HelpCommand(editor));
    editor.commands.add('AiReformatHTMLCommand', new ReformatHTMLCommand(editor, config.completion));

    editor.ui.componentFactory.add( 'aickeditor', locale => {
      const items = new Collection();

      // @todo: loop Enabled plugins and add them as items with their configuration
      items.add( {
        type: 'button',
        model: new ViewModel( {
            isEnabled: config.completion.enabled,
            label: 'Text Completion',
            withText: true,
            command: 'AiCompletionCommand',
            group: config.completion
        } )
      });

      items.add( {
        type: 'button',
        model: new ViewModel( {
          isEnabled: config.completion.enabled,
          label: 'Adjust tone/voice',
          withText: true,
          command: 'AiToneCommand',
          group: config.completion
        } )
      });

      items.add( {
        type: 'button',
        model: new ViewModel( {
            isEnabled: config.completion.enabled,
            label: 'Summarize',
            withText: true,
            command: 'AiSummarizeCommand',
            group: config.completion
        } )
      });

      items.add( {
        type: 'button',
        model: new ViewModel( {
            isEnabled: config.completion.enabled,
            label: 'Translate',
            withText: true,
            command: 'AiTranslateCommand',
            group: config.completion
        } )
      });

      items.add( {
        type: 'button',
        model: new ViewModel( {
          isEnabled: config.completion.enabled,
          label: 'Reformat/correct HTML',
          withText: true,
          command: 'AiReformatHTMLCommand',
          group: config.completion
        } )
      });

      //
      // items.add( {
      //   type: 'button',
      //   model: new ViewModel( {
      //       isEnabled: false,
      //       label: 'Sentiment analysis',
      //       withText: true,
      //       command: '',
      //       group: {}
      //   } )
      // });

      items.add( {
        type: 'button',
        model: new ViewModel( {
          label: 'Help & Support',
          withText: true,
          command: 'AiHelpCommand',
          group: {}
        } )
      });

      const dropdownView = createDropdown( locale, DropdownButtonView );

      // Create a dropdown with a list inside the panel.
      addListToDropdown( dropdownView, items );

      // Attach the dropdown menu to the dropdown button view.
      dropdownView.buttonView.set( {
        label: 'AI Assistant',
        class: 'ai-dropdown',
        icon,
        tooltip: true,
        withText: true,
      });

      this.listenTo(dropdownView, 'execute', (evt) => {
        this.editor.execute(evt.source.command, evt.source.group);
      });

      return dropdownView;
    });

  }
}
