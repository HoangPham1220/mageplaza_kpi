<?php
// SELECT * FROM table WHERE id = (SELECT MAX(id) FROM table);
//SELECT extension_name, COUNT(*) AS total_ticket, SUM(CASE WHEN ticket_type = "Question" OR ticket_type = "Github" THEN 1 ELSE 0 END) AS question_ticket, SUM(CASE WHEN ticket_type != "Question" AND ticket_type != "Github" THEN 1 ELSE 0 END) AS problem_ticket FROM ticket WHERE created_at >= "2018-06-01" AND created_at < "2018-07-01" GROUP BY extension_name


// CREATE TABLE `ticket` (
//   `id` int(11) NOT NULL AUTO_INCREMENT,
//   `ticket_id` int(24) NOT NULL,
//   `ticket_status` varchar(255) NOT NULL,
//   `agent_name` varchar(255) NOT NULL,
//   `cs_name` varchar(255) NOT NULL,
//   `group_name` varchar(255) NOT NULL,
//   `extension_name` varchar(255) NOT NULL,
//   `ticket_type` varchar(255) DEFAULT NULL,
//   `point` int(11) DEFAULT NULL,
//   `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
//   `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
//   PRIMARY KEY (`id`)
// ) ENGINE=InnoDB AUTO_INCREMENT=4131 DEFAULT CHARSET=utf8

// CREATE TABLE `survey` (
//   `id` int(11) NOT NULL AUTO_INCREMENT,
//   `survey_id` varchar(255) NOT NULL,
//   `ticket_id` varchar(255) NOT NULL,
//   `rate` varchar(255) NOT NULL,
//   `feedback` varchar(255) DEFAULT NULL,
//   `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//   `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
//   PRIMARY KEY (`id`)
// ) ENGINE=InnoDB AUTO_INCREMENT=240 DEFAULT CHARSET=utf8

//CREATE TABLE agent (
//    id VARCHAR(50) PRIMARY KEY,
//    name VARCHAR(255) NOT NULL,
//    target INT NOT NULL,
//    month DATE NOT NULL,
//    update_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
//        ON UPDATE CURRENT_TIMESTAMP
//);

//CREATE TABLE daily_snapshot (
//    id VARCHAR(50) PRIMARY KEY,
//    agent_id VARCHAR(50) NOT NULL,
//    magento_point FLOAT,
//    shopify_point FLOAT,
//    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//
//    CONSTRAINT fk_agent
//        FOREIGN KEY (agent_id)
//        REFERENCES agent(id)
//        ON DELETE CASCADE
//);

class TicketDb
{
    const DB_HOST = "localhost";  // Change as required
    //const DB_HOST = "localhost";  // Change as required
    // const DB_HOST = "mageplaza-1329:asia-east1:mageplaza-mysql";  // Change as required
    const DB_USER = "root";  // Change as required
    //const DB_USER = "root";  // Change as required
    const DB_PASSWORD = "admin123";  // Change as required
    //const DB_PASSWORD = "brian123";  // Change as required
    const DB_NAME = "ticket";    // Change as required

    const TABLE_TICKET = 'ticket';
    const TABLE_SURVEY = 'survey';

    const API_KEY = 'fdRUD1n89VqFJjaNNoF'; //'lxJ3VHBcR66mkl8jv6';
    const API_TICKET_URL = 'https://mageplaza.freshdesk.com/api/v2/tickets/';
    const API_SURVEY_URL = 'https://mageplaza.freshdesk.com/api/v2/surveys/satisfaction_ratings/';
    const API_TICKET_FIELD_URL = 'https://mageplaza.freshdesk.com/api/v2/ticket_fields/';

    private $db;
    protected $responderId; //group_name/Team
    public $ticketStatus;

    function __construct()
    {
        $this->db = new mysqli(self::DB_HOST, self::DB_USER, self::DB_PASSWORD, self::DB_NAME);

        if ($this->db->connect_error) {
            trigger_error('Database connection failed: ' . $this->db->connect_error, E_USER_ERROR);
            die;
        }
        mysqli_query($this->db, 'set names utf8'); //đồng bộ dữ liệu charset để hiện thị tiếng việt

        $this->getTicketFields();
    }

    public function getTicketUrl($ticketId)
    {
        return 'https://mageplaza.freshdesk.com/helpdesk/tickets/' . $ticketId;
    }

