<?php

require_once __DIR__.'/../papaya.php';
require_once __DIR__.'/../vendor/autoload.php';
$PAPAYA_PAGE = new papaya_page();
$PAPAYA_PAGE->execute();
$PAPAYA_PAGE->get();
