// File: /bitnami/drupal/modules/contrib/myfolder/js/firebase_editor.js

(function ($, Drupal, once) {
  Drupal.behaviors.firebaseEditor = {
    attach: function (context, settings) {
      // Use event delegation on the table for the change event.
      once('firebase-editor-init', '#firestore-documents-table', context).forEach(function(tableElement) {
          $(tableElement).on('change', '.firebase-edit', function() {
            const $input = $(this);
            if (this.checkValidity()) {
              updateFirebaseField($input);
            } else {
              alert('Invalid input. Only alphanumeric characters and spaces are allowed.');
              $input.val($input.data('original-value'));  // Use data attribute
              $input.focus();
            }
          });
        });

      // Function to prepare data and call AJAX
      function getUpdateData($input) {
        const field = $input.data('field');
        const doc = $input.data('doc');
        const collection = $('#edit-firebase-collection').val(); // Get collection from the form
        const newValue = $input.val();
        return { collection, doc, field, value: newValue };
      }

      //Process Input and store original value
      $(context).find('.firebase-edit').each(function() {
        const $input = $(this);
        $input.data('original-value', $input.val());
        $input.attr('pattern', '[a-zA-Z0-9\\s]*');
      });


      async function updateFirebaseField($input) {
        const data = getUpdateData($input);

        try {
          const response = await fetch('/admin/firebase/update-document', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': Drupal.csrfToken,
            },
            body: JSON.stringify(data),
          });

          const result = await response.json();

          if (result.status === 'success') {
            $input.css('background-color', '#d4edda'); // Green for success
            // Update the original value after a successful update
            $input.data('original-value', $input.val());
          } else {
            $input.css('background-color', '#f8d7da'); // Red for error
            alert('Failed to update: ' + (result.message || 'Unknown error'));
            $input.val($input.data('original-value')); // Revert on error
          }
        } catch (error) {
          $input.css('background-color', '#f8d7da');
          console.error('Error updating Firestore:', error);
          alert('Error updating Firestore: ' + error.message);
          $input.val($input.data('original-value')); // Revert on error
        }
      }
    }
  };
})(jQuery, Drupal, once);