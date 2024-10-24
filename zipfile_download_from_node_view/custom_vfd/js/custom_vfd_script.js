(function ($, Drupal) {
    Drupal.behaviors.downloadSelectedFiles = {
      attach: function (context, settings) {
        $('#download-button', context).once('downloadSelectedFiles').on('click', function () {
          // Collect all checked checkboxes with class .file-checkbox
          var selectedFiles = [];
          $('.file-checkbox:checked').each(function () {
            selectedFiles.push($(this).val());
          });
        // var selectedFiles = [7, 8];
          // Send the selected node IDs to the server
          if (selectedFiles.length > 0) {
            $.ajax({
              url: '/download-custom-view/download_files', // Replace with the path to your download handler
              type: 'POST',
              data: { 'node_ids': selectedFiles },
              success: function (response) {
                // alert(response.fileName);
                if (response.status === 'success') {
                    var downloadUrl = '/download-node-files/' + response.fileName;
                    window.location.href = downloadUrl; // Triggers the download
                }
              },
              error: function () {
                alert('There was an error processing your request.');
              }
            });
          } else {
            alert('Please select at least one file to download.');
          }
        });
      }
    };
  })(jQuery, Drupal);
  