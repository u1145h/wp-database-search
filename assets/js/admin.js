/**
 * Admin JavaScript for WP Database Search Plugin
 * 
 * @package WP_Database_Search
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Admin object
    var WPDatabaseSearchAdmin = {
        
        // Configuration
        config: {
            ajaxUrl: '',
            nonce: '',
            confirmDelete: 'Are you sure you want to delete this record?',
            savingText: 'Saving...',
            savedText: 'Saved!',
            errorText: 'An error occurred. Please try again.'
        },
        
        // State
        state: {
            isUploading: false,
            isEditing: false,
            currentRecordId: null
        },
        
        // Initialize
        init: function() {
            this.setupConfig();
            this.bindEvents();
            this.initializeComponents();
        },
        
        // Setup configuration from localized data
        setupConfig: function() {
            if (typeof wpDatabaseSearchAdmin !== 'undefined') {
                this.config.ajaxUrl = wpDatabaseSearchAdmin.ajaxUrl;
                this.config.nonce = wpDatabaseSearchAdmin.nonce;
                this.config.confirmDelete = wpDatabaseSearchAdmin.confirmDelete;
                this.config.savingText = wpDatabaseSearchAdmin.savingText;
                this.config.savedText = wpDatabaseSearchAdmin.savedText;
                this.config.errorText = wpDatabaseSearchAdmin.errorText;
            }
        },
        
        // Bind events
        bindEvents: function() {
            var self = this;
            
            // File upload form
            $(document).on('submit', '#wp-database-search-upload-form', function(e) {
                e.preventDefault();
                self.handleFileUpload();
            });
            
            // Add new record button
            $(document).on('click', '#add-new-record', function() {
                self.openRecordModal();
            });
            
            // Edit record buttons
            $(document).on('click', '.edit-record', function() {
                var recordId = $(this).data('record-id');
                self.editRecord(recordId);
            });
            
            // Delete record buttons
            $(document).on('click', '.delete-record', function() {
                var recordId = $(this).data('record-id');
                self.deleteRecord(recordId);
            });
            
            // Export data button
            $(document).on('click', '#export-data', function() {
                self.exportData();
            });
            
            // Clear all data button
            $(document).on('click', '#clear-all-data', function() {
                self.clearAllData();
            });
            
            // Modal events
            $(document).on('click', '.close, .cancel-modal', function() {
                self.closeModal();
            });
            
            $(document).on('click', '.modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });
            
            // Record form submission
            $(document).on('submit', '#record-form', function(e) {
                e.preventDefault();
                self.saveRecord();
            });
            
            // Inline editing
            $(document).on('click', '.cell-content', function() {
                self.startInlineEdit($(this));
            });
            
            $(document).on('blur', '.cell-edit', function() {
                self.finishInlineEdit($(this));
            });
            
            $(document).on('keydown', '.cell-edit', function(e) {
                if (e.which === 13) { // Enter
                    e.preventDefault();
                    self.finishInlineEdit($(this));
                } else if (e.which === 27) { // Escape
                    e.preventDefault();
                    self.cancelInlineEdit($(this));
                }
            });
        },
        
        // Initialize components
        initializeComponents: function() {
            // Initialize tooltips if available
            if ($.fn.tooltip) {
                $('[data-tooltip]').tooltip();
            }
            
            // Initialize sortable if available
            if ($.fn.sortable) {
                $('#data-table tbody').sortable({
                    handle: '.sort-handle',
                    placeholder: 'sortable-placeholder',
                    update: function(event, ui) {
                        // Handle reordering if needed
                    }
                });
            }
        },
        
        // Handle file upload
        handleFileUpload: function() {
            var self = this;
            var formData = new FormData();
            var fileInput = document.getElementById('file');
            var clearExisting = document.getElementById('clear_existing').checked;
            
            if (!fileInput.files.length) {
                this.showMessage('Please select a file to upload.', 'error');
                return;
            }
            
            formData.append('action', 'wp_database_admin_upload');
            formData.append('file', fileInput.files[0]);
            formData.append('clear_existing', clearExisting ? '1' : '0');
            formData.append('nonce', this.config.nonce);
            
            this.state.isUploading = true;
            this.showUploadProgress();
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            var percentComplete = evt.loaded / evt.total * 100;
                            self.updateUploadProgress(percentComplete);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    self.handleUploadSuccess(response);
                },
                error: function(xhr, status, error) {
                    self.handleUploadError(error);
                },
                complete: function() {
                    self.state.isUploading = false;
                    self.hideUploadProgress();
                }
            });
        },
        
        // Show upload progress
        showUploadProgress: function() {
            $('#upload-progress').show();
            this.updateUploadProgress(0);
        },
        
        // Hide upload progress
        hideUploadProgress: function() {
            $('#upload-progress').hide();
        },
        
        // Update upload progress
        updateUploadProgress: function(percent) {
            $('.progress-fill').css('width', percent + '%');
            $('.progress-text').text('Uploading... ' + Math.round(percent) + '%');
        },
        
        // Handle upload success
        handleUploadSuccess: function(response) {
            var message = response.message || 'File uploaded successfully!';
            var type = response.success ? 'success' : 'error';
            
            this.showMessage(message, type);
            
            if (response.success) {
                // Clear form
                $('#wp-database-search-upload-form')[0].reset();
                
                // Reload page to show new data
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            }
        },
        
        // Handle upload error
        handleUploadError: function(error) {
            this.showMessage(this.config.errorText + ': ' + error, 'error');
        },
        
        // Open record modal
        openRecordModal: function(recordId) {
            this.state.currentRecordId = recordId || 0;
            this.state.isEditing = recordId ? true : false;
            
            var title = recordId ? 'Edit Record' : 'Add New Record';
            $('#modal-title').text(title);
            
            if (recordId) {
                this.loadRecordData(recordId);
            } else {
                this.createEmptyRecordForm();
            }
            
            $('#record-modal').show();
        },
        
        // Load record data for editing
        loadRecordData: function(recordId) {
            var self = this;
            
            // This would typically load data via AJAX
            // For now, we'll get it from the table row
            var $row = $('tr[data-record-id="' + recordId + '"]');
            var data = {};
            
            $row.find('td[data-column]').each(function() {
                var column = $(this).data('column');
                var value = $(this).find('.cell-content').text();
                data[column] = value;
            });
            
            this.populateRecordForm(data);
        },
        
        // Create empty record form
        createEmptyRecordForm: function() {
            var $fieldsContainer = $('#record-fields');
            $fieldsContainer.empty();
            
            // Get column names from table header
            var columns = [];
            $('#data-table thead th[class*="column-"]').each(function() {
                var className = $(this).attr('class');
                var column = className.replace('column-', '').replace('column-', '');
                if (column && column !== 'id' && column !== 'actions') {
                    columns.push($(this).text());
                }
            });
            
            // Create form fields
            columns.forEach(function(column) {
                var fieldHtml = '<div class="form-field">' +
                    '<label for="field_' + column + '">' + column + '</label>' +
                    '<input type="text" id="field_' + column + '" name="data[' + column + ']" value="" />' +
                '</div>';
                $fieldsContainer.append(fieldHtml);
            });
        },
        
        // Populate record form with data
        populateRecordForm: function(data) {
            var $fieldsContainer = $('#record-fields');
            $fieldsContainer.empty();
            
            for (var column in data) {
                var fieldHtml = '<div class="form-field">' +
                    '<label for="field_' + column + '">' + column + '</label>' +
                    '<input type="text" id="field_' + column + '" name="data[' + column + ']" value="' + this.escapeHtml(data[column]) + '" />' +
                '</div>';
                $fieldsContainer.append(fieldHtml);
            }
        },
        
        // Save record
        saveRecord: function() {
            var self = this;
            var formData = $('#record-form').serialize();
            formData += '&action=wp_database_admin_save';
            formData += '&record_id=' + this.state.currentRecordId;
            formData += '&nonce=' + this.config.nonce;
            
            this.showSavingState();
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    self.handleSaveSuccess(response);
                },
                error: function(xhr, status, error) {
                    self.handleSaveError(error);
                },
                complete: function() {
                    self.hideSavingState();
                }
            });
        },
        
        // Handle save success
        handleSaveSuccess: function(response) {
            var message = response.message || this.config.savedText;
            var type = response.success ? 'success' : 'error';
            
            this.showMessage(message, type);
            
            if (response.success) {
                this.closeModal();
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            }
        },
        
        // Handle save error
        handleSaveError: function(error) {
            this.showMessage(this.config.errorText + ': ' + error, 'error');
        },
        
        // Delete record
        deleteRecord: function(recordId) {
            var self = this;
            
            if (!confirm(this.config.confirmDelete)) {
                return;
            }
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_database_admin_delete',
                    record_id: recordId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    self.handleDeleteSuccess(response, recordId);
                },
                error: function(xhr, status, error) {
                    self.handleDeleteError(error);
                }
            });
        },
        
        // Handle delete success
        handleDeleteSuccess: function(response, recordId) {
            var message = response.message || 'Record deleted successfully!';
            var type = response.success ? 'success' : 'error';
            
            this.showMessage(message, type);
            
            if (response.success) {
                $('tr[data-record-id="' + recordId + '"]').fadeOut(300, function() {
                    $(this).remove();
                });
            }
        },
        
        // Handle delete error
        handleDeleteError: function(error) {
            this.showMessage(this.config.errorText + ': ' + error, 'error');
        },
        
        // Export data
        exportData: function() {
            var form = $('<form method="post" action="' + this.config.ajaxUrl + '">' +
                '<input type="hidden" name="action" value="wp_database_admin_export" />' +
                '<input type="hidden" name="nonce" value="' + this.config.nonce + '" />' +
            '</form>');
            
            $('body').append(form);
            form.submit();
            form.remove();
        },
        
        // Clear all data
        clearAllData: function() {
            var self = this;
            
            if (!confirm('Are you sure you want to clear all data? This action cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_database_admin_clear',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    self.handleClearSuccess(response);
                },
                error: function(xhr, status, error) {
                    self.handleClearError(error);
                }
            });
        },
        
        // Handle clear success
        handleClearSuccess: function(response) {
            var message = response.message || 'All data cleared successfully!';
            var type = response.success ? 'success' : 'error';
            
            this.showMessage(message, type);
            
            if (response.success) {
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            }
        },
        
        // Handle clear error
        handleClearError: function(error) {
            this.showMessage(this.config.errorText + ': ' + error, 'error');
        },
        
        // Close modal
        closeModal: function() {
            $('#record-modal').hide();
            this.state.isEditing = false;
            this.state.currentRecordId = null;
        },
        
        // Start inline editing
        startInlineEdit: function($cell) {
            var $content = $cell.find('.cell-content');
            var $edit = $cell.find('.cell-edit');
            
            $content.hide();
            $edit.show().focus().select();
        },
        
        // Finish inline editing
        finishInlineEdit: function($edit) {
            var self = this;
            var $cell = $edit.closest('td');
            var $content = $cell.find('.cell-content');
            var column = $cell.data('column');
            var recordId = $cell.closest('tr').data('record-id');
            var newValue = $edit.val();
            
            // Update content
            $content.text(newValue);
            $edit.hide();
            $content.show();
            
            // Save via AJAX
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_database_admin_save',
                    record_id: recordId,
                    column: column,
                    value: newValue,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (!response.success) {
                        self.showMessage(response.message || self.config.errorText, 'error');
                        // Revert value
                        $content.text($edit.data('original-value'));
                    }
                },
                error: function() {
                    self.showMessage(self.config.errorText, 'error');
                    // Revert value
                    $content.text($edit.data('original-value'));
                }
            });
        },
        
        // Cancel inline editing
        cancelInlineEdit: function($edit) {
            var $cell = $edit.closest('td');
            var $content = $cell.find('.cell-content');
            
            $edit.hide();
            $content.show();
        },
        
        // Show saving state
        showSavingState: function() {
            $('#record-form button[type="submit"]').text(this.config.savingText).prop('disabled', true);
        },
        
        // Hide saving state
        hideSavingState: function() {
            $('#record-form button[type="submit"]').text('Save Record').prop('disabled', false);
        },
        
        // Show message
        showMessage: function(message, type) {
            type = type || 'info';
            
            var $message = $('<div class="notice notice-' + type + ' is-dismissible">' +
                '<p>' + this.escapeHtml(message) + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                    '<span class="screen-reader-text">Dismiss this notice.</span>' +
                '</button>' +
            '</div>');
            
            $('.wp-database-search-content').prepend($message);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        // Escape HTML
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        WPDatabaseSearchAdmin.init();
    });
    
    // Expose to global scope for external access
    window.WPDatabaseSearchAdmin = WPDatabaseSearchAdmin;
    
})(jQuery);
