<?php
require_once 'TicketDb.php';

class SurveyTicket
{
    /** @var TicketDb */
    private $db;

    protected $surveyData = [], $feedbackData = [], $ticketData = [], $csTicketData = [], $reviewRate = [], $badReviews = [];

    protected $agent, $fromDate, $toDate, $tag, $extension;

    const kpiData = [
        'question_points' => 10,
        'issue_points' => 20,
        'simple_points' => 45,
        'simple-medium_points' => 51,
        'medium_points' => 57,
        'medium-complex_points' => 63,
        'complex_points' => 69
    ];

    const shopifyKpiData = [
        'shopify_question_ah_point' => 16,
        'shopify_question_point' => 8,
        'shopify_issue_ah_point' => 30,
        'shopify_issue_point' => 15,
        'shopify_problem_ah_point' => 50,
        'shopify_problem_point' => 25,
    ];

    protected $shopifyTs = ['Steve', 'Tony', 'Linh (Luna)'];

    const saMember = ['Elle', 'Hannah'];

    public function __construct()
    {

        $this->db = new TicketDb();

        if (isset($_GET['getfrom'])) {
            $this->db->pickData($_GET['getfrom']);
        }

        $this->agent = isset($_GET['agent']) ? $_GET['agent'] : null;
        $this->extension = isset($_GET['extension']) ? $_GET['extension'] : null;
        $this->fromDate = isset($_GET['from-date']) ? $_GET['from-date'] : date('Y-m-01');
        $this->toDate = isset($_GET['to-date']) ? $_GET['to-date'] : date('Y-m-t');
        $this->tag = isset($_GET['tag']) ? $_GET['tag'] : null;
    }

    public function getData($key)
    {
        switch ($key) {
            case 'agent':
                return $this->agent;
            case 'from':
                return $this->fromDate;
            case 'to':
                return $this->toDate;
            case 'extension':
                return $this->extension;
        }

        return '';
    }

    public function getShopifyTs()
    {
        return $this->shopifyTs;
    }

    function getGoogleSheetData() {
        $spreadsheetId = '1c3qnPDDf1UsbfJt96fQGRHIDhALxjl2aCA2q3qFFpKM';
        $sheetName = 'phan_ca';


        $url = "https://opensheet.elk.sh/$spreadsheetId/$sheetName";

        // Dùng file_get_contents
        $response = @file_get_contents($url);

        // Nếu fail → fallback sang cURL
        if ($response === false) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
        }

