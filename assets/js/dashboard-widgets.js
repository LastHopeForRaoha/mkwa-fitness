// assets/js/dashboard-widgets.js

(function($) {
    'use strict';

    class MKWADashboard {
        constructor() {
            this.widgets = new Map();
            this.init();
        }

        init() {
            this.initializeWidgets();
            this.setupEventListeners();
        }

        initializeWidgets() {
            $('.mkwa-dashboard-widget').each((index, element) => {
                const widget = new MKWAWidget(element);
                this.widgets.set(widget.getId(), widget);
            });
        }

        setupEventListeners() {
            $(document).on('click', '.refresh-widget', (e) => {
                const widgetId = $(e.target).closest('.mkwa-dashboard-widget').attr('id');
                this.refreshWidget(widgetId);
            });

            $(document).on('click', '.configure-widget', (e) => {
                const widgetId = $(e.target).closest('.mkwa-dashboard-widget').attr('id');
                this.configureWidget(widgetId);
            });
        }

        refreshWidget(widgetId) {
            const widget = this.widgets.get(widgetId);
            if (widget) {
                widget.refresh();
            }
        }

        configureWidget(widgetId) {
            const widget = this.widgets.get(widgetId);
            if (widget) {
                widget.showConfiguration();
            }
        }
    }

    class MKWAWidget {
        constructor(element) {
            this.element = $(element);
            this.id = this.element.attr('id');
            this.type = this.element.data('type');
            this.refreshInterval = this.element.data('refresh');
            this.chart = null;

            this.init();
        }

        init() {
            this.initializeContent();
            this.setupAutoRefresh();
        }

        getId() {
            return this.id;
        }

        initializeContent() {
            switch (this.type) {
                case 'chart':
                    this.initializeChart();
                    break;
                case 'stats':
                    this.initializeStats();
                    break;
                // Add other widget type initializations
            }
        }

        initializeChart() {
            const canvas = this.element.find('canvas');
            if (canvas.length) {
                const ctx = canvas[0].getContext('2d');
                const data = canvas.data('chart');
                const settings = canvas.data('settings');

                this.chart = new Chart(ctx, {
                    type: settings.type,
                    data: data,
                    options: settings.options
                });
            }
        }

        initializeStats() {
            // Initialize stats specific functionality
        }

        refresh() {
            this.element.addClass('widget-loading');

            $.ajax({
                url: mkwaDashboard.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mkwa_refresh_widget',
                    widget_id: this.id,
                    nonce: mkwaDashboard.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateContent(response.data);
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: () => {
                    this.showError(mkwaDashboard.i18n.error);
                },
                complete: () => {
                    this.element.removeClass('widget-loading');
                }
            });
        }

        updateContent(data) {
            switch (this.type) {
                case 'chart':
                    this.updateChart(data);
                    break;
                case 'stats':
                    this.updateStats(data);
                    break;
                case 'list':
                    this.updateList(data);
                    break;
                case 'progress':
                    this.updateProgress(data);
                    break;
            }
        }

        updateChart(data) {
            if (this.chart) {
                this.chart.data = data;
                this.chart.update();
            }
        }

        updateStats(data) {
            const statsContainer = this.element.find('.stats-grid');
            // Update stats content
        }

        updateList(data) {
            const listContainer = this.element.find('.widget-list');
            // Update list content
        }

        updateProgress(data) {
            const progressBar = this.element.find('.progress-bar');
            const percentage = (data.current / data.total) * 100;
            progressBar.css('width', `${percentage}%`);
            progressBar.find('.progress-text').text(`${data.current} / ${data.total}`);
        }

        setupAutoRefresh() {
            if (this.refreshInterval > 0) {
                setInterval(() => this.refresh(), this.refreshInterval * 1000);
            }
        }

        showConfiguration() {
            // Implement widget configuration modal
        }

        showError(message) {
            // Show error message
            console.error(`Widget Error: ${message}`);
        }
    }

    // Initialize dashboard when document is ready
    $(document).ready(() => {
        window.mkwaDashboard = new MKWADashboard();
    });

})(jQuery);