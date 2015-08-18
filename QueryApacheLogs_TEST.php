<?php
/**
 * User: angus
 * Date: 18/08/15
 * Time: 16:53
 */

include('./QueryApacheLogs.php');

class QueryApacheLogs_TEST extends PHPUnit_Framework_TestCase {


    public function __construct() {
        define('UNIT_TESTING', true);
        $this->Subject = new QueryApacheLogs();
    }


    public function testQueryElastic()
    {
        $ping = $this->Subject->queryElastic(QueryApacheLogs::ELASTIC_ENDPOINT);
        $this->assertEquals($ping->status, 200);
    }

    public function testIndexExists()
    {
        $url = $this->Subject->base_url . "/_count";
        $ping = $this->Subject->queryElastic($url);

        $this->assertEquals(property_exists($ping, 'count'), true);
        $this->assertGreaterThan(0, $ping->count);

    }


    public function testCalculateRate() {
        $value = $this->Subject->calculateRate(100);
        $this->assertGreaterThan(0, $value);
    }


    public function testSuccessfulRequests() {
        $this->assertTrue(is_int($this->Subject->total_successful_requests));
    }

    public function testUnSuccessfulRequests() {
        $this->assertTrue(is_int($this->Subject->total_unsuccessful_requests));
    }

    public function testTotalBytesSent() {
        $this->assertTrue(is_float($this->Subject->total_megabytes_sent));
    }

    public function testTimeFrame() {
        $this->assertTrue(is_float($this->Subject->total_number_of_minutes));
    }

    public function testBucketsExist() {
        $this->assertTrue(is_array($this->Subject->request_time_buckets));
    }


    public function testBucketSpansMinToMax() {
       $this->BucketSpansMinToMax($this->Subject->success_time_buckets);
       $this->BucketSpansMinToMax($this->Subject->no_success_time_buckets);
       $this->BucketSpansMinToMax($this->Subject->request_time_buckets);
       $this->BucketSpansMinToMax($this->Subject->bytes_sent_buckets);
    }

    public function BucketSpansMinToMax($bucket) {
        $min_time =  date('Y-m-d H:i', $this->Subject->min_log_timestamp);
        $this->assertArrayHasKey($min_time,$bucket);

        $max_time =  date('Y-m-d H:i', $this->Subject->max_log_timestamp);
        $this->assertArrayHasKey($max_time, $bucket);

    }


}