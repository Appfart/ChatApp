{{-- 
/**
*
* Created a new component <x-rtl.widgets._w-one/>.
* 
*/
--}}

<div class="widget-one widget">
    <div class="widget-content">
        <div class="w-numeric-value">
            <div class="w-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-message-circle">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l2.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9 8.5 8.5 0 0 1 8.5 8.5z"></path>
                </svg>
            </div>

            <div class="w-content">
                <span class="w-value">3,192</span>
                <span class="w-numeric-title">Total Activities</span>
            </div>
        </div>
        <div class="w-chart">
            <div id="total-orders"></div>
        </div>
    </div>
</div>

<script>
/** 
 * 
 * Total Orders Chart Initialization
 * 
**/
window.addEventListener("load", function(){
    try {
        let getcorkThemeObject = sessionStorage.getItem("theme");
        let getParseObject = JSON.parse(getcorkThemeObject);
        let Theme = getParseObject && getParseObject.settings.layout.darkMode ? 'dark' : 'light';

        Apex.tooltip = { theme: Theme };

        var d_2options2 = {
            chart: {
                id: 'sparkline1',
                group: 'sparklines',
                type: 'area',
                height: 290,
                sparkline: { enabled: true },
            },
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: "gradient",
                gradient: {
                    type: "vertical",
                    shadeIntensity: 1,
                    inverseColors: false,
                    opacityFrom: 0.30,
                    opacityTo: 0.05,
                    stops: [100, 100]
                }
            },
            series: [{
                name: 'Chats',
                data: [28, 40, 36, 52, 38, 60, 38, 52, 36, 40]
            }],
            labels: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
            yaxis: { min: 0 },
            grid: {
                padding: {
                    top: 125,
                    right: 0,
                    bottom: 0,
                    left: 0
                }, 
            },
            tooltip: { x: { show: false }, theme: Theme },
            colors: ['#00ab55']
        };

        var d_2C_2 = new ApexCharts(document.querySelector("#total-orders"), d_2options2);
        d_2C_2.render();

        document.querySelector('.theme-toggle').addEventListener('click', function() {
            let getParseObject = JSON.parse(sessionStorage.getItem("theme"));
            let Theme = getParseObject && getParseObject.settings.layout.darkMode ? 'dark' : 'light';

            d_2C_2.updateOptions({
                fill: {
                    type: "gradient",
                    gradient: {
                        type: "vertical",
                        shadeIntensity: 1,
                        inverseColors: false,
                        opacityFrom: Theme === 'dark' ? 0.30 : 0.45,
                        opacityTo: Theme === 'dark' ? 0.05 : 0.10,
                        stops: [100, 100]
                    }
                }
            });
        });

    } catch(e) {
        console.log("Chart Initialization Error:", e);
    }
});
</script>
