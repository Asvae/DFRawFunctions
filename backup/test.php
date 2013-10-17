<?php
$data = "[OBJECT:BUILDING][BUILDING_WORKSHOP:MISC_KOBOLD]";
$start = strpos($data, '[OBJECT:') + 8;
$end = strpos($data, ']', $start + 1);
if (!isset($start, $end))
	echo 'object not found';
echo substr($data, $start, $end - $start);
