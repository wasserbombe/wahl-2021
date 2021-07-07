(function(){
    var StatsAPI = function (){
        this.getView = function (view, data){
            var body = data || {};
            body.view = view; 
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: "/stats/api.php",
                    data: body,
                    dataType: 'json',
                    method: 'POST',
                    success: (data) => {
                        resolve(data.data); 
                    },
                    error: (data) => {
                        reject(data); 
                    }
                });
            }); 
        }
    }
    var API = new StatsAPI(); 

    API.getView('hashtags-over-time').then((data) => {
        var colors = Highcharts.getOptions().colors;
        var series = {}; 
        data.forEach((e) => {
            if (!series[e.hashtag]) series[e.hashtag] = []; 
            series[e.hashtag].push([new Date(e.date).getTime(), e.tweets]);
        });
        var all_series = []; 
        for (var name in series){
            if (series.hasOwnProperty(name)){
                all_series.push({
                    name: name, 
                    data: series[name],
                    cursor: 'pointer',
                    events: {
                        click: function (event) {
                            window.open('https://twitter.com/search?q='+encodeURIComponent(event.point.series.name)+'&src=typed_query&f=live');
                        }
                    }
                });
            }
        }

        Highcharts.chart('chart-hashtags-over-time', {
            credits: {
                enabled: false
            },
            chart: {
                type: 'streamgraph',
                marginBottom: 30,
                zoomType: 'x'
            },
            title: {
                floating: true,
                // align: 'left',
                text: ''
            },
            subtitle: {
                floating: true,
                // align: 'left',
                y: 30,
                text: ''
            },
            xAxis: {
                maxPadding: 0,
                type: 'datetime',
                crosshair: true,
                labels: {
                    align: 'left',
                    reserveSpace: false,
                    rotation: 270
                },
                lineWidth: 0,
                margin: 20,
                tickWidth: 0
            },

            yAxis: {
                visible: false,
                startOnTick: false,
                endOnTick: false
            },

            legend: {
                enabled: false
            },

            plotOptions: {
                series: {
                    label: {
                        minFontSize: 5,
                        maxFontSize: 15,
                        style: {
                            color: 'rgba(255,255,255,0.75)'
                        }
                    }
                }
            },

            // Data parsed with olympic-medals.node.js
            series: all_series
        });
    }); 

    API.getView('current-hashtags').then((data) => {
        var series = { 
            name: 'Hashtag', 
            type: 'wordcloud', 
            data: [],
            cursor: 'pointer',
            events: {
                click: function (event) {
                    window.open('https://twitter.com/search?q='+encodeURIComponent(event.point.name)+'&src=typed_query&f=live');
                }
            }
        };
        var c = 0; 
        data.forEach((e, i) => {
            if (c < 200){
                series.data.push({ name: '#' + e.hashtag, weight: e.tweets });
                c++; 
            }
        });
        Highcharts.chart('chart-current_hashtags', {
            credits: {
                enabled: false
            },
            title: {
                text: 'Häufig genutzte Hashtags'
            },
            subtitle: {
                text: '(letzte 7 Tage)'
            },
            series: [
                series
            ]
        });
    });

    var loadGeneralData = () => {
        var granularity = $("#granularity-selection a.active").data("granularity"); 
        API.getView('tweets-per-'+granularity).then((data) => {
            var series = {}; 
            data.forEach((row) => {
                for (var k in row){
                    if (granularity == "hour"){
                        if (row.hasOwnProperty(k) && k != "hour"){
                            if (!series[k]) series[k] = []; 
                            series[k].push([new Date(row.hour+':00:00').getTime(), row[k]]);
                        }
                    } else {
                        if (row.hasOwnProperty(k) && k != "date"){
                            if (!series[k]) series[k] = []; 
                            series[k].push([new Date(row.date).getTime(), row[k]]);
                        }
                    }
                }
            })
    
            Highcharts.chart('chart-tweets_per_day', {
                credits: {
                    enabled: false
                },
                title: {
                    text: 'Tweets pro ' + (granularity == "day"?"Tag":"Stunde")
                },
                subtitle: {
                    text: 'seit 01.07.2021'
                },
                xAxis: {
                    type: "datetime"
                },
                yAxis: [{
                    title: {
                        "text": "Anzahl Tweets"
                    }
                },{
                    opposite: true,
                    title: {
                        "text": "Geschätzte Reichweite"
                    }
                }],
                tooltip: {
                    shared: true
                },
                series: [{
                    "name": "Tweets",
                    "data": series["tweets"],
                    "type": "line"
                },{
                    "name": "Geschätzte Reichweite",
                    "data": series["reach"],
                    "type": "column",
                    "yAxis": 1
                }]
            });
        });
    };

    $("#granularity-selection a").on("click", function (){
        var granularity = $(this).data("granularity");
        $("#granularity-selection a").attr("class", "btn btn-outline-primary");
        $("#granularity-selection a[data-granularity=" + granularity + "]").removeClass("btn-outline-primary").addClass("btn-primary").addClass("active");
        loadGeneralData(); 
    });
    loadGeneralData(); 

    
    API.getView('tweets-per-hour-per-search').then((data) => {
        var series = {}; 
        data.forEach((row) => {
            var seriesname = row.id + ' - ' + row.name; 
            if (!series[seriesname]) series[seriesname] = []; 
                series[seriesname].push([new Date(row.hour+':00:00').getTime(), row.tweets]);
        });

        var all_series = []; 
        for (var id in series){
            if (series.hasOwnProperty(id)){
                all_series.push({
                    name: id,
                    data: series[id]
                });
            }
        }

        Highcharts.chart('tweets-per-hour-per-search', {
            credits: {
                enabled: false
            },
            title: {
                text: 'Tweets pro Stunde pro Suchanfrage'
            },
            subtitle: {
                text: 'seit 01.07.2021'
            },
            xAxis: {
                type: "datetime"
            },
            yAxis: [{
                title: {
                    "text": "Anzahl Tweets"
                }
            }],
            legend: {
                align: "right",
                maxHeight: 100
            },
            plotOptions: {
                series: {
                    connectNulls: false
                }
            },            
            tooltip: {
                //shared: true
            },
            series: all_series
        });
    });

    var loadPartyRelatedData = () => {
        var party = $("#party-selection a.active").data("party");
        console.log("Loading data for "+party+"...");

        API.getView('current-hashtags-by-party', { party: party }).then((data) => {
            var series = { 
                name: 'Hashtag', 
                type: 'wordcloud', 
                data: [],
                cursor: 'pointer',
                events: {
                    click: function (event) {
                        window.open('https://twitter.com/search?q='+encodeURIComponent(event.point.name)+'&src=typed_query&f=live');
                    }
                }
            };
            var c = 0; 
            data.forEach((e, i) => {
                if (c < 200){
                    series.data.push({ name: '#' + e.hashtag, weight: e.tweets });
                    c++; 
                }
            });
            Highcharts.chart('chart-party-current-hashtags', {
                credits: {
                    enabled: false
                },
                title: {
                    text: 'Häufig genutzte Hashtags im Zusammenhang mit ' + party.toUpperCase()
                },
                subtitle: {
                    text: '(letzte 7 Tage)'
                },
                series: [
                    series
                ]
            });
        });
        API.getView('current-hashtags-by-party-candidate', { party: party }).then((data) => {
            var series = { 
                name: 'Hashtag', 
                type: 'wordcloud', 
                data: [],
                cursor: 'pointer',
                events: {
                    click: function (event) {
                        window.open('https://twitter.com/search?q='+encodeURIComponent(event.point.name)+'&src=typed_query&f=live');
                    }
                }
            };
            var c = 0; 
            data.forEach((e, i) => {
                if (c < 200){
                    series.data.push({ name: '#' + e.hashtag, weight: e.tweets });
                    c++; 
                }
            });
            Highcharts.chart('chart-party-current-hashtags-candidate', {
                credits: {
                    enabled: false
                },
                title: {
                    text: 'Häufig genutzte Hashtags von Kandidat:innen von ' + party.toUpperCase()
                },
                subtitle: {
                    text: '(letzte 7 Tage)'
                },
                series: [
                    series
                ]
            });
        });
        API.getView('current-hashtags-by-party-account', { party: party }).then((data) => {
            var series = { 
                name: 'Hashtag', 
                type: 'wordcloud', 
                data: [],
                cursor: 'pointer',
                events: {
                    click: function (event) {
                        window.open('https://twitter.com/search?q='+encodeURIComponent(event.point.name)+'&src=typed_query&f=live');
                    }
                }
            };
            var c = 0; 
            data.forEach((e, i) => {
                if (c < 200){
                    series.data.push({ name: '#' + e.hashtag, weight: e.tweets });
                    c++; 
                }
            });
            Highcharts.chart('chart-party-current-hashtags-account', {
                credits: {
                    enabled: false
                },
                title: {
                    text: 'Häufig genutzte Hashtags von Partei-Accounts der ' + party.toUpperCase()
                },
                subtitle: {
                    text: '(letzte 7 Tage)'
                },
                series: [
                    series
                ]
            });
        });
    };

    $("#party-selection a").on("click", function (){
        var party = $(this).data("party");
        $("#party-selection a").attr("class", "btn btn-outline-primary");
        $("#party-selection a[data-party=" + party + "]").removeClass("btn-outline-primary").addClass("btn-primary").addClass("active");
        loadPartyRelatedData(); 
    });
    loadPartyRelatedData(); 
})(); 