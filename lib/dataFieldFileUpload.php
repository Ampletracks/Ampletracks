<?

include_once(CORE_DIR.'/fileUpload.php');

class dataFieldFileUpload extends fileUpload  {

    // Longevity options for download links
    public static $linkLongevity = array(
        'short'     => 60,
        'medium'    => 3600,
        'long'      => 86400 * 7
    );

    function __construct() {
        parent::__construct(...func_get_args());
    }

    public function location() {
        return DATA_DIR.'/record/'.(int)$this->attributes['recordId'].'/uploads/dataField_'.$this->attributes['dataFieldId'];
    }

    protected function packAttributes() {
        return ((int)$this->attributes['recordId']).':'.((int)$this->attributes['dataFieldId']);
    }

    protected function unpackAttributes($attributes) {
        $attributes = explode( ':', $attributes.':');
        return $this->attributes = array(
            'recordId'  => (int)$attributes[0],
            'dataFieldId' => (int)$attributes[1]
        );
    }

    public function getDownloadName() {
        $filename = 'upload_'.(int)$this->attributes['dataFieldId'];
        $name = $this->name();
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (empty($ext)) $ext='unknown';
        $filename .= '.'.$ext;
        return $filename;
    }
}
