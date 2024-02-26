<?

if (!defined('SECRET')) die('SECRET must be defined as a constant');

global $extraScripts;
$extraScripts[] = '/javascript/core/asyncUpload.js';

if (!function_exists('base64URLEncode')) { 
    function base64URLEncode($input) {
        return strtr(base64_encode($input), '+/=', '._-');
    }

    function base64URLDecode($input) {
        return base64_decode(strtr($input, '._-', '+/='));
    }
}

class formAsyncUpload {
    
    private $name;
    private $idHolder;
    public $id = null;
    private $tmpLocation = '';
    private $url;
    private $secret;
    private $stage;
    private $metadata = false;
    private $canRead = false;
    private $canWrite = false;
    private $makeDirectoryDepth = 0;
    private $state = array();
    private $storeWidth = false;
    private $stateNeedsSave = false;
    private $stateLoaded = false;
    private $attributes = false;
    private $fileObject = false;
    
    private $lastError = '';
    private static $errorCodeLookup = array(
        UPLOAD_ERR_INI_SIZE     => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE    => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
        UPLOAD_ERR_PARTIAL      => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE      => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR   => 'Server is missing a temporary folder to store uploads in.',
        UPLOAD_ERR_CANT_WRITE   => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION    => 'A PHP extension stopped the file upload.',
    );
    
    private static $asyncUploads = array();

    // Returns a file size limit in bytes based on the PHP upload_max_filesize
    // and post_max_size
    public static function getMaxUploadSize() {
      static $max_size = -1;

      if ($max_size < 0) {
        // Start with post_max_size.
        $post_max_size = self::parseSize(ini_get('post_max_size'));
        if ($post_max_size > 0) {
          $max_size = $post_max_size;
        }

        // If upload_max_size is less, then reduce. Except if upload_max_size is
        // zero, which indicates no limit.
        $upload_max = self::parseSize(ini_get('upload_max_filesize'));
        if ($upload_max > 0 && $upload_max < $max_size) {
          $max_size = $upload_max;
        }
      }
      return $max_size;
    }

    private static function parseSize($size) {
      $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
      $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
      if ($unit) {
        // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
      }
      else {
        return round($size);
      }
    }

    public static function deleteAll($group='_default') {
        if (!isset(self::$asyncUploads[$group])) return false;
        foreach (self::$asyncUploads[$group] as $name=>$upload) {
            $upload->delete();
        }
    }

    public static function setAttributesAll($attributes,$group='_default') {
        if (!isset(self::$asyncUploads[$group])) return false;
        foreach (self::$asyncUploads[$group] as $name=>$upload) {
            $upload->setAttributes($attributes);
        }
    }

    public function setAttributes($attributes) {
        $this->attributes = $attributes;
    }
    
    // This is called in step 1 and step 3, but only actually does anything in step 3
    // This actually puts the files that were uploaded in their final location
    public static function storeAll($group='_default') {
        
        if (!isset(self::$asyncUploads[$group])) return false;
        $errors = array();

        foreach (self::$asyncUploads[$group] as $name=>$upload) {
            // if we are not in step 3 store() will just return true without doing anything
            $result = $upload->store();
            if ($result !== true) {
                $errors[$name] = $result;
            }
        }
        
        return $errors;
    }
    
    function checkFileId( $signedId ) {
        list($name,$time,$id,$hmac) = explode(':',$signedId.':::');

        $age = time()-$time;
        if ($age>86400 || $age<1) return 'The file upload token has expired';
        if (!strlen($hmac)) return 'The file upload token is not signed';
        
        $desiredHmac = hash_hmac('sha256',$name.':'.$time.':'.$id,$this->secret);
        if (!hash_equals( $desiredHmac, $hmac )) return 'The file upload token is invalid';

        $this->id=$id;

        if (isset($this->name)) {
            if ($this->name !== $name) return 'This upload token is for a different upload';
        }
        else $this->name = $name;
                
        return true;
    }
    
