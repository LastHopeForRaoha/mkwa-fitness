// assets/js/charts.js

(function($) {
    'use strict';

    const MKWA_Charts = {
        charts: {},
        
        init: function() {
            this.initCharts();
            this.bindEvents();
        },

        initCharts: function() {
            $('.mkwa-chart-container canvas').each((index, canvas) => {
                this.initializeChart(canvas);
            });
        },

        bindEvents: function() {
            // Handle window resize for responsiveness
            let resizeTimer;
            $(window).on('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    this.resizeAllCharts();
                }, 250);
            });

            // Handle chart export buttons
            $('.mkwa-chart-export').on('click', (e) => {
                e.preventDefault();
                const chartId = $(e.currentTarget).data('chart-id');
                const format = $(e.currentTarget).data('format');
                this.exportChart(chartId, format);
            });

            // Handle chart refresh/update
            $('.mkwa-chart-refresh').on('click', (e) => {
                e.preventDefault();
                const chartId = $(e.currentTarget).data('chart-id');
                this.refreshChart(chartId);
            });
        },

        initializeChart: function(canvas) {
            const $canvas = $(canvas);
            const chartId = $canvas.attr('id');
            const config = $canvas.data('chart-config');

            if (!config) {
                console.error('No configuration found for chart:', chartId);
                return;
            }

            // Apply global defaults from localized data
            const finalConfig = this.mergeWithDefaults(config);

            // Initialize Chart.js instance
            this.charts[chartId] = new Chart(canvas, finalConfig);
        },

        mergeWithDefaults: function(config) {
            // Deep merge with global defaults from localized mkwaChartData
            return {
                ...mkwaChartData.defaults,
                ...config,
                options: {
                    ...mkwaChartData.defaults.options,
                    ...config.options,
                    plugins: {
                        ...mkwaChartData.defaults.plugins,
                        ...config.options?.plugins
                    }
                }
            };
        },

        resizeAllCharts: function() {
            Object.values(this.charts).forEach(chart => {
                if (chart && typeof chart.resize === 'function') {
                    chart.resize();
                }
            });
        },

        updateChart: function(chartId, newData, newOptions = {}) {
            const chart = this.charts[chartId];
            if (!chart) {
                console.error('Chart not found:', chartId);
                return;
            }

            // Update data
            if (newData.labels) {
                chart.data.labels = newData.labels;
            }
            if (newData.datasets) {
                chart.data.datasets = newData.datasets;
            }

            // Update options
            if (Object.keys(newOptions).length > 0) {
                chart.options = this.mergeWithDefaults({
                    ...chart.options,
                    ...newOptions
                });
            }

            chart.update();
        },

        refreshChart: function(chartId) {
            const $container = $(`#${chartId}`).closest('.mkwa-chart-container');
            $container.addClass('loading');

            $.ajax({
                url: mkwaChartData.ajaxurl,
                method: 'POST',
                data: {
                    action: 'mkwa_refresh_chart',
                    nonce: mkwaChartData.nonce,
                    chart_id: chartId
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.updateChart(chartId, response.data.data, response.data.options);
                    } else {
                        console.error('Failed to refresh chart:', response.data?.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Chart refresh failed:', error);
                },
                complete: () => {
                    $container.removeClass('loading');
                }
            });
        },

        exportChart: function(chartId, format = 'png') {
            const chart = this.charts[chartId];
            if (!chart) {
                console.error('Chart not found:', chartId);
                return;
            }

            // Handle client-side export for supported formats
            if (format === 'png' || format === 'jpg') {
                const link = document.createElement('a');
                link.download = `chart-${chartId}.${format}`;
                link.href = chart.toBase64Image(format);
                link.click();
                return;
            }

            // Server-side export for PDF and other formats
            $.ajax({
                url: mkwaChartData.ajaxurl,
                method: 'POST',
                data: {
                    action: 'mkwa_export_chart',
                    nonce: mkwaChartData.nonce,
                    chart_id: chartId,
                    format: format
                },
                success: (response) => {
                    if (response.success && response.data?.url) {
                        window.location.href = response.data.url;
                    } else {
                        console.error('Export failed:', response.data?.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Chart export failed:', error);
                }
            });
        },

        destroy: function(chartId) {
            if (this.charts[chartId]) {
                this.charts[chartId].destroy();
                delete this.charts[chartId];
            }
        }
    };

    // Initialize on document ready
    $(document).ready(() => {
        MKWA_Charts.init();
    });

    // Make available globally
    window.MKWA_Charts = MKWA_Charts;

})(jQuery);