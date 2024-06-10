(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.searchApiAiStream = {
    attach: (context) => {
      let streamElements = $('[data-search-api-ai-ajax]', context);
      // @todo: Move away from once() since its not in core?
      once('data-streamed', streamElements).forEach((item) => {
        const element = $(item);
        const form = element.closest('form');

        // Set up a key down handler to submit the form on Enter press in textarea.
        form.find('.chat-form-query').on('keydown', (event) => {
          if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.find('.chat-form-send').click();
          }
        });

        // Set up a click handler to submit the form and stream the response back.
        element.click((event) => {
          event.preventDefault();

          const clickedElement = $(event.currentTarget);
          const clickedBlock = clickedElement.attr('data-search-api-ai-ajax');
          // Get the message from the textarea.
          let message = form.find('.chat-form-query').val();
          renderUserChatMessage(message)
          .then(() => {
            renderBotChatMessage(form);
          });
        });
      });
    }
  };

  function renderUserChatMessage(message) {
    return new Promise((resolve, reject) => {
      $.ajax({
        url: drupalSettings.path.baseUrl + 'ajax/chatbot/message-skeleton'
      })
      .done((data) => {
        let skeleton = data.skeleton;
        $('.chat-history').append(skeleton);
        $('.chat-history .chat-message:last h5').html(drupalSettings.search_api_ai_chatbot.default_username);
        $('.chat-history .chat-message:last img').attr('src', drupalSettings.search_api_ai_chatbot.default_avatar);
        let responseField = $('.chat-history .chat-message:last');
        responseField.find('.chat-message-message').html(message);
        $('.chat-history').scrollTop($('.chat-history')[0].scrollHeight);
        return resolve();
      })
      .fail(() => {
        return reject();
      });
    });
  }

  function renderBotChatMessage(form) {
    $.ajax({
      url: drupalSettings.path.baseUrl + 'ajax/chatbot/message-skeleton'
    })
    .done((data) => {
      let skeleton = data.skeleton;
      $('.chat-history').append(skeleton);
      $('.chat-history .chat-message:last h5').html(drupalSettings.search_api_ai_chatbot.bot_name);
      let responseField = $('.chat-history .chat-message:last .chat-message-message');
      let postData = form.serializeArray();

        $.ajax({
          url: form.attr('action'),
          method: 'POST',
          data: postData,
          xhrFields: {
            onprogress: function (event) {
              responseField.html(event.currentTarget.response);
              $('.chat-history').scrollTop($('.chat-history')[0].scrollHeight);
            },
            onended: function (event) {
              console.log('test');
            }
          }
        });
    });
  }

  // Logic for minimizing the chatbot.
  $(document).ready(() => {
    const chatStatus = localStorage.getItem("livechat.closed");
    if (chatStatus == 'false') {
      $('#live-chat .chat').show();
    }
    console.log(chatStatus);
    $('#live-chat header').click(function() {
      $('.chat').toggle(function () {
        localStorage.setItem("livechat.closed", localStorage.getItem("livechat.closed") == 'true' ? 'false' : 'true');
        $(this).animate({
          display: 'block',
        }, 100);
      })
    });
    expandTextarea('edit-query');
  });

  function expandTextarea(id) {
    document.getElementById(id).addEventListener('keyup', function () {
      this.style.overflow = 'hidden';
      this.style.height = 0;
      this.style.height = this.scrollHeight + 'px';
    }, false);
  }

})(jQuery, Drupal, drupalSettings);


