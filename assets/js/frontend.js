/**
 * Frontend JavaScript for WP Database Search Plugin
 * 
 * @package WP_Database_Search
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Plugin object
    var WPDatabaseSearch = {
        
        // Configuration
        config: {
            searchDelay: 300,
            resultsLimit: 10,
            minSearchLength: 2,
            ajaxUrl: '',
            nonce: '',
            loadingText: 'Searching...',
            noResultsText: 'No results found',
            errorText: 'An error occurred. Please try again.'
        },
        
        // State
        state: {
            currentSearch: '',
            currentColumn: '',
            isSearching: false,
            searchTimeout: null,
            lastSearchTime: 0
        },
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.setupConfig();
        },
        
        // Setup configuration from localized data
        setupConfig: function() {
            if (typeof wpDatabaseSearch !== 'undefined') {
                this.config.ajaxUrl = wpDatabaseSearch.ajaxUrl;
                this.config.nonce = wpDatabaseSearch.nonce;
                this.config.loadingText = wpDatabaseSearch.loadingText;
                this.config.noResultsText = wpDatabaseSearch.noResultsText;
                this.config.errorText = wpDatabaseSearch.errorText;
            }
            
            // Get config from container data attributes
            var container = $('.wp-database-search-container');
            if (container.length) {
                this.config.searchDelay = parseInt(container.data('search-delay')) || this.config.searchDelay;
                this.config.resultsLimit = parseInt(container.data('results-limit')) || this.config.resultsLimit;
            }
        },
        
        // Bind events
        bindEvents: function() {
            var self = this;
            
            // Search input events
            $(document).on('input', '.wp-database-search-input', function() {
                self.handleSearchInput($(this));
            });
            
            // Column filter events
            $(document).on('change', '.wp-database-search-column-filter', function() {
                self.handleColumnFilter($(this));
            });
            
            // Clear search events
            $(document).on('click', '.clear-search', function() {
                self.clearSearch();
            });
            
            // Click outside to close results
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.wp-database-search-container').length) {
                    self.hideResults();
                }
            });
            
            // Keyboard navigation
            $(document).on('keydown', '.wp-database-search-input', function(e) {
                self.handleKeyboardNavigation(e);
            });
            
            // Result item click events
            $(document).on('click', '.search-result-item', function(e) {
                if (!$(e.target).is('a')) {
                    var link = $(this).find('.result-link');
                    if (link.length) {
                        window.location.href = link.attr('href');
                    }
                }
            });
        },
        
        // Handle search input
        handleSearchInput: function($input) {
            var self = this;
            var searchTerm = $input.val().trim();
            
            // Clear previous timeout
            if (this.state.searchTimeout) {
                clearTimeout(this.state.searchTimeout);
            }
            
            // Update current search
            this.state.currentSearch = searchTerm;
            
            // Show/hide results container
            if (searchTerm.length >= this.config.minSearchLength) {
                this.showResults();
                this.showLoading();
                
                // Debounce search
                this.state.searchTimeout = setTimeout(function() {
                    self.performSearch(searchTerm);
                }, this.config.searchDelay);
            } else {
                this.hideResults();
            }
        },
        
        // Handle column filter change
        handleColumnFilter: function($select) {
            var self = this;
            var column = $select.val();
            
            this.state.currentColumn = column;
            
            // If we have a current search, perform it again with new filter
            if (this.state.currentSearch.length >= this.config.minSearchLength) {
                this.performSearch(this.state.currentSearch);
            }
        },
        
        // Perform search
        performSearch: function(searchTerm) {
            var self = this;
            
            // Prevent duplicate searches
            var currentTime = Date.now();
            if (currentTime - this.state.lastSearchTime < 100) {
                return;
            }
            this.state.lastSearchTime = currentTime;
            
            this.state.isSearching = true;
            
            // Add timestamp for cache busting
            var timestamp = Date.now();
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_database_search',
                    search_term: searchTerm,
                    column_filter: this.state.currentColumn,
                    nonce: this.config.nonce,
                    timestamp: timestamp
                },
                timeout: 10000, // 10 second timeout
                success: function(response) {
                    console.log('Search response:', response); // Debug log
                    self.handleSearchSuccess(response, searchTerm);
                },
                error: function(xhr, status, error) {
                    console.error('Search error:', xhr, status, error); // Debug log
                    self.handleSearchError(error);
                },
                complete: function() {
                    self.state.isSearching = false;
                    self.hideLoading();
                }
            });
        },
        
        // Handle successful search
        handleSearchSuccess: function(response, searchTerm) {
            var self = this;
            var results = response.data || [];
            var $resultsContainer = $('.wp-database-search-results');
            var $resultsList = $resultsContainer.find('.results-list');
            var $resultsCount = $resultsContainer.find('.results-count');
            var $noResults = $resultsContainer.find('.no-results');
            var $searchError = $resultsContainer.find('.search-error');
            
            console.log('Processing results:', results); // Debug log
            
            // Clear previous results
            $resultsList.empty();
            $noResults.hide();
            $searchError.hide();
            
            if (results.length > 0) {
                // Render as table
                var table = self.renderResultsTable(results, searchTerm);
                $resultsList.append(table);
                var countText = results.length === 1 ? '1 result found' : results.length + ' results found';
                $resultsCount.text(countText);
                $resultsContainer.find('.clear-search').show();
            } else {
                // Show no results message
                $noResults.show();
                $resultsCount.text('0 results found');
                $resultsContainer.find('.clear-search').hide();
            }
            
            // Show results container
            self.showResults();
        },
        
        // Handle search error
        handleSearchError: function(error) {
            var self = this;
            var $resultsContainer = $('.wp-database-search-results');
            var $searchError = $resultsContainer.find('.search-error');
            
            $searchError.show();
            $resultsContainer.find('.results-list').empty();
            $resultsContainer.find('.no-results').hide();
            $resultsContainer.find('.results-count').text('Search error');
            
            self.showResults();
            
            console.error('WP Database Search Error:', error);
        },
        
        // Render results as a table
        renderResultsTable: function(results, searchTerm) {
            if (!results.length) return '';
            var self = this;
            var columns = Object.keys(results[0].data);
            var tableHtml = '<table class="wp-database-search-table"><thead><tr>';
            columns.forEach(function(col) {
                tableHtml += '<th>' + self.escapeHtml(col) + '</th>';
            });
            tableHtml += '</tr></thead><tbody>';
            results.forEach(function(result) {
                tableHtml += '<tr tabindex="0" class="search-result-row" data-url="' + result.url + '">';
                columns.forEach(function(col) {
                    var value = result.data[col] !== undefined ? result.data[col] : '';
                    var highlighted = self.highlightSearchTerm(value ? value.toString() : '', searchTerm);
                    tableHtml += '<td>' + highlighted + '</td>';
                });
                tableHtml += '</tr>';
            });
            tableHtml += '</tbody></table>';
            var $table = $(tableHtml);
            // Make rows clickable
            $table.find('tbody').on('click', 'tr', function(e) {
                var url = $(this).data('url');
                if (url) {
                    window.location.href = url;
                }
            });
            // Keyboard accessibility
            $table.find('tbody').on('keydown', 'tr', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    var url = $(this).data('url');
                    if (url) {
                        window.location.href = url;
                    }
                }
            });
            return $table;
        },
        
        // Get result title from data
        getResultTitle: function(data) {
            var titleFields = ['name', 'title', 'company', 'organization', 'business'];
            
            for (var i = 0; i < titleFields.length; i++) {
                if (data[titleFields[i]] && data[titleFields[i]].trim()) {
                    return data[titleFields[i]];
                }
            }
            
            // Use first non-empty field
            for (var key in data) {
                if (data[key] && data[key].trim()) {
                    return data[key];
                }
            }
            
            return 'Record';
        },
        
        // Get result summary from data
        getResultSummary: function(data) {
            var summaryParts = [];
            var count = 0;
            
            for (var key in data) {
                if (data[key] && data[key].trim() && count < 3) {
                    summaryParts.push(key + ': ' + data[key]);
                    count++;
                }
            }
            
            return summaryParts.join(' | ');
        },
        
        // Highlight search term in text
        highlightSearchTerm: function(text, searchTerm) {
            if (!searchTerm || !text) {
                return this.escapeHtml(text);
            }
            
            var regex = new RegExp('(' + this.escapeRegex(searchTerm) + ')', 'gi');
            return this.escapeHtml(text).replace(regex, '<mark class="search-highlight">$1</mark>');
        },
        
        // Escape HTML
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        // Escape regex special characters
        escapeRegex: function(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },
        
        // Show results
        showResults: function() {
            $('.wp-database-search-results').show();
        },
        
        // Hide results
        hideResults: function() {
            $('.wp-database-search-results').hide();
        },
        
        // Show loading
        showLoading: function() {
            $('.search-loading').show();
        },
        
        // Hide loading
        hideLoading: function() {
            $('.search-loading').hide();
        },
        
        // Clear search
        clearSearch: function() {
            $('.wp-database-search-input').val('');
            $('.wp-database-search-column-filter').val('');
            this.state.currentSearch = '';
            this.state.currentColumn = '';
            this.hideResults();
            
            if (this.state.searchTimeout) {
                clearTimeout(this.state.searchTimeout);
            }
        },
        
        // Handle keyboard navigation
        handleKeyboardNavigation: function(e) {
            var $results = $('.search-result-item');
            var $current = $results.filter('.keyboard-selected');
            var currentIndex = $results.index($current);
            
            switch (e.which) {
                case 38: // Up arrow
                    e.preventDefault();
                    if (currentIndex > 0) {
                        $current.removeClass('keyboard-selected');
                        $results.eq(currentIndex - 1).addClass('keyboard-selected');
                    }
                    break;
                    
                case 40: // Down arrow
                    e.preventDefault();
                    if (currentIndex < $results.length - 1) {
                        $current.removeClass('keyboard-selected');
                        $results.eq(currentIndex + 1).addClass('keyboard-selected');
                    } else if (currentIndex === -1 && $results.length > 0) {
                        $results.eq(0).addClass('keyboard-selected');
                    }
                    break;
                    
                case 13: // Enter
                    if ($current.length) {
                        e.preventDefault();
                        var link = $current.find('.result-link');
                        if (link.length) {
                            window.location.href = link.attr('href');
                        }
                    }
                    break;
                    
                case 27: // Escape
                    this.hideResults();
                    break;
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        WPDatabaseSearch.init();
    });
    
    // Expose to global scope for external access
    window.WPDatabaseSearch = WPDatabaseSearch;
    
})(jQuery);