    private function getTicketFields($force = false)
    {
        if (!$force && file_exists('data.json')) {
            $content = file_get_contents('data.json');
            $data = json_decode($content, true);
        } else {
            $data = [];

            $ticketFields = $this->curlUrl(self::API_TICKET_FIELD_URL);
            foreach ($ticketFields as $field) {
                $fieldName = $field['name'];
                switch ($fieldName) {
                    case 'agent':
                        $data['agent'] = $this->getAgents($field['choices']);
                        break;
                    case 'status':
                        $data['status'] = $this->getTicketStatus($field['choices']);
                        break;
                    default:
                        break;
                }
            }

            file_put_contents('data.json', json_encode($data));
        }

        $this->responderId = $data['agent'];
        $this->ticketStatus = $data['status'];
    }

    private function getAgents($agents)
    {
        $result = [];
        foreach ($agents as $name => $id) {
            $agentId = (string)$id;
            $result[$agentId] = $name;
        }
        $result["000"] = "NOT SET";
        return $result;
    }

    private function getTicketStatus($statuses)
    {
        $result = [];
        foreach ($statuses as $id => $textArr) {
            $result[$id] = $textArr[0];
        }
        return $result;
    }

    public function isSurveyExist($sid)
    {
        $sql = "SELECT * FROM " . self::TABLE_SURVEY . " where survey_id =" . $sid;
        $rs = $this->dbQuery($sql);

        return $rs->num_rows > 0;
    }

    public function updateSurvey($sid, $tdata)
    {
        $sql = "UPDATE " . self::TABLE_SURVEY . " set ";
        $sql .= "`ticket_id` = '" . $tdata['ticket_id'] . "',";
        $sql .= "`rate` = '" . $tdata['rate'] . "',";
        $sql .= "`feedback` = '" . $tdata['feedback'] . "',";
        $sql .= "`created_at` = '" . $tdata['created_at'] . "',";
        $sql .= "`updated_at` = '" . $tdata['updated_at'] . "'";
        $sql .= " where survey_id =" . $sid;

        $this->dbQuery($sql);
    }

    public function fetchTicket($fromDate = null, $toDate = null, $agent = null, $isCs = false, $tag = null, $extension = null)
    {
        $date = !empty($fromDate) ? $fromDate : date('Y-m-d', strtotime("-30 days"));
        $dateCreated = date('Y-m-d', strtotime("-60 days"));
        $sql = "SELECT * FROM " . self::TABLE_TICKET . " where updated_at >= '" . $date . "'";

        if (!empty($toDate)) {
            $toDate = date('Y-m-d', strtotime($toDate . '+1 day')); //xử lý ticket ngày cuối cùng ko đc tính. Cộng thêm 1 ngày để tính
            $sql .= " AND updated_at <= '" . $toDate . "'";
        }

        if ($agent) {
            if($isCs){
                $sql .= " AND cs_name = '" . $agent . "'";
            } else {
                $sql .= " AND agent_name = '" . $agent . "'";
            }
        }

        if($tag){
            $sql .= " AND tags like '%" . $tag . "%'";
        }

        if($extension){
            $sql .= " AND extension_name = '" . $extension . "'";
        }

        $type = isset($_GET['type']) ? $_GET['type'] : null;
        if($type){
            $sql .= " AND ticket_type = '" . $type . "'";
        }

        $sql .= ' order by updated_at ASC';

        $rs = $this->dbQuery($sql);

        return mysqli_fetch_all($rs, MYSQLI_ASSOC);
    }

    public function fetchTicketBySurvey($survey)
    {
        $ticketId = array_keys($survey);
        if(!count($ticketId)){
            return [];
        }

        $sql = "SELECT * FROM " . self::TABLE_TICKET . " where ticket_id IN (" . implode(',', $ticketId) . ") order by ticket_id ASC";

        $rs = $this->dbQuery($sql);

        return mysqli_fetch_all($rs, MYSQLI_ASSOC);
    }

