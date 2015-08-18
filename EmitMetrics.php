<?php
/**
 * Created by PhpStorm.
 * User: angus
 * Date: 18/08/15
 * Time: 17:03
 */

include('./QueryApacheLogs.php');
$QueryApacheLogs = new QueryApacheLogs();
$QueryApacheLogs->EmitMetrics();
