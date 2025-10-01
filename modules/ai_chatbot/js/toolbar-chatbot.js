(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiChatbot = {
    attach: function (context, settings) {
      const toggleChatbot = (isOpen) => {
        if (window.Drupal.ginCoreNavigation) {
          window.Drupal.ginCoreNavigation.collapseToolbar();
        }
        document.body.classList.toggle('ai-chatbot-opened', isOpen);
        window.localStorage.setItem('Drupal.ai.chatbotExpanded', isOpen ? 'true' : 'false');
      };

      once('ai-chatbot', '.button--ai-chatbot', context).forEach(($toolbarIcon) => {

        $toolbarIcon.classList.remove('hidden');

        if ($toolbarIcon.classList.contains('button--primary')) {
          $toolbarIcon.classList.add('button');
        }

        if (window.localStorage.getItem('Drupal.ai.chatbotExpanded') === 'true') {
          toggleChatbot(true);
        }

        $toolbarIcon.addEventListener('click', (e) => {
          toggleChatbot(true);
        });
      });

      once('ai-chatbot-close', '.sidebar-header--icon.toolbar-button.close', context).forEach(($closeIcon) => {
        $closeIcon.addEventListener('click', (e) => {
          toggleChatbot(false);
        });
      });
    }
  };

})(Drupal, once);
