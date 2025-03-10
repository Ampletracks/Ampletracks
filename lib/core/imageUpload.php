<?

include_once(CORE_DIR.'/fileUpload.php');

class imageUpload extends fileUpload {

    private $thumbnailSize;
    private $metadata;
    
    public static function infoLocation($base) {
        return $base.'/info.serialized';
    }
    
    public static function validate($filename,&$metadata, &$state) {
        // Check that the upload is actually an image
        $fh = finfo_open(FILEINFO_MIME_TYPE);
        if (!is_resource($fh)) return 'Problem loading finfo extension';
        
        $mimeTypeLookup = array(
            'image/gif' => IMG_GIF ,
            'image/jpeg'=> IMG_JPG ,
            'image/png' => IMG_PNG ,
            'image/vnd.wap.wbmp'    => IMG_WBMP,
            'image/xpm' => IMG_XPM ,
        );
        // These two aren't set in some older versions of PHP
        if (defined('IMG_BMP')) $mimeTypeLookup['image/bmp'] = IMG_BMP;
        if (defined('IMG_WEBP')) $mimeTypeLookup['image/webp'] = IMG_WEBP;
        
        // Check that the file is actually an image
        $mimeType = finfo_file($fh,$filename);
        if ($mimeType=='image/jpg') $mimeType='image/jpeg';
        if (!isset($mimeTypeLookup[$mimeType])) return 'The uploaded file is not an image';
        
        // See if this PHP build supports this image type
        $supportedImages = imagetypes();
        if (!( $supportedImages & $mimeTypeLookup[$mimeType] )) return 'The image file format is not supported';
        
        $size = getimagesize($filename);
        if (!is_array($size) || count($size)<2) return 'Unexpected error determining image dimensions';
        
        list($width,$height) = $size;
        $dimsFromState = isset($state['dims'])?$state['dims']:array();
        
        // Check it conforms to the minimum and maximum size limit
        foreach( array('minDims','maxDims','minAspectRatio','maxAspectRatio') as $thing ) {
            $$thing = '';
            if (isset($dimsFromState[$thing]) && strlen($dimsFromState[$thing])) $$thing = $dimsFromState[$thing];
            else if ( isset(static::$$thing) && strlen(static::$$thing) ) $$thing = static::$$thing;
        }
        
        if (strlen($minDims)) {
            list($minWidth,$minHeight) = explode('x',$minDims.'x0');
            if ($minWidth==='-') $minWidth=0;
            if ($minHeight==='-') $minHeight=0;
            if ($width<$minWidth || $height<$minHeight) {
                $error = '';
                if ($minWidth>0) $error .= htmlspecialchars($minWidth).' pixels wide';
                if ($minHeight>0) {
                    if (strlen($error)) $error .= ' and ';
                    $error .= htmlspecialchars($minHeight).' pixels high';
                }
                return 'The image is too small - it must be at least '.$error;
            }
        }
        
        // Check it conforms to the maximum size limit
        if (strlen($maxDims)) {
            list($maxWidth,$maxHeight) = explode('x',$maxDims.'x0');
            if ($maxWidth==='-') $maxWidth=99999999;
            if ($maxHeight==='-') $maxHeight=99999999;
            if ($width>$maxWidth || $height>$maxHeight) {
                $error = '';
                if ($maxWidth!=99999999) $error .= htmlspecialchars($maxWidth).' pixels wide';
                if ($maxHeight!=99999999) {
                    if (strlen($error)) $error .= ' or ';
                    $error .= htmlspecialchars($maxHeight).' pixels high';
                }
                return 'The image is too big - it must not be larger than '.$error;
            }
        }
        
        $aspectRatio = $width/$height;
        // Check it conforms to the minimum aspect ratio limit
        if ( strlen($minAspectRatio) ) {
            list($widthRatio,$heightRatio) = explode(':',$minAspectRatio.':1');
            $minRatio = $widthRatio/$heightRatio;
            if ($aspectRatio < $minRatio) return 'The image is too tall. The minimum aspect ratio allowed is '.htmlspecialchars($minAspectRatio);
        }
        
        // Check it conforms to the maximum proportion limit
        if ( strlen($maxAspectRatio) ) {
            list($widthRatio,$heightRatio) = explode(':',$maxAspectRatio.':1');
            $maxRatio = $widthRatio/$heightRatio;
            if ($aspectRatio > $maxRatio) return 'The image is too wide. The maximum aspect ratio allowed is '.htmlspecialchars($maxAspectRatio);
        }
        
        // Override the mime type supplied by the client with the one we worked out above
        $metadata['type']=$mimeType;
        $metadata['width']=$width;
        $metadata['height']=$height;
        return true;
    }

