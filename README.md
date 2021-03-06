# Apache log analyser

Assignment:

The approach taken here was to parse the logfile with logstash and pass off the structured logs to elasticsearch for indexing and then querying the data with a php script.
Logstash was chosen as its an ideal candidate for this kind of job with its powerful grok matching and it integrates nicely with elasticsearch.
The metrics requested would be easily visualised using a tool such as R, kibana or Graphite. However this is perhaps outwith the scope of the question.
I chose php to query the index as I'm most familiar with it and its testing framework phpunit. I initially set out to find the averages but decided to include the breakdown per minute as well.


<h3>Inventory</h3>
README            - This file outlining process used

logstash.conf           - The file used to tell logstash how to expect logs to come in, how to deal with the logs and where to send them

run_logstash.sh         - This creates and index in elasticsearch with a predefined mapping to give more control over how the data is stored in elasicsearch
                          it then runs logstash and assumes that the logstash.conf file is in the same directory

QueryApacheLogs.php     - The file that contains a class to query the elasticsearch index for the the metrics in the question

QueryApacheLogs_TEST.php- The file that contains a unit test for QueryApacheLogs.php. uses phpUnit - assumes installed

EmitMetrics.php         - The script which calls the QueryApacheLogs class to generate metrics

UnitTestOutput.txt      - Output from the unit test

grokparsefailures       - The file contains a list of log messages which weren't parsed by the grok pattern I used. given more time I would modify the grok pattern
                          or create additional filters to catch all logs. incidentally, in order to keep parse failures out of the main index they have been offloaded
                          to a separate index. This would help in the effort to find and fix all grok parse failures in a production environment.

ANSWER                  - the output from the php script containing answers to the questions in the assignment in a verbose fashion. Answer is currently not 100% accurate as there is ~8000 logs failing grok parsing





<h3>To Reproduce</h3>
In order to get this working on OSX. The following steps had to be carried out. This assumes brew is installed and a recent version of Java and Ruby

$ brew update

$ brew install logstash

$ brew install elasticsearch && elasticsearch

$ cp path_to_access_log  path_to_checked_out_code

$ cd path_to_checked_out_code

$ ./run_logstash.sh

$ php EmitMetrics.php

$ brew install homebrew/php/phpunit

$ phpunit QueryApacheLogs_TEST.php

