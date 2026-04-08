<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

ini_set('max_execution_time', 0);

require_once 'SurveyTicket.php';
require_once 'TicketDb.php';

$surveyTicket = new SurveyTicket();

//$surveyTicket1 = new TicketDb();
//$surveyTicket1->pickData(100);

//$cacheFile = __DIR__ . '/cache_pickData.txt';
//$cacheTime = 1000;
//
//$run = true;
//
//if (file_exists($cacheFile)) {
//    $lastRun = (int) file_get_contents($cacheFile);
//
//    if (time() - $lastRun < $cacheTime) {
//        $run = false;
//    }
//}
//
//if ($run) {
//    $surveyTicket1 = new TicketDb();
//    $surveyTicket1->pickData(100);
//
//    file_put_contents($cacheFile, time());
//}

?>
<header>
    <title>Suport ticket Reports</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
</header>
<body>
<div class="container" style="padding-top: 20px; margin-bottom: 150px">
    <div class="container-header" style="margin-bottom: 50px">
        <div class="pull-left">
            <!--            <a href="/avada">Avada Support</a>-->
            <h1 style="margin-top: 0">Mageplaza Support Reports</h1>
        </div>
        <form class="form-inline pull-right" method="get">
            <div class="form-group">
                <label>From</label>
                <input type="date" name="from-date" value="<?= $surveyTicket->getData('from') ?>">
            </div>
            <div class="form-group">
                <label>To</label>
                <input type="date" name="to-date" value="<?= $surveyTicket->getData('to') ?>">
            </div>
            <button type="submit" class="btn btn-warning">Submit</button>
        </form>
        <div style="clear: both"></div>
    </div>
    <div class="container-body">
        <?php $surveyTicket->collectTicketData(); ?>

        <script>
            var totalMagento = [],
                totalShopify = [],
                totalShiftAll = [];

        </script>
        <?php if ($surveyTicket->getData('agent') || $surveyTicket->getData('extension')): ?>
                <a href='http://kpi.com/' id='agent-table-back'>Back</a>
                <h2>Detail for <b><?= $surveyTicket->getData('agent') ?: $surveyTicket->getData('extension') ?></b></h2>