    public function fetchSurvey($fromDate = null, $toDate = null)
    {
        $date = date('Y-m-d', strtotime("-30 days"));
        if (!empty($fromDate)) {
            $date = $fromDate;
        }
        $sql = "SELECT * FROM " . self::TABLE_SURVEY . " where created_at >= '" . $date . "'";
        if (!empty($toDate)) {
            $toDate = date('Y-m-d', strtotime($toDate . '+1 day')); //xử lý ticket ngày cuối cùng ko đc tính. Cộng thêm 1 ngày để tính
            $sql .= " AND created_at <= '" . $toDate . "'";
        }

        $ret = [];
        $rs = $this->dbQuery($sql);
        $rows = mysqli_fetch_all($rs, MYSQLI_ASSOC);
        foreach ($rows as $key => $dt) {
            $id = (string)$dt['ticket_id'];
            $ret[$id] = $dt;
        }

        return $ret;
    }

    public function saveSurveyToDb($data)
    {
        $rateMap = array( //https://developers.freshdesk.com/api/#create_satisfaction_rating
            103 => "Extremely Happy",
            100 => "Neutral",
            -103 => "Extremely Unhappy"
        );

        $tids = [];
        $result = [];

        foreach ($data as $key => $dt) {
            $tid = (string)$dt['ticket_id'];
            $tids[] = $tid;
            $sid = (string)$dt['id'];
            $rateVal = $rateMap[$dt["ratings"]["default_question"]];
            $feedback = $this->db->real_escape_string($dt['feedback']);
            $created_at = date('Y-m-d H:i:s', strtotime($dt['created_at']));
            $updated_at = date('Y-m-d H:i:s', strtotime($dt['updated_at']));

            if ($this->isSurveyExist($sid)) {
                $sdata = ['ticket_id' => $tid, 'rate' => $rateVal, 'feedback' => $feedback, 'created_at' => $created_at, 'updated_at' => $updated_at];
                $this->updateSurvey($sid, $sdata);
            } else {
                $sql = "INSERT INTO `" . self::TABLE_SURVEY . "` (`survey_id`, `ticket_id`, `rate`, `feedback`, `created_at`, `updated_at`) VALUES (";
                $sql .= $sid;
                $sql .= ",'" . $tid . "'";
                $sql .= ",'" . $rateVal . "'";
                $sql .= ",'" . $feedback . "'";
                $sql .= ",";
                $sql .= "CAST('" . $created_at . "' AS DATETIME)";
                $sql .= ",";
                $sql .= "CAST('" . $updated_at . "' AS DATETIME)";
                $sql .= ")";

                $this->dbQuery($sql);
            }
        }

        if (!empty($tids)) {
            foreach ($tids as $tidItem) {
                $result[] = $this->curlUrl(self::API_TICKET_URL . $tidItem);
            }
            $this->saveTicketToDb($result);
        }
    }

    public function getCloseExistTicket($data)
    {
        $ticketExists = [];

        $sql = "Select * from " . self::TABLE_TICKET . " where ticket_id IN (" . implode(',', array_column($data, 'id')) . ") AND ticket_status = 'Closed'";
        $rs = $this->dbQuery($sql);
        $rows = mysqli_fetch_all($rs, MYSQLI_ASSOC);
        foreach ($rows as $row) {
            $ticketExists[] = $row['ticket_id'];
        }

        return $ticketExists;
    }

    public function saveTicketToDb($data)
    {
        $ticketExist = $this->getCloseExistTicket($data);
        $values = '';

        foreach ($data as $dt) {
            $tid = (string)$dt['id'];
            if (in_array($tid, $ticketExist)) {
                continue;
            }

            $ticketdata = [
                'ticket_id' => $tid,
                'subject' => $this->db->real_escape_string($dt['subject']),
                'status' => $this->ticketStatus[$dt['status']],
                'agName' => $this->getAgentName($dt['custom_fields']['rocket_agent'] ?: 'NOT SET'),
                'csName' => $this->getAgentName($dt['custom_fields']['cf_cs_agent'] ?: 'NOT SET'),
                'groupName' => $dt['responder_id'] ?
                    (isset($this->responderId[(string)$dt['responder_id']]) ?
                        $this->responderId[(string)$dt['responder_id']] : 'NOT SET') :
                    'NOT SET',
                'extName' => $this->getExName($dt['custom_fields']['extension'] ?: '[EXTENSION_NAME]'),
                'type' => $dt['type'] ?: "NOT SET",
                //'customize' => $dt['custom_fields']['cf_customize_price'],
                'tags' => is_array($dt['tags']) ? implode(',', $dt['tags']) : '',
                'is_weekend' => (is_array($dt['tags']) && in_array('mp_weekend', $dt['tags'])) ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s', strtotime($dt['created_at'])),
                'updated_at' => date('Y-m-d H:i:s', strtotime($dt['updated_at']))
            ];
            $values .= ($values ? ',' : '') . '("' . implode('","', $ticketdata) . '")';
        }

        if ($values === '') {
            return $this;
        }

        $sql = "INSERT INTO " . self::TABLE_TICKET . " (ticket_id,subject,ticket_status,agent_name,cs_name,group_name,extension_name,ticket_type,tags,is_weekend,created_at,updated_at) VALUES " . $values;
        $sql .= " ON DUPLICATE KEY UPDATE subject=VALUES(subject),ticket_status=VALUES(ticket_status),agent_name=VALUES(agent_name),cs_name=VALUES(cs_name),group_name=VALUES(group_name),extension_name=VALUES(extension_name),ticket_type=VALUES(ticket_type),tags=VALUES(tags),is_weekend=VALUES(is_weekend),created_at=VALUES(created_at),updated_at=VALUES(updated_at)";

        $this->dbQuery($sql);
    }

