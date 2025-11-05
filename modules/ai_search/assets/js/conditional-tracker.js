/**
 * @file
 * Conditional tracker behavior for AI Search.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Behavior for conditional tracker visibility and selection.
   */
  Drupal.behaviors.aiSearchConditionalTracker = {
    attach: function (context, settings) {
      // Get server IDs that use AI Search backend from settings.
      var aiSearchServerIds = settings.aiSearch?.serverIds || [];

      // Find server radio buttons and tracker radio buttons.
      var $serverRadios = $('input[name="server"]', context);
      var $trackerRadios = $('input[name="tracker"]', context);
      var $aiSearchTracker = $('input[name="tracker"][value="ai_search_tracker"]', context);

      // Function to handle tracker state based on server selection.
      function updateTrackerState(selectedServerId, preventTrigger) {
        var isAiSearchServer = aiSearchServerIds.indexOf(selectedServerId) !== -1;

        if (isAiSearchServer) {
          // AI Search server selected: enable AI Search tracker.
          $aiSearchTracker.prop('disabled', false);

          // Disable other trackers.
          $trackerRadios.not($aiSearchTracker).prop('disabled', true);

          // Only check AI Search tracker if no tracker is currently selected
          // or if the current selection is disabled.
          var $currentChecked = $trackerRadios.filter(':checked');
          if ($currentChecked.length === 0 || $currentChecked.prop('disabled')) {
            $trackerRadios.prop('checked', false);
            $aiSearchTracker.prop('checked', true);
            if (!preventTrigger) {
              $aiSearchTracker.trigger('change');
            }
          }
        } else {
          // Non-AI Search server selected: disable AI Search tracker.
          $aiSearchTracker.prop('disabled', true);

          // Enable other trackers.
          $trackerRadios.not($aiSearchTracker).prop('disabled', false);

          // If AI Search tracker was selected, select the first available alternative.
          if ($aiSearchTracker.is(':checked')) {
            $aiSearchTracker.prop('checked', false);
            var $firstAvailable = $trackerRadios.not($aiSearchTracker).first();
            $firstAvailable.prop('checked', true);
            if (!preventTrigger) {
              $firstAvailable.trigger('change');
            }
          }
        }
      }

      // Handle server selection changes.
      $serverRadios.on('change.aiSearchTracker', function () {
        var selectedServerId = $(this).val();
        updateTrackerState(selectedServerId, false);
      });

      // Initialize state on page load without triggering change events.
      var $checkedServer = $serverRadios.filter(':checked');
      if ($checkedServer.length > 0) {
        updateTrackerState($checkedServer.val(), true);
      } else {
        // No server selected: disable AI Search tracker.
        $aiSearchTracker.prop('disabled', true);
        $trackerRadios.not($aiSearchTracker).prop('disabled', false);
      }
    },

    detach: function (context) {
      // Clean up event handlers.
      $('input[name="server"]', context).off('change.aiSearchTracker');
    }
  };

})(jQuery, Drupal);
