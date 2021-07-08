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
                    name: "#" + name, 
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
                            series[k].push([new Date(row.hour.replace(" ","T")+':00:00').getTime(), row[k]]);
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
                chart: {
                },
                title: {
                    text: 'Tweets pro ' + (granularity == "day"?"Tag":"Stunde")
                },
                subtitle: {
                    text: 'seit 01.07.2021'
                },
                xAxis: {
                    type: "datetime",
                    plotBands: [{
                        color: 'rgba(0, 0, 0, 0.2)',
                        from: new Date().getTime()-2*60*60*1000,
                        to: new Date().getTime(),
                        zIndex: -6
                    }]
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
                }],
                annotations: [{
                    labels: [{
                        point: {
                            x: new Date("2021-07-01").getTime(),
                            xAxis: 0,
                            y: 2000,
                            yAxis: 0
                        },
                        text: 'Datenerfassung: Allg. Tweets Bundestagswahl 2021'
                    }, {
                        point: {
                            x: new Date("2021-07-06").getTime(),
                            xAxis: 0,
                            y: 2000,
                            yAxis: 0
                        },
                        text: 'Datenerfassung: Partein + Spitzenkandidaten'
                    }],
                    labelOptions: {
                        backgroundColor: 'rgba(255,255,255,0.5)',
                        borderColor: 'silver'
                    }
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
                series[seriesname].push([new Date(row.hour.replace(" ","T")+':00:00').getTime(), row.tweets]);
        });

        var all_series = []; 
        for (var id in series){
            if (series.hasOwnProperty(id)){
                all_series.push({
                    type: "area",
                    name: id,
                    data: series[id],
                    stack: 0
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
                type: "datetime",
                plotBands: [{
                    color: 'rgba(0, 0, 0, 0.2)',
                    from: new Date().getTime()-2*60*60*1000,
                    to: new Date().getTime(),
                    zIndex: -6
                }]
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
                    connectNulls: true
                },
                area: {
                    stacking: 'normal'
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
                        window.open('https://twitter.com/search?q='+encodeURIComponent(event.point.name + " AND " + party)+'&src=typed_query&f=live');
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
        
        API.getView('current-domains-by-party', { party: party }).then((data) => {
            var series = { 
                name: 'Tweets', 
                type: 'wordcloud', 
                data: [],
                cursor: 'pointer',
                rotation: {
                    from: 0, 
                    to: 0,
                    orientations: 1
                },
                events: {
                    click: function (event) {
                       // window.open('https://twitter.com/search?q='+encodeURIComponent(event.point.name + " AND " + party)+'&src=typed_query&f=live');
                    }
                }
            };
            var c = 0; 
            data.forEach((e, i) => {
                if (c < 200){
                    series.data.push({ name: e.domain, weight: e.tweets });
                    c++; 
                }
            });
            Highcharts.chart('chart-party-current-domains', {
                credits: {
                    enabled: false
                },
                title: {
                    text: 'Häufig verlinkte Domains im Zusammenhang mit ' + party.toUpperCase()
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

    $("#switch-darkmode").on("change", function (){
        var darkModeEnabled = this.checked;
        if (darkModeEnabled){
            $("[data-darkmode~='text-light']").removeClass("text-dark").addClass("text-light");
            $("[data-darkmode~='text-dark']").removeClass("text-light").addClass("text-dark");

            $("[data-darkmode~='bg-light']").removeClass("bg-dark").addClass("bg-light");
            $("[data-darkmode~='bg-dark']").removeClass("bg-light").addClass("bg-dark");
        } else {
            $("[data-darkmode~='text-light']").removeClass("text-light").addClass("text-dark");
            $("[data-darkmode~='text-dark']").removeClass("text-dark").addClass("text-light");

            $("[data-darkmode~='bg-light']").removeClass("bg-light").addClass("bg-dark");
            $("[data-darkmode~='bg-dark']").removeClass("bg-dark").addClass("bg-light");
        }
    });

    /*API.getView("nodegraph-test2").then((data) => {
        console.log(data);
       // network-test
       // Add the nodes option through an event call. We want to start with the parent
        // item and apply separate colors to each child element, then the same color to
        // grandchildren.
        Highcharts.addEvent(
            Highcharts.Series,
            'afterSetOptions',
            function (e) {
                var colors = Highcharts.getOptions().colors,
                    i = 0,
                    nodes = {};

                if (
                    this instanceof Highcharts.seriesTypes.networkgraph &&
                    e.options.id === 'lang-tree'
                ) {
                    e.options.data.forEach(function (link) {

                        if (link[0] === 'Proto Indo-European') {
                            nodes['Proto Indo-European'] = {
                                id: 'Proto Indo-European',
                                marker: {
                                    radius: 20
                                }
                            };
                            nodes[link[1]] = {
                                id: link[1],
                                marker: {
                                    radius: 10
                                },
                                color: colors[i++]
                            };
                        } else if (nodes[link[0]] && nodes[link[0]].color) {
                            nodes[link[1]] = {
                                id: link[1],
                                color: nodes[link[0]].color
                            };
                        }
                    });

                    e.options.nodes = Object.keys(nodes).map(function (id) {
                        return nodes[id];
                    });
                }
            }
        );

        Highcharts.chart('network-test', {
            chart: {
                type: 'networkgraph',
                height: '100%'
            },
            title: {
                text: 'The Indo-European Language Tree'
            },
            subtitle: {
                text: 'A Force-Directed Network Graph in Highcharts'
            },
            plotOptions: {
                networkgraph: {
                    keys: ['from', 'to'],
                    layoutAlgorithm: {
                        enableSimulation: true,
                        friction: -0.9
                    }
                }
            },
            series: [{
                dataLabels: {
                    enabled: true,
                    linkFormat: ''
                },
                id: 'lang-tree',
                data: data
            }]
        });
 
    });*/
})(); 