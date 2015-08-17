#!/bin/bash

#delete the old indexes before running.
curl -XDELETE 'http://localhost:9200/apache_logs/';
curl -XDELETE 'http://localhost:9200/apache_logs_grok_failures/';

#put a mapping into elasticsearch for this specific use case  - this enables fine grained control over data types and string analyzers etc
curl -XPUT 'http://localhost:9200/apache_logs/' -d '{
	"mappings": {
            "logs": {
                "properties": {
                    "@timestamp": {
                        "type": "date",
                        "format": "dateOptionalTime"
                    },
                    "@version": {
                        "type": "string"
                    },
                    "bytes": {
                        "type": "long"
                    },
                    "clientip": {
                        "type": "string"
                    },
                    "host": {
                        "type": "string"
                    },
                    "httpversion": {
                        "type": "string"
                    },
                    "message": {
                        "type": "string"
                    },
                    "microseconds": {
                        "type": "long"
                    },
                    "path": {
                        "type": "string"
                    },
                    "request": {
                        "type": "string"
                    },
                    "request_timestamp": {
                        "type": "string"
                    },
                    "response": {
                        "type": "string"
                    },
                    "tags": {
                        "type": "string"
                    },
                    "verb": {
                        "type": "string"
                    }
                }
            }
        }
}'

# run logstash with specified config file - note it must be in same directory as this file
`which logstash` -f ./logstash.conf
