/**
 * SECOP Suite - Admin Filters JavaScript
 *
 * @package SecopSuite
 * @version 4.1.0
 */

(function($) {
    'use strict';

    const FilterConfigManager = {
        columns: [],

        init: function() {
            this.bindEvents();

            const selectedTable = $('#ss_filter_table_name').val();
            if (selectedTable) {
                this.loadColumns(selectedTable);
            }
        },

        bindEvents: function() {
            const self = this;

            // Table selection
            $('#ss_filter_table_name').on('change', function() {
                const table = $(this).val();
                if (table) {
                    self.loadColumns(table);
                    $('#ss-filter-fields-section, #ss-result-columns-section').show();
                } else {
                    $('#ss-filter-fields-section, #ss-result-columns-section').hide();
                }
            });

            // Add filter field
            $('#ss-add-filter-field').on('click', function() {
                self.addFilterFieldRow();
            });

            // Remove filter field
            $(document).on('click', '.ss-remove-filter-field', function() {
                $(this).closest('.ss-filter-field-row').remove();
                self.reindexFilterFields();
            });

            // Copy shortcode
            $('.ss-copy-shortcode').on('click', function() {
                const text = $('#ss-filter-shortcode-display').text();
                navigator.clipboard.writeText(text).then(function() {
                    const $btn = $('.ss-copy-shortcode');
                    const originalText = $btn.html();
                    $btn.html('<span class="dashicons dashicons-yes"></span> Copiado');
                    setTimeout(function() {
                        $btn.html(originalText);
                    }, 2000);
                });
            });
        },

        loadColumns: function(table) {
            const self = this;

            $.ajax({
                url: secopSuiteFilterAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_get_table_columns',
                    nonce: secopSuiteFilterAdmin.nonce,
                    table: table
                },
                success: function(response) {
                    if (response.success) {
                        self.columns = response.data.columns;
                        self.populateColumnSelects();
                        self.renderResultColumnsCheckboxes();
                    }
                }
            });
        },

        populateColumnSelects: function() {
            const self = this;

            $('.ss-filter-column-select').each(function() {
                const $select = $(this);
                const savedValue = $select.data('saved-value') || $select.val();
                const $firstOption = $select.find('option:first');

                $select.empty().append($firstOption);

                self.columns.forEach(function(col) {
                    const isNumeric = col.type.includes('int') || col.type.includes('decimal');
                    const isDate = col.type.includes('date') || col.type.includes('time');

                    let label = col.name;
                    if (isNumeric) label += ' (numérico)';
                    if (isDate) label += ' (fecha)';

                    $select.append(
                        $('<option>').val(col.name).text(label)
                    );
                });

                if (savedValue) {
                    $select.val(savedValue);
                }
            });
        },

        renderResultColumnsCheckboxes: function() {
            const $container = $('#ss-result-columns-container');
            $container.empty();

            const savedColumns = (typeof ssFilterSavedConfig !== 'undefined' && ssFilterSavedConfig.result_columns) || [];

            const $grid = $('<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">');

            this.columns.forEach(function(col) {
                const checked = savedColumns.indexOf(col.name) !== -1 ? 'checked' : '';
                $grid.append(
                    $('<label style="display: flex; align-items: center; gap: 5px; padding: 4px 8px; background: #fff; border: 1px solid #dcdcde; border-radius: 3px; font-size: 12px;">').append(
                        $('<input type="checkbox" name="ss_result_columns[]">').val(col.name).prop('checked', !!checked),
                        $('<span>').text(col.name)
                    )
                );
            });

            $container.append($grid);
        },

        addFilterFieldRow: function() {
            const index = $('#ss-filter-fields-container .ss-filter-field-row').length;
            const template = $('#ss-filter-field-template').html().replace(/{{index}}/g, index);
            const $row = $(template);

            $('#ss-filter-fields-container').append($row);

            // Populate column selects in new row
            this.populateNewRowSelects($row);
        },

        populateNewRowSelects: function($row) {
            const self = this;
            $row.find('.ss-filter-column-select').each(function() {
                const $select = $(this);
                const $firstOption = $select.find('option:first');
                $select.empty().append($firstOption);

                self.columns.forEach(function(col) {
                    $select.append(
                        $('<option>').val(col.name).text(col.name)
                    );
                });
            });
        },

        reindexFilterFields: function() {
            $('#ss-filter-fields-container .ss-filter-field-row').each(function(index) {
                $(this).find('[name]').each(function() {
                    const name = $(this).attr('name');
                    const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                });
            });
        }
    };

    $(document).ready(function() {
        if ($('#ss_filter_table_name').length) {
            FilterConfigManager.init();
        }
    });

})(jQuery);
