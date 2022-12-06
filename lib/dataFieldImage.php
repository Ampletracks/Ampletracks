<?

include_once(CORE_DIR.'/imageUpload.php');

class dataFieldImage extends imageUpload  {

    private $id;
    
    public static $minDims='10x10'; // Defines the minimum dimensions in <width>x<height> e.g. "400x300"
    public static $maxDims='2000x2000'; // Defines the maximum dimensions in <width>x<height> e.g. "4000x3000"
    public static $minAspectRatio='1:400'; // Defines the "most portaity" the image can be e.g. "1:4"
    public static $maxAspectRatio='400:1'; // Defines the "most lanscapey" the image can be e.g. "4:1"

    // Longevity options for download links
    public static $linkLongevity = array(
        'short'     => 60,
        'medium'    => 3600,
        'long'      => 86400 * 7
    );
    
    public static $missingImage = '/images/missingImage.png';
    public static $errorImage = '/images/errorImage.png';

    protected $gotSizes;
    
    function __construct() {
        // In this case the sizes here are just defaults which are overridden from data in the state
        
        // If you don't define a thumbnail then the default size will be used
        // In this case the thumbnail will be generated on the fly from the first image size in the list
        // In this case, for efficiency's sake, you probably want to put the smallest image size first in the list
        $this->sizes = array(
            // <width>x<height>[x<compression>[x<resizeMode>[x<backgroundColour>]]]
            // compression is a number from 0 (no compression) to 9 (maximum compression)
            // resizeMode can be "fill", "fit" or "crop". Defaults to "fit"
            // backgroundColour is only relevant if $resizeMode=='fill'. Expressed as three, or six digit hex colour (with or without leading #)
            //     Defaults to transparent if unset or empty string
            // The thumbnail mode is special - this defines the treatment of the image preview thumbnail
            // If the dimensions specification is just '-' then the file is retained as-is
            'thumbnail' => '50x50x9xcropx#f00',      
            'preview'    => '100x100x9xfit',
            // If you want the original file to be included you should include this next line
            'original'  => 'original',
        );
        
        // Call the parent constructor passing along all parameters
        parent::__construct(...func_get_args());
    }
    
    protected function getImageSize($size=null) {
        // Generate the sizes array if it hasn't been done already
        if (!isset($this->gotSizes)) {
            if ($this->upload) {
                $this->gotSizes = true;
                $newSizes = $this->upload->getState('sizes');
                if (is_array($newSizes)) $this->sizes = array_merge( $this->sizes, $newSizes );
            }
        }

        // If this is called without $size then we just build $this->sizes and return
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

    public function location() {
        return DATA_DIR.'/record/'.(int)$this->attributes['recordId'].'/images/dataField_'.$this->attributes['dataFieldId'];
    }

    protected function packAttributes() {
        return (int)$this->attributes['recordId'].':'.(int)$this->attributes['dataFieldId'];
    }

    protected function unpackAttributes($attributes) {
        $attributes = explode( ':', $attributes.':');
        return $this->attributes = array(
            'recordId'  => (int)$attributes[0],
            'dataFieldId' => (int)$attributes[1]
        );
    }

    public function refresh() {
        // Check that the thumbnail images on disk are the right size
        // They might not be if the thumbnail size has been changed
        
        if (!$this->exists()) return;
                
        if (!$this->upload) return;
        
        $originalFile = $this->getFileForSize('original');
        if (!file_exists($originalFile)) $originalFile = '';
        
        $lastChanged = $this->upload->getState('settingsLastChangedAt');

        foreach ( $lastChanged as $size=>$settingsLastChangedAt ) {
            
            $file = $this->getFileForSize($size);
            $fileExists = file_exists($file);
            if (!strlen($originalFile) && !$fileExists) continue;

            if (!$fileExists || filemtime($file)<$settingsLastChangedAt) {
                // If we kept the original file then use that
                // Otherwise use the current thumbnail (hopefully we are scaling down, not up)               
                $this->scaleImage( strlen($originalFile)?$originalFile:$file, $this->getImageSize($size), $file );
            }
        }
        
        // If the "keep original" setting has changed then remove the original file
        if ($originalFile && !strlen($this->getImageSize('original'))) unlink( $originalFile );
    }
}