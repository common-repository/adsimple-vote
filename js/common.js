(function($) {
    var dataBezierSmoothing = function(data) {
        for (var j = 0, m = 4; j < m; j++) {
            for (var i = 1, l = data.length - 1; i < l; i++) {
                data[i] = 0.5 * (0.5 * (data[i - 1] + data[i + 1]) + data[i]);
            }
        }
    };

    /**
    * Create Vote Chart.
    */
    var showChart = function(id, debug) {
        jQuery('#adsimplevote-' + id + ' .ct-chart').show();

        var i, j;
        var data = JSON.parse(JSON.stringify(window['adsimplevote_data_' + id]));

        if (!debug) {
            for (i = 0; i < data.series.length; i++) {
                for (j = 0; j < 4; j++) {
                    dataBezierSmoothing(data.series[i]);
                }
            }
        }

        // Create a new line chart object.
        var chart = new Chartist.Line(
            '#adsimplevote-' + id + ' .ct-chart',
            data,
            {
                showPoint: false,
                showArea: true,
                axisX: {showGrid: true, showLabel: false, offset: 0},
                axisY: {showGrid: false, showLabel: !!debug, offset: debug ? 60 : 0}
            }
        );

        // Cart animation on draw.
        chart.on('draw', function (data) {
            if (data.type === 'line' || data.type === 'area') {
                data.element.animate({
                    d: {
                        begin: 2000 * data.index,
                        dur: 1500,
                        from: data.path.clone().scale(1, 0).translate(0, data.chartRect.height()).stringify(),
                        to: data.path.clone().stringify(),
                        easing: Chartist.Svg.Easing.easeOutQuint
                    }
                });
            }
        });
    };

    window.showChart = showChart;

    // init charts

    $(function() {
        $('.adsimplevote-chart').each(function () {
            var id = $(this).data('id');
            var debug = $(this).data('debug');
            if (id) {
                showChart($(this).data('id'), debug);
            }
        });
    });
})(jQuery);