<?

class fileUpload {

    protected $upload;
    protected $attributes;

    function __construct($attributes = false) {
        $this->attributes = $attributes;
    }

    public function setUpload( $upload ) {
        $this->upload = $upload;
    }
    
    public static function validate($filename,&$metadata,&$state) {
        return true;
    }

    public function makeDirectory($path) {
        if ($path===false) return false;
        $subDirs = array();
        for( $depth=10; $depth>0; $depth-- ) {
            array_unshift( $subDirs, basename($path) );
            $path = dirname($path);
        }

        if (!is_dir($path)) return false;
        foreach( $subDirs as $dir ) {
            $path .= DIRECTORY_SEPARATOR.$dir;
            if (!is_dir($path)) {
                if (!mkdir( $path, 0760 )) return false;
            }
        }
        return true;
    }
    
    public function remove() {
        $finalLocation = $this->location();
        @unlink($finalLocation);
        @unlink($finalLocation.'.info');
        return true;
    }

    public function exists() {
        return file_exists($this->location());
    }
    
    public function store($tmpLocation, $info) {
        
        $finalLocation = $this->location();
        $infoLocation = $finalLocation.'.info';
        
        // move the file to the desired location
        if ($finalLocation===false) return 'No location to store file to.';

        if (!$this->makeDirectory(dirname($finalLocation))) return 'Unable to find directory on server to save file to.';

        @unlink($finalLocation);
        @unlink($infoLocation);
        if (!@rename($tmpLocation,$finalLocation)) {
            return 'Unable to store uploaded file.';
        }
        
        // Store the metadata alongside the file
        file_put_contents( $infoLocation, serialize($info) );
        
        if (method_exists($this,'process')) {
            $result = $this->process( $info );
            if ($result !== true) {
                return $result;
                @unlink($finalLocation);
                @unlink($infoLocation);                
            }
        }
        
        return true;
    }

    private static Function getFilePreview($location, $metadata, $extraMarkup='') {
        return
            sprintf('<div class="existingFileDescription">%s (%s)</div>',htmlspecialchars($metadata['name']),formatBytes($metadata['size'])).
            '<button type="button" class="clickToEdit">Replace</button>'.
            '<button type="button" class="clickToRemove">Remove</button>'.
            $extraMarkup
        ;
    }

    public function displayLivePreview($filename,$metadata) {
        echo self::getFilePreview($filename,$metadata);
        return true;
    }
    
    public function loadMetadata() {
        if (isset($this->metadata)) return $this->metadata;

        $finalLocation = $this->location();
        $infoLocation = $finalLocation.'.info';

        if (!file_exists($infoLocation)) return false;

        $metadata = file_get_contents($infoLocation);
        if ($metadata===false) return false;
            
        $metadata = @unserialize($metadata);
        if (!is_array($metadata)) return false;

        $this->metadata = $metadata;
        return $metadata;
    }

    public function displayPreview() {
        $metadata = $this->loadMetadata();
        if ($metadata===false) return false;
        $finalLocation = $this->location();
            
        $extraMarkup = '';
        if (strlen(file_exists($finalLocation))) {
            $downloadUrl = $this->downloadUrl( 'medium' );
            if (strlen($downloadUrl)) $extraMarkup = '<a target="_blank" href="'.$downloadUrl.'&download"><button type="button" class="download">Download</button></a>';
        }
        echo self::getFilePreview($finalLocation,$metadata,$extraMarkup);
        return true;
    }

    function downloadUrl( $longevity='' ) {
        if (!$this->exists()) return false;

        $spec = 'download:'.time().':'.$longevity.':'.$this->packAttributes();
        
        $this->signFileSpec($spec);
        
        $url='/fileDownload.php?spec='.rawurlencode($spec);
        return $url;    
    }

    public static function download($spec) {
        list($type,$time,$longevity,$packedAttributes) = explode(':',$spec,4);

        // Longevity can either be a number of seconds or a lookup to pre-defined longevity values
        if (preg_match('/^[0-9]+$/',$longevity)) {
            $longevity = (int)$longevity;
        } else {
            if (!strlen($longevity) || !isset(static::$linkLongevity[$longevity])) {
                list($longevity,$notUsed) = each(static::$linkLongevity);
                reset(static::$linkLongevity);
            }
            $longevity = static::$linkLongevity[$longevity];
        }
        
        if (($time+$longevity) < time() ) {
            echo "Download link has expired";
            exit;
        }
                
        $class = get_called_class();
        $object = new $class();
        $object->unpackAttributes($packedAttributes);
        $metadata = $object->loadMetadata();
        
        if ($type=="download") {
            $name = isset($metadata['name'])?$metadata['name']:'unknown';
            header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($name));
        }

        readfile($object->location());
        exit;
    }
    

    public function signFileSpec( &$spec ) {
        $class = get_class($this);
        $secret = getLocalSecret( $class.'FileSpec' );
        
        // Put the class name on the front
        $spec = $class.':'.$spec;
        // Put the signature on the end
        $spec .= ':'.hash_hmac('sha256',$spec,$secret);
        return $spec;
    }
    
    // This function returns false if the spec is not valid
    // Otherwise it returns the class name.
    // It removes the class name and the signature from the spec string
    public static function validateFileSpec( &$spec ) {
        
        // Get the signature off the end
        $pos = strrpos( $spec, ':' );
        $signature = substr( $spec, $pos+1);
        // remove the signature
        $spec = substr($spec,0,$pos);

        // Get the class name off the front
        $pos = strpos( $spec, ':' );
        $class = substr($spec,0,strpos( $spec, ':' ));
        
        $secret = getLocalSecret( $class.'FileSpec' );
        if (!hash_equals( hash_hmac('sha256',$spec,$secret), $signature )) return false;

        // remove the class name from the front
        $spec = substr($spec,$pos+1);
        // return the class name
        return $class;

    }
    
    // This simple implementation assumes that attributes are just a string...
    protected function packAttributes() {
        return $this->attributes;
    }

    protected function unpackAttributes($attributes) {
        return $this->attributes = $attributes;
    }
    
}