    function __construct( $name='', $classFile='', $group='_default' ) {
        // This code can be run at three different stages
        // 1. When rendering the original form containing the asyncUpload
        // 2. When the file is being uploaded
        // 3. When the form is submitted and we need to do something with the uploaded file

        if (!strlen($name)) {
            $this->stage = 2;
        } else {
            $this->idHolder = 'asyncyUpload_'.$name.'_id';
            $this->stage = isset($_REQUEST[$this->idHolder]) ? 3:1;
        }
        
        // During stage 2 this object is instantiated without a name - in this case we get the name (and other stuff)
        // from the asyncUploadId POST field
        if ($this->stage == 1) {
            // In all other stages we have to make the secret by creating the URL of this page exactly
            // as it will appear in the referer header when the file is uploaded
            $proto = isset($_SERVER['HTTP_X_FORWARDED_PROTO'])?$_SERVER['HTTP_X_FORWARDED_PROTO']:$_SERVER['REQUEST_SCHEME'];
            // Use REQUEST_URI if we have it otherwise fall back to SCRIPT_NAME
            // see https://stackoverflow.com/questions/279966/php-self-vs-path-info-vs-script-name-vs-request-uri
            $requestUri = isset($_SERVER["REQUEST_URI"])? explode('?',$_SERVER["REQUEST_URI"])[0] : $_SERVER["SCRIPT_NAME"];
            $this->url = $proto.'://'.$_SERVER['HTTP_HOST'].$requestUri;

            $this->name = $name;
        } else {
            // In this case we include the referer in the secret
            $referer = isset($_SERVER["HTTP_REFERER"])?$_SERVER["HTTP_REFERER"]:'';
            $this->url = explode('?',$referer)[0];
        }

        $this->secret = SECRET.'_'.$this->url.'_asyncUpload_id';
        
        if ($this->stage == 2) {
            // ============================
            // STAGE 2
            // ============================

            // Nothing to do here - it is all done in processUpload
            
        } else {
            // ============================
            // STAGE 1 & 3
            // ============================
            
            if ($this->stage == 3) {
                // Here we are running when the form is submitted after the file should have been uploaded - stage 3
                // ============================
                // STAGE 3
                // ============================
                $this->checkFileId($_REQUEST[$this->idHolder]);   
            } else {
                // ============================
                // STAGE 1
                // ============================
                $this->id = base64URLEncode(openssl_random_pseudo_bytes( 32 ));
                if (!$classFile) $classFile = CORE_DIR.'/fileUpload.php';
                $this->setState('classFile',$classFile);
            }

            if (!isset(self::$asyncUploads[$group])) self::$asyncUploads[$group] = array();
            self::$asyncUploads[$group][$name] = $this;
        }
    }
    
    function __destruct() {
        // Save the object state if there is any
        $this->saveState();
    }

    function getState($key) {
        if (!$this->stateLoaded) $this->loadState();
        if (!isset($this->state[$key])) return null;
        return $this->state[$key];
    }
    
    function setState($key,$value,$forceSave=false) {
        if (!$this->stateLoaded) $this->loadState();
        if (!is_array($this->state)) $this->state = array();
        
        if (!$forceSave && isset($this->state[$key]) && $this->state[$key]===$value) return false;

        $this->state[$key]=$value;
        $this->stateNeedsSave=true;
        
        return true;
    }
    
    function saveState() {
        if (!$this->stateNeedsSave) return false;
        if (!array_length($this->state)) return false;
        
        file_put_contents( $this->tmpLocation().'.state', serialize($this->state) );
    }
    
    function loadState() {
        if ($this->stateLoaded) return true;
        
        $stateFilename = $this->tmpLocation().'.state';
        if ( file_exists($stateFilename) ) {
            $this->state = @unserialize( file_get_contents( $stateFilename ) );
        } else {
            $this->state = array();
        }
        $this->stateLoaded=true;
    }
    
    private function tmpLocation() {
        if (strlen($this->tmpLocation)) return $this->tmpLocation;

        if (!$this->url) return false;
        $scriptUri = substr($this->url,strpos($this->url,'/',8)+1);
        $scriptUri = substr($scriptUri,0,strpos($scriptUri,'?'));

        $this->tmpLocation = SITE_BASE_DIR.'/data/tmp/uploads/'.preg_replace('/[^a-zA-Z0-9.]+/','_',$scriptUri).'_'.$this->id;
        return $this->tmpLocation;
    }

