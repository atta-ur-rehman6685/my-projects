(function ($, Drupal) {
    Drupal.behaviors.reloadPage = {
      attach: function (context, settings) {
        if (settings.reloadPage) {
          location.reload();
        }
      }
    };
  })(jQuery, Drupal);
  