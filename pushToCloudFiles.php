<?php
require_once("cloudfiles/cloudfiles.php");

class pushToCloudFiles
{
    /**
     * Fill in these two variables with your username
     * from RackSpace Cloud Files and your Api Key
     */
    protected $username=''; //Cloud Files username
    protected $apiKey=''; //Cloud Files Api Key

    protected static $totalBytesTransferred = 0;
    protected static $bytesTransferred = 0;
    protected static $fileSize = 0;
    protected static $lastPercent = 0;
    protected static $start_time;
    /**
     * Replace username and apiKey with your specific keys from
     * rackspace cloud api.
     */
    protected $fileCount = 0;
    protected $currentDate;
    protected $fileList;
    protected $conn;
    protected $auth;
    protected $bucket;
    /**
     * Command Line Arguments  
     */
    protected $bucketName;
    protected $sourceBackupDirectory;
    protected $verbose = FALSE;
    
    public function __construct()
    {
        $this->currentDate = date('m-d-Y');
    }

    public function init($argv = null)
    {
        $this->setupArgs($argv);
        $this->authenticate();
        $this->createBucket();
        $this->getFileList();
        $this->backupFileList();
    }

    public function setupArgs($argv)
    {
        if(empty($this->username) || empty($this->apiKey)){
            echo "username and apiKey must both be filled. Please edit the script and fill in these variables\n";
            exit();
        }
        $args = $this->parseArgs($argv);
        if (isset($args['commands']['help'])){
            echo "--bucket=foo for bucket name\n--source=/backup/dir/ for backup source\n-v for verbose options including progress bars\n";
            exit();
        }
        if(isset($args['commands']['bucket'])){
            $this->bucketName = $args['commands']['bucket'];
        } else {
            echo "--bucket is required\n";
            exit();
        }
        if(isset($args['commands']['source'])){
            $this->sourceBackupDirectory = $args['commands']['source'];
        } else {
            echo "--source is required\n";
            exit();
        }
        if(in_array('v', $args['flags'])){
            $this->verbose = TRUE;
        }
    }

    protected function parseArgs($args)
    {
        array_shift($args);
        $args = join($args,' ');

        preg_match_all('/ (--\w+ (?:[= ] [^-]+ [^\s-] )? ) | (-\w+) | (\w+) /x', $args, $match );
        $args = array_shift( $match );

        $ret = array(
                'input'    => array(),
                'commands' => array(),
                'flags'    => array()
                );

        foreach ( $args as $arg ) {
            // Is it a command? (prefixed with --)
            if ( substr( $arg, 0, 2 ) === '--' ) {
                $value = preg_split( '/[= ]/', $arg, 2 );
                $com   = substr( array_shift($value), 2 );
                $value = join($value);
                $ret['commands'][$com] = !empty($value) ? $value : true;
                continue;
            }
            // Is it a flag? (prefixed with -)
            if ( substr( $arg, 0, 1 ) === '-' ) {
                $ret['flags'][] = substr( $arg, 1 );
                continue;
            }
            $ret['input'][] = $arg;
            continue;
        }
        return $ret;
    }
    
    protected function authenticate()
    {
        $this->auth = new CF_Authentication($this->username, $this->apiKey);
        $this->auth->ssl_use_cabundle();
        $this->auth->authenticate();
        $this->conn = new CF_Connection($this->auth);
        if($this->verbose){
            echo "Authenticating...\n";
            $this->conn->set_write_progress_function("backupToCloud::write_callback");
        }

    }

    protected function createBucket()
    {
        try {
            $this->bucket = $this->conn->get_container($this->bucketName);
            if($this->verbose){
                echo "Using {$this->bucketName} bucket\n";
            }
        } catch (Exception $e){
            if($this->verbose){
                echo "Bucket does not exist creating {$this->bucketName}\n";
            }
            $this->bucket = $this->conn->create_container($this->bucketName);
            $e = null;
        }
    }

    protected function getFileList()
    {
        $this->fileList = $this->scandir_through($this->sourceBackupDirectory);
        if($this->verbose){
            echo "Filelist:\n";
            var_dump($this->fileList);
        }
        $this->fileCount = count($this->fileList);
        if($this->verbose){
            echo "{$this->fileCount} files to be backed up\n";
        }
    }

    protected function backupFileList()
    {
        foreach ($this->fileList as $filepath){
            if($this->verbose){
                echo "Transfering $filepath\n";
            }
            self::$fileSize = sprintf("%u", filesize($filepath));
            $file = $this->bucket->create_object(basename($filepath));
            $file->load_from_filename($filepath);
            $file = null;
            self::$bytesTransferred = 0;
            self::$fileSize = 0;
        }
    }
    
    protected function scandir_through($dir)
    {
        $items = glob($dir . '/*');
        for ($i = 0; $i < count($items); $i++) {
            if (is_dir($items[$i])){
            $add = glob($items[$i] . '/*');
            unset($items[$i]);
            $items = array_merge($items, $add);
            }
        }
        return $items;
    }

    public static function write_callback($bytes_transferred) 
    {
        self::$totalBytesTransferred +=$bytes_transferred;
        self::$bytesTransferred += $bytes_transferred;
        if (self::$bytesTransferred == self::$fileSize){
            self::$start_time = 0;
            return;
        }
        self::show_status(self::$bytesTransferred,self::$fileSize);
        return;
    }

    public static function show_status($done, $total, $size=30)
    {
        // if we go over our bound, just ignore it
        if($done > $total){
        return;
        }

        if(empty(self::$start_time)) self::$start_time=time();
        $now = time();

        $perc=(double)($done/$total);

        $bar=floor($perc*$size);

        $status_bar="\r[";
        $status_bar.=str_repeat("=", $bar);
        if($bar<$size){
        $status_bar.=">";
        $status_bar.=str_repeat(" ", $size-$bar);
        } else {
        $status_bar.="=";
        }

        $disp=number_format($perc*100, 0);

        $status_bar.="] $disp%  $done/$total";

        $rate = ($now-self::$start_time)/$done;
        $left = $total - $done;
        $eta = round($rate * $left, 2);

        $elapsed = $now - self::$start_time;

        $status_bar.= " remaining: ".number_format($eta)." sec.  elapsed: ".number_format($elapsed)." sec.";

        echo "$status_bar  ";

        flush();

        // when done, send a newline
        if($done == $total) {
        echo "\n";
        }

        }
}

$pushToCloudFiles = new pushToCloudFiles();
$pushToCloudFiles->init($argv);
