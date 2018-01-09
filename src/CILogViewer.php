<?php
/**
 * Author: Seun Matt (https://github.com/SeunMatt)
 * Date: 09-Jan-18
 * Time: 4:30 AM
 */
namespace CILogViewer;

defined('BASEPATH') OR exit('No direct script access allowed');
defined('APPPATH') OR exit('Not a Code Igniter Environment');


class CILogViewer {

    private $CI;

    private static $levelsIcon = [
        'INFO' => 'glyphicon glyphicon-info-sign',
        'ERROR' => 'glyphicon glyphicon-warning-sign',
        'DEBUG' => 'glyphicon glyphicon-exclamation-sign',
    ];

    private static $levelClasses = [
        'INFO' => 'info',
        'ERROR' => 'danger',
        'DEBUG' => 'warning',
    ];


    const LOG_LINE_START_PATTERN = "/((INFO)|(ERROR)|(DEBUG)|(ALL))[\s-\d:]+(-->)/";
    const LOG_DATE_PATTERN = "/(\d{4,}-[\d-:]{2,})\s([\d:]{2,})/";
    const LOG_LEVEL_PATTERN = "/^((ERROR)|(INFO)|(DEBUG))/";
    const FILE_PATH_PATTERN = APPPATH . "/logs/log-*.php";
    const LOG_FOLDER_PREFIX = APPPATH . "/logs";


    public function __construct() {
        $this->init();
    }


    private function init() {

        if(!function_exists(get_instance())) {
            throw new \Exception("This library works in a Code Igniter Project/Environment");
        }

        $this->CI = &get_instance();

        $destination = APPPATH . "/views/cilogviewer/logs.php";

        if(!file_exists($destination)) {
            $src = 'view/cilogviewer.php';
            file_put_contents($destination, file_get_contents($src));
        }
    }

    /*
     * This function will return the processed HTML page
     * and return it's content that can then be echoed
     *
     * @param $fileName optional base64_encoded filename of the log file to process.
     * @returns the parse view file content as a string that can be echoed
     * */
    public function showLogs($fileName = null) {

        //get the log files from the log directory
        $files = $this->getFiles();

        if(!is_null($fileName)) {
            $currentFile = self::LOG_FOLDER_PREFIX . "/". base64_decode($fileName);
        }
        else if(is_null($fileName) && !empty($files)) {
            $currentFile = self::LOG_FOLDER_PREFIX."/" . $files[0];
        }
        else {
            $data['logs'] = [];
            $data['files'] = [];
            $data['currentFile'] = "";
            return $this->CI->load->view("logs/index", $data, true);
        }

        $logs = $this->processLogs($this->getLogs($currentFile));
        $data['logs'] = $logs;
        $data['files'] =  $files;
        $data['currentFile'] = basename($currentFile);
        return $this->CI->load->view("logs/index", $data, true);
    }


    private function processLogs($logs) {

        $superLog = [];

        foreach ($logs as $log) {

            //get the logLine Start
            $logLineStart = $this->getLogLineStart($log);

            if(!empty($logLineStart)) {
                //this is actually the start of a new log and not just another line from previous log
                $level = $this->getLogLevel($logLineStart);
                array_push($superLog, [
                    "level" => $level,
                    "date" => $this->getLogDate($logLineStart),
                    "icon" => self::$levelsIcon[$level],
                    "class" => self::$levelClasses[$level],
                    "content" => $log
                ]);
            } else if(!empty($superLog)) {
                //this log line is a continuation of previous logline
                //so let's add them as extra
                $prevLog = $superLog[count($superLog) - 1];
                $extra = (array_key_exists("extra", $prevLog)) ? $prevLog["extra"] : "";
                $prevLog["extra"] = $extra . "<br>" . $log;
                $superLog[count($superLog) - 1] = $prevLog;
            } else {
                //this means the file has content that are not logged
                //using log_message()
                //they may be sensitive! so we are just skipping this
                //other we could have just insert them like this
//               array_push($superLog, [
//                   "level" => "INFO",
//                   "date" => "",
//                   "icon" => self::$levelsIcon["INFO"],
//                   "class" => self::$levelClasses["INFO"],
//                   "content" => $log
//               ]);
            }
        }

        return $superLog;
    }


    /*
     * extract the log level from the logLine
     * @param $logLineStart - The single line that is the start of log line.
     * extracted by getLogLineStart()
     *
     * @return log level e.g. ERROR, DEBUG, INFO
     * */
    private function getLogLevel($logLineStart) {
        preg_match(self::LOG_LEVEL_PATTERN, $logLineStart, $matches);
        return $matches[0];
    }

    private function getLogDate($logLineStart) {
        preg_match(self::LOG_DATE_PATTERN, $logLineStart, $matches);
        return $matches[0];
    }

    private function getLogLineStart($logLine) {
        preg_match(self::LOG_LINE_START_PATTERN, $logLine, $matches);
        if(!empty($matches)) {
            return $matches[0];
        }
        return "";
    }

    /*
     * returns an array of the file contents
     * each element in the array is a line in the file
     *
     * */
    private function getLogs($filename) {
        return file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }


    /*
     * This will get all the files in the logs folder
     * It will reverse the files fetched and
     * make sure the latest log file is in the first index
     *
     * @param boolean. If true returns the basenames of the files otherwise full path
     * @returns array of file
     * */
    private function getFiles($basename = true)
    {

        $files = glob(self::FILE_PATH_PATTERN);

        $files = array_reverse($files);
        $files = array_filter($files, 'is_file');
        if ($basename && is_array($files)) {
            foreach ($files as $k => $file) {
                $files[$k] = basename($file);
            }
        }
        return array_values($files);
    }



}