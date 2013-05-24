<?php
function getDatFilesList() {
    return glob('data/*.dat');
}
?>

<html>
<head>
<title>Benchy - ApacheBench Visualization</title>
<script src="http://code.jquery.com/jquery-1.9.1.min.js"></script>
<script src="http://code.highcharts.com/highcharts.js"></script>
<script src="http://code.highcharts.com/highcharts-more.js"></script>
<script src="http://code.highcharts.com/modules/exporting.js"></script>
<style type="text/css">
* {
    font-family: 'Lucida Grande', 'Lucida Sans Unicode', Arial, Helvetica, sans-serif;
}
label {
    display: block;
    font-size: .8em;
}


#header h1 {
    font-size: 2.4em;
    color: #274B6D;
    margin-top: 10px;
    margin-bottom: 10px;
    width: 300px;
}

#header {
    color: gray;
    float: left;
    clear: both;
    padding-bottom: 20px;
    border-bottom: 4px solid #DDD;
}


h2 {
    font-size: 1em;
    color: #274B6D;
}

#metrics, #datasets {
    float: left;
    clear: both;
}

.chart_container {
    height: 350px; 
    margin-left: 400px;
    width: 800px;
    padding-top: 40px;
} 
</style>
<script type="text/javascript">
var metrics = [
    {name: "Throughput"         ,unit: "requests per second"        ,metric: "requests_per_second"      , show: true  },
    {name: "Time per request"   ,unit: "milliseconds"               ,metric: "time_per_request"         , show: true  },
    {name: "Time per request (concurrent)" ,unit: "milliseconds"    ,metric: "time_per_request_conc"    , show: false },
    {name: "Total test time"    ,unit: "seconds"                    ,metric: "time_taken_for_tests"     , show: false },
    {name: "Transfer rate"      ,unit: "KB/second"                  ,metric: "transfer_rate"            , show: false },
    {name: "Complete requests"  ,unit: null                         ,metric: "complete_requests"        , show: false },
    {name: "Failed requests"    ,unit: null                         ,metric: "failed_requests"          , show: false },
    {name: "Write errors"       ,unit: null                         ,metric: "write_errors"             , show: false },
    {name: "Document length"    ,unit: "bytes"                      ,metric: "document_length"          , show: false },
    {name: "Total transferred"  ,unit: "bytes"                      ,metric: "total_transferred"        , show: false },
    {name: "HTML transferred"   ,unit: "bytes"                      ,metric: "html_transferred"         , show: false }
//    {name:                      ,unit:                              ,metric:                            , show: false },
];

var files = <?php echo json_encode(getDatFilesList()) ?>;
var toLoad = files.length;

</script>
<script type="text/javascript">
var json_data = {};

$(function() {
    fetchDataFiles();
});

function afterLoading() {
    $('#datasets').html('<h2>Benchmark runs</h2>');
    $('#metrics').html('<h2>Metrics</h2>');
    createMetricOptions();
    createDataFileOptions();
    createChartContainers();
    $('input:checkbox').change(refreshPlots);
    refreshPlots();
}

function fetchDataFiles() {
    var funcs = [];
    function createFunc(i) {
        return function(data, textStatus, jqXHR) {
            json_data[files[i]] = data;
            toLoad--;
            if (!toLoad) {
                afterLoading();
            }
        };
    };
    for (var file in files) {
        $.getJSON(files[file], createFunc(file));
    }
}

var _metrics_dict = {}
function getMetric(metric_name) {
    if (_metrics_dict.length != metrics.length) {
        for(m in metrics) {
            metric = metrics[m];
            _metrics_dict[metric.metric] = metric;
        }
    }
    return _metrics_dict[metric_name];
}

function createDataFileOptions() {
    for (var f in files) {
        file = json_data[files[f]];
        file_label = file.config.comment;
        file_id = f;
        $('#datasets').append($('<label for="file_' + file_id + '">' +
                               '<input type="checkbox" name="file_' + 
                               file_id + 
                               '" id="file_checkbox_' + 
                               file_id + 
                               '" checked></input>' +
                               file_label + 
                               '</label>'));

    }
}

function createMetricOptions() {
    for (var m in metrics) {
        metric = metrics[m];
        metric_id = metric.metric;
        metric_label = metric.name;
        if (metric.unit) {
            metric_label += ' (' + metric.unit + ')';
        }
        if (metric.show) {
            metric_checked = ' checked';
        } else {
            metric_checked = '';
        }
        $('#metrics').append($('<label for="' + metric_id + '">' +
                               '<input type="checkbox" name="' + 
                               metric.metric + 
                               '" id="metric_checkbox_' + 
                               metric_id + 
                               '"'+ 
                               metric_checked + 
                               '></input>' +
                               metric_label + 
                               '</label>'));
    }
}

function createChartContainers() {
    for (var m in metrics) {
        metric = metrics[m];
        $('#charts').append($('<div class="chart_container" style="display: none;" id="' + 'chart_' + metric.metric + '"></div>'));
    }
}

function generateSeries(series_name) {
    var series = [];
    for (var file in files) {
        file_id = '#file_checkbox_' + file;
        if ($(file_id).prop('checked')) {
            series.push({
                name: json_data[files[file]].config.comment,
                data: json_data[files[file]].zipped_results[series_name]
            });
        }
    }
    return series;
}

function refreshPlots() {
    for (var m in metrics) {
        metric = metrics[m];
        metric_id = '#metric_checkbox_' + metric.metric;
        plot_container_id = '#chart_' + metric.metric;
        if ($(metric_id).prop('checked')) {
            $(plot_container_id).slideDown();
            plotSeries(metric.metric);
        } else {
            $(plot_container_id).slideUp();
        }
    }
}

function plotSeries(series_name) {
    var container_id = 'chart_' + series_name;
    $('#' + container_id).show();
    
    $('#' + container_id).highcharts({
        chart: {
            type: 'column',
            marginRight: 250,
            marginBottom: 75
        },

        title: {
            text: getMetric(series_name).name,
            x: -80 //center
        },
        subtitle: {
            text: getMetric(series_name).unit,
            x: -80 //center
        },

        legend: {
            layout: 'vertical',
            align: 'right',
            verticalAlign: 'top',
            x: -50,
            y: 40,
//            floating: true,
            borderWidth: 1,
            backgroundColor: '#FFFFFF',
            shadow: true
        },
        credits: {
            enabled: false
        },

        xAxis: {
            categories: json_data[files[0]].zipped_results.concurrency_level,
            title: {
                text: "Concurrency level"
            }},
        yAxis: {
            title: {
                text: getMetric(series_name).unit
            }},
        series: generateSeries(series_name)
    });
}
</script>
</head>
<body>
<div id="header">
<h1>Benchy</h1>
A simple apachebench visualization tool.
</div>
<div id="metrics">
</div>
<div id="datasets">
<h2>Loading...</h2>
</div>
<div id="charts">
</div>
</body>
</html>
