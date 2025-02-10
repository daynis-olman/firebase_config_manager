(function ($, Drupal) {
  Drupal.behaviors.firebaseEditor = {
      attach: function (context, settings) {
          if ($('#firestore-documents-table').length > 0) {
              $('#firestore-documents-table').DataTable({
                  paging: true,
                  searching: true,
                  ordering: true,
                  lengthMenu: [10, 25, 50, 100],
                  language: {
                      search: "Filter results:",
                      lengthMenu: "Show _MENU_ entries",
                      zeroRecords: "No matching documents found"
                  }
              });
          }

          $('.firebase-edit', context).once('firebase-edit').each(function () {
              var input = $(this);
              var field = input.data('field');
              var doc = input.data('doc');
              var collection = $('#edit-firebase-collection').val();
              var originalValue = input.val(); 

              var undoBtn = $('<button class="firebase-undo">Undo</button>');
              input.after(undoBtn);

              input.on('change', function () {
                  var newValue = $(this).val();
                  var inputElement = $(this);

                  $.ajax({
                      url: Drupal.url('admin/firebase/update-document'),
                      type: 'POST',
                      data: { collection, field, doc, value: newValue },
                      success: function (response) {
                          if (response.status === 'success') {
                              inputElement.css('background-color', '#d4edda'); 
                          } else {
                              alert("Failed to update: " + (response.message || "Unknown error"));
                          }
                      },
                      error: function (xhr, status, error) {
                          alert("Error updating Firestore: " + error);
                      }
                  });
              });

              undoBtn.on('click', function () {
                  $.ajax({
                      url: Drupal.url('admin/firebase/restore-document'),
                      type: 'POST',
                      data: { collection, field, doc },
                      success: function (response) {
                          if (response.status === 'success') {
                              input.val(originalValue).css('background-color', '#ffebcc'); 
                          } else {
                              alert("Failed to restore: " + response.message);
                          }
                      },
                      error: function () {
                          alert("Error restoring Firestore value.");
                      }
                  });
              });
          });
      }
  };
})(jQuery, Drupal);