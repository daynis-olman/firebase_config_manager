(function ($, Drupal) {
    Drupal.behaviors.firebaseEditor = {
      attach: function (context, settings) {
        // Apply DataTables to Firestore documents table
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
          var originalValue = input.val(); // Store original value
  
          // Append an Undo button
          var undoBtn = $('<button class="firebase-undo">Undo</button>');
          input.after(undoBtn);
  
          input.on('change', function () {
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
  
          // Undo button functionality
          undoBtn.on('click', function () {
            $.ajax({
              url: Drupal.url('admin/firebase/restore-document'),
              type: 'POST',
              data: {
                collection: collection,
                field: field,
                doc: doc
              },
              success: function (response) {
                if (response.status === 'success') {
                  input.val(originalValue).css('background-color', '#ffebcc'); // Yellow for undo
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