    private function loadClass() {
        static $included;
        if (!isset($included)) $included = array();
        
        if (!isset($this->state['classFile'])) $this->loadState();
        if (!isset($this->state['classFile'])) return false;
        
        $classFile = $this->state['classFile'];
        
        if (isset($included[$classFile])) return $included[$classFile];
        
        // The classfile can return the name of the main file object    
        $class = include_once($classFile);
    
        // but if it doesn't try and guess it from the filename
        if($class===1) {
            $class=preg_replace('/(\\.class)?(\\.php)$/i','',basename($classFile));
        }
        
        $included[$classFile] = $class;

        return $class;
    }
    
    function display() {
        // Here we are setting things up to render the form

        // ============================
        // STAGE 1
        // ============================
        // N.B. this also runs at stage 3 if we are saving the uploaded file AND displaying a new editting interface at the same time
        // ... which we usually are.
        
        $token = sprintf('%s:%s:%s',$this->name,time(),$this->id);
        $token .= ':'.hash_hmac('sha256',$token,$this->secret);

        $this->setState('mode','start',true);
       
        // echo implode(':',array($this->name,time(),$id,$this->secret));

        echo '<div class="asyncUploadContainer">';

        $maxUploadSize = self::getMaxUploadSize();
        // The upload size might actually be the POST size - the POST includes some other ancilliary data so take a bit off this
        // just to be on the safe side!
        $maxUploadSize -= 200;
        
         echo '<div class="editting">';
        // The file input gets moved out of whatever form it is in into its own form
        // The data-asyncuploadid has to be all lowercase - or else the browser just converts it to lower case
        printf('<input type="file" name="file" data-asyncuploadid="%s"/>',$token);
        printf('<div class="maxSizeWarning">Maximum upload size is %s </div>',htmlspecialchars(formatBytes($maxUploadSize,0,true)));
        
        // The hidden input stays in the original form and is submitted when the form is submitted
        printf('<input type="hidden" name="%s" value="%s"/>',htmlspecialchars($this->idHolder),$token);
        echo '<a href="#" class="clickToCancel">Cancel edit and keep existing file</a>';
        echo '</div>';
        
        echo '<div class="existing">';
        $file = $this->getFileObject();
        if ($file && $file->exists()) {
            $result = $file->displayPreview();
            if (!$result) echo '<button type="button" class="clickToEdit">Upload new file</button>';
        }
        echo '</div>';
        echo '</div>';
    }

    public function processUpload( $mode, $uploadId ) {
        // In this block we are processing an uploaded file
        // ============================
        // STAGE 2
        // ============================

        if (!isset($uploadId) || strlen($uploadId)<52) {
            return array( 'ERROR', 'Couldn\'t find upload ID' );
        }
        
        $result = $this->checkFileId( $uploadId );
        if ($result !== true) return array('ERROR', $result);

        // Handle when the user clicks the "remove" button to remove existing file
        // Also handle when the user changes their mind after clicking remove and decides to keep the existing file
        if ($mode==='remove' || $mode==='keep') {
            $this->setState('mode',$mode);
            @unlink($this->tmpLocation);
            return array( 'OK', '' );
        }

        // Check that a file has actually been uploaded
        if (!isset($_FILES['file'])) return array('ERROR','No file uploaded');
        $errorCode = $_FILES['file']['error'];
        
        if (isset(self::$errorCodeLookup[$errorCode])) return array('ERROR',self::$errorCodeLookup[$errorCode]);
        
        // OK... so a file has been uploaded -
        // Save the file in a temporary location
        if (!isset($_FILES['file'])) return array('ERROR','No file uploaded');
        $errorCode = $_FILES['file']['error'];

        // load the class to see if there is a validation function (this also loads the state)
        $fileClass = $this->loadClass();
        $fileObject = $this->getFileObject();
        
        $result = true;

        $info = $_FILES['file'];
        $info['uploadedAt'] = time();
        $info['uploadedBy'] = isset($GLOBALS['USER_ID']) ? $GLOBALS['USER_ID'] : 0;
        
        if (method_exists($fileObject,'validate')) $result = call_user_func_array($fileClass.'::validate',array($_FILES['file']['tmp_name'], &$info, &$this->state) );
        
        if ($result !== true) {
            if ($result === false) return array('ERROR','The file was not valid');
            else return array('ERROR',$result);
        }
        
        if (!move_uploaded_file($_FILES['file']['tmp_name'],$this->tmpLocation)) {
            return array('ERROR','Unable to store uploaded file.');
        }
        
        // If there is a .moved file still there from a previous upload, then delete this
        @unlink($this->tmpLocation.'.moved' );
        
        file_put_contents( $this->tmpLocation.'.info', serialize($info) );
        if (!file_exists( $this->tmpLocation.'.info' )) {
            return array('ERROR','Unable to store uploaded file metadata.');
        }
        
        ob_start();
        $fileObject->displayLivePreview($this->tmpLocation,$_FILES['file']);
        $message = ob_get_clean();
        
        // set the mode to uploaded
        $this->setState('mode','uploaded');
        return array('OK',$message);
    }
        
