(function(){
    var StatsAPI = function (){
        this.getView = function (view){
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: "/stats/api.php",
                    data: { view: view },
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
    API.getView('tweets-per-hour').then((data) => {
        console.log(data); 
        var series = {}; 
        data.forEach((row) => {
            for (var k in row){
                if (row.hasOwnProperty(k) && k != "hour"){
                    if (!series[k]) series[k] = []; 
                    series[k].push([new Date(row.hour+':00:00').getTime(), row[k]]);
                }
            }
        })

        Highcharts.chart('chart-tweets_per_day', {
            credits: {
                enabled: false
            },
            title: {
                text: 'Tweets pro Stunde'
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
})(); 