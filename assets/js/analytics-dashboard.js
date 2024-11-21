(function($) {
    'use strict';

    // Chart instances storage
    let charts = {};

    // Chart color schemes
    const colors = {
        primary: '#2271b1',
        secondary: '#135e96',
        tertiary: '#72aee6',
        success: '#00a32a',
        background: 'rgba(34, 113, 177, 0.1)',
        gridLines: '#f0f0f1'
    };

    // Default chart options
    const defaultChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: 'rgba(255, 255, 255, 0.1)',
                borderWidth: 1
            }
        },
        scales: {
            x: {
                grid: {
                    color: colors.gridLines
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: colors.gridLines
                }
            }
        }
    };

    // Initialize dashboard
    function initDashboard() {
        initDateRangePicker();
        loadAnalyticsData();
        initExportButtons();
    }

    // Initialize date range picker
    function initDateRangePicker() {
        const picker = $('.mkwa-date-range-picker');
        if (!picker.length) return;

        // Add period options
        const periods = [
            { value: 'last_7_days', label: 'Last 7 Days' },
            { value: 'last_30_days', label: 'Last 30 Days' },
            { value: 'last_90_days', label: 'Last 90 Days' }
        ];

        const select = $('<select>', {
            class: 'mkwa-period-select'
        });

        periods.forEach(period => {
            select.append($('<option>', {
                value: period.value,
                text: period.label
            }));
        });

        picker.append(select);

        // Handle period change
        select.on('change', function() {
            loadAnalyticsData($(this).val());
        });
    }

    // Load analytics data via AJAX
    function loadAnalyticsData(period = 'last_30_days') {
        $('.mkwa-dashboard-item').addClass('loading');

        $.ajax({
            url: mkwaAnalytics.ajaxurl,
            type: 'POST',
            data: {
                action: 'mkwa_get_analytics_data',
                nonce: mkwaAnalytics.nonce,
                period: period
            },
            success: function(response) {
                if (response.success) {
                    updateCharts(response.data);
                    updateTimeline(response.data.events);
                }
            },
            error: function(xhr, status, error) {
                console.error('Analytics data loading failed:', error);
            },
            complete: function() {
                $('.mkwa-dashboard-item').removeClass('loading');
            }
        });
    }

    // Update all charts with new data
    function updateCharts(data) {
        updatePageViewsChart(data.page_views);
        updateEventDistributionChart(data.events);
        updateMetricsChart(data.metrics);
    }

    // Page Views Chart
    function updatePageViewsChart(pageViews) {
        const ctx = document.getElementById('page-views').getContext('2d');
        
        if (charts.pageViews) {
            charts.pageViews.destroy();
        }

        const chartData = processPageViewsData(pageViews);

        charts.pageViews = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Page Views',
                    data: chartData.values,
                    borderColor: colors.primary,
                    backgroundColor: colors.background,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                ...defaultChartOptions,
                plugins: {
                    ...defaultChartOptions.plugins,
                    title: {
                        display: true,
                        text: 'Page Views Over Time'
                    }
                }
            }
        });
    }

    // Event Distribution Chart
    function updateEventDistributionChart(events) {
        const ctx = document.getElementById('event-distribution').getContext('2d');
        
        if (charts.events) {
            charts.events.destroy();
        }

        const chartData = processEventData(events);

        charts.events = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Event Count',
                    data: chartData.values,
                    backgroundColor: colors.primary,
                    borderColor: colors.secondary,
                    borderWidth: 1
                }]
            },
            options: {
                ...defaultChartOptions,
                plugins: {
                    ...defaultChartOptions.plugins,
                    title: {
                        display: true,
                        text: 'Event Distribution'
                    }
                }
            }
        });
    }

    // Metrics Overview Chart
    function updateMetricsChart(metrics) {
        const ctx = document.getElementById('metrics-overview').getContext('2d');
        
        if (charts.metrics) {
            charts.metrics.destroy();
        }

        const chartData = processMetricsData(metrics);

        charts.metrics = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: chartData.datasets
            },
            options: {
                ...defaultChartOptions,
                plugins: {
                    ...defaultChartOptions.plugins,
                    title: {
                        display: true,
                        text: 'Metrics Overview'
                    }
                }
            }
        });
    }

    // Data processing helpers
    function processPageViewsData(pageViews) {
        // Process page views data for chart
        const dates = Object.keys(pageViews);
        const counts = Object.values(pageViews);
        
        return {
            labels: dates,
            values: counts
        };
    }

    function processEventData(events) {
        // Process events data for chart
        const eventTypes = {};
        events.forEach(event => {
            eventTypes[event.event_type] = (eventTypes[event.event_type] || 0) + 1;
        });

        return {
            labels: Object.keys(eventTypes),
            values: Object.values(eventTypes)
        };
    }

    function processMetricsData(metrics) {
        // Process metrics data for chart
        const metricsByType = {};
        metrics.forEach(metric => {
            if (!metricsByType[metric.metric_name]) {
                metricsByType[metric.metric_name] = {
                    dates: [],
                    values: []
                };
            }
            metricsByType[metric.metric_name].dates.push(metric.timestamp);
            metricsByType[metric.metric_name].values.push(metric.value);
        });

        const datasets = Object.entries(metricsByType).map(([name, data], index) => ({
            label: name,
            data: data.values,
            borderColor: getChartColor(index),
            fill: false,
            tension: 0.4
        }));

        return {
            labels: Object.values(metricsByType)[0]?.dates || [],
            datasets: datasets
        };
    }

    // Export functionality
    function initExportButtons() {
        $('.mkwa-chart-export').on('click', function() {
            const chartId = $(this).data('chart-id');
            const format = $(this).data('format');
            exportChart(chartId, format);
        });
    }

    function exportChart(chartId, format) {
        const chart = charts[chartId];
        if (!chart) return;

        const link = document.createElement('a');
        link.download = `mkwa-${chartId}-${new Date().toISOString()}.${format}`;
        link.href = chart.toBase64Image();
        link.click();
    }

    // Helper function to get chart colors
    function getChartColor(index) {
        const colorsList = [
            colors.primary,
            colors.secondary,
            colors.tertiary,
            colors.success
        ];
        return colorsList[index % colorsList.length];
    }

    // Initialize on document ready
    $(document).ready(initDashboard);

})(jQuery);