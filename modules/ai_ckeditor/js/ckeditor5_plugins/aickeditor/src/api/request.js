import {Plugin} from 'ckeditor5/src/core';
import NetworkStatus from "../status/status";

export default class AiRequest extends Plugin {

  constructor(editor) {
    super(editor);
    this._status = this.editor.plugins.get( NetworkStatus );
  }

  doRequest(endpoint, data) {
    const editor = this.editor;
    const status = this._status;

    status.fire('ai_status', {
      status: 'Waiting for response...'
    });

    editor.model.change(async writer => {
      const response = await fetch(drupalSettings.path.baseUrl + endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        body: JSON.stringify(data),
      });

      if (!response.ok) {
        status.fire('ai_status', {
          status: 'An error occurred. Check the logs for details.'
        });

        setTimeout(() => {
          status.fire('ai_status', {status: 'Idle'});
        }, 3000);
      }

      status.fire('ai_status', {
        status: 'Receiving response...'
      });

      const reader = response.body.getReader();

      while (true) {
        const {value, done} = await reader.read();
        const text = new TextDecoder().decode(value);

        if (done) {
          status.fire('ai_status', {
            status: 'Request completed.'
          });

          setTimeout(() => {
            status.fire('ai_status', {status: 'Idle'});
          }, 1200);
          break;
        }

        editor.model.insertContent(
          writer.createText(text)
        );
      }
    } );
  }

}