    public function saveAllSurveys($day = null)
    {
        $date = !empty($day) ? $day : "2017-01-01T00:00:00Z";
        $page = 1;
        $allSurveyUrl = self::API_SURVEY_URL . '?created_since=' . $date . '&per_page=100&page='; //max 100
        $result = $this->curlUrl($allSurveyUrl . $page);
        while (count($result)) {
            $this->saveSurveyToDb($result);
            $result = $this->curlUrl($allSurveyUrl . ++$page);
        }
    }

    public function saveAllTickets($day = null)
    {
        $date = !empty($day) ? $day : "2017-01-01T00:00:00Z";
        $page = 1;
        $allTicketUrl = self::API_TICKET_URL . '?updated_since=' . $date . '&per_page=100&page='; //max 100
        $result = $this->curlUrl($allTicketUrl . $page);
        while (count($result)) {
            $this->saveTicketToDb($result);
            $result = $this->curlUrl($allTicketUrl . ++$page);
        }
    }

    public function pickData($day = null)
    {
        $this->getTicketFields(true);

        $day = $day ?: 35;
        $lastUpdateTime = date('Y-m-d', strtotime("-$day days"));
        $this->saveAllTickets($lastUpdateTime);
        $this->saveAllSurveys($lastUpdateTime);
    }

    private function dbQuery($sql)
    {
        $rs = $this->db->query($sql);
        if ($rs === false) {
            $e = new Exception();
            echo '<pre>' . $e->getTraceAsString() . '</pre>';
            trigger_error('Wrong SQL: ' . $sql . ' Error: ' . $this->db->error, E_USER_ERROR);
            die;
        }

        return $rs;
    }

