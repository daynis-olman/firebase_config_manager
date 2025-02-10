(function ($, Drupal) {
  Drupal.behaviors.firebaseEditor = {
      attach: function (context, settings) {
          $('.firebase-edit', context).once('firebase-edit').each(function () {
              var input = $(this);
              var field = input.data('field');
              var doc = input.data('doc');
              var collection = $('#edit-firebase-collection').val();
              var originalValue = input.val();

              // Add Undo button dynamically
              var undoBtn = $('<button class="firebase-undo">Undo</button>');
              input.after(undoBtn);

              // Firestore document update event
              input.on('change', function () {
                  var newValue = $(this).val();
                  var inputElement = $(this);

                  $.ajax({
                      url: Drupal.url('admin/firebase/update-document'),
                      type: 'POST',
                      data: { collection, field, doc, value: newValue },
                      dataType: 'json',
                      success: function (response) {
                          if (response.status === 'success') {
                              inputElement.css('background-color', '#d4edda'); // Green for success
                          } else {
                              inputElement.css('background-color', '#f8d7da'); // Red for failure
                              alert("Failed to update: " + (response.message || "Unknown error"));
                          }
                      },
                      error: function (xhr) {
                          inputElement.css('background-color', '#f8d7da');
                          alert("Error updating Firestore: " + xhr.responseText || "Unknown error");
                      }
                  });
              });

              // Undo button functionality
              undoBtn.on('click', function () {
                  $.ajax({
                      url: Drupal.url('admin/firebase/restore-document'),
                      type: 'POST',
                      data: { collection, field, doc },
                      dataType: 'json',
                      success: function (response) {
                          if (response.status === 'success') {
                              input.val(originalValue).css('background-color', '#ffebcc'); // Yellow for undo
                          } else {
                              alert("Failed to restore: " + (response.message || "Unknown error"));
                          }
                      },
                      error: function (xhr) {
                          alert("Error restoring Firestore value: " + xhr.responseText || "Unknown error");
                      }
                  });
              });
          });
      }
  };
})(jQuery, Drupal);