        return json_decode($response, true);
    }

    protected $fetchSurveyData = [];
    public function collectTicketData()
    {
        // Collect ticket data
        $ticketData = $this->fetchTicketData();
        foreach ($ticketData as $ticket) {
            if ($ticket['ticket_status'] !== 'Closed') {
                continue;
            }

            $ticket['extension_name'] = $this->db->getExName($ticket['extension_name'], true); //correct for old data

            $this->csTicketData[$ticket['cs_name'] ?: 'NOT SET'][] = $ticket;
            $this->ticketData[$ticket['agent_name']][] = $ticket;
        }

        // Collect survey data
        $this->fetchSurveyData = $this->db->fetchSurvey($this->fromDate, $this->toDate);
        foreach ($this->fetchSurveyData as $id => $data) {
            $this->surveyData[$id] = [
                'rate' => $data['rate'],
                'feedback' => $data['feedback']
            ];
        }
        $this->reviewProcess();
        $this->feedbackProcess();
    }

    protected $fetchTicketData = [];
    public function fetchTicketData()
    {
        if(empty($this->fetchTicketData)) {
            $agent = null;
            $isCs = false;
            if ($this->agent) {
                $isCs = isset($_GET['cs']) ? (bool)$_GET['cs'] : false;
            }

            $this->fetchTicketData = $this->db->fetchTicket($this->fromDate, $this->toDate, $this->agent, $isCs, $this->tag, $this->extension);
        }

        return $this->fetchTicketData;
    }

    public function reviewProcess()
    {
        $this->badReviews = [];
        $this->reviewRate = [];

        // Get ticket data by survey
        $ticketData = [];
        $ticketBySurvey = $this->db->fetchTicketBySurvey($this->surveyData);
        foreach ($ticketBySurvey as $ticket) {
            if ($ticket['ticket_status'] !== 'Closed') {
                continue;
            }
            $ticketData[$ticket['agent_name']][] = $ticket;
        }

        foreach ($ticketData as $agent => $tickets) {
            $totalReview = 0; //total reviews
            foreach ($tickets as $ticket) {
                $id = $ticket['ticket_id'];

                $this->surveyData[$id]['ext'] = $ticket['extension_name'];
                $this->surveyData[$id]['agent'] = $agent;
                $this->surveyData[$id]['cs_agent'] = $ticket['cs_name'];

                $totalReview++;
                $rate = $this->surveyData[$id]['rate'];
                if (in_array($rate, ['Extremely Unhappy'])) { //Neutral & Unhappy
                    $this->badReviews[$agent][] = compact('rate', 'id');
                }
            }
            if ($totalReview) { //only show has review
                $percentage = 100;
                $rateDetail = $totalReview . '/' . $totalReview;
                if (isset($this->badReviews[$agent])) {
                    $percentage = 100 - number_format(count($this->badReviews[$agent]) / $totalReview, 2) * 100;
                    $rateDetail = ($totalReview - count($this->badReviews[$agent])) . '/' . $totalReview;
                }
                $this->reviewRate[$agent] = ['percent' => $percentage, 'detail' => $rateDetail, 'total' => $totalReview];
            }
        }
        uasort($this->reviewRate, static function ($a, $b){
            if($a['total'] === $b['total']) return 0;

            return ($a['total'] < $b['total']) ? 1 : -1;
        });
    }

    public function feedbackProcess()
    {
        foreach ($this->surveyData as $id => $data) {
            $fb = $data['feedback'];
            if (!empty($fb) && strlen($fb) > 30) {
                $this->feedbackData[$id] = array(
                    "ext" => isset($data['ext']) ? $data['ext'] : 'N/A',
                    "agent" => isset($data['agent']) ? $data['agent'] : 'N/A',
                    "feedback" => $fb
                );
            }
        }
    }

    public function renderFeed()
    {
        $html = "<h2>Feedsback from Clients <span class='badge'>" . count($this->feedbackData) . "</span></h2>";
        $html .= "<ol>";

        foreach ($this->feedbackData as $ticketID => $info) {
            $html .= "<li class='list-group-item'><ul class='list-group'>";

            $html .= "<li class='list-group-item'>";
            $html .= "<b>" . $info['feedback'] . "</b>";
            $html .= "</li>";

            $html .= "<li class='list-group-item'>";
            $html .= "<a target='_blank' href='" . $this->db->getTicketUrl($ticketID) . " '> ";
            $html .= $info['ext'] . ' - ' . $ticketID . ' - ' . $info['agent'];
            $html .= "</a></li>";

            $html .= "</ul></li>";
        }

        $html .= "</ol>";

        return $html;
    }

    public function renderAgentRate()
    {
        $html = "<ul class='list-group'>";
        $good = 0;
        $total = 0;
        foreach ($this->reviewRate as $agName => $rateInfo) {
            $html .= "<li class='list-group-item'>" . $agName . ': ';
            $html .= "<span class='badge'>" . $rateInfo['percent'] . '% (' . $rateInfo['detail'] . ')' . "</span>";
            if (isset($this->badReviews[$agName])) {
                $badTicketHtml = $this->badIdToHtml($this->badReviews[$agName]);
                $html .= $badTicketHtml;
            }
            $html .= "</li>";

            $detail = explode('/', $rateInfo['detail']);
            $good += $detail[0];
            $total += $detail[1];
        }
        $html .= "</ul>";

        $returnHtml = "<h2>Rocket Agent Rate</h2>";
        $round = $total >0? round($good / $total * 100, 2) :0;
        $returnHtml .= 'Good: ' . $good . '; Total: ' . $total . '; Rate: ' . $round . '%';

        return $returnHtml . $html;
    }

    public function badIdToHtml($badTK)
    {
        $badTicketDetails = '';
        $badTickets = array_column($badTK, 'id');
        foreach ($badTickets as $id) {
            $badTicketDetails .= '<a target="_blank" href="' . $this->db->getTicketUrl($id) . '">' . $id . '</a>';
            $badTicketDetails .= ', ';
        }
        return ' [' . rtrim($badTicketDetails, ', ') . ']';
    }

    public function renderExtensionTickets()
    {
        $data = [];
        foreach ($this->ticketData as $agent => $tickets) {
            foreach ($tickets as $ticket) {
                $exName = $ticket['extension_name'];
                if(!$exName || $exName == "N/a" || $exName == "[EXTENSION_NAME]") continue;
                if (!isset($data[$exName])) {
                    $data[$exName] = ['question' => 0, 'github' => 0, 'problem' => 0, 'hard_problem' => 0, 'issue' => 0, 'refund' => 0, 'customize' => 0, 'total' => 0, 'team' => ''];
                }

                if ($ticket['ticket_type'] == 'Question'){
                    $data[$exName]['question']++;
                } else if ($ticket['ticket_type'] == 'Github'){
                    $data[$exName]['github']++;
                } else if ($ticket['ticket_type'] == 'Issue'){
                    $data[$exName]['issue']++;
                } else if ($ticket['ticket_type'] == 'Problem'){
                    $data[$exName]['problem']++;
                } else if ($ticket['ticket_type'] == 'Hard Problem'){
                    $data[$exName]['hard_problem']++;
                } else if ($ticket['ticket_type'] == 'Refund'){
                    $data[$exName]['refund']++;
                } else if ($ticket['ticket_type'] == 'Customize'){
                    $data[$exName]['customize']++;
                } else {
                    continue;
                }
                $data[$exName]['total']++;

                $data[$exName]['team'] = in_array($exName, $this->aresExtensions) ? 'Ares' :
                    (in_array($exName, $this->artemisExtensions) ? 'Artemis' :
                        (in_array($exName, $this->poseidonExtensions) ? 'Poseidon' :
                            (in_array($exName, $this->uranusExtensions) ? 'Uranus' : 'Other')));
            }
        }

        uasort($data, static function ($a, $b){
            if($a['total'] === $b['total']) return 0;

            return ($a['total'] < $b['total']) ? 1 : -1;
        });

        return $data;
    }

    public function renderTeamTickets(){
        $teamTickets = ['ares' => [], 'poseidon' => [], 'uranus' => [], 'artemis' => [], 'other' => []];
        $extensionTickets = $this->renderExtensionTickets();
        foreach($extensionTickets as $exName => $exTickets){
            $team = in_array($exName, $this->aresExtensions) ? 'ares' :
                        (in_array($exName, $this->artemisExtensions) ? 'artemis' :
                            (in_array($exName, $this->poseidonExtensions) ? 'poseidon' :
                                (in_array($exName, $this->uranusExtensions) ? 'uranus' : 'other')));

            $teamTickets[$team][$exName] = $exTickets;
        }

        return $teamTickets;
    }

    public function renderSteveExtensionTickets()
    {
        $ticketData =$this->ticketData;
        $data = [];
        $dataShopify = [];
        foreach ($ticketData as $agent => $tickets) {
            if ($agent !== 'Steve') {
                continue;
            }
            foreach ($tickets as $ticket) {
                if ($ticket['group_name'] === 'Technical Support Shopify') {
                    $dataShopify[] = $ticket;
                }

                if ($ticket['ticket_type'] == 'Problem' && $ticket['group_name'] !== 'Technical Support Shopify'){
                    if (!isset($data[$ticket['extension_name']])) {
                        $data[$ticket['extension_name']] = [
                            'count' => 1,
                            'ids'   => [$ticket['ticket_id']]
                        ];
                    } else {
                        $data[$ticket['extension_name']]['count']++;
                        $data[$ticket['extension_name']]['ids'][] = $ticket['ticket_id'];

                    }
                }
            }
        }
        return $data;
    }

    public function renderCloseTickets($isAgent = true)
    {
        $ticketData = $isAgent ? $this->ticketData : $this->csTicketData;

        $data = [];

        foreach ($ticketData as $agent => $tickets) {
            if($agent === 'NOT SET'){
                continue;
            }

            if (!isset($data[$agent])) {
                $data[$agent] = [
                    'question' => 0,
                    'problem' => 0,
                    'simple' => 0,
                    'simple-medium' => 0,
                    'medium' => 0,
                    'medium-complex' => 0,
                    'complex' => 0,
                    'issue' => 0,
                    'hard_problem' => 0,
                    'refund' => 0,
                    'total' => 0,
                    'customize' => 0,
                    'bad' => 0,
                    'question_points' => 0,
                    'issue_points' => 0,
                    'simple_points' => 0,
                    'simple-medium_points' => 0,
                    'medium_points' => 0,
                    'medium-complex_points' => 0,
                    'complex_points' => 0,
                    'hard_problem_ticket' => 0,
                    'hard_problem_points' => 0,
                    'holiday_ticket' => 0,
                    'holiday_points' => 0,
                    'good_rate_ticket' => 0,
                    'good_rate_points' => 0,
                    'question_hard_problem_ticket' => 0,
                    'question_holiday_ticket' => 0,
                    'question_good_rate_ticket' => 0,
                    'issue_hard_problem_ticket' => 0,
                    'issue_holiday_ticket' => 0,
                    'issue_good_rate_ticket' => 0,
                    'simple_hard_problem_ticket' => 0,
                    'simple_holiday_ticket' => 0,
                    'simple_good_rate_ticket' => 0,
                    'simple-medium_hard_problem_ticket' => 0,
                    'simple-medium_holiday_ticket' => 0,
                    'simple-medium_good_rate_ticket' => 0,
                    'medium_hard_problem_ticket' => 0,
                    'medium_holiday_ticket' => 0,
                    'medium_good_rate_ticket' => 0,
                    'medium-complex_hard_problem_ticket' => 0,
                    'medium-complex_holiday_ticket' => 0,
                    'medium-complex_good_rate_ticket' => 0,
                    'complex_hard_problem_ticket' => 0,
                    'complex_holiday_ticket' => 0,
                    'complex_good_rate_ticket' => 0,
                    'shopify_question' => 0,
                    'shopify_issue' => 0,
                    'shopify_problem' => 0,
                    'shopify_question_ah' => 0,
                    'shopify_issue_ah' => 0,
                    'shopify_problem_ah' => 0,
                    'shopify_question_ah_point' => 0,
                    'shopify_question_point' => 0,
                    'shopify_issue_ah_point' => 0,
                    'shopify_issue_point' => 0,
                    'shopify_problem_ah_point' => 0,
                    'shopify_problem_point' => 0,
                    'shopify_total' => 0
                ];
            }
            foreach ($tickets as $ticket) {
                if ($ticket['group_name'] === 'Technical Support Shopify') {
                    $tags = explode(',', $ticket['tags']);
                    if ($ticket['ticket_type'] === 'Question') {
                        if (in_array('after-hours', $tags)) {
                            $data[$agent]['shopify_question_ah'] += 1;
                            $data[$agent]['shopify_question_ah_point'] += 1;
                        } else {
                            $data[$agent]['shopify_question'] += 1;
                            $data[$agent]['shopify_question_point'] += 1;
                        }
                    }
                    if ($ticket['ticket_type'] === 'Issue') {
                        if (in_array('after-hours', $tags)) {
                            $data[$agent]['shopify_issue_ah'] += 1;
                            $data[$agent]['shopify_issue_ah_point'] += 1;
                        } else {
                            $data[$agent]['shopify_issue'] += 1;
                            $data[$agent]['shopify_issue_point'] += 1;
                        }
                    }
                    if ($ticket['ticket_type'] === 'Problem') {
                        if (in_array('after-hours', $tags)) {
                            $data[$agent]['shopify_problem_ah'] += 1;
                            $data[$agent]['shopify_problem_ah_point'] += 1;
                        } else {
                            $data[$agent]['shopify_problem'] += 1;
                            $data[$agent]['shopify_problem_point'] += 1;
                        }
                    }
                    continue;
                }
                $rate = 1;
                $tagHardProblem = 0;
                $tagHoliday = 0;
                $tagGoodRate = 0;
                $surveryKey = $isAgent ? 'agent' : 'cs_agent';
                $tags = explode(',', $ticket['tags']);
                if (in_array('re_opend', $tags) || in_array('re_opened', $tags) || in_array('reopen', $tags)) {
                    continue;
                }
                if (in_array($ticket['ticket_id'], array_keys($this->surveyData)) && $this->surveyData[$ticket['ticket_id']]['rate'] == 'Extremely Happy') {
                    if (isset($this->surveyData[$ticket['ticket_id']][$surveryKey]) || $this->surveyData[$ticket['ticket_id']][$surveryKey] !== 'NOT SET') {
                        $tagGoodRate++;
                        $rate += 1.0;
                    }
                }

                if (in_array('mp_ultimate', $tags)) {
                    $tagHardProblem += 0.5;
                    $rate += 0.5;
                }

                if (in_array('hard_problem', $tags)) {
                    $tagHardProblem++;
                    $rate += 1.0;
                }

                if (in_array('mp_weekend', $tags)) {
                    $tagHoliday++;
                    $rate += 1.0;
                }

                if (in_array('mp_enterprise', $tags)) {
                    $tagHardProblem++;
                    $rate += 1.0;
                }

                if (in_array('urgent_lunch', $tags)) {
                    $tagHardProblem++;
                    $rate += 1.0;
                }

                if (in_array('urgent_weekend', $tags)) {
                    $tagHoliday++;
                    $rate += 1.0;
                }

                if (in_array('mp_cloud', $tags)) {
                    $tagHardProblem++;
                    $rate += 2.0;
                }

                if (in_array('mp_holiday', $tags)) {
                    $tagHoliday++;
                    $rate += 2.0;
                }

                if ($ticket['ticket_type'] == 'Problem' || $ticket['ticket_type'] == 'Installation') {
                    if (in_array($ticket['extension_name'], $this->simpleExtensions)) {
                        $data[$agent]['simple'] += 1;
                        $data[$agent]['simple_points'] += $rate;
                        $data[$agent]['simple_hard_problem_ticket'] += $tagHardProblem;
                        $data[$agent]['simple_holiday_ticket'] += $tagHoliday;
                        $data[$agent]['simple_good_rate_ticket'] += $tagGoodRate;
                    } elseif (in_array($ticket['extension_name'], $this->simpleMediumExtensions)) {
                        $data[$agent]['simple-medium'] += 1;
                        $data[$agent]['simple-medium_points'] += $rate;
                        $data[$agent]['simple-medium_hard_problem_ticket'] += $tagHardProblem;
                        $data[$agent]['simple-medium_holiday_ticket'] += $tagHoliday;
                        $data[$agent]['simple-medium_good_rate_ticket'] += $tagGoodRate;
                    } elseif (in_array($ticket['extension_name'], $this->mediumExtensions)) {
                        $data[$agent]['medium'] += 1;
                        $data[$agent]['medium_points'] += $rate;
                        $data[$agent]['medium_hard_problem_ticket'] += $tagHardProblem;
                        $data[$agent]['medium_holiday_ticket'] += $tagHoliday;
                        $data[$agent]['medium_good_rate_ticket'] += $tagGoodRate;
                    } elseif (in_array($ticket['extension_name'], $this->mediumComplexExtensions)) {
                        $data[$agent]['medium-complex'] += 1;
                        $data[$agent]['medium-complex_points'] += $rate;
                        $data[$agent]['medium-complex_hard_problem_ticket'] += $tagHardProblem;
                        $data[$agent]['medium-complex_holiday_ticket'] += $tagHoliday;
                        $data[$agent]['medium-complex_good_rate_ticket'] += $tagGoodRate;
                    } elseif (in_array($ticket['extension_name'], $this->complexExtensions)) {
                        $data[$agent]['complex'] += 1;
                        $data[$agent]['complex_points'] += $rate;
                        $data[$agent]['complex_hard_problem_ticket'] += $tagHardProblem;
                        $data[$agent]['complex_holiday_ticket'] += $tagHoliday;
                        $data[$agent]['complex_good_rate_ticket'] += $tagGoodRate;
                    }
                    $data[$agent]['problem'] += 1;
                    $data[$agent]['total']++;
                } else if ($ticket['ticket_type'] == 'Issue' || $ticket['ticket_type'] == 'Github') {
                    $data[$agent]['issue'] += 1;
                    $data[$agent]['issue_points'] += $rate;
                    $data[$agent]['issue_hard_problem_ticket'] += $tagHardProblem;
                    $data[$agent]['issue_holiday_ticket'] += $tagHoliday;
                    $data[$agent]['issue_good_rate_ticket'] += $tagGoodRate;
                } else if ($ticket['ticket_type'] == 'Question') {
                    $data[$agent]['question'] += 1;
                    $data[$agent]['question_points'] += $rate;
                    $data[$agent]['question_hard_problem_ticket'] += $tagHardProblem;
                    $data[$agent]['question_holiday_ticket'] += $tagHoliday;
                    $data[$agent]['question_good_rate_ticket'] += $tagGoodRate;
                }
                $data[$agent]['hard_problem_ticket'] += $tagHardProblem >= 1 ? 1 : $tagHardProblem;
                $data[$agent]['holiday_ticket'] += $tagHoliday >= 1 ? 1 : 0;
                $data[$agent]['good_rate_ticket'] += $tagGoodRate >= 1 ? 1 : 0;
            }
        }

        // Add total points
        if(!isset($_GET['real'])) {
            foreach ($data as $agent => $kpi) {
//                if ($agent === 'Steve') {
//                    echo '';
//                }

                $total = 0;
                $shopifyTotal = 0;
                $totalHardProblemPoints = 0;
                $totalHolidayPoints = 0;
                $totalGoodRatePoints = 0;
                foreach ($kpi as $key => $ticket) {
                    $data[$agent][$key] = $ticket;
                    if(in_array($key, ['bad', 'refund'])) continue; //skip bad/refund to leader board

                    if (array_key_exists($key, self::shopifyKpiData)) {
                        if($isAgent) {
                            $shopifyTotal += self::shopifyKpiData[$key] * $ticket;
                        }
                    } elseif (array_key_exists($key, self::kpiData)) {
                        if($isAgent) {
                            $total += self::kpiData[$key] * $ticket;
                        } else {
                            $total += self::kpiData[$key] * $ticket * 0.3;
                        }
                    }
                    if (strpos($key, '_hard_problem_ticket')) {
                        $key = str_replace('_hard_problem_ticket', '_points', $key);
                        $totalHardProblemPoints += self::kpiData[$key] * $ticket;
                    }
                    if (strpos($key, '_holiday_ticket')) {
                        $key = str_replace('_holiday_ticket', '_points', $key);
                        $totalHolidayPoints += self::kpiData[$key] * $ticket;
                    }
                    if (strpos($key, '_good_rate_ticket')) {
                        $key = str_replace('_good_rate_ticket', '_points', $key);
                        $totalGoodRatePoints += self::kpiData[$key] * $ticket;
                    }
                }
                $data[$agent]['hard_problem_points'] = $totalHardProblemPoints;
                $data[$agent]['holiday_points'] = $totalHolidayPoints;
                $data[$agent]['good_rate_points'] = $totalGoodRatePoints;

                $data[$agent]['total'] = $total;
                $data[$agent]['shopify_total'] = $shopifyTotal;
            }

            uasort($data, static function ($a, $b){
                if($a['total'] === $b['total']) return 0;

                return ($a['total'] < $b['total']) ? 1 : -1;
            });
        }

        return $data;
    }

    public function renderAllTickets(){
        $data = [];
        foreach ($this->fetchTicketData as $ticket) {
            $agent = $ticket['agent_name'] == 'NOT SET' ? '' : $ticket['agent_name'];
            $sa = $ticket['cs_name'] == 'NOT SET' ? '' : $ticket['cs_name'];

            if($ticket['ticket_type'] == 'NOT SET' || $ticket['ticket_status'] !== 'Closed' || (empty($agent) && empty($sa))) continue;

            $finalTags = [];
            $tags = explode(',', $ticket['tags']);
            foreach($tags as $tag){
                if(!in_array($tag, ['mp_holiday', 'mp_weekend', 'hard_problem', 'mp_ultimate', 'mp_enterprise', 'mp_cloud', 'urgent_lunch, urgent_weekend'])){
                    continue;
                }
                $finalTags[] = $tag;
            }
            $data[] = [
                'id' => $ticket['ticket_id'],
                'extension' => ($ticket['extension_name'] == '[EXTENSION_NAME]' || $ticket['extension_name'] == 'N/a') ? '' : $ticket['extension_name'],
                'agent' => $ticket['agent_name'] == 'NOT SET' ? '' : $ticket['agent_name'],
                'sa' => $ticket['cs_name'] == 'NOT SET' ? '' : $ticket['cs_name'],
                'type' => $ticket['ticket_type'],
                'tags' => implode(',', $finalTags),
                'created' => $ticket['created_at'],
                'updated' => $ticket['updated_at'],
            ];
        }

        return $data;
    }

    public function renderAllSurveys(){
        $data = [];
        $tickets = $this->db->fetchTicketBySurvey($this->fetchSurveyData);
        foreach ($tickets as $ticket) {
            $agent = $ticket['agent_name'] == 'NOT SET' ? '' : $ticket['agent_name'];
            $sa = $ticket['cs_name'] == 'NOT SET' ? '' : $ticket['cs_name'];

            if($ticket['ticket_type'] == 'NOT SET' || (empty($agent) && empty($sa))) continue;

            $data[] = [
                'id' => $ticket['ticket_id'],
                'extension' => ($ticket['extension_name'] == '[EXTENSION_NAME]' || $ticket['extension_name'] == 'N/a') ? '' : $ticket['extension_name'],
                'agent' => $ticket['agent_name'] == 'NOT SET' ? '' : $ticket['agent_name'],
                'sa' => $ticket['cs_name'] == 'NOT SET' ? '' : $ticket['cs_name'],
                'type' => $ticket['ticket_type'],
                'rate' => $this->fetchSurveyData[$ticket['ticket_id']]['rate'],
                'feedback' => $this->fetchSurveyData[$ticket['ticket_id']]['feedback'],
                'created' => $this->fetchSurveyData[$ticket['ticket_id']]['created_at'],
            ];
        }

        return $data;
    }

    public function renderRealCloseTickets()
    {
        $data = [];
        foreach ($this->ticketData as $agent => $tickets) {
            if (!isset($data[$agent])) {
                $data[$agent] = ['question' => 0, 'problem' => 0, 'issue' => 0, 'refund' => 0];
            }
            foreach ($tickets as $ticket) {
                if ($ticket['ticket_type'] == 'Problem' || $ticket['ticket_type'] == 'Hard Problem') {
                    $data[$agent]['problem'] ++;
                } else if ($ticket['ticket_type'] == 'Refund') {
                    $data[$agent]['refund']++;
                } else if ($ticket['ticket_type'] == 'Issue' || $ticket['ticket_type'] == 'Github') {
                    $data[$agent]['issue'] ++;
                } else{
                    $data[$agent]['question'] ++;
                }
            }
        }

        return $data;
    }

    protected $aresExtensions = [
        'Free Gifts',
        'Gift Card',
        'PDF Invoice',
        'RMA',
        'Store Locator',
        'Call for Price',
        'Follow Up Email',
        'Product Alerts',
        'Required Login',
        'SMTP',
        'Social Login',
        'Age Verification',
        'Backend Reindex',
        'Barcode',
        'Better Coupon',
        'Better Product Options',
        'Better Sorting',
        'Catalog Permissions',
        'Currency Formatter',
        'Custom Order Number',
        'Customer Approval',
        'Delete Order',
        'Free Shipping Bar',
        'Freshsales',
        'GDPR',
        'Google Maps',
        'Google reCaptcha',
        'Mass Order Actions',
        'Order History',
        'Order Labels',
        'Product Grid',
        'Quick Flush Cache',
        'Quick Order',
        'Same Order Number',
        'Size Chart',
        'Social Share'
    ];

    protected $uranusExtensions = [
        'Abandoned Cart Email',
        'Affiliate',
        'Customer Attributes',
        'One Step Checkout',
        'Gift Wrap',
        'Membership',
        'Store Credit',
        'Table Rate Shipping',
        'Thank You Page',
        '2Checkout',
        'ABN AMRO',
        'Barclaycard',
        'Canada Post',
        'CartaSi',
        'CommWeb',
        'Delivery Time',
        'Loyalty Program',
        'Milestone',
        'Moneris',
        'Multi Flat Rates',
        'Multiple Coupons',
        'Order Attributes',
        'Pre Order',
        'Quick Cart',
        'Sagepay',
        'Save Cart',
        'SecurePay',
        'Share Cart',
        'Special Promotions',
        'Stripe',
        'Wepay',
        'Westpac',
        'Worldpay',
        'Shipping Cost',
        'Language Pack'
    ];

    protected $artemisExtensions = [
        'SEO',
        'Reward Points',
        'Extra Fee',
        'Edit Order',
        'FAQs',
        'Order Export',
        'Quick View',
        'Request For Quote',
        'Blog',
        'Custom Form',
        'Product Attachments',
        'Product Feed',
        'Security',
        'Shipping Rules',
        'Admin Permissions',
        'Better Change Qty',
        'Better Product Reviews',
        'Better Tier Price',
        'Better WishList',
        'Configurable Grid View',
        'Cron Schedule',
        'Email Attachment',
        'Google Tag Manager',
        'Import Export Categories',
        'Import Export CMS',
        'Login As Customer',
        'Mass Product Actions',
        'Payment Restriction',
        'Reports',
        'Review Reminder',
        'Shipping Restriction',
        'SMS Notification',
        'Table Category View',
        'Two-Factor Authentication',
        'Webhook',
        'Zoho CRM',
        'Salesforce'
    ];

    protected $poseidonExtensions = [
        'Automatic Related Products',
        'Layered Navigation',
        'Search',
        'Shop By Brand',
        'Banner Slider',
        'Better Popup',
        'Daily Deal',
        'Product Labels',
        'Product Slider',
        'Store Switcher',
        'Ajax Layered Navigation',
        'Better Maintenance',
        'Better Order Grid',
        'Better Static Block',
        'Configurable Preselect',
        'Countdown Timer',
        'Custom Stock Status',
        'Facebook Plugin',
        'Frequently Bought Together',
        'Geo IP',
        'Image Optimizer',
        'Instagram Feed',
        'Lazy Loading',
        'Name Your Price',
        'Order Archive',
        'Product Finder',
        'Promo banner',
        'Promo bar',
        'Quickbooks Online',
        'Sales Pop',
        'Seo Url',
        'Google Xml Sitemap',
        'Twitter Widget',
        'Who Bought This Also Bought',
        'Who Viewed This Also Viewed'
    ];

    protected $complexExtensions = [
        "One Step Checkout",
        "Reward Points",
        "SEO",
        "Gift Card",
        "Layered Navigation",
        "Edit Order",
        "Affiliate",
        "Reports"
    ];

    protected $mediumComplexExtensions = [
        "Product Feed",
        "Google Tag Manager",
        "Free Gifts",
        "Order Attributes",
        "Extra Fee",
        "Shop By Brand",
        "Customer Attributes",
        "Subscription & Recurring Payments",
        "Social Login",
        "Blog",
        "RMA",
        "Barclaycard",
        "Sage Pay",
        "Store Credit",
        "Stripe",
        "Request For Quote",
        "Better Product Reviews",
        "Moneris",
        "Follow Up Email"
    ];

    protected $mediumExtensions = [
        "PDF Invoice",
        "Abandoned Cart Email",
        "Store Locator",
        "Store Locator (Pickup)",
        "Auto Related Product",
        "Webhook",
        "Table Rate Shipping",
        "Custom Form",
        "Zoho CRM",
        "Better Sorting",
        "Shipping Rules",
        "Daily Deals",
        "Mass Order Actions",
        "Special Promotions",
        "Loyalty Program",
        "Lookbook",
        "Shipping Cost",
        "FAQ", "Membership",
        "SMS Notification",
        "Better WishList",
        "Salesforce",
        "Better Product Options",
        "Company Accounts",
        "Quickbooks Online",
        "Better Order Grid",
        "Product Finder",
        "Name Your Price",
        "Name your price",
        "Recent Sales Notification",
        "MileStone",
        "CommWeb",
        "SecurePay",
        "Westpac",
        "Worldpay/Pay360",
        "ABN AMRO",
        "CartaSi",
        "Wepay",
        "2Checkout",
        "Freshsales"
    ];

    protected $simpleMediumExtensions = [
        "Product Labels",
        "Call for Price",
        "Catalog Permissions",
        "Image Optimizer",
        "Security",
        "Store Switcher",
        "Product Attachments",
        "Search",
        "Customer Approval",
        "Currency Formatter",
        "Instagram Feed",
        "Frequently Bought Together",
        "Quick Order",
        "Mass Product Actions",
        "Shipping Restriction",
        "GDPR",
        "Product Alerts",
        "Pre Order",
        "Gift Wrap",
        "Quick View & Ajax Cart",
        "Who Bought This Also Bought",
        "Multiple Coupons",
        "Better Maintenance",
        "Countdown Timer",
        "Thank You Page",
        "Facebook Plugin",
        "Payment Restriction",
        "Order Export",
        "Admin Permissions",
        "Better Tier Price",
        "Import Export Categories",
        "Promo banner",
        "Banner Slider",
        "Product Slider",
        "Better Change Qty",
        "Import Export CMS",
        "Promo bar",
        "Lazy Loading",
        "Custom Order Number",
        "Configurable Product Preselect",
        "Age Verification",
        "Barcode",
        "Better Coupon",
        "Product Grid",
        "Required Login",
        "Size Chart",
        "Review Reminder",
        "Google reCaptcha",
        "Order History",
        "Order Labels",
        "Better Popup",
        "Google Maps",
        "SEO-Friendly URL",
        "Custom Stock Status",
        "Better Static Block",
        "Canada Post",
        "Language Pack"
    ];

    protected $simpleExtensions = [
        "Internal",
        "Quick Cart",
        "Save Cart & Buy Later",
        "Delete Orders",
        "Who Viewed This Also Viewed",
        "Delivery Time",
        "Multi Flat Rates",
        "Share Cart",
        "Two-Factor Authentication",
        "Configurable Product Grid View",
        "Order Archive",
        "Table Category View",
        "Cron Schedule",
        "Email Attachments",
        "Twitter Widget",
        "Geo IP",
        "Sitemap",
        "SMTP",
        "Free Shipping Bar",
        "Same Order Number",
        "Social Share",
        "Ajax Layered Navigation",
        "Backend Tools",
        "Login As Customer",
        "Backend Reindex",
        "Quick Flush Cache"
    ];

    public function getTargetByAgent($agent)
    {
        return $this->db->fetchTarget($agent);
    }

    public function updateTargetByAgent($agent, $newTarget)
    {
        return $this->db->updateTarget($agent, $newTarget);
    }
}
