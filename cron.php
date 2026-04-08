<?php

/***
Cái này nó nằm ở Server Demo 245 chú nhé. Trước a nhớ có save git, mà giờ ktra ko thấy, chắc là a sửa trực tiếp ở đó.
Applications: Setup
Thư mục: applications/setup/public_html/support-kpi
Trong đó có thư mục mageplaza và avada (trước làm cho cả Avada luôn, nhưng sau bỏ rồi). Dữ liệu của Mageplaza thì nằm hết trong thư mục mageplaza đấy

Cloudways url https://platform.cloudways.com/apps/3262639/access_detail
**/
require_once 'TicketDb.php';

$surveyTicket = new TicketDb();

$surveyTicket->pickData(2);
