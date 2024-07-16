(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.chatFormStream = {
    attach: (context) => {

      let streamElements = $('[data-response]', context);
      once('data-streamed', streamElements).forEach((item) => {
        const element = $(item);
        const form = element.closest('form');

        // Set up a click handler to submit the form and stream the response back.
        element.click((event) => {
          event.preventDefault();
          const clickedElement = $(event.currentTarget);
          const responseField = $('#' + clickedElement.attr('data-response'));
          let data = form.serializeArray();

          // Push an event for the current submission.
          data.push({
            name: event.currentTarget.name,
            value: event.currentTarget.value
          });

          $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: data,
            xhrFields: {
              onprogress: function (event) {
                responseField.html(event.currentTarget.response.replaceAll("\n", "<br />"));
                responseField.scrollTop(responseField[0].scrollHeight);
              }
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