<!--                <div id='agent-table' style='width: 100%; height: 800px;'></div>-->
                <div id='pie-chart' style='width: 100%; height: 500px;'></div>
                <div id="column-chart" style="width: 100%; height: 500px;"></div>


                <script type="text/javascript">
                    google.charts.load('current', {'packages': ['corechart']});
                    google.charts.setOnLoadCallback(() => {
                        var agent = "<?= $surveyTicket->getData('agent') ?>";
                        drawPieChart(agent);
                        drawColumnChart(agent);
                    });

                    function drawPieChart(agent) {
                        var ticketData = <?= json_encode($surveyTicket->renderCloseTickets()) ?>,
                            totalShift = [];

                        buildShiftPointRows(totalShift);
                        var shiftPoint = ((agentName) => {
                            var item = totalShift.find(d => d.agent === agentName);
                            return item ? item.total : 0;
                        })(agent);

                        var magentoPoint = ticketData[agent].total,
                            shopifyPoint = ticketData[agent].shopify_total;

                        var goal = 6000;

                        var used = shiftPoint + magentoPoint + shopifyPoint;
                        var remaining = Math.max(goal - used, 0);

                        var chartData = [
                            ['Type', 'Point'],
                            ['Shift', shiftPoint],
                            ['Magento', magentoPoint],
                            ['Shopify', shopifyPoint],
                            ['Remaining', remaining]
                        ];

                        var data = google.visualization.arrayToDataTable(chartData);

                        var options = {
                            title: 'Performance of ' + agent + ' / Goal: ' + goal,
                            pieHole: 0, // pie thường
                            width: '100%',
                            height: 500,
                            colors: ['#4CAF50', '#2196F3', '#FFC107', '#9E9E9E'],
                            legend: { position: 'right' }
                        };

                        var chart = new google.visualization.PieChart(
                            document.getElementById('pie-chart')
                        );

                        chart.draw(data, options);

                        window.addEventListener('resize', () => drawPieChart(agent));
                    }

                    function drawColumnChart(agent) {
                        var ticketData = <?= json_encode($surveyTicket->renderCloseTickets()) ?>,
                            totalShift = [];

                        buildShiftPointRows(totalShift);
                        var shiftPoint = ((agentName) => {
                            var item = totalShift.find(d => d.agent === agentName);
                            return item ? item.total : 0;
                        })(agent);

                        var magentoPoint = ticketData[agent].total,
                            shopifyPoint = ticketData[agent].shopify_total;

                        var goal = 6000;

                        var chartData = [
                            ['Type', 'Goal', 'Shift', 'Magento', 'Shopify'],

                            // Goal column
                            ['Goal', goal, 0, 0, 0],

                            // Achieved column (stacked)
                            ['Achieved', 0, shiftPoint, magentoPoint, shopifyPoint]
                        ];

                        var data = google.visualization.arrayToDataTable(chartData);

                        var options = {
                            title: 'KPI of ' + agent,
                            isStacked: true,
                            width: '100%',
                            height: 500,
                            colors: ['#9E9E9E', '#4CAF50', '#2196F3', '#FFC107'],
                            // Goal | Shift | Magento | Shopify

                            series: {
                                0: { targetAxisIndex: 0 }, // Goal
                                1: { targetAxisIndex: 0 },
                                2: { targetAxisIndex: 0 },
                                3: { targetAxisIndex: 0 }
                            },

                            legend: { position: 'right' },
                            vAxis: { title: 'Points' }
                        };

                        var chart = new google.visualization.ColumnChart(
                            document.getElementById('column-chart')
                        );

                        chart.draw(data, options);SS

                        window.addEventListener('resize', () => drawColumnChart(agent));
                    }
                </script>
            <?php else: ?>
                <h2>Leader Board</h2>
                <div id='closedTicketTable' style='width: 100%; height: 500px; width: 1350px'></div>

                <h3>Full Point</h3>
                <div id="fullPoint" style="width:100%; height:300px;"></div>

                <h3>Shopify Detail</h3>
                <div id="shopifyTable" style="width:100%; height:300px;"></div>

                <h3>Steve Extensions Count </h3>
                <div id="extensionsCount" style="width:100%; height:300px;"></div>

                <h3>Shift Point</h3>
                <div id="shiftPoint" style="width:100%; height:300px;"></div>

                <h3>Shopify Schedule Table</h3>
                <div id="scheduleTable" style="width:100%;"></div>




    <!--            <h2>Supporter Ticket Report (Real Data)</h2>-->
    <!--            <div id='realClosedTicketTable' style='width: 100%; height: 500px;'></div>-->

<!--                <h2>Customer Success Ticket Report</h2>-->
<!--                <div id='csClosedTicketTable' style='width: 100%; height: 200px;'></div>-->

<!--                --><?php //= $surveyTicket->renderAgentRate() ?>

<!--                <h2>Ticket by Extensions</h2>-->
<!--                <div id='extensionTicketTable' style='width: 100%; height: 900px;'></div>-->

<!--                <h2>Ares: Ticket by Extensions</h2>-->
<!--                <div id='aresExtensionTicketTable' style='width: 100%; height: 900px;'></div>-->
<!---->
<!--                <h2>Artemis: Ticket by Extensions</h2>-->
<!--                <div id='artemisExtensionTicketTable' style='width: 100%; height: 900px;'></div>-->
<!---->
<!--                <h2>Poseidon: Ticket by Extensions</h2>-->
<!--                <div id='poseidonExtensionTicketTable' style='width: 100%; height: 900px;'></div>-->
<!---->
<!--                <h2>Uranus: Ticket by Extensions</h2>-->
<!--                <div id='uranusExtensionTicketTable' style='width: 100%; height: 900px;'></div>-->
<!---->
<!--                <h2>Other: Ticket by Extensions</h2>-->
<!--                <div id='otherExtensionTicketTable' style='width: 100%; height: 900px;'></div>-->

<!--                --><?php //= $surveyTicket->renderFeed() ?>

