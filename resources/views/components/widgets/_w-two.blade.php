{{-- 
/**
*
* Created a new component <x-rtl.widgets._w-two/>.
* 
*/
--}}

<div class="widget-two">
    <div class="widget-content">
        <div class="w-numeric-value">
            <div class="w-content">
                <span class="w-value">{{$title}}</span>
                <span class="w-numeric-title">Msg sent and received.</span>
            </div>
            <div class="w-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-message-circle">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l2.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9 8.5 8.5 0 0 1 8.5 8.5z"></path>
                </svg>
            </div>
        </div>
        <div class="w-chart">
            <div id="daily-sales"></div>
        </div>
    </div>
</div>

<script>
/** 
 * 
 * Sales Chart Initialization
 * 
**/
window.addEventListener("load", function(){
    try {
        let getcorkThemeObject = sessionStorage.getItem("theme");
        let getParseObject = JSON.parse(getcorkThemeObject);
        let ParsedObject = getParseObject;
        let Theme = ParsedObject && ParsedObject.settings.layout.darkMode ? 'dark' : 'light';

        Apex.tooltip = {
            theme: Theme
        }

        var d_2options1 = {
            chart: {
                height: 160,
                type: 'bar',
                stacked: true,
                stackType: '100%',
                toolbar: { show: false },
            },
            dataLabels: { enabled: false },
            stroke: { show: true, width: [3, 4], curve: "smooth" },
            colors: ['#e2a03f', '#e0e6ed'],
            series: [{
                name: 'Sent/Received',
                data: [44, 55, 41, 67, 22, 43, 21]
            }, {
                name: 'Last Week',
                data: [13, 23, 20, 8, 13, 27, 33]
            }],
            xaxis: {
                labels: { show: false },
                categories: ['Sun', 'Mon', 'Tue', 'Wed', 'Thur', 'Fri', 'Sat'],
                crosshairs: { show: false }
            },
            yaxis: { show: false },
            fill: { opacity: 1 },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '25%',
                    borderRadius: 8,
                }
            },
            legend: { show: false },
            grid: {
                show: false,
                xaxis: { lines: { show: false } },
                padding: {
                    top: -20,
                    right: 0,
                    bottom: -40,
                    left: 0
                }, 
            },
            responsive: [{
                breakpoint: 575,
                options: {
                    plotOptions: {
                        bar: {
                            borderRadius: 5,
                            columnWidth: '35%'
                        }
                    },
                }
            }],
        }

        var d_2C_1 = new ApexCharts(document.querySelector("#daily-sales"), d_2options1);
        d_2C_1.render();
    
    } catch(e) {
        console.log("Chart Initialization Error:", e);
    }
});
</script>
