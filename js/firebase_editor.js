// File: /bitnami/drupal/modules/contrib/myfolder/js/firebase_editor.js

(function ($, Drupal, once) { // Add 'once' to the function parameters
  Drupal.behaviors.firebaseEditor = {
    attach: function (context, settings) {
      // Use event delegation on the table.  This is crucial for AJAX.
      // Only process elements that haven't been processed before.
      once('firebase-editor-init', '#firestore-documents-table', context).forEach(function(tableElement) {
          $(tableElement).on('change', '.firebase-edit', function() {
            const $input = $(this);
            if (this.checkValidity()) {
              updateFirebaseField($input);
            }
            else {
              alert('Invalid input. Only alphanumeric characters and spaces are allowed.');
              $input.val($input.data('original-value'));  // Use data attribute
              $input.focus();
            }
          });

          $(tableElement).on('click', '.firebase-undo', function(event) {
            event.preventDefault(); // Prevent default button behavior
            const $button = $(this);
            const $input = $button.siblings('.firebase-edit'); // Find the input next to the button
            undoFirebaseEdit($input);
        });
      });


      // Function to prepare data and call AJAX (factored out for reuse)
      function getUpdateData($input) {
        const field = $input.data('field');
        const doc = $input.data('doc');
        const collection = $('#edit-firebase-collection').val(); // Get collection from the form
        const newValue = $input.val();
        return { collection, doc, field, value: newValue };
      }

      // Function to set up undo button
      function setupUndoButton(input) {
          const $undoBtn = $('<button>', {
              text: 'Undo',
              class: 'firebase-undo',
              style: 'margin-left: 10px;',
          });
          $(input).after($undoBtn);
          return $undoBtn;
      }

      //Process Input
      $(context).find('.firebase-edit').each(function() {
        const $input = $(this);
        // Store original value in a data attribute.
        $input.data('original-value', $input.val());
        $input.attr('pattern', '[a-zA-Z0-9\\s]*');

        setupUndoButton($input);

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
            body: JSON.stringify(data), // Send the prepared data
          });

          const result = await response.json();

          if (result.status === 'success') {
            $input.css('background-color', '#d4edda');
             // Update the original value after a successful update
            $input.data('original-value', $input.val());
          } else {
            $input.css('background-color', '#f8d7da');
            alert('Failed to update: ' + (result.message || 'Unknown error'));
            $input.val($input.data('original-value')); // Revert
          }
        } catch (error) {
          $input.css('background-color', '#f8d7da');
          console.error('Error updating Firestore:', error);
          alert('Error updating Firestore: ' + error.message);
          $input.val($input.data('original-value')); // Revert
        }
      }

      async function undoFirebaseEdit($input) {
          const data = getUpdateData($input); // Reuse the data preparation
        // We don't need the 'value' for restoring, as it's handled server-side
        delete data.value; // Remove the 'value' property

        try {
          const response = await fetch('/admin/firebase/restore-document', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': Drupal.csrfToken,
            },
            body: JSON.stringify(data), // Send collection, doc, and field
          });

          const result = await response.json();

          if (result.status === 'success') {
            $input.val($input.data('original-value'));
            $input.css('background-color', '#ffebcc');
          } else {
            alert('Failed to restore: ' + (result.message || 'Unknown error'));
          }
        } catch (error) {
          console.error('Error restoring Firestore value:', error);
          alert('Error restoring Firestore value: ' . error.message);
        }
      }
    }
  };
})(jQuery, Drupal, once);