    protected function curlUrl($url)
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_USERPWD, self::API_KEY . ':X');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $server_output = curl_exec($ch);
        $info = curl_getinfo($ch);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headers = substr($server_output, 0, $header_size);
        $response = substr($server_output, $header_size);
        if ($info['http_code'] == 200) {
            return json_decode($response, true);
        }

        throw new Exception($headers);
    }

    private $extensionNameMap = [
        'Automatic Related Products' => 'Auto Related Product',
        'FAQs' => 'FAQ',
        'Store Locator' => 'Store Locator (Pickup)',
        'Email Attachment' => 'Email Attachments',
        'Configurable Preselect' => 'Configurable Product Preselect',
        'Configurable Grid View' => 'Configurable Product Grid View',
        'Quick View' => 'Quick View & Ajax Cart',
        'Daily Deal' => 'Daily Deals',
        'Delete Order' => 'Delete Orders',
        'Sagepay' => 'Sage Pay',
        'Google Xml Sitemap' => 'Sitemap',
        'Save Cart' => 'Save Cart & Buy Later',
        'Age Verfication' => 'Age Verification',
        'Report' => 'Reports'
    ];
    public function getExName($exName, $correctName = false){
        $exName = str_replace('M2 ', '', $exName);

        if(substr($exName, -4) === ' Pro'){
            $exName = substr($exName, 0, -4);
        }
        if(substr($exName, -9) === ' Ultimate'){
            $exName = substr($exName, 0, -9);
        }
        if (array_key_exists($exName, $this->extensionNameMap)) {
            $exName = $this->extensionNameMap[$exName];
        }

        return $exName ?: '[EXTENSION_NAME]';
    }

    private $agentNameMap = [
        'Hiếu (Jimmy)' => 'Jimmy',
        'Hiếu Jimmy' => 'Jimmy',
        'Arthas (Dương)' => 'Arthas',
        'Thái (Eric)' => 'Eric',
        'Horus (Đức)' => 'Horus',
        'Tuấn Anh (Thomas)' => 'Thomas',
        'Hiếu ĐT (Edward)' => 'Edward',
        'Biên (Ben)' => 'Ben',
        'Bruce (Lương)' => 'Bruce',
        'Sáu (Roger)' => 'Roger',
        'Tùng (Drake)' => 'Drake',
        'Prime (Trung)' => 'Prime',
        'Tú Curtis' => 'Curtis',
        'Hoàng (Kenny)' => 'Kenny',
        'Chiến (Rango)' => 'Rango',
        'Vũ Đức Tú (Shin)' => 'Shin',
        'Cường (Bruno)' => 'Bruno',
        'Hưng (Bang)' => 'Bang',
        'Định (Desmond)' => 'Desmond',
        'Đạt' => 'Mardian',
        'Đạt (Mardian)' => 'Mardian',
        'Vinh (Victor)' => 'Victor',
        'Toàn (Uri)' => 'Uri',
        'Ninh' => 'Jordi',
        'Nghĩa (Ethan)' => 'Ethan',
        'Hà (Elle)' => 'Elle',
        'Tuvn (Tom)' => 'Tom',
        'Tú (Tom)' => 'Tom',
        'Việt (Jerry)' => 'Jerry',
        'Phúc (Mars)' => 'Mars',
        'Giáp (Shox)' => 'Shox',
        'Hant' => 'Elle',
        'Chức (Neil)' => 'Neil',
        'Khiết (Justin)' => 'Justin',
        'Hà Đỗ (Hannah)' => 'Hannah',
        'Quyền (Shaggy)' => 'Shaggy',
        'Phú (Onesh)' => 'Onesh',
        'Hadt' => 'Hannah',
        'Sơn (Ryan)' => 'Ryan',
        'Huy (Teddy)' => 'Teddy',
        'Tùng (Issac)' => 'Issac',
        'Nam (Johan)' => 'Johan',
        'Nghĩa (Karlis)' => 'Karlis',
        'Hiếu (Timo)' => 'Timo',
        'Tâm (Thomas)' => 'Thomas',
        'Nhật (Dom)' => 'Dom',
        'Linh (Joscata)' => 'Joscata',
        'Thắng (James)' => 'James',
        'Dương (Rin)' => 'Rin',
        'Trâm (Anna)' => 'Anna',
        'Chinh (Nancy)' => 'Nancy',
        'Trang (Elsie)' => 'Elsie',
        'Steve (Hoàng)' => 'Steve',
        'Kein (Kiên)' => 'Kein',
        'Leon (Lâm)' => 'Leon',
        'Logan (An)' => 'Logan',
        'Mo (Tiến Sơn)' => 'Mo',
        'Tony (Hữu)' => 'Tony',
        'CS Team' => 'CS team',
    ];

    public function getAgentName($name)
    {
        if (array_key_exists($name, $this->agentNameMap)) {
            return $this->agentNameMap[$name];
        }

        return $name ?: 'NOT SET';
    }

    public function fetchTarget($agent)
    {
        $month = date('n');

        $sql = "SELECT target FROM agent WHERE 1=1";

        if (!empty($agent)) {
            $sql .= " AND name = '" . $agent . "'";
        }

        $sql .= " AND month = " . $month;

        $sql .= " ORDER BY update_time DESC LIMIT 1";

        // 4. Query
        $rs = $this->dbQuery($sql);

        // 5. Fetch
        $row = mysqli_fetch_assoc($rs);

        return $row ? $row['target'] : 6000;
    }

    public function updateTarget($agent, $newTarget)
    {
        $month = date('n');


        $newTarget = (int)$newTarget;

        $sql = "
        INSERT INTO agent (name, target, month)
        VALUES ('" . $agent . "', " . $newTarget . ", " . $month . ")
    ";

        return $this->dbQuery($sql);
    }

}
