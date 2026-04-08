<?php

$data = json_decode($_POST['payload'], true);

$event = $_SERVER['HTTP_X_GITHUB_EVENT'];
$eventLabel = ucfirst(str_replace('_', ' ', $event));
$eventData = $data[$event];

if((isset($data['action']) && $data['action'] != 'opened')
	|| (isset($eventData['user']['login']) && $eventData['user']['login'] == 'haitv282')
){
	return "Skip";
}

$moduleName = ucfirst(str_replace(['magento-2-', '-'], ['', ' '], $data['repository']['name']));

$curl_post_data = [
	'subject' => "[" . $moduleName . "] " . $eventLabel . ': ' . $eventData['title'],
	'description' => "{$eventData['body']}<br>
-----------<br>
{$eventData['html_url']}",
	'email' => 'github@mageplaza.com',
	'type' => 'Github',
	'priority' => 1,
	'status' => 2,
	'custom_fields[rocket_agent]' => 'Khiết (Justin)'
];

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, 'https://mageplaza.freshdesk.com/api/v2/tickets');
curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($curl, CURLOPT_USERPWD, "fdRUD1n89VqFJjaNNoF:X");
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_post_data);

$result_data = curl_exec($curl);
curl_close($curl);