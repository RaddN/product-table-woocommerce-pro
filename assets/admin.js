jQuery(document).ready(function ($) {
    let currentCell = null;
    let excludedProducts = [];
    let tableData = {
        headers: ["Column 1", "Column 2", "Column 3", "Column 4", "Column 5"],
        rows: [[
            { elements: [] },
            { elements: [] },
            { elements: [] },
            { elements: [] },
            { elements: [] }
        ]],
        footers: ["Footer 1", "Footer 2", "Footer 3", "Footer 4", "Footer 5"],
        show_header: true,
        show_footer: false
    };

    window.plugincyLoadTableData = function (data) {
        tableData = data;
        if (data.query_settings && data.query_settings.excluded_products) {
            excludedProducts = data.query_settings.excluded_products;
            updateExcludedCount();
        }
        renderTable();
        if (data.query_settings) {
            updateQuerySettingsUI(data.query_settings);
            refreshProductPreview(); // Load preview after loading data
        }
    };

    // Function to refresh product preview
    function refreshProductPreview() {
        const querySettings = gatherQuerySettings();
        const $table = $("#plugincy-editable-table");
        const $body = $table.find("tbody");

        // Update loading message with correct colspan
        const colCount = tableData.headers.length + 1; // +1 for actions column
        $body.find('tr.plugincy-preview-loading-row').html(`<td colspan="${colCount}" class="plugincy-preview-loading" style="text-align:center;"><p>Loading products...</p></td>`);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wcproducttab_get_preview_products',
                nonce: wcproducttab_ajax.nonce,
                query_settings: JSON.stringify(querySettings),
                excluded_products: excludedProducts,
                table_data: JSON.stringify(tableData) // Send current table structure
            },
            success: function (response) {
                if (response.success) {
                    $body.find('tr:not(:first-child)').remove();
                    $body.append(response.data);
                } else {
                    $body.find('tr.plugincy-preview-loading-row').html(`<td colspan="${colCount}"><p>Error loading products</p></td>`);
                }
            },
            error: function () {
                $body.find('tr.plugincy-preview-loading-row').html(`<td colspan="${colCount}"><p>Error loading products</p></td>`);
            }
        });
    }

    // Function to gather current query settings
    function gatherQuerySettings() {
        return {
            query_type: $('#query-type').val(),
            selected_categories: $('#selected-categories').val() || [],
            selected_tags: $('#selected-tags').val() || [],
            selected_products: $('#selected-products').val() ? $('#selected-products').val().map(Number) : [],
            products_per_page: parseInt($('#products-per-page').val()) || 10,
            order_by: $('#order-by').val(),
            order: $('#order').val()
        };
    }

    // Function to update excluded products count
    function updateExcludedCount() {
        const count = excludedProducts.length;
        $('#excluded-count').text(count > 1 ? `${count - 1} product(s) excluded` : '');
        $('#excluded-products-input').val(JSON.stringify(excludedProducts));
    }

    // Event handlers for query setting changes
    $(document).on('change', '#query-type, #selected-categories, #selected-tags, #selected-products, #products-per-page, #order-by, #order', function () {
        refreshProductPreview();
    });

    // Refresh preview button
    $(document).on('click', '#refresh-preview', function () {
        refreshProductPreview();
    });

    // Remove product button
    $(document).on('click', '.remove-product', function () {
        const productId = parseInt($(this).data('product-id'));
        if (productId && !excludedProducts.includes(productId)) {
            excludedProducts.push(productId);
            updateExcludedCount();
            $(this).closest('tr').fadeOut(300, function () {
                $(this).remove();
            });
        }
    });

    // Clear all exclusions button
    $(document).on('click', '#clear-excluded', function () {
        if (excludedProducts.length > 1 && confirm('Clear all excluded products?')) {
            excludedProducts = [];
            updateExcludedCount();
            refreshProductPreview();
        }
    });

    // Tab functionality
    $(document).on('click', '.nav-tab', function (e) {
        e.preventDefault();

        // Remove active class from all tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').hide();

        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');

        // Show corresponding tab content
        const tabId = $(this).data('tab');
        $('#tab-' + tabId).show();
    });

    // Query type change handler
    $(document).on('change', '#query-type', function () {
        const queryType = $(this).val();
        updateQueryUI(queryType);
        updateAddRowVisibility(queryType);
    });

    // Initialize query UI based on current selection
    function initializeQueryUI() {
        const queryType = $('#query-type').val();
        updateQueryUI(queryType);
        updateAddRowVisibility(queryType);
    }

    function updateQueryUI(queryType) {
        // Hide all query-specific sections
        $('#category-selection, #tags-selection, #products-selection').hide();

        // Show relevant section based on query type
        switch (queryType) {
            case 'category':
                $('#category-selection').show();
                break;
            case 'tags':
                $('#tags-selection').show();
                break;
            case 'products':
                $('#products-selection').show();
                break;
            case 'all':
            default:
                // No additional UI needed for 'all products'
                break;
        }
    }

    function updateAddRowVisibility(queryType) {
        const $addRowBtn = $('#add-row');
        const $rowInfoMessage = $('#row-info-message');

        if (queryType === 'products') {
            $addRowBtn.show();
            $rowInfoMessage.show();

            // Show delete buttons for existing rows if more than one row
            if (tableData.rows.length > 1) {
                $('.delete-row').show();
            }
        } else {
            $addRowBtn.hide();
            $rowInfoMessage.hide();
            $('.delete-row').hide();

            // Reset to single row if query type is not 'products'
            if (tableData.rows.length > 1) {
                tableData.rows = [tableData.rows[0]]; // Keep only first row
                renderTable();
            }
        }
    }

    function updateQuerySettingsUI(querySettings) {
        if (querySettings.query_type) {
            $('#query-type').val(querySettings.query_type).trigger('change');
        }

        if (querySettings.selected_categories) {
            $('#selected-categories').val(querySettings.selected_categories);
        }

        if (querySettings.selected_tags) {
            $('#selected-tags').val(querySettings.selected_tags);
        }

        if (querySettings.selected_products) {
            $('#selected-products').val(querySettings.selected_products.map(String));
        }

        if (querySettings.products_per_page) {
            $('#products-per-page').val(querySettings.products_per_page);
        }

        if (querySettings.order_by) {
            $('#order-by').val(querySettings.order_by);
        }

        if (querySettings.order) {
            $('#order').val(querySettings.order);
        }
    }

    function renderTable() {
        const $table = $("#plugincy-editable-table");
        const $header = $table.find("thead tr:nth-child(2)");
        const $body = $table.find("tbody");
        const $footer = $table.find("tfoot tr");

        // Render header
        $header.empty();
        tableData.headers.forEach(function (header, index) {
            $header.append(`
        <th contenteditable="true" class="plugincy-editable-header" data-index="${index}">
            ${header}
            <span class="plugincy-column-actions">
                <span class="plugincy-edit-element" data-type="" title="Edit Element">✏️</span>
                <span class="plugincy-delete-column" data-index="${index}" title="Delete Column">🗑️</span>
            </span>
        </th>
    `);
        });
        $header.append(`<th class="plugincy-action-column">
            <span class="button"><span class="dashicons dashicons-admin-customizer"></span></span>
            <span class="button"><span class="dashicons dashicons-visibility"></span></span>
            </th>`);

        // Render body
        $body.find('tr').first().remove();
        tableData.rows.forEach(function (row, rowIndex) {
            let $row = $("<tr>");
            row.forEach(function (cell, cellIndex) {
                let cellContent = "";
                if (cell.elements && cell.elements.length > 0) {
                    cell.elements.forEach(function (element) {
                        cellContent += `
                <div class="plugincy-element" data-type="${element.type}">
                    ${element.type.replace(/_/g, " ")}
                    <span class="plugincy-element-actions">
                        <span class="plugincy-edit-element" data-type="${element.type}" title="Edit Element">✏️</span>
                        <span class="plugincy-delete-element" data-type="${element.type}" title="Remove Element">🗑️</span>
                    </span>
                </div>
            `;
                    });
                } else {
                    cellContent = `<div class="plugincy-add-element">+</div>`;
                }
                $row.append(`<td class="plugincy-editable-cell" data-row="${rowIndex}" data-col="${cellIndex}"><div class="plugincy-cell-content">${cellContent}</div></td>`);
            });

            // Show delete button only if query type is 'products' and there's more than one row
            const queryType = $('#query-type').val();
            const showDeleteBtn = (queryType === 'products' && tableData.rows.length > 1) ? '' : 'style="display:none;"';
            $row.append(`<td class="plugincy-action-column">
                <button type="button" class="button button-small delete-row" ${showDeleteBtn}>Delete</button>
                </td>`);
            $body.prepend($row);
        });

        // Render footer
        $footer.empty();
        tableData.footers.forEach(function (footer, index) {
            $footer.append(`<td contenteditable="true" class="plugincy-editable-footer" data-index="${index}">${footer}</td>`);
        });
        $footer.append(`<td>
            <span class="button"><span class="dashicons dashicons-admin-customizer"></span></span>
            <span class="button"><span class="dashicons dashicons-visibility"></span></span>
            </td>`);

        // Update visibility settings
        $("#show-header").prop("checked", tableData.show_header);
        $("#show-footer").prop("checked", tableData.show_footer);

        if (tableData.show_header) {
            $table.find("thead").show();
        } else {
            $table.find("thead").hide();
        }

        if (tableData.show_footer) {
            $table.find("tfoot").show();
        } else {
            $table.find("tfoot").hide();
        }

        // Update shortcode display if in edit mode
        updateShortcodeDisplay();
    }

    function updateShortcodeDisplay() {
        const editId = $('input[name="edit_id"]').val();
        if (editId && editId !== '0') {
            const shortcode = `[wcproducttab_table id="${editId}"]`;
            $('#table-shortcode').text(shortcode);
            $('.copy-shortcode').attr('data-shortcode', shortcode);
            $('.plugincy-shortcode-section').show();
        }
    }

    // Function to serialize table data
    function serializeTableData() {
        return JSON.stringify(tableData);
    }

    // Handle form submission
    $(document).on("submit", "#plugincy-table-form", function (e) {
        // Add excluded products to table data
        tableData.query_settings = tableData.query_settings || {};
        tableData.query_settings.excluded_products = excludedProducts;

        // Serialize the table data before submitting
        $("#table-data-input").val(serializeTableData());
    });

    const modal_content = $("#plugincy-element-modal .plugincy-modal-body").html();

    $(document).on("click", ".plugincy-add-element", function () {
        currentCell = $(this).closest(".plugincy-editable-cell");
        $("#plugincy-element-modal .plugincy-modal-body").html(modal_content);
        $("#plugincy-element-modal .plugincy-modal-header h3").text("Add Element");
        $("#plugincy-element-modal").show();
    });

    $(document).on("click", ".plugincy-element-option", function () {
        const elementType = $(this).data("type");
        const rowIndex = currentCell.data("row");
        const colIndex = currentCell.data("col");

        if (!tableData.rows[rowIndex][colIndex].elements) {
            tableData.rows[rowIndex][colIndex].elements = [];
        }

        let elementContent = "";
        if (elementType === "custom_text") {
            elementContent = prompt("Enter custom text:");
            if (elementContent === null) {
                return; // User cancelled
            }
        }

        tableData.rows[rowIndex][colIndex].elements.push({
            type: elementType,
            content: elementContent
        });

        renderTable();
        refreshProductPreview();
        $("#plugincy-element-modal").hide();
    });

    $(document).on("click", ".plugincy-close", function () {
        $("#plugincy-element-modal").hide();
    });

    $(document).on("click", "#add-column", function () {
        const newIndex = tableData.headers.length;
        tableData.headers.push("Column " + (newIndex + 1));
        tableData.footers.push("Footer " + (newIndex + 1));

        tableData.rows.forEach(function (row) {
            row.push({ elements: [] });
        });

        renderTable();
        refreshProductPreview();
    });

    $(document).on("click", "#add-row", function () {
        const queryType = $('#query-type').val();

        if (queryType !== 'products') {
            alert('Additional rows can only be added when using "Specific Products" query type.');
            return;
        }

        const newRow = [];
        for (let i = 0; i < tableData.headers.length; i++) {
            newRow.push({ elements: [] });
        }
        tableData.rows.push(newRow);

        // Add product selection row after preview products
        const $table = $("#plugincy-editable-table tbody");
        const colCount = tableData.headers.length + 1;

        const productSelectRow = `
        <tr class="plugincy-product-select-row">
            <td colspan="${colCount}" style="padding: 15px; background: #f9f9f9; border: 2px dashed #ddd;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label>Select Product for this row:</label>
                    <select class="product-selector" style="min-width: 200px;">
                        <option value="">Choose a product...</option>
                        ${$('#selected-products option').map(function () {
            return `<option value="${this.value}">${this.text}</option>`;
        }).get().join('')}
                    </select>
                    <button type="button" class="button button-primary add-selected-product">Add Product</button>
                    <button type="button" class="button remove-row-selector">Cancel</button>
                </div>
            </td>
        </tr>`;

        $table.append(productSelectRow);
    });

    $(document).on('click', '.add-selected-product', function () {
        const $row = $(this).closest('.plugincy-product-select-row');
        const selectedProductId = $row.find('.product-selector').val();

        if (!selectedProductId) {
            alert('Please select a product first.');
            return;
        }

        // Update selected_products array
        let selectedProducts = $('#selected-products').val() || [];
        if (!selectedProducts.includes(selectedProductId)) {
            selectedProducts.push(selectedProductId);
            $('#selected-products').val(selectedProducts).trigger('change');

            // Remove the selector row
            $row.remove();

            // Refresh preview to show the new product
            refreshProductPreview();
        } else {
            alert('This product is already selected.');
        }
    });

    // Handle canceling product selection
    $(document).on('click', '.remove-row-selector', function () {
        const $row = $(this).closest('.plugincy-product-select-row');
        // Remove the last added row from tableData
        if (tableData.rows.length > 1) {
            tableData.rows.pop();
        }
        $row.remove();
        renderTable();
    });

    $(document).on("click", ".plugincy-delete-column", function (e) {
        e.stopPropagation();
        const columnIndex = $(this).data("index");

        if (tableData.headers.length <= 1) {
            alert("Cannot delete the last column!");
            return;
        }

        if (confirm("Are you sure you want to delete this column? This will remove all data in this column.")) {
            // Remove from headers and footers
            tableData.headers.splice(columnIndex, 1);
            tableData.footers.splice(columnIndex, 1);

            // Remove from all rows
            tableData.rows.forEach(function (row) {
                row.splice(columnIndex, 1);
            });

            renderTable();
            refreshProductPreview();
        }
    });


    $(document).on("click", ".delete-row", function () {
        const rowIndex = $(this).closest("tr").index();
        const queryType = $('#query-type').val();

        // Only allow deleting rows when query type is 'products'
        if (queryType !== 'products') {
            return;
        }

        if (tableData.rows.length > 1) {
            tableData.rows.splice(rowIndex, 1);
            renderTable();
        } else {
            alert("Cannot delete the last row!");
        }
    });

    $(document).on("blur", ".plugincy-editable-header", function () {
        const index = $(this).data("index");
        tableData.headers[index] = $(this).text().replace(/\n/g, '');
    });

    $(document).on("blur", ".plugincy-editable-footer", function () {
        const index = $(this).data("index");
        tableData.footers[index] = $(this).text().replace(/\n/g, '');
    });

    $(document).on("change", "#show-header", function () {
        tableData.show_header = $(this).is(":checked");
        renderTable();
    });

    $(document).on("change", "#show-footer", function () {
        tableData.show_footer = $(this).is(":checked");
        renderTable();
    });

    $(document).on("click", ".plugincy-delete-element", function (e) {
        e.stopPropagation();
        if (confirm("Remove this element?")) {
            const $cell = $(this).closest(".plugincy-editable-cell");
            const rowIndex = $cell.data("row");
            const colIndex = $cell.data("col");
            const elementType = $(this).data("type");

            tableData.rows[rowIndex][colIndex].elements = tableData.rows[rowIndex][colIndex].elements.filter(function (element) {
                return element.type !== elementType;
            });

            renderTable();
            refreshProductPreview();
        }
    });

    // Handle edit element click
    $(document).on("click", ".plugincy-edit-element", function (e) {
        e.stopPropagation();

        const $element = $(this).closest('.plugincy-element');
        const $cell = $element.closest('.plugincy-editable-cell');
        const rowIndex = $cell.data("row");
        const colIndex = $cell.data("col");
        const elementType = $(this).data("type");



        if (elementType === "product_table") {
            tableData.rows[rowIndex] = [];
            tableData.rows[rowIndex][colIndex] = {};
            tableData.rows[rowIndex][colIndex]["elements"] = [
                {
                    "content": "",
                    "type": "product_table"
                }
            ];
        }

        // Find the element in tableData
        const cellElements = tableData.rows[rowIndex][colIndex].elements;
        const elementData = cellElements.find(el => el.type === elementType);

        // Get element configuration
        const elementConfig = getElementConfig(elementType);
        if (!elementConfig) {
            alert('Element configuration not found');
            return;
        }

        // Store current editing context
        window.currentEditContext = {
            rowIndex: rowIndex,
            colIndex: colIndex,
            elementType: elementType,
            elementData: elementData
        };

        // Generate and show customization form
        const formHTML = generateCustomizationForm(elementConfig, elementData.settings || {});

        // Update modal content
        $("#plugincy-element-modal .plugincy-modal-header h3").text("Edit Element");
        $("#plugincy-element-modal .plugincy-modal-body").html(formHTML);
        $("#plugincy-element-modal").show();
    });

    // Handle range input updates
    $(document).on('input', '.range-input-wrapper input[type="range"]', function () {
        $(this).siblings('.range-value').text($(this).val());
    });

    $(document).on("click", ".copy-shortcode", function () {
        const shortcode = $(this).data("shortcode");
        navigator.clipboard.writeText(shortcode).then(function () {
            alert("Shortcode copied to clipboard!");
        }).catch(function () {
            // Fallback for older browsers
            const textArea = document.createElement("textarea");
            textArea.value = shortcode;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert("Shortcode copied to clipboard!");
        });
    });

    $(document).on("click", ".delete-table", function () {
        if (confirm("Are you sure you want to delete this table?")) {
            const tableId = $(this).data("id");
            const $row = $(this).closest("tr");

            $.ajax({
                url: wcproducttab_ajax.ajax_url,
                type: "POST",
                data: {
                    action: "delete_table",
                    nonce: wcproducttab_ajax.nonce,
                    id: tableId
                },
                success: function (response) {
                    if (response.success) {
                        $row.remove();
                        alert("Table deleted successfully!");
                    } else {
                        alert("Error: " + response.data);
                    }
                },
                error: function () {
                    alert("An error occurred while deleting the table.");
                }
            });
        }
    });


    // Make elements draggable
    $(document).on('mousedown', '.plugincy-element', function (e) {
        e.preventDefault();
        const $element = $(this);
        const $cell = $element.closest('.plugincy-editable-cell');

        $element.addClass('dragging');

        // Store drag data
        const dragData = {
            element: $element,
            sourceRow: $cell.data('row'),
            sourceCol: $cell.data('col'),
            elementType: $element.data('type')
        };

        // Mouse move handler
        $(document).on('mousemove.drag', function (e) {
            // Visual feedback - element follows cursor (optional)
            const $target = $(e.target).closest('.plugincy-editable-cell');

            $('.drop-target').removeClass('drop-target');

            if ($target.length && !$target.is($cell)) {
                $target.addClass('drop-target');
            }
        });

        // Mouse up handler
        $(document).on('mouseup.drag', function (e) {
            const $target = $(e.target).closest('.plugincy-editable-cell');

            if ($target.length && !$target.is($cell)) {
                // Valid drop target
                const targetRow = $target.data('row');
                const targetCol = $target.data('col');

                // Move element in tableData
                moveElement(dragData.sourceRow, dragData.sourceCol, targetRow, targetCol, dragData.elementType);
            }

            // Cleanup
            $('.dragging').removeClass('dragging');
            $('.drop-target').removeClass('drop-target');
            $(document).off('.drag');
        });
    });

    // Function to move element between cells
    function moveElement(sourceRow, sourceCol, targetRow, targetCol, elementType) {
        // Find and remove element from source
        const sourceElements = tableData.rows[sourceRow][sourceCol].elements;
        const elementIndex = sourceElements.findIndex(el => el.type === elementType);

        if (elementIndex === -1) return;

        const element = sourceElements.splice(elementIndex, 1)[0];

        // Add to target
        if (!tableData.rows[targetRow][targetCol].elements) {
            tableData.rows[targetRow][targetCol].elements = [];
        }

        tableData.rows[targetRow][targetCol].elements.push(element);

        // Re-render and refresh preview
        renderTable();
        refreshProductPreview();
    }

    // Column drag and drop functionality
    $(document).on('mousedown', '.plugincy-editable-header', function (e) {
        // Don't drag if clicking on delete button
        if ($(e.target).hasClass('plugincy-delete-column')) {
            return;
        }

        const $header = $(this);
        let isDragging = false;
        let dragTimeout;
        const startX = e.pageX;
        const startY = e.pageY;
        const sourceIndex = $header.data('index');

        // Set a timeout to start dragging - this allows for click-to-edit
        dragTimeout = setTimeout(function () {
            // Only start dragging if mouse hasn't moved much (not editing text)
            isDragging = true;
            $header.addClass('column-dragging');

            $(document).on('mousemove.columnDrag', function (e) {
                if (!isDragging) return;

                const $target = $(e.target).closest('.plugincy-editable-header');
                $('.column-drop-target').removeClass('column-drop-target');

                if ($target.length && $target.data('index') !== sourceIndex) {
                    $target.addClass('column-drop-target');
                }
            });
        }, 200); // 200ms delay before drag starts

        $(document).on('mousemove.columnDragInit', function (e) {
            // If mouse moves significantly before timeout, it's likely a drag intent
            const deltaX = Math.abs(e.pageX - startX);
            const deltaY = Math.abs(e.pageY - startY);

            if (deltaX > 5 || deltaY > 5) {
                clearTimeout(dragTimeout);
                if (!isDragging) {
                    isDragging = true;
                    $header.addClass('column-dragging');

                    $(document).on('mousemove.columnDrag', function (e) {
                        const $target = $(e.target).closest('.plugincy-editable-header');
                        $('.column-drop-target').removeClass('column-drop-target');

                        if ($target.length && $target.data('index') !== sourceIndex) {
                            $target.addClass('column-drop-target');
                        }
                    });
                }
            }
        });

        $(document).on('mouseup.columnDrag mouseup.columnDragInit', function (e) {
            clearTimeout(dragTimeout);

            if (isDragging) {
                // Handle drop
                const $target = $(e.target).closest('.plugincy-editable-header');

                if ($target.length && $target.data('index') !== sourceIndex) {
                    const targetIndex = $target.data('index');
                    moveColumn(sourceIndex, targetIndex);
                }

                // Prevent the click from triggering contenteditable focus
                e.preventDefault();
            } else {
                // This was a click, not a drag - allow normal contenteditable behavior
                // Focus the header for editing
                setTimeout(function () {
                    $header.focus();
                }, 10);
            }

            // Cleanup
            $('.column-dragging').removeClass('column-dragging');
            $('.column-drop-target').removeClass('column-drop-target');
            $(document).off('.columnDrag .columnDragInit');
            isDragging = false;
        });
    });

    // Function to move column
    function moveColumn(sourceIndex, targetIndex) {
        // Move header
        const headerItem = tableData.headers.splice(sourceIndex, 1)[0];
        tableData.headers.splice(targetIndex, 0, headerItem);

        // Move footer
        const footerItem = tableData.footers.splice(sourceIndex, 1)[0];
        tableData.footers.splice(targetIndex, 0, footerItem);

        // Move all row data
        tableData.rows.forEach(row => {
            const cellItem = row.splice(sourceIndex, 1)[0];
            row.splice(targetIndex, 0, cellItem);
        });

        // Re-render and refresh preview
        renderTable();
        refreshProductPreview();
    }

    $(window).on("click", function (event) {
        if (event.target.id === "plugincy-element-modal") {
            $("#plugincy-element-modal").hide();
        }
    });

    // Element customization elements.json file
    const elementCustomizationConfig = wcproducttab_ajax.elements_json || [];

    // Function to get element configuration by type
    function getElementConfig(elementType) {
        return elementCustomizationConfig.find(config => config.el_type === elementType);
    }

    // switch between tabs
    $(document).on("click", ".plugincy-tab-item", function () {
        const tabId = $(this).data("tab");

        // Remove active class from all tabs
        $(".plugincy-tab-item").removeClass("active");
        $(".plugincy-tab-content").removeClass("active");

        // Add active class to clicked tab
        $(this).addClass("active");
        $("#plugincy-form-" + tabId).addClass("active");
    });

    // Function to generate customization form HTML
    function generateCustomizationForm(elementConfig, existingSettings = {}) {
        let formHTML = `
        <div class="plugincy-customization-form">
            <h4>${elementConfig.el_name}</h4>
            <p class="description">${elementConfig.el_description}</p>
            <div class="plugincy-tabs">
                <ul class="plugincy-tab-list">
                    <li class="plugincy-tab-item active" data-tab="content">Content</li>
                    <li class="plugincy-tab-item" data-tab="style">Style</li>
                </ul>
            </div>
            <div class="plugincy-tab-content active" id="plugincy-form-content">`;
        // Content customization options
        elementConfig.el_customization_options.content.forEach(option => {
            const optionKeys = Object.keys(option); // Get the key of the option group
            const fieldId = `custom_${option.name}`;
            const currentValue =
                existingSettings &&
                    existingSettings["content_settings"] &&
                    existingSettings["content_settings"][option.name] !== undefined
                    ? existingSettings["content_settings"][option.name]
                    : option.default;
            formHTML += `<div class="plugincy-form-field" data-tab="content" data-checkbox = "${option.checkboxOptions ?? ''}" data-unit="${option.unit ?? ''}" data-field="${option.name}">`;
            formHTML += `<label for="${fieldId}">${option.title}</label>`;

            switch (option.type) {
                case 'text':
                    formHTML += `<input type="text" id="${fieldId}" name="${option.name}" value="${currentValue}" />`;
                    break;

                case 'textarea':
                    formHTML += `<textarea id="${fieldId}" name="${option.name}" rows="3">${currentValue}</textarea>`;
                    break;

                case 'number':
                    const unit = option.unit ? ` <span class="unit">${option.unit}</span>` : '';
                    const min = option.min !== undefined ? `min="${option.min}"` : '';
                    const max = option.max !== undefined ? `max="${option.max}"` : '';
                    formHTML += `<div class="number-input-wrapper">
                    <input type="number" id="${fieldId}" name="${option.name}" value="${currentValue}" ${min} ${max} />
                    ${unit}
                </div>`;
                    break;

                case 'color':
                    formHTML += `<input type="color" id="${fieldId}" name="${option.name}" value="${currentValue}" />`;
                    break;

                case 'checkbox':
                    const checked = option.checkboxOptions && option.checkboxOptions[0] && option.checkboxOptions[0] === currentValue ? 'checked' : '';
                    formHTML += `<input type="checkbox" id="${fieldId}" name="${option.name}" value="1" ${checked} />`;
                    break;

                case 'radio':
                    option.options.forEach(radioOption => {
                        const radioChecked = currentValue === radioOption.value ? 'checked' : '';
                        formHTML += `
                        <label class="radio-option">
                            <input type="radio" name="${option.name}" value="${radioOption.value}" ${radioChecked} />
                            ${radioOption.label}
                        </label>
                    `;
                    });
                    break;

                case 'select':
                    formHTML += `<select id="${fieldId}" name="${option.name}">`;
                    option.options.forEach(selectOption => {
                        const selected = currentValue === selectOption.value ? 'selected' : '';
                        formHTML += `<option value="${selectOption.value}" ${selected}>${selectOption.label}</option>`;
                    });
                    formHTML += `</select>`;
                    break;

                case 'file':
                    formHTML += `<input type="file" id="${fieldId}" name="${option.name}" accept="${option.accept || '*'}" />`;
                    if (currentValue) {
                        formHTML += `<div class="current-file">Current: ${currentValue}</div>`;
                    }
                    break;

                case 'range':
                    const rangeMin = option.min || 0;
                    const rangeMax = option.max || 100;
                    formHTML += `
                    <div class="range-input-wrapper">
                        <input type="range" id="${fieldId}" name="${option.name}" min="${rangeMin}" max="${rangeMax}" value="${currentValue}" />
                        <span class="range-value">${currentValue}</span>
                    </div>
                `;
                    break;
            }
            if (option.description) {
                formHTML += `<p class="field-description">${option.description}</p>`;
            }
            formHTML += `</div>`;
        });
        // End of content customization options


        formHTML += `</div>
            <div class="plugincy-tab-content plugincy-form-fields" id="plugincy-form-style">`;

        elementConfig.el_customization_options.style.forEach(optionGroup => {
            const optionKeys = Object.keys(optionGroup); // Get the key of the option group
            optionKeys.forEach(optionKey => {
                const options = optionGroup[optionKey];

                options.forEach(option => {
                    const fieldId = `custom_${option.name}`;
                    const currentValue =
                        existingSettings &&
                            existingSettings[optionKey] &&
                            existingSettings[optionKey][option.name] !== undefined ?
                            (typeof existingSettings[optionKey][option.name] === 'string' &&
                                existingSettings[optionKey][option.name].includes('!important') ?
                                existingSettings[optionKey][option.name].replace('!important', '').trim() :
                                existingSettings[optionKey][option.name]) :
                            option.default;
                    const numericValue = parseInt(currentValue, 10) || 0;

                    formHTML += `<div class="plugincy-form-field" data-important = "${option.use_important ?? ''}"  data-checkbox = "${option.checkboxOptions ?? ''}" data-unit="${option.unit ?? ''}" data-selector="${optionKey}" data-field="${option.name}">`;
                    formHTML += `<label for="${fieldId}">${option.title}</label>`;

                    switch (option.type) {
                        case 'text':
                            formHTML += `<input type="text" id="${fieldId}" name="${option.name}" value="${currentValue}" />`;
                            break;

                        case 'textarea':
                            formHTML += `<textarea id="${fieldId}" name="${option.name}" rows="3">${currentValue}</textarea>`;
                            break;

                        case 'number':
                            const unit = option.unit ? ` <span class="unit">${option.unit}</span>` : '';
                            const min = option.min !== undefined ? `min="${option.min}"` : '';
                            const max = option.max !== undefined ? `max="${option.max}"` : '';
                            formHTML += `<div class="number-input-wrapper">
                    <input type="number" id="${fieldId}" name="${option.name}" value="${numericValue}" ${min} ${max} />
                    ${unit}
                </div>`;
                            break;

                        case 'color':
                            formHTML += `<input type="color" id="${fieldId}" name="${option.name}" value="${currentValue}" />`;
                            break;

                        case 'checkbox':
                            const checked = option.checkboxOptions && option.checkboxOptions[0] && option.checkboxOptions[0] === currentValue ? 'checked' : '';
                            formHTML += `<input type="checkbox" id="${fieldId}" name="${option.name}" value="1" ${checked} />`;
                            break;

                        case 'radio':
                            option.options.forEach(radioOption => {
                                const radioChecked = currentValue === radioOption.value ? 'checked' : '';
                                formHTML += `
                        <label class="radio-option">
                            <input type="radio" name="${option.name}" value="${radioOption.value}" ${radioChecked} />
                            ${radioOption.label}
                        </label>
                    `;
                            });
                            break;

                        case 'select':
                            formHTML += `<select id="${fieldId}" name="${option.name}">`;
                            option.options.forEach(selectOption => {
                                const selected = currentValue === selectOption.value ? 'selected' : '';
                                formHTML += `<option value="${selectOption.value}" ${selected}>${selectOption.label}</option>`;
                            });
                            formHTML += `</select>`;
                            break;

                        case 'file':
                            formHTML += `<input type="file" id="${fieldId}" name="${option.name}" accept="${option.accept || '*'}" />`;
                            if (currentValue) {
                                formHTML += `<div class="current-file">Current: ${currentValue}</div>`;
                            }
                            break;

                        case 'range':
                            const rangeMin = option.min || 0;
                            const rangeMax = option.max || 100;
                            formHTML += `
                    <div class="range-input-wrapper">
                        <input type="range" id="${fieldId}" name="${option.name}" min="${rangeMin}" max="${rangeMax}" value="${currentValue}" />
                        <span class="range-value">${currentValue}</span>
                    </div>
                `;
                            break;
                    }

                    if (option.description) {
                        formHTML += `<p class="field-description">${option.description}</p>`;
                    }

                    formHTML += `</div>`;
                });
            });
        });

        formHTML += `
            </div>
            <div class="plugincy-form-actions">
                <button type="button" class="button button-primary save-element-settings">Save Settings</button>
                <button type="button" class="button cancel-element-edit">Cancel</button>
            </div>
        </div>
    `;

        return formHTML;
    }


    // Handle save element settings
    $(document).on("click", ".save-element-settings", function () {
        const context = window.currentEditContext;
        if (!context) return;

        // Collect form data
        const settings = {};
        $('.plugincy-customization-form .plugincy-form-field').each(function () {
            const fieldName = $(this).data('field');
            let selector = $(this).data('selector');
            let use_important = $(this).data('important');
            let checkboxOptions = $(this).data('checkbox') ?? ''; // Get the string from data attribute
            let checkboxArray = checkboxOptions.split(','); // Convert the string to an array
            const tab = $(this).data('tab');
            if (tab === "content") {
                selector = "content_settings";
            }
            const unit = $(this).data('unit') || '';
            const $input = $(this).find('input, select, textarea');
            if (!settings[selector]) {
                settings[selector] = {};
            }

            if ($input.attr('type') === 'checkbox') {
                settings[selector][fieldName] = checkboxArray[$input.is(':checked') ? 0 : 1] + (use_important === true ? `!important` : '');
            } else if ($input.attr('type') === 'radio') {
                const checkedRadio = $(this).find('input[type="radio"]:checked');
                if (checkedRadio.length) {
                    settings[selector][fieldName] = checkedRadio.val() + (use_important === true ? `!important` : '');
                }
            } else {
                settings[selector][fieldName] = $input.val() + (unit ? `${unit}` : '') + (use_important === true ? `!important` : '');
            }
        });

        // Update element data
        const cellElements = tableData.rows[context.rowIndex][context.colIndex].elements;

        const elementIndex = cellElements.findIndex(el => el.type === context.elementType);

        if (elementIndex !== -1) {
            cellElements[elementIndex].settings = settings;

            // Update content for custom text
            if (context.elementType === 'custom_text' && settings.custom_content) {
                cellElements[elementIndex].content = settings.custom_content;
            }
        }

        // Re-render table and refresh preview
        renderTable();
        refreshProductPreview();

        // Close modal
        $("#plugincy-element-modal").hide();
        window.currentEditContext = null;
    });

    // Handle cancel edit
    $(document).on("click", ".cancel-element-edit", function () {
        $("#plugincy-element-modal").hide();
        window.currentEditContext = null;
    });

    // Initialize everything on page load
    function initializePlugin() {
        renderTable();
        initializeQueryUI();
        updateExcludedCount();

        // Show first tab by default
        $('.nav-tab').first().addClass('nav-tab-active');
        $('.tab-content').first().show();

        // Load preview if we have query settings
        setTimeout(function () {
            refreshProductPreview();
        }, 500);
    }

    // Initialize the plugin
    initializePlugin();
});