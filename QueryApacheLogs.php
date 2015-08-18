<?php
/**
 * User: angus
 * Date: 16/08/15
 * Time: 22:32
 */


/**
 * Queries Elasticsearch index created by logstash to gather Metrics pertaining to skyscanner interview screening
 * For the sake of simplicity simple curl requests are used to query elasticsearch. The php elasticsearch api
 * would make for more readable code.
 * Class QueryApacheLogs
 */
class QueryApacheLogs
{

    const INDEX_NAME = 'apache_logs';
    const ELASTIC_ENDPOINT = '127.0.0.1:9200';
    public $base_url;

    public $total_logs;
    public $min_log_timestamp;
    public $max_log_timestamp;
    public $total_number_of_minutes;

    public $total_successful_requests;
    public $success_time_buckets;

    public $total_unsuccessful_requests;
    public $no_success_time_buckets;

    public $total_megabytes_sent;
    public $bytes_sent_buckets;

    public $request_time_buckets;




    /**
     * sends request to url with optional data param
     * @param $url - elasticsearch end point
     * @param bool|false $datastring use to set post data for request
     * @return array json_decoded array
     */
    public function queryElastic($url, $datastring = false )
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($datastring !== false ) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $datastring);
        }
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result);
    }

    /**
     * Gathers data from elasticsearch
     */
    public function __construct() {

        $this->base_url = self::ELASTIC_ENDPOINT . '/' .self::INDEX_NAME ;

        $this->clearCache();

        //get some initial stats
        $this->setMinLogTimestamp();
        $this->setMaxLogTimestamp();
        $this->calculateTimeFrameMinutes();


        //tackle the four metrics
        //1
        $this->setTotalSuccessfulRequests();
        $this->getTotalSuccessfulRequestsByMinute();

        //2
        $this->setTotalUnSuccessfulRequests();
        $this->getTotalUnSuccessfulRequestsByMinute();

        //3
        $this->getMeanResponseTimePerMinute();

        //4
        $this->getTotalBytesSent();
        $this->getTotalBytesSentRequestsByMinute();

        return true;


    }

    /**
     * formalises and formats metrics as per Question
     */
    public function EmitMetrics()
    {
        /**
         * Metric 1
         */
        $successfulRequestRate = $this->calculateRate($this->total_successful_requests);
        echo "\n1. Average successful requests per minute = ". $successfulRequestRate  . "\n\nBreakDown:\n";
        echo "Minute \t\t number of successful requests"  . PHP_EOL;
        foreach ($this->success_time_buckets as $time => $successful) {
            echo "$time \t $successful" . PHP_EOL;
        }

        /**
         * Metric 2
         */
        $unSuccessfulRequestRate = $this->calculateRate($this->total_unsuccessful_requests);
        echo "\n\n2. Average unsuccessful requests per minute = ". $unSuccessfulRequestRate   . "\n\nBreakDown:\n";
        echo "Minute \t\t number of Unsuccessful requests"  . PHP_EOL;
        foreach ($this->no_success_time_buckets as $time => $unsuccessful) {
            echo "$time \t $unsuccessful" . PHP_EOL;
        }

        /**
         * Metric 3
         */
        $average_response_time = round(array_sum(array_values($this->request_time_buckets)) / count($this->request_time_buckets), 4);
        echo "\n\n3. Average response time per minute = ". $average_response_time   . " ms \n\nBreakDown:\n";
        echo " Minute \t\t average_response_time in milliseconds"  . PHP_EOL;
        foreach ($this->request_time_buckets as $time => $avg_request_time) {
            echo "$time \t $avg_request_time" . PHP_EOL;
        }

        /**
         * Metric 4
         */
        $megabytesSent = $this->calculateRate($this->total_megabytes_sent);
        echo "\n\n4. Average megabytes per minute = ". $megabytesSent    . " M\n\nBreakDown:\n";
        echo "Minute \t\t number of Megabytes sent"  . PHP_EOL;
        foreach ($this->bytes_sent_buckets as $time => $bytes_sent) {
            echo "$time \t $bytes_sent" . PHP_EOL;
        }

        return true;

    }


    /**
     * @return float
     */
    public function calculateRate($total_metric)
    {
        if ($this->total_number_of_minutes > 0) {
            return round($total_metric /  $this->total_number_of_minutes, 3);
        } else {
            return round($total_metric, 3);
        }
    }



    /**
     * Clears the elasticserach query cache for good measure before running queries
     * @return bool
     */
    private function clearCache()
    {
        $url = $this->base_url . '/_cache/clear';
        $this->queryElastic($url);
        return true;
    }


    /**
     * sets $max_log_timestamp to get the timestamp of the oldest log - uses min aggregate
     * @return bool
     */
    private function setMinLogTimestamp ()
    {
        $url = $this->base_url . "/_search?search_type=count&?pretty";
        $datastring = '{
                        "aggs" : {
                                  "min_date" : { "min" : { "field" : "@timestamp" } }
                        }
        }';
        $result = $this->queryElastic($url, $datastring);
        $this->min_log_timestamp = strtotime($result->aggregations->min_date->value_as_string);

        return true;
    }

    /**
     * sets $max_log_timestamp to get the timestamp of the newest log - uses max aggregate
     * @return bool
     */
    private function setMaxLogTimestamp ()
    {

        $url = $this->base_url . "/_search?search_type=count&?pretty";
        $datastring = '{
                        "aggs" : {
                            "max_date" : { "max" : { "field" : "@timestamp" } }
                        }
        }';

        $result = $this->queryElastic($url, $datastring);
        $this->max_log_timestamp = strtotime($result->aggregations->max_date->value_as_string);

        return true;
    }

    /**
     * based on max and min timestamps sets the total number of minutes the log file spans
     * @return bool
     */
    private function calculateTimeFrameMinutes()
    {
        $this->total_number_of_minutes =  round(abs($this->max_log_timestamp - $this->min_log_timestamp ) / 60,2);
        return true;
    }

    /**
     * counts the number of requests with response code of 200 (* is wild)
     * @return bool
     */
    private function setTotalSuccessfulRequests()
    {
        $url = $this->base_url . "/_search?pretty&search_type=count&q=response:2**";
        $result = $this->queryElastic($url);
        $this->total_successful_requests = $result->hits->total;

        return true;
    }

    /**
     * counts the number of requests with 400 or 500 response codes (* is wild)
     */
    private function setTotalUnSuccessfulRequests()
    {
        $url = $this->base_url . "/_search?pretty&search_type=count&q=response:4**&q=response:5**";
        $result = $this->queryElastic($url);
        $this->total_unsuccessful_requests = $result->hits->total;

        return true;

    }


    /**
     * sums the total bytes sent over the entire log time frame - uses sum aggregate
     */
    private function getTotalBytesSent ()
    {
        $url = $this->base_url . "/_search?search_type=count&?pretty";
        $datastring = '{
                        "aggs" : {
                                  "total_bytes" : { "sum" : { "field" : "bytes" } }
                        }
        }';
        $result = $this->queryElastic($url, $datastring);

        $total_bytes_sent = $result->aggregations->total_bytes->value;

        //value is in bytes so need convert to megabytes
        $this->total_megabytes_sent  = round($total_bytes_sent/1048576,1);

        return true;

    }


    /**
     * gets the average response time for each minute of logging data -
     * uses nested aggregates first grouping by minute then avg minute buckets
     */
    private function getMeanResponseTimePerMinute ()
    {

        $url = $this->base_url . "/_search?search_type=count&?pretty";

        $datastring = '{
                        "aggs" : {
                                 "group_by_minute" : {
                                        "date_histogram" : {
                                            "field" : "@timestamp",
                                            "interval" : "minute"
                                        },
                                        "aggs" : {
                                            "mean_response" : { "avg" : { "field" : "microseconds" } }
                                        }

                                 }
                        }
        }';

        $buckets = array();
        $result = $this->queryElastic($url, $datastring);
        foreach ($result->aggregations->group_by_minute->buckets as $bucket) {
            $minute = date('Y-m-d H:i', strtotime($bucket->key_as_string));
            $buckets[$minute] = round($bucket->mean_response->value, 3);
        }

        $this->request_time_buckets = $buckets;
        return true;
    }


    private function getTotalSuccessfulRequestsByMinute() {

        $url = $this->base_url . "/_search?pretty&search_type=count&q=response:2**";

        $datastring = '{
                        "aggs" : {
                                 "group_by_minute" : {
                                        "date_histogram" : {
                                            "field" : "@timestamp",
                                            "interval" : "minute"
                                        }
                                 }
                        }
        }';

        $buckets = array();
        $result = $this->queryElastic($url, $datastring);
        foreach ($result->aggregations->group_by_minute->buckets as $bucket) {
            $minute = date('Y-m-d H:i', strtotime($bucket->key_as_string));
            $buckets[$minute] = $bucket->doc_count;
        }

        $this->success_time_buckets = $buckets;
        return true;
    }

    private function getTotalUnSuccessfulRequestsByMinute() {

        $url = $this->base_url . "/_search?pretty&search_type=count&q=response:4**&q=response:5**";

        $datastring = '{
                        "aggs" : {
                                 "group_by_minute" : {
                                        "date_histogram" : {
                                            "field" : "@timestamp",
                                            "interval" : "minute"
                                        }
                                 }
                        }
        }';

        $buckets = array();
        $result = $this->queryElastic($url, $datastring);
        foreach ($result->aggregations->group_by_minute->buckets as $bucket) {
            $minute = date('Y-m-d H:i', strtotime($bucket->key_as_string));
            $buckets[$minute] = $bucket->doc_count;
        }

        $this->no_success_time_buckets = $buckets;
        return true;
    }


    private function getTotalBytesSentRequestsByMinute() {

        $url = $this->base_url . "/_search?pretty&search_type=count";

        $datastring = '{
                        "aggs" : {
                                 "group_by_minute" : {
                                        "date_histogram" : {
                                            "field" : "@timestamp",
                                            "interval" : "minute"
                                        },
                                        "aggs" : {
                                            "total_bytes" : { "sum" : { "field" : "bytes" } }
                                        }

                                 }
                        }
        }';

        $buckets = array();
        $result = $this->queryElastic($url, $datastring);
        foreach ($result->aggregations->group_by_minute->buckets as $bucket) {
            $minute = date('Y-m-d H:i', strtotime($bucket->key_as_string));
            $buckets[$minute] = round($bucket->total_bytes->value/1048576,4);
        }

        $this->bytes_sent_buckets = $buckets;
        return true;
    }



}


date_default_timezone_set('Europe/London');
error_reporting(E_ERROR);//not got time for warnings right now :)