    public function getFileObject() {
        if ($this->fileObject) return $this->fileObject;
        
        $class = $this->loadClass();
        
        if ($class===false) return false;
        
        $this->fileObject = new $class($this->attributes);
        $this->fileObject->setUpload($this);
        
        return $this->fileObject;
    }

    public function store() {
        // In this block we are storing the file in its final location
        // ============================
        // STAGE 3
        // ============================

        if ($this->stage != 3) return true;        

        $this->loadState();

        // The state file is missing - this can happen in some scenarios when the user hits reload after the state file has been removed
        if (!array_length($this->state)) return true;
        if (!isset($this->state['mode'])) $this->state['mode']='';
        
        $fileObject = $this->getFileObject();
        
        // If the fileObject comes back as false that means we couldn't find the state file for this upload
        // This can happen if the form is reloaded
        // This is OK - it just means there is nothing to do.
        if ($fileObject===false) return true;
        
        
        if ($this->state['mode']=='remove') {
            $return = $fileObject->remove();
        } else if ($this->state['mode']=='uploaded') {
            // This may be a re-store - see if there is a ".moved" file
            if (file_exists($this->tmpLocation.'.moved')) {
                $return=true;
            } else {
                $return = $fileObject->store( $this->tmpLocation, $this->getMetadata(), $this->state );
                if ($return === true) {
                    // Leave a ".moved" file there so we know we successfully stored this file
                    // If the user reloads and this causes a re-store then we can safely ignore that.
                    // Put the place we moved the file to in the ".moved" file in case anyone wants to check
                    file_put_contents($this->tmpLocation.'.moved',time());            
                }
            }
        } else {
            $return = true;
            if (method_exists($fileObject,'refresh')) $fileObject->refresh();
        }
        
        @unlink($this->tmpLocation);
        @unlink($this->tmpLocation.'.state');
        @unlink($this->tmpLocation.'.info');
                
        return $return;
    }
    
    function getMetadata() {
        if ($this->metadata!==false) return $this->metadata;

        $this->tmpLocation();
        if (!file_exists($this->tmpLocation.'.info')) return false;

        $metadata = file_get_contents($this->tmpLocation.'.info');
        if ($metadata===false) return false;
        $this->metadata = @unserialize($metadata);
        
        if ( $this->metadata === false ) return false;
        return $this->metadata;
    }
    
    function originalFilename() {
        if ($this->metadata===false || !isset($this->metadata['name'])) return false;
        return $this->metadata['name'];
    }

    function type() {
        if ($this->metadata===false || !isset($this->metadata['type'])) return false;
        return $this->metadata['type'];
    }

    function contents() {
        if (!file_exists($this->tmpLocation())) return false;
        return file_get_contents( $this->tmpLocation );
    }
    
    function readWrite( $read=true, $write=true ) {
        $this->canRead = $read;
        $this->canWrite = $write;
    }

    function read( $read=true ) {
        $this->canRead = $read;
    }

    function write( $write=true ) {
        $this->canWrite = $write;
    }

    function getLastError() {
        return $this->lastError;
    }
    
    function delete() {
        $fileObject = $this->getFileObject();
        if (is_object($fileObject)) $fileObject->remove();
    }
    
}
