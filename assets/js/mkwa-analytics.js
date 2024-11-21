// assets/js/mkwa-analytics.js

(function($) {
    'use strict';

    const MKWA_Analytics = {
        charts: {},  // Store chart instances
        
        init: function() {
            this.bindEvents();
            this.initializeCharts();
        },

        bindEvents: function() {
            // Export button handlers
            $('.mkwa-chart-export').on('click', this.handleChartExport.bind(this));
            
            // Date range picker handler
            $('.mkwa-date-range-picker select').on('change', this.handleDateRangeChange.bind(this));
            
            // Handle window resize for chart responsiveness
            $(window).on('resize', this.handleResize.bind(this));
        },

        initializeCharts: function() {
            // Initialize each chart container
            $('.mkwa-chart-container').each((index, container) => {
                const $container = $(container);
                const chartId = $container.attr('id');
                const chartType = $container.data('chart-type');
                const chartData = $container.data('chart-data');

                if (chartData && chartType) {
                    this.createChart(chartId, chartType, chartData);
                }
            });
        },

        createChart: function(chartId, type, data) {
            const ctx = document.getElementById(chartId).getContext('2d');
            const config = this.getChartConfig(type, data);
            
            // Store the chart instance
            this.charts[chartId] = new Chart(ctx, config);
        },

        getChartConfig: function(type, data) {
            const baseConfig = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 10,
                        cornerRadius: 4,
                        displayColors: false
                    },
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            };

            return {
                ...baseConfig,
                type: type,
                data: data,
                options: this.getChartTypeOptions(type)
            };
        },

        getChartTypeOptions: function(type) {
            const options = {
                line: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                },
                bar: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    barThickness: 'flex'
                }
            };

            return options[type] || {};
        },

        handleChartExport: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const chartId = $button.data('chart-id');
            const format = $button.data('format') || 'png';

            this.exportChart(chartId, format, $button);
        },

        exportChart: function(chartId, format, $button) {
            const chart = this.charts[chartId];
            if (!chart) return;

            // Add loading state
            $button.prop('disabled', true);
            $button.closest('.mkwa-dashboard-item').addClass('loading');

            try {
                // Get chart canvas and convert to image
                const imageData = chart.canvas.toDataURL(`image/${format}`);
                
                // Create and trigger download
                const link = document.createElement('a');
                link.download = `mkwa-analytics-${chartId}-${this.getFormattedDate()}.${format}`;
                link.href = imageData;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                this.showNotification('success', 'Chart exported successfully');
            } catch (error) {
                console.error('Export failed:', error);
                this.showNotification('error', 'Failed to export chart');
            } finally {
                // Remove loading state
                $button.prop('disabled', false);
                $button.closest('.mkwa-dashboard-item').removeClass('loading');
            }
        },

        handleDateRangeChange: function(e) {
            const $select = $(e.currentTarget);
            const $form = $select.closest('form');
            
            // Add loading state to dashboard
            $('.mkwa-analytics-dashboard').addClass('loading');
            
            // Submit form
            $form.submit();
        },

        handleResize: function() {
            // Update all charts on window resize
            Object.values(this.charts).forEach(chart => {
                if (chart && typeof chart.resize === 'function') {
                    chart.resize();
                }
            });
        },

        showNotification: function(type, message) {
            const notificationClass = type === 'error' ? 'notice-error' : 'notice-success';
            const $notification = $(`
                <div class="notice ${notificationClass} is-dismissible">
                    <p>${message}</p>
                </div>
            `);

            // Insert notification at the top of the dashboard
            $('.mkwa-analytics-dashboard').prepend($notification);

            // Auto dismiss after 3 seconds
            setTimeout(() => {
                $notification.fadeOut(() => $notification.remove());
            }, 3000);
        },

        getFormattedDate: function() {
            const now = new Date();
            return now.toISOString().split('T')[0];
        },

        updateChart: function(chartId, newData) {
            const chart = this.charts[chartId];
            if (chart) {
                chart.data = newData;
                chart.update();
            }
        },

        destroyChart: function(chartId) {
            const chart = this.charts[chartId];
            if (chart) {
                chart.destroy();
                delete this.charts[chartId];
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        MKWA_Analytics.init();
    });

})(jQuery);