<?

include_once(CORE_DIR.'/imageUpload.php');

class recordTypeLabelImage extends imageUpload  {

    public static $minDims='10x10'; // Defines the minimum dimensions in <width>x<height> e.g. "400x300"
    public static $maxDims='2000x2000'; // Defines the maximum dimensions in <width>x<height> e.g. "4000x3000"
    public static $minAspectRatio='1:400'; // Defines the "most portaity" the image can be e.g. "1:4"
    public static $maxAspectRatio='400:1'; // Defines the "most lanscapey" the image can be e.g. "4:1"

    protected $sizes;
    
    // Longevity options for download links
    public static $linkLongevity = array(
        'short'     => 60,
        'medium'    => 3600,
        'long'      => 86400 * 7
    );
    
    public static $missingImage = '/images/missingImage.png';
    public static $errorImage = '/images/errorImage.png';
    
    function __construct() {
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
            'thumbnail' => '100x40x9xcropx#f00',      
            'medium'    => '200x80x9xfit',
            'big'       => 'original',
        );
        
        // Call the parent constructor passing along all parameters
        parent::__construct(...func_get_args());
    }
    
    public function location() {
        return DATA_DIR.'/images/recordType/main/'.$this->attributes;
    }

    // This function is called after the file has been moved to the location specified by the locate method
    public function process($filename) {
        return true;
    }

}