    protected function getImageSize($size=null) {
        if (is_null($size)) return false;
        
        // If this is called with an empty size then get the first size
        if ($size==='') {
            if (function_exists('array_key_first')) {
                $dims = $this->sizes[ array_key_first($this->sizes) ];
            } else {
                list( $notUsed, $dims ) = each($this->sizes);
                reset($this->sizes);
            }
            return $dims;
        }
        
        if (isset($this->sizes[$size]) && strlen($this->sizes[$size])) return $this->sizes[$size];
        return false;
    }
    
    protected function getThumbnailSize() {
        if ($this->thumbnailSize) return $this->thumbnailSize;
        
        $this->thumbnailSize = $this->getImageSize('thumbnail');
        if ($this->thumbnailSize===false) $this->thumbnailSize = '100x100x-1';

        return $this->thumbnailSize;
    }
    
    protected static function scaleImage($inputFile, $dims, $outputFile='') {
        
        list($outWidth,$outHeight,$compression,$resizeMode,$backgroundColor) = explode('x',$dims.'xxxx');
        if ($compression==='') $compression=-1;
        
        $imageIn = imagecreatefromstring(file_get_contents($inputFile));
        $inWidth  = imagesx($imageIn);
        $inHeight = imagesy($imageIn);
        
        if ($dims==='original') {
            // this is a special case which means just store the file as-is without scaling
            $outWidth = $inWidth;
            $outHeight = $inHeight;
            if ($outputFile==='') {
                $return = file_get_contents($inputFile);
            } else {
                $return = copy( $inputFile, $outputFile ); 
            }
        } else {
            $resizedWidth = $outWidth;
            $resizedHeight = $outHeight;
            $outXOffset = 0;
            $outYOffset = 0;
            $inXOffset = 0;
            $inYOffset = 0;
            
            if ($resizeMode=='crop') {
                // crop - reduce the picture as much as possible whilst ensuring it fills the resulting image
                // if the input image is not the right aspect ratio then this will result in part of the image being cropped
                $scale = max( $outWidth/$inWidth, $outHeight/$inHeight );
                $inXOffset = (($inWidth*$scale - $outWidth)/2)/$scale;
                $inYOffset = (($inHeight*$scale - $outHeight)/2)/$scale;
                $inWidth = $outWidth/$scale;
                $inHeight = $outHeight/$scale;
            } else if ($resizeMode=='fill') {
                //fill - reduce the picture so it fits in the desired dimensions filling the empty space that results
                // if the input image is not the right aspect ratio then this will result in bands either above and below, or to left and right
                $scale = min( $outWidth/$inWidth, $outHeight/$inHeight );
                $resizedWidth = $inWidth*$scale;
                $resizedHeight = $inHeight*$scale;
                $outXOffset = floor( ($outWidth-$resizedWidth)/2 );
                $outYOffset = floor( ($outHeight-$resizedHeight)/2 );
            } else {
                // fit - reduce the picture so it fits in the desired dimensions, but do not pad the empty space
                // if the input image is not the right aspect ratio then this will result in the output image not have the requested dimensions
                $scale = min( $outWidth/$inWidth, $outHeight/$inHeight );
                $resizedWidth = $outWidth = $inWidth*$scale;
                $resizedHeight = $outHeight = $inHeight*$scale;
            }

            $imageOut = imagecreatetruecolor($outWidth, $outHeight);
            
            if ($resizeMode=='fill') {
                imagealphablending( $imageOut, false );
                imagesavealpha( $imageOut, true );
                if (!isset($backgroundColor) || !strlen($backgroundColor)) $background = imagecolorallocatealpha($imageOut, 0, 0, 0, 127);
                else {
                    $background = str_replace('#','',trim($backgroundColor));
                    if (strlen($background)==3) $background = preg_replace('/(.)/','\\1\\1',$background);
                    $background.='000000';
                    list($r, $g, $b) = sscanf($background, "%02x%02x%02x");
                    $background = imagecolorallocate($imageOut, $r, $g, $b);
                }
                imagefill($imageOut, 0, 0, $background);
            }
            imagecopyresampled($imageOut, $imageIn, $outXOffset, $outYOffset, $inXOffset, $inYOffset, $resizedWidth, $resizedHeight, $inWidth, $inHeight);
            
            if ($outputFile==='') {
                $stream = fopen('php://memory','r+');
                imagepng($imageOut,$stream);
                rewind($stream);
                $return = stream_get_contents($stream);
                fclose($stream);
            } else {
                $return = imagepng($imageOut, $outputFile, $compression, PNG_ALL_FILTERS);
            }
        }
        
        return array($return,$outWidth,$outHeight);
    }
    
   public function remove() {
        $finalLocation = $this->location();
        if (is_dir($finalLocation)) {
            $dir = opendir($finalLocation);
            while( $file=readdir($dir) ) {
                if (strpos($file,'.')===0) continue;
                @unlink($finalLocation.'/'.$file);
            }
            @rmdir($finalLocation);
        }
        return true;
    }

    public function store($tmpLocation, $metadata) {
        
        $finalLocation = $this->location();
        
        // move the file to the desired location
        if ($finalLocation===false) return 'No location to store file to.';
        
        if (!$this->makeDirectory($finalLocation)) return 'Unable to find directory on server to save file to.';

        $infoLocation = self::infoLocation($finalLocation);
        
        $this->getImageSize();
        // clear away any existing files
        foreach( $this->sizes as $name=>$dims ) {
            @unlink($finalLocation.'/'.$name) ? 1 : 0;
        }
        @unlink($infoLocation);
        
        // Check that we managed to delete all the files
        foreach( $this->sizes as $name=>$dims ) {
            if (file_exists($finalLocation.'/'.$name)) return 'Unable to remove existing files to make way for new upload';
            
        }
        if (file_exists($infoLocation)) return 'Unable to remove existing files to make way for new upload';
        
        foreach( $this->sizes as $name=>$dims ) {
            if (strlen($dims)) self::scaleImage($tmpLocation, $dims, $finalLocation.'/'.$name);
        }
        
        // Store the metadata alongside the file
        file_put_contents( $infoLocation, serialize($metadata) );
        
        return true;
    }

    public function displayLivePreview($location,$metadata) {

        list($imageData,$width,$height) = self::scaleImage($location, $this->getThumbnailSize(), '');
        
        $url = 'data: image/png;base64,'.base64_encode($imageData);
        
        $extraMarkup = '<div class="note">'.cms('The change to this image is not final until you save this page').'</div>';
        self::displayPreviewMarkup( $url, $width, $height, $extraMarkup );
        return true;
    }
    
    public static function displayPreviewMarkup( $url, $width, $height, $extraMarkup='' ) {
        echo
            '<div class="existingFileDescription">'.
            sprintf('<img src="%s" width="%s" height="%s" class="resizeOnLoad"/>',htmlspecialchars($url),$width,$height).
            '</div>'.
            '<button type="button" class="clickToEdit">Replace</button>'.
            '<button type="button" class="clickToRemove">Remove</button>'.
            $extraMarkup
        ;
    }

    public function loadMetadata($dir='') {
        if (isset($this->metadata)) return $this->metadata;
        if (!strlen($dir)) $dir = $this->location();
        return @unserialize(file_get_contents(self::infoLocation($dir)));
    }
    
    public function displayPreview() {

        list($url,$width,$height) = $this->downloadUrl('short','thumbnail',false);

        $extraMarkup = '';
        if (strlen($this->getImageSize('original')) && file_exists($this->getFileForSize('original'))) {
            $downloadUrl = $this->downloadUrl( 'medium', 'original', true );
            if (strlen($downloadUrl)) $extraMarkup = '<a target="_blank" href="'.$downloadUrl.'&download"><button type="button" class="download">Download</button></a>';
        }
        
        self::displayPreviewMarkup( $url, $width, $height, $extraMarkup );
        
        return true;
    }

    // Returns the file size of the best available image
    public function size() {
        $dir = $this->location();
        if (!is_dir($dir)) return false;
        $size = $this->getBestSize();
        return filesize($dir.'/'.$size);
    }

    function getBestSize() {
        if (strlen($this->getImageSize('original')) && file_exists($this->getFileForSize('original'))) {
            return 'original';
        }
        $bestArea=0;
        $bestSize='';
        foreach( $this->sizes as $name=>$dims ) {
            if (!strlen($dims)) continue;
            if ($name=='original') continue;
            if (!file_exists($this->getFileForSize($name))) continue;

            list($x,$y) = explode( 'x', $dims );
            $area = $x*$y;
            if ($area > $bestArea) {
                $bestArea = $area;
                $bestSize = $name;
            }
        }
        return $bestSize;
    }

    function downloadUrl( $longevity='', $size='best', $justUrl=true ) {

        if ($size=='best') $size = $this->getBestSize();

        $downloadDetails = $this->display( $size,$longevity,true );
        if ($justUrl) return $downloadDetails[0];
        return $downloadDetails;
    }
    
    function display($size='',$longevity='', $dontPrint=false ) {
        if ($size=='thumbnail') {
            $dims = $this->getThumbnailSize();
        } else {
            $dims = $this->getImageSize($size);
            
            if ($dims===false) {
                echo "Unknown image size";
                return false;
            }
        }
        if (!$this->exists()) {
            if ($dims==='original') $dims = '100x100';
            list($width,$height) = explode('x',$dims);
            if ($dontPrint) return array( static::$missingImage, $width, $height );
            printf('<img src="%s" width="%s" height="%s" />',htmlspecialchars(static::$missingImage),htmlspecialchars($width),htmlspecialchars($height));
            return false;
        }
        if ($dims==='original') {
            $metadata = $this->loadMetadata();
            $width = isset($metadata['width']) ? $metadata['width'] : '';
            $height = isset($metadata['height']) ? $metadata['height'] : '';
        } else {
            list($width,$height,$compression,$resizeMode) = explode('x',$dims.'xxxx');
        }
        
        $spec = 'image:'.$size.':'.time().':'.$longevity.':'.$this->packAttributes();
        
        $this->signFileSpec($spec);
        
        $url='/fileDownload.php?spec='.rawurlencode($spec);
        if ($dontPrint) return array( $url, $width, $height );
        // We want to provide height and width here so that the page loads quicker
        // However, these may not be correct if the image was generated in the past when the dimensions for this image type were different
        // The "resizeOnLoad" class triggers javascript which resizes the image on load to fit within the new dimensions without changing
        // the aspect ratio.
        $output = sprintf('<img src="%s" width="%s" height="%s" class="fitOnLoad" />',htmlspecialchars($url),htmlspecialchars($width),htmlspecialchars($height));
        echo $output;
        return true;
    }

    public static function download($spec) {

        list($type,$size,$time,$longevity,$packedAttributes) = explode(':',$spec,5);

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
            if ($type=="download") {
                header("Content-Disposition: attachment; filename*=UTF-8''imageError.png");
            }
            readfile(SITE_BASE_DIR.'/www/'.static::$errorImage);
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

        if (!strlen($size)) $size='thumbnail';
        $object->stream($size);
        exit;
    }
    
    protected function getFileForSize( $size ) {
        return $this->location().'/'.$size;
    }
    
    function stream($size) {

        // We can tell if this request is to download an image embedded in a web page vs downloading the image on its own
        // by looking at the HTTP_ACCEPT header - if this starts image/... then it is embedded
        $isEmbeddedImage = strpos( $_SERVER['HTTP_ACCEPT'],'image/' )===0;
        
        // We don't want to go to the hassle of getting the original filename if this is just an ordinary embedded image
        if (!$isEmbeddedImage) {
            // Get the original filename
            
            $metadata = $this->loadMetadata();
            // Set the filename in case they want to save the image
            header( 'Content-Disposition: filename*=UTF-8\'\'' . rawurlencode ( $metadata['name'] ) );
        }
        
        header('Content-type: image/png');
        $imageFile = $this->getFileForSize($size);
        
        if (!file_exists($imageFile)) {
            $dims = $this->getImageSize($size);
            echo $this->scaleImage(SITE_BASE_DIR.'/www/'.static::$missingImage, $dims)[0];
        } else {
            //header('Content-Encoding: none');
            header('Content-Length: ' . filesize($imageFile));
            readfile( $imageFile );
        }
        exit;
    }

}
