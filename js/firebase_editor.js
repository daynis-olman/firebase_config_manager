(function ($, Drupal) {
    Drupal.behaviors.firebaseEditor = {
      attach: function (context, settings) {
        $('.firebase-edit', context).once('firebase-edit').on('change', function () {
          var field = $(this).data('field');
          var doc = $(this).data('doc');
          var collection = $('#edit-firebase-collection').val();
          var newValue = $(this).val();
          var inputElement = $(this);
  
          $.ajax({
            url: Drupal.url('admin/firebase/update-document'),
            type: 'POST',
            data: {
              collection: collection,
              field: field,
              doc: doc,
              value: newValue
            },
            success: function (response) {
              if (response.status === 'success') {
                inputElement.css('background-color', '#d4edda'); // Green for success
              } else {
                inputElement.css('background-color', '#f8d7da'); // Red for failure
                alert("Failed to update: " + response.message);
              }
            },
            error: function () {
              inputElement.css('background-color', '#f8d7da');
              alert("Error updating Firestore.");
            }
          });
        });
      }
    };
  })(jQuery, Drupal);