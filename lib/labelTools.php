<?

function assignLabelToRecord( $labelId, $recordId, $force=false ) {
    global $DB, $USER_ID;
    // Check if this label is already attached to a different record
    $alreadyAttachedTo = $DB->getValue('SELECT recordId FROM label WHERE id=?',$labelId);
    if ($alreadyAttachedTo===false) return('No label exists with this ID');
    else if ($alreadyAttachedTo==$recordId) return false; // nothing to do
    else if ($alreadyAttachedTo>0 && !$force ) return('This label ID is already taken');
    else {
        $DB->update('label',array('id'=>$labelId),[
            'recordId'      => $recordId,
            'assignedBy'    => $USER_ID,
            'assignedAt'    => time(),
        ]);
    }
    return false; // i.e. no errors
}

// This function is useful for debugging the packed label ID's
function stringToBinary($string) {
    $characters = str_split($string);
 
    $binary = [];
    foreach ($characters as $character) {
        $data = unpack('H*', $character);
        $binary[] = str_pad(base_convert($data[1], 16, 2),8,'0',STR_PAD_LEFT);
    }
 
    return implode(' ', $binary );
}

function random_str($length, $keyspace = 'abcdefghijklmnopqrstuvwxyz234567') {
    require_once LIB_DIR.'/random-compat.phar';
    require_once LIB_DIR.'/sodium-compat.phar';

    $str = '';
    $keysize = strlen($keyspace);
    for ($i = 0; $i < $length; ++$i) {
        $str .= $keyspace[\Sodium\randombytes_uniform($keysize)];
    }
    return $str;
}

class label {
    public $securityCode;
    public $id;
    public $recordId;
    public $siteId = 0;
    public $redirectUrl = false;
    private $packedId = false;
    private $version = 1;
    private $error = false;
    
    function __construct( $data=null, $securityCode=null ) {
        global $DB;
       
        $this->siteId = LABEL_SITE_ID; 

        if (is_string($securityCode)) {
            $this->securityCode = $securityCode;
            $this->id = (int)$data;
            $this->checkSecurityCode();
        } else if (is_int($data)) {
            list( $this->id, $this->version, $this->recordId ) = $DB->getRow('SELECT id, version, recordId FROM label WHERE id=?',$data);
            // We can't reverse the security code so we'll just have to generate a new one
            // N.B. THIS WILL INVALIDATE ANY PREVIOUS VERSIONS OF THIS LABEL
            $this->securityCode = random_str( LABEL_SECURITY_CODE_LENGTH, LABEL_SECURITY_CODE_KEYSPACE );
            $hashedSecurityCode = password_hash($this->securityCode, PASSWORD_BCRYPT, ["cost" => LABEL_SECURITY_CODE_HASH_COST]);
            $DB->update('label',array('id'=>$this->id),array('securityCode'=>$hashedSecurityCode));
        } else if (is_string($data) && $data=='dummy') {
            $this->id=0;
            $this->securityCode='XXXXXX';
            $this->siteId=0;
        } else if (is_string($data)) {
            $this->packedId = $data;
            // parse a label from a packed ID
            list( $id, $securityCode, $siteId ) = self::unpackId( $data );
            $this->securityCode = $securityCode;
            $this->id = (int)$id;
            $this->siteId = (int)$siteId;
            $this->checkSecurityCode();
        } else if (is_array($data) || is_null($data)) {
            // Create a new label
            $this->securityCode = random_str( LABEL_SECURITY_CODE_LENGTH, LABEL_SECURITY_CODE_KEYSPACE );
            $hashedSecurityCode = password_hash($this->securityCode, PASSWORD_BCRYPT, ["cost" => LABEL_SECURITY_CODE_HASH_COST]);
            $siteId = 0;
            if (is_array($data)) {
                if (isset($data['siteId'])) $this->siteId=$data['siteId'];
            }

            $this->id = $DB->insert( 'label', array(
                'securityCode'  => $hashedSecurityCode,
                'version'       => $this->version,
            ));
        }
    }

    function error() {
        return $this->error;
    }
    
    function packId() {
        $shiftedSecurityCode = strtr( $this->securityCode, LABEL_SECURITY_CODE_KEYSPACE,'0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ' );
        $securityCodeInteger = base_convert( $shiftedSecurityCode, strlen(LABEL_SECURITY_CODE_KEYSPACE), 10 );
        if ($this->version==0) {
            // Version 0
            /*
            Format is as follows
            4 bits for format of data - currently only version 00 exists
            4 unused bits
            label ID - represented as an unsigned long (4 bytes)
            security code - represented as an unsigned long (4 bytes)
            */
            return base64URLEncode( pack( "HNN",dechex($this->version<<4),$this->id,$securityCodeInteger) );
        } else {
            // Version 1
            /*
            Format is as follows
            4 bits for format of data - currently only version 00 exists
            12 bits for site ID
            label ID - represented as an unsigned long (4 bytes)
            security code - represented as an unsigned long (4 bytes)
            */
            $firstTwoBytes = dechex((($this->version & 15)<<12) | ($this->siteId & 4095));
            $packedId = base64URLEncode( pack( "H*NN",$firstTwoBytes,$this->id,$securityCodeInteger) );
            return $packedId;
        }
    }
   
    static function unpackId( $data ) {
        $bits = unpack( "Cbyte1/Cbyte2", base64URLDecode($data) );
        $version = hexdec( $bits['byte1'] ) >> 4;
        if ($version==0) {
            $bits = unpack( "Cbyte1/Nid/NsecurityCode", base64URLDecode($data) );
            $siteId = 1;
        } else {
            $bits = unpack( "Cbyte1/Cbyte2/Nid/NsecurityCode", base64URLDecode($data) );
            // last nibble of version is first part of siteId
            $siteId = (($bits['byte1'] & 15) << 8) | $bits['byte2'];
        }
        $shiftedSecurityCode = strtoupper(base_convert( (int)$bits['securityCode'], 10, strlen(LABEL_SECURITY_CODE_KEYSPACE) ));
        $securityCode = strtr( $shiftedSecurityCode,'0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', LABEL_SECURITY_CODE_KEYSPACE );
        return array( $bits['id'], $securityCode, $siteId );
    }
    
    function checkSecurityCode() {
        global $DB;
        $result = false;

        if ($this->siteId == LABEL_SITE_ID) {
            // This label belongs to this site
            list($hashedSecurityCode,$recordId) = $DB->getRow('SELECT securityCode,recordId FROM label WHERE id=?',$this->id);
            if (password_verify($this->securityCode,$hashedSecurityCode)) {
                $this->recordId=$recordId;
                $result = true;
            }
        } else {
            // This label belongs to another site
            // get the fqdn for the site
            $fqdn = $DB->getValue('SELECT site.fqdn FROM site WHERE id=?',$this->siteId);
            $this->redirectUrl = 'https://'.$fqdn.'/record/find.php?id='.$_GET['id'];

            $ch = curl_init();
            curl_setopt ($ch, CURLOPT_URL, $this->redirectUrl);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $notUsed = curl_exec($ch);
            $status = curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
            if ($status ==200 ) $result = true;
            else $this->redirectUrl = false;
        }
        if (!$result) {
            $this->error = 'Invalid security code';
            $this->id=0;
            $this->recordId=0;
            $this->securityCode='';
            return false;
        }
        return true;
    }
    
    function getCompactId() {
        return $this->packId();
    }
    
    function getQrCodeUrl() {
        return LABEL_QR_CODE_BASE_URL.$this->getCompactId();
    }

    function getImageQrCode($asDataUri=0, $size=3) {
        include_once(LIB_DIR.'phpqrcode.php');
        $content = $this->getQrCodeUrl();
        $image = QRcode::png($content, '-', constant('QR_ECLEVEL_'.LABEL_QR_CODE_ERROR_CORRECTION),$size );
        if (!$asDataUri) return $image;
        
        $stream = fopen('php://memory','r+');
        imagepng($image,$stream);
        rewind($stream);
        $imageString = stream_get_contents($stream);
        fclose($stream);
        
        $imageString = 'data: image/png;base64,'.base64_encode($imageString);
        if ($asDataUri==1) return $imageString;
        $width  = imagesx($image);
        $height = imagesy($image);
        return "<img src=\"$imageString\" width=\"$width\" height=\"$height\"/>";
    }
    
    function getHtmlQrCode( $echo = true ) {
        include_once(LIB_DIR.'phpqrcode.php');
        $style =  "<style>
x,y { border: none; width: 10px; height: 10px; display: inline-block; }
x{ background-color: white;}
y{ background-color: black;}
div.qr { border: 2px solid #ccc; padding: 40px; background: #fff; }
</style>";

        static $started = false;
        if ($echo && !$started) {
            $started=true;
            echo $style;
        }

        $content = $this->getQrCodeURL();
        $qr = implode('<br />',QRcode::text($content,false, constant('QR_ECLEVEL_'.LABEL_QR_CODE_ERROR_CORRECTION)));
        $qr = '<div class="qr">'.strtr($qr, array( '<x></x>', '<y></y>')).'</div>';
        if ($echo) echo $qr;
        else return array( $qr, $style );
    }

}
