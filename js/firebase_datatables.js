/* File: /Users/daynis.olman/Documents/GitHub/firebase_config_manager/js/firebase_datatables.js */

(function ($, Drupal, once) {
    Drupal.behaviors.firebaseDatatables = {
      attach: function (context, settings) {
        once('firestore-documents-table', '#firestore-documents-table', context).forEach(function (element) {
          var tableElement = $(element);
          if (!$.fn.DataTable.isDataTable(tableElement)) {
            tableElement.DataTable({
              paging: true,
              searching: true,
              ordering: true,
              lengthMenu: [10, 25, 50, 100],
              language: {
                search: "Filter results:",
                lengthMenu: "Show _MENU_ entries",
                zeroRecords: "No matching documents found"
              },
              // Add destroy: true to allow reinitialization (important for AJAX updates)
              destroy: true
            });
          }
        });
      }
    };
  })(jQuery, Drupal, once);