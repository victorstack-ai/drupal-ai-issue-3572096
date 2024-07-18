import { Command } from 'ckeditor5/src/core';
import AiRequest from "../api/request";

export default class SummarizeCommand extends Command {
  constructor(editor, config) {
    super(editor);
    this._config = config;
    this._request = this.editor.plugins.get( AiRequest );
  }

  execute(options = {}) {
    const editor = this.editor;
    const selection = editor.model.document.selection;
    const range = selection.getFirstRange();
    let selectedText = '';

    for (const item of range.getItems()) {
      if (typeof item.data !== undefined) {
        selectedText += item.data + ' ';
      }
    }

    if (!selectedText.length) {
      return;
    }

    const prompt = 'Summarize the following text into something more compact using the same language as the following text: ' + selectedText;
    this._request.doRequest('api/ai-ckeditor/completion', {'prompt': prompt, 'options': this._config});
  }
}
