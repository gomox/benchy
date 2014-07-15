<?php

define('CONFIG_FILE','config.json');
define('DATA_DIR', 'data/');
define('AB_EXECUTABLE', 'ab');


class ApacheBenchRunner {
    private $config;
    private $defaultconfig;

    public function __construct($config) {
        $this->defaultconfig = json_decode('
            {
                "url": "http://localhost/",
                "cookie": null,
                "basicauth": null,
                "comment": null,
                "tests": [
                         { "concurrency": 1,   "requests": 10   },
                         { "concurrency": 2,   "requests": 20   },
                         { "concurrency": 4,   "requests": 40   },
                         { "concurrency": 8,   "requests": 80   },
                         { "concurrency": 16,  "requests": 160  },
                         { "concurrency": 32,  "requests": 320  },
                         { "concurrency": 64,  "requests": 640  },
                         { "concurrency": 128, "requests": 1280 }
                ]
            }
        ');
        $this->config = (object) array_merge((array)$this->defaultconfig, (array) $config);
        $this->checkApacheBench();
    }

    private function checkApacheBench() {
        exec(AB_EXECUTABLE . " -V", $output, $return_var);
        if ($return_var != 0) {
            throw new Exception("Could not execute ApacheBench! (used \"" .AB_EXECUTABLE. "\" as command)");
        }
    }

    private function buildCommand($concurrency, $requests) {
        $s =  AB_EXECUTABLE;

        if (!is_null($this->config->cookie)) { $s .= " -C ".$this->config->cookie; }
        if (!is_null($this->config->basicauth)) { $s .= " -A ".$this->config->basicauth; }

        $s .= " -q";
        $s .= " -c ".$concurrency;
        $s .= " -n ".$requests;
        $s .= " ".$this->config->url;
        $s .= " 2>&1 | tee";

        echo "INFO: Running ".$s." ...\n";
        return $s;
    }

    public function hasComment() {
        return $this->config->comment !== null;
    }

    public function setComment($c) {
        $this->config->comment = $c;
    }

    public function getConfig() {
        return $this->config;
    }

    public function runBench() {
        $start = microtime(true);
        $results = array();

        foreach($this->config->tests as $test) {
            $concurrency = $test->concurrency;
            $requests = $test->requests;
            $command = $this->buildCommand($concurrency, $requests);
            
            ob_start();
            $output = shell_exec($command);
            ob_end_clean();

            $res = new ApacheBenchResult($output);
            $results[] = $res;
        }

        $end = microtime(true);

        return $results;
    }
}

class ApacheBenchResult {
    private $results;

    public function __construct($cmd_output) {
        $expressions = array(
            "server_software"       => "/Server Software:        (.*)/",
            "server_hostname"       => "/Server Hostname:        (.*)/",
            "server_port"           => "/Server Port:            (.*)/",
            "document_path"         => "/Document Path:          (.*)/",
            "document_length"       => "/Document Length:        (.*) bytes/",
            "concurrency_level"     => "/Concurrency Level:      (.*)/",
            "time_taken_for_tests"  => "/Time taken for tests:   (.*) seconds/",
            "complete_requests"     => "/Complete requests:      (.*)/",
            "failed_requests"       => "/Failed requests:        (.*)/",
            "write_errors"          => "/Write errors:           (.*)/",
            "total_transferred"     => "/Total transferred:      (.*) bytes/",
            "html_transferred"      => "/HTML transferred:       (.*) bytes/",
            "requests_per_second"   => "/Requests per second:    (.*) \[#\/sec] \(mean\)/",
            "time_per_request"      => "/Time per request:       (.*) \[ms\] \(mean\)/",
            "time_per_request_conc" => "/Time per request:       (.*) \[ms\] \(mean, across all concurrent requests\)/",
            "transfer_rate"         => "/Transfer rate:          (.*) \[Kbytes\/sec\] received/"
        );

        $types = array(
            "server_software"       => "string",
            "server_hostname"       => "string",
            "server_port"           => "string",
            "document_path"         => "string",
            "document_length"       => "int",
            "concurrency_level"     => "int",
            "time_taken_for_tests"  => "float",
            "complete_requests"     => "int",
            "failed_requests"       => "int",
            "write_errors"          => "int",
            "total_transferred"     => "int",
            "html_transferred"      => "int",
            "requests_per_second"   => "float",
            "time_per_request"      => "float",
            "time_per_request_conc" => "float",
            "transfer_rate"         => "float"
       );

        foreach($expressions as $key => $regex) {
            preg_match_all($regex, $cmd_output, $matches);
            $this->results[$key] = cast($matches[1][0], $types[$key]);
        }
    }

    public function getResults() {
        return $this->results;
    }
}


function zip_associative_arrays(array $array_list) {
    if (count($array_list) == 0) {
        return array();
    }
    $keys = array_keys($array_list[0]);
    $ret = array();

    foreach ($keys as $k) {
        $ret[$k] = array();
    }
    
    foreach ($array_list as $a) {
        foreach ($keys as $k) {
            $ret[$k][] = $a[$k];
        }
    }
    
    return $ret;
}


function cast($value, $type) {
    if ($type == "int") {
        return intval($value);
    } else if ($type == "float") {
        return floatval($value);
    } else {
        return $value;
    }
}


function main() {
    
    if ($conf = file_get_contents(CONFIG_FILE)) {
    echo "INFO: Loading configuration from ".CONFIG_FILE."\n";
    
        $config = json_decode($conf);
        if ($config === null) {
            echo "ERROR: Invalid configuration format in ".CONFIG_FILE."\n";
            exit(1);
        }
    } else {
        $config = null;
    }

    try {
        $abr = new ApacheBenchRunner($config);
        if (!$abr->hasComment()) {
            $abr->setComment(readline('Please enter a comment to describe this run: '));
        }
        
        $results = $abr->runBench();
       
        $output_filename = DATA_DIR.'ab-run-' . date('YmdHis'). ".dat";
        echo "INFO: Storing benchmark results in $output_filename\n";

        $ret_obj = new stdClass();
        $ret_obj->config = $abr->getConfig();
        $ret_obj->results = array();

        foreach ($results as $r) {
            $ret_obj->results[] = $r->getResults();
        }

        $ret_obj->zipped_results = zip_associative_arrays($ret_obj->results);
        file_put_contents($output_filename, json_encode($ret_obj));

    } catch(Exception $e) {
        echo "ERROR: " . $e->getMessage()."\n";
        exit(1);
    }
} 


main();