<!--                <h2>List All Tickets</h2>-->
<!--                <div id='allTickets' style='width: 100%; height: 900px;'></div>-->
<!---->
<!--                <h2>List All Surveys</h2>-->
<!--                <div id='allSurveys' style='width: 100%; height: 900px;'></div>-->

                <script type="text/javascript">
                    google.charts.load('current', {'packages': ['corechart', 'table']});
                    google.charts.setOnLoadCallback(drawClosedTicket);
                    google.charts.setOnLoadCallback(drawClosedShopifyTicket);
                    google.charts.setOnLoadCallback(drawSteveExCount);
                    google.charts.setOnLoadCallback(drawScheduleTable);
                    google.charts.setOnLoadCallback(drawShiftPoint);
                    google.charts.setOnLoadCallback(drawFullPoint);


                    // google.charts.setOnLoadCallback(drawRealClosedTicket);

                   google.charts.setOnLoadCallback(drawCsClosedTicket);
                   google.charts.setOnLoadCallback(drawExtensionTicket);
                    // google.charts.setOnLoadCallback(drawAresExtensionTicket);
                    // google.charts.setOnLoadCallback(drawArtemisExtensionTicket);
                    // google.charts.setOnLoadCallback(drawUranusExtensionTicket);
                    // google.charts.setOnLoadCallback(drawPoseidonExtensionTicket);
                    // google.charts.setOnLoadCallback(drawOtherExtensionTicket);
                    google.charts.setOnLoadCallback(allTickets);
                   google.charts.setOnLoadCallback(allSurveys);

                   function drawFullPoint() {
                       var total = [];
                       var shopifyTs = <?= json_encode($surveyTicket->getShopifyTs()) ?>;
                       debugger

                       totalMagento.forEach(function(item) {
                           if (!total[item.agent]) {
                               total[item.agent] = item.total;
                           } else {
                               total[item.agent] += item.total;
                           }
                       });

                       totalShopify.forEach(function(item) {
                           if (!total[item.agent]) {
                               total[item.agent] = item.total;
                           } else {
                               total[item.agent] += item.total;
                           }
                       });

                       totalShiftAll.forEach(function(item) {
                           if (!total[item.agent]) {
                               total[item.agent] = item.total;
                           } else {
                               total[item.agent] += item.total;
                           }
                       });

                       var rowData = Object.keys(total).map(function (agent) {

                           return [agent , total[agent]];

                       });
                       var data = new google.visualization.DataTable();

                       data.addColumn('string', 'Agent');
                       data.addColumn('number', 'Full Kpi');

                       data.addRows(rowData);

                       var table = new google.visualization.Table(
                           document.getElementById('fullPoint')
                       );

                       table.draw(data, {
                           width: '100%',
                           height: '100%',
                           showRowNumber: true,
                           sortAscending: true
                       });

                   }

                    function drawClosedTicket(agent) {

                        if(agent === undefined){
                            agent = true;
                        }

                        if(agent) {
                            var closeData = <?= json_encode($surveyTicket->renderCloseTickets()) ?>;
                        } else {
                            var closeData = <?= json_encode($surveyTicket->renderCloseTickets(false)) ?>;
                        }
                        var rowData = Object.keys(closeData).map(function (key) {
                            var agentUrl = window.location.search;
                            agentUrl += ((agentUrl.indexOf('?') !== -1) ? '&' : '?') + 'agent=' + key + (!agent ? '&cs=1' : '');
                            var agentHtml = '<a href="' + agentUrl + '">' + key + '</a>';

                            totalMagento.push({
                                agent: key,
                                total: closeData[key]['total']
                            });

                            return [agentHtml, closeData[key]['question'], closeData[key]['issue'], closeData[key]['problem'],
                                closeData[key]['simple'], closeData[key]['simple-medium'], closeData[key]['medium'],
                                closeData[key]['medium-complex'], closeData[key]['complex'],
                                closeData[key]['hard_problem_ticket'], closeData[key]['hard_problem_points'],
                                closeData[key]['holiday_ticket'], closeData[key]['holiday_points'],
                                closeData[key]['good_rate_ticket'], closeData[key]['good_rate_points'],
                                closeData[key]['total']];
                        });
                        var options = {
                            allowHtml: true,
                            width: '100%',
                            height: '100%',
                            sortAscending: true
                        };
                        var data = new google.visualization.DataTable();
                        data.addColumn('string', agent ? 'Agent' : 'CS Agent');
                        data.addColumn('number', 'Ques');
                        data.addColumn('number', 'Issue');
                        data.addColumn('number', 'Problem');
                        data.addColumn('number', 'Simple');
                        data.addColumn('number', 'S-Medium');
                        data.addColumn('number', 'Medium');
                        data.addColumn('number', 'M-Complex');
                        data.addColumn('number', 'Complex');
                        data.addColumn('number', 'Hard');
                        data.addColumn('number', 'Hard Points');
                        data.addColumn('number', 'Holiday');
                        data.addColumn('number', 'Holiday Points');
                        data.addColumn('number', 'Good');
                        data.addColumn('number', 'Good Points');
                        data.addColumn('number', 'Total');
                        data.addRows(rowData);

                        if(agent) {
                            options['showRowNumber'] = true;
                            // data.addRow(['TOTAL', getSum(data, 1), getSum(data, 2), getSum(data, 3), getSum(data, 4), getSum(data, 5), getSum(data, 6), getSum(data, 7), getSum(data, 8)]);
                        }

                        var table = new google.visualization.Table(document.getElementById(agent ? 'closedTicketTable' : 'csClosedTicketTable'));
                        table.draw(data, options);
                        window.addEventListener('resize', function () {
                            drawClosedTicket(agent);
                        })
                    }

                    function drawClosedShopifyTicket(agent) {
                        if (agent === undefined) {
                            agent = true;
                        }

                        var closeData = agent
                            ? <?= json_encode($surveyTicket->renderCloseTickets()) ?>
                            : <?= json_encode($surveyTicket->renderCloseTickets(false)) ?>;

                        var shopifyTs = <?= json_encode($surveyTicket->getShopifyTs()) ?>;

                        var rows = Object.keys(closeData)
                            .filter(function (key) {
                                return shopifyTs.includes(key);
                            })
                            .map(function (key) {
                                var agentUrl = window.location.search;
                                agentUrl += ((agentUrl.indexOf('?') !== -1) ? '&' : '?') + 'agent=' + key + (!agent ? '&cs=1' : '');

                                var agentHtml = '<a href="' + agentUrl + '">' + key + '</a>';

                                totalShopify.push({
                                    agent: key,
                                    total: closeData[key]['shopify_total']
                                });

                                return [
                                    agentHtml,
                                    closeData[key]['shopify_question'],
                                    closeData[key]['shopify_question_ah'],
                                    closeData[key]['shopify_issue'],
                                    closeData[key]['shopify_issue_ah'],
                                    closeData[key]['shopify_problem'],
                                    closeData[key]['shopify_problem_ah'],
                                    closeData[key]['shopify_total']
                                ];
                            });

                        var data = new google.visualization.DataTable();

                        data.addColumn('string', 'Agent');
                        data.addColumn('number', 'Question');
                        data.addColumn('number', 'Question Night');
                        data.addColumn('number', 'Issue');
                        data.addColumn('number', 'Issue Night');
                        data.addColumn('number', 'Problem');
                        data.addColumn('number', 'Problem Night');
                        data.addColumn('number', 'Shopify Total');

                        data.addRows(rows);

                        var table = new google.visualization.Table(
                            document.getElementById('shopifyTable')
                        );

                        table.draw(data, {
                            allowHtml: true,
                            width: '100%',
                            height: '100%',
                            sortAscending: true,
                            showRowNumber: true
                        });
                    }

                    function drawShiftPoint() {
                        var totalShift = [];

                        var rows = buildShiftPointRows(totalShift);

                        var data = new google.visualization.DataTable();

                        data.addColumn('string', 'Date');
                        data.addColumn('number', 'Night');
                        data.addColumn('number', 'Weekend');
                        data.addColumn('number', 'Point');

                        data.addRows(rows);

                        var table = new google.visualization.Table(
                            document.getElementById('shiftPoint')
                        );

                        table.draw(data, {
                            width: '100%',
                            height: '100%',
                            showRowNumber: true,
                            sortAscending: true
                        });
                    }




                    function drawScheduleTable() {

                        var scheduleData = <?= json_encode($surveyTicket->getGoogleSheetData('1c3qnPDDf1UsbfJt96fQGRHIDhALxjl2aCA2q3qFFpKM', 'phan_ca')) ?>;

                        const today = new Date();

                        const formattedDate = `${today.getMonth() + 1}/${today.getDate()}/${today.getFullYear()}`;

                        var rows = scheduleData.map(function (item) {
                            var isToday = false;
                            if (item.date === formattedDate) {
                                isToday = true
                            }
                            return [
                                item.date,
                                item.day,
                                item.morning || '',
                                item.night || '',
                                isToday ? '✅' : ''
                            ];
                        });

                        var data = new google.visualization.DataTable();

                        data.addColumn('string', 'Date');
                        data.addColumn('string', 'Day');
                        data.addColumn('string', 'Morning');
                        data.addColumn('string', 'Night');
                        data.addColumn('string', 'Today');


                        data.addRows(rows);

                        var table = new google.visualization.Table(
                            document.getElementById('scheduleTable')
                        );

                        table.draw(data, {
                            width: '100%',
                            height: '100%',
                            showRowNumber: true,
                            sortAscending: true
                        });
                    }

                    function drawSteveExCount() {

                        var closeData = <?= json_encode($surveyTicket->renderSteveExtensionTickets()) ?>;

                        var rows = Object.keys(closeData).map(function (key) {
                            var item = closeData[key];
                            var idsHtml = item.ids.map(function(id) {
                                return '<a href="https://mageplaza.freshdesk.com/helpdesk/tickets/' + id + '" target="_blank">' + id + '</a>';
                            }).join(', ');

                            return [
                                key,
                                item.count,
                                idsHtml
                            ];
                        });

                        var data = new google.visualization.DataTable();

                        data.addColumn('string', 'Extension');
                        data.addColumn('number', 'Count');
                        data.addColumn('string', 'Ticket Ids');

                        data.addRows(rows);

                        var table = new google.visualization.Table(
                            document.getElementById('extensionsCount')
                        );

                        table.draw(data, {
                            allowHtml: true,
                            width: '100%',
                            height: '100%',
                            sortAscending: true,
                            showRowNumber: true
                        });
                    }

                    function drawRealClosedTicket() {
                        var closeData = <?= json_encode($surveyTicket->renderRealCloseTickets()) ?>;
                        var rowData = Object.keys(closeData).map(function (key) {
                            return [key, Math.round(closeData[key]['question']), Math.round(closeData[key]['issue']), Math.round(closeData[key]['problem']), closeData[key]['refund']];
                        });
                        var options = {
                            allowHtml: true,
                            width: '100%',
                            height: '100%',
                            sortAscending: true
                        };
                        var data = new google.visualization.DataTable();
                        data.addColumn('string', 'Agent');
                        data.addColumn('number', 'Question');
                        data.addColumn('number', 'Issue');
                        data.addColumn('number', 'Problem');
                        data.addColumn('number', 'Refund');
                        data.addRows(rowData);

                        data.addRow(['TOTAL', getSum(data, 1), getSum(data, 2), getSum(data, 3), getSum(data, 4)]);

                        var table = new google.visualization.Table(document.getElementById('realClosedTicketTable'));
                        table.draw(data, options);
                        window.addEventListener('resize', function () {
                            drawRealClosedTicket();
                        })
                    }

                    function drawCsClosedTicket() {
                        drawClosedTicket(false);
                    }

                    function drawExtensionTicket() {
                        var closeData = <?= json_encode($surveyTicket->renderExtensionTickets()) ?>;

                        var rowData = Object.keys(closeData).map(function (key) {
                            var extensionUrl = window.location.search;
                            extensionUrl += ((extensionUrl.indexOf('?') !== -1) ? '&' : '?') + 'extension=' + key;
                            var exNameHtml = '<a href="' + extensionUrl + '">' + key + '</a>';

                            return [exNameHtml, closeData[key]['question'], closeData[key]['issue'], closeData[key]['github'], closeData[key]['problem'], closeData[key]['refund']];
                        });

                        var options = {
                            allowHtml: true,
                            // showRowNumber: true,
                            width: '100%',
                            height: '100%',
                            sortAscending: true
                        };
                        var data = new google.visualization.DataTable();
                        data.addColumn('string', 'Extension');
                        // data.addColumn('number', 'Total');
                        data.addColumn('number', 'Question');
                        data.addColumn('number', 'Issue');
                        data.addColumn('number', 'Github');
                        data.addColumn('number', 'Problem');
                        data.addColumn('number', 'Refund');
                        // data.addColumn('string', 'Team');
                        data.addRows(rowData);
                        // data.addRow(['TOTAL', getSum(data, 1), getSum(data, 2), getSum(data, 3), getSum(data, 4), getSum(data, 5), getSum(data, 6)]);

                        var table = new google.visualization.Table(document.getElementById('extensionTicketTable'));
                        table.draw(data, options);
                        window.addEventListener('resize', function () {
                            drawExtensionTicket();
                        })
                    }

                    var teamExtensionTickets = <?= json_encode($surveyTicket->renderTeamTickets()) ?>;
                    function drawAresExtensionTicket() {
                        var closeData = teamExtensionTickets['ares'];
                        var rowData = Object.keys(closeData).map(function (key) {
                            return [key, closeData[key]['problem'] + closeData[key]['hard_problem'], closeData[key]['issue'] + closeData[key]['github'], closeData[key]['question'], closeData[key]['refund'], closeData[key]['customize'], closeData[key]['total']];
                        });

                        var options = {
                            showRowNumber: true,
                            width: '100%',
                            height: '100%',
                            sortAscending: true
                        };
                        var data = new google.visualization.DataTable();
                        data.addColumn('string', 'Extension');
                        data.addColumn('number', 'Problem');
                        data.addColumn('number', 'Issue');
                        data.addColumn('number', 'Question');
                        data.addColumn('number', 'Refund');
                        data.addColumn('number', 'Other');
                        data.addColumn('number', 'Total');
                        data.addRows(rowData);
                        data.addRow(['TOTAL', getSum(data, 1), getSum(data, 2), getSum(data, 3), getSum(data, 4), getSum(data, 5), getSum(data, 6)]);

                        var table = new google.visualization.Table(document.getElementById('aresExtensionTicketTable'));
                        table.draw(data, options);
                        window.addEventListener('resize', function () {
                            drawAresExtensionTicket();
                        })
                    }
                    function drawArtemisExtensionTicket() {
                        var closeData = teamExtensionTickets['artemis'];
                        var rowData = Object.keys(closeData).map(function (key) {
                            return [key, closeData[key]['problem'] + closeData[key]['hard_problem'], closeData[key]['issue'] + closeData[key]['github'], closeData[key]['question'], closeData[key]['refund'], closeData[key]['customize'], closeData[key]['total']];
                        });

                        var options = {
                            showRowNumber: true,
                            width: '100%',
                            height: '100%',
                            sortAscending: true
                        };
                        var data = new google.visualization.DataTable();
                        data.addColumn('string', 'Extension');
                        data.addColumn('number', 'Problem');
                        data.addColumn('number', 'Issue');
                        data.addColumn('number', 'Question');
                        data.addColumn('number', 'Refund');
                        data.addColumn('number', 'Other');
                        data.addColumn('number', 'Total');
                        data.addRows(rowData);
                        data.addRow(['TOTAL', getSum(data, 1), getSum(data, 2), getSum(data, 3), getSum(data, 4), getSum(data, 5), getSum(data, 6)]);

                        var table = new google.visualization.Table(document.getElementById('artemisExtensionTicketTable'));
                        table.draw(data, options);
                        window.addEventListener('resize', function () {
                            drawArtemisExtensionTicket();
                        })
                    }
                    function drawPoseidonExtensionTicket() {
                        var closeData = teamExtensionTickets['poseidon'];
                        var rowData = Object.keys(closeData).map(function (key) {
                            return [key, closeData[key]['problem'] + closeData[key]['hard_problem'], closeData[key]['issue'] + closeData[key]['github'], closeData[key]['question'], closeData[key]['refund'], closeData[key]['customize'], closeData[key]['total']];
                        });

                        var options = {
                            showRowNumber: true,
                            width: '100%',
                            height: '100%',
                            sortAscending: true
                        };
                        var data = new google.visualization.DataTable();
                        data.addColumn('string', 'Extension');
                        data.addColumn('number', 'Problem');
                        data.addColumn('number', 'Issue');
                        data.addColumn('number', 'Question');
                        data.addColumn('number', 'Refund');
                        data.addColumn('number', 'Other');
                        data.addColumn('number', 'Total');
                        data.addRows(rowData);
                        data.addRow(['TOTAL', getSum(data, 1), getSum(data, 2), getSum(data, 3), getSum(data, 4), getSum(data, 5), getSum(data, 6)]);

                        var table = new google.visualization.Table(document.getElementById('poseidonExtensionTicketTable'));
                        table.draw(data, options);
                        window.addEventListener('resize', function () {
                            drawPoseidonExtensionTicket();
                        })
                    }
                    function drawUranusExtensionTicket() {
                        var closeData = teamExtensionTickets['uranus'];
                        var rowData = Object.keys(closeData).map(function (key) {
                            return [key, closeData[key]['problem'] + closeData[key]['hard_problem'], closeData[key]['issue'] + closeData[key]['github'], closeData[key]['question'], closeData[key]['refund'], closeData[key]['customize'], closeData[key]['total']];
                        });

                        var options = {
                            showRowNumber: true,
                            width: '100%',
                            height: '100%',
                            sortAscending: true
                        };
                        var data = new google.visualization.DataTable();
                        data.addColumn('string', 'Extension');
                        data.addColumn('number', 'Problem');
                        data.addColumn('number', 'Issue');
                        data.addColumn('number', 'Question');
                        data.addColumn('number', 'Refund');
                        data.addColumn('number', 'Other');
                        data.addColumn('number', 'Total');
                        data.addRows(rowData);
                        data.addRow(['TOTAL', getSum(data, 1), getSum(data, 2), getSum(data, 3), getSum(data, 4), getSum(data, 5), getSum(data, 6)]);

                        var table = new google.visualization.Table(document.getElementById('uranusExtensionTicketTable'));
                        table.draw(data, options);
                        window.addEventListener('resize', function () {
                            drawUranusExtensionTicket();
                        })
                    }
                    function drawOtherExtensionTicket() {
                        var closeData = teamExtensionTickets['other'];
                        var rowData = Object.keys(closeData).map(function (key) {
                            return [key, closeData[key]['problem'] + closeData[key]['hard_problem'], closeData[key]['issue'] + closeData[key]['github'], closeData[key]['question'], closeData[key]['refund'], closeData[key]['customize'], closeData[key]['total']];
                        });

                        var options = {
                            showRowNumber: true,
                            width: '100%',
                            height: '100%',
                            sortAscending: true
                        };
                        var data = new google.visualization.DataTable();
                        data.addColumn('string', 'Extension');
                        data.addColumn('number', 'Problem');
                        data.addColumn('number', 'Issue');
                        data.addColumn('number', 'Question');
                        data.addColumn('number', 'Refund');
                        data.addColumn('number', 'Other');
                        data.addColumn('number', 'Total');
                        data.addRows(rowData);
                        data.addRow(['TOTAL', getSum(data, 1), getSum(data, 2), getSum(data, 3), getSum(data, 4), getSum(data, 5), getSum(data, 6)]);

                        var table = new google.visualization.Table(document.getElementById('otherExtensionTicketTable'));
                        table.draw(data, options);
                        window.addEventListener('resize', function () {
                            drawOtherExtensionTicket();
                        })
                    }

                    function allTickets() {
                        var ticketData = <?= json_encode($surveyTicket->renderAllTickets()) ?>;

                        var rowData = Object.keys(ticketData).map(function (key) {
                            let ticketUrl = '<a href="https://mageplaza.freshdesk.com/helpdesk/tickets/' + ticketData[key]['id'] + '" target="_blank">' + ticketData[key]['id'] + '</a>';
                            return [ticketUrl, ticketData[key]['extension'], ticketData[key]['agent'], ticketData[key]['sa'], ticketData[key]['type'], ticketData[key]['tags'], ticketData[key]['created'], ticketData[key]['updated']];
                        });

                        var options = {
                            allowHtml: true,
                            width: '100%',
                            height: '100%',
                            sortAscending: true
                        };
                        var data = new google.visualization.DataTable();
                        data.addColumn('string', 'ID');
                        data.addColumn('string', 'Extension');
                        data.addColumn('string', 'Agent');
                        data.addColumn('string', 'SA');
                        data.addColumn('string', 'Type');
                        data.addColumn('string', 'Tags');
                        data.addColumn('string', 'Created');
                        data.addColumn('string', 'Updated');
                        data.addRows(rowData);

                        var table = new google.visualization.Table(document.getElementById('allTickets'));
                        table.draw(data, options);
                        window.addEventListener('resize', function () {
                            allTickets();
                        })
                    }

                    function allSurveys() {
                        var surveyData = <?= json_encode($surveyTicket->renderAllSurveys()) ?>;

                        var rowData = Object.keys(surveyData).map(function (key) {
                            return [surveyData[key]['id'], surveyData[key]['extension'], surveyData[key]['agent'], surveyData[key]['sa'], surveyData[key]['type'], surveyData[key]['rate'], surveyData[key]['feedback'], surveyData[key]['created']];
                        });

                        var options = {
                            allowHtml: true,
                            width: '100%',
                            height: '100%',
                            sortAscending: true
                        };
                        var data = new google.visualization.DataTable();
                        data.addColumn('string', 'ID');
                        data.addColumn('string', 'Extension');
                        data.addColumn('string', 'Agent');
                        data.addColumn('string', 'SA');
                        data.addColumn('string', 'Type');
                        data.addColumn('string', 'Rate');
                        data.addColumn('string', 'Feedback');
                        data.addColumn('string', 'Created');
                        data.addRows(rowData);

                        var table = new google.visualization.Table(document.getElementById('allSurveys'));
                        table.draw(data, options);
                        window.addEventListener('resize', function () {
                            allSurveys();
                        })
                    }

                    function getSum(data, column) {
                        var total = 0;
                        for (i = 0; i < data.getNumberOfRows(); i++)
                            total = total + data.getValue(i, column);
                        return total;
                    }
                </script>
            <?php endif; ?>
    <script>
        function buildShiftPointRows(totalShift) {
            var scheduleData = <?= json_encode($surveyTicket->getGoogleSheetData()) ?>;

            var result = {};

            scheduleData.forEach(item => {
                const name = item.night,
                    morning = item.morning;

                if (!name) return;

                // Init
                if (!result[name]) {
                    result[name] = { night: 0, weekend: 0 };
                }

                if (morning !== '' && !result[morning]) {
                    result[morning] = { night: 0, weekend: 0 };
                }

                // Logic tính
                if (item.weekend === "TRUE") {
                    result[name].weekend++;
                    if (morning) {
                        result[morning].weekend++;
                    }
                } else {
                    result[name].night++;
                }
            });

            // Convert → rows
            return Object.entries(result).map(function ([name, agent]) {
                var point = agent.night * 150 + agent.weekend * 300;

                // Rename
                if (name === "Hoàng PH") {
                    name = 'Steve';
                } else if (name === "Linh NT") {
                    name = "Linh (Luna)";
                } else if (name === "Hữu HH") {
                    name = "Tony";
                }

                totalShiftAll.push({
                    agent: name,
                    total: point
                });

                return [
                    name,
                    agent.night,
                    agent.weekend,
                    point
                ];
            });
        }
    </script>
    </div>
</div>
<style type="text/css">
    h2 {
        margin-bottom: 25px;
        margin-top: 40px;
    }

    #closedTicketTable table tr td:last-child,
    #closedTicketTable table tr th:last-child {
        font-weight: bold;
        color: red;
    }


    #shopifyTable table tr td:last-child,
    #shopifyTable table tr th:last-child {
        font-weight: bold;
        color: red;
    }

    #shiftPoint table tr td:last-child,
    #shiftPoint table tr th:last-child {
        font-weight: bold;
        color: red;
    }

    #fullPoint table tr td:last-child,
    #fullPoint table tr th:last-child {
        font-weight: bold;
        color: red;
    }

</style>
</body>
