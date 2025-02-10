(function ($, Drupal) {
    Drupal.behaviors.firebaseDatatables = {
        attach: function (context, settings) {
            var tableElement = $('#firestore-documents-table');

            if (tableElement.length && !$.fn.DataTable.isDataTable(tableElement)) {
                tableElement.DataTable({
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
        }
    };
})(jQuery, Drupal);