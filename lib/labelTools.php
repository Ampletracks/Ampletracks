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
    
        $this->siteId = defined('LABEL_SITE_ID') ? LABEL_SITE_ID:0;

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

        $thisSiteId = defined('LABEL_SITE_ID') ? LABEL_SITE_ID : 0;

        if ($this->siteId == $thisSiteId) {
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
        // If site ID is not defined then return just the Site URL
        $base = $this->siteId > 0 ? LABEL_QR_CODE_BASE_URL : SITE_URL;
        return $base.$this->getCompactId();
    }

    function getImageQrCode($asDataUri=0, $size=3) {
        if ($this->id==0) {
            $b64 = DUMMY_QR_CODE;
            $width = DUMMY_QR_CODE_WIDTH;
            $height = DUMMY_QR_CODE_HEIGHT;

            if (!$asDataUri) {
                $decodedData = base64_decode($b64);
                $image = imagecreatefromstring($decodedData);
                return $image;
            }
        } else {
            include_once(LIB_DIR.'phpqrcode.php');
            $content = $this->getQrCodeUrl();
            $image = QRcode::png($content, '-', constant('QR_ECLEVEL_'.LABEL_QR_CODE_ERROR_CORRECTION),$size );
            if (!$asDataUri) return $image;
            
            $stream = fopen('php://memory','r+');
            imagepng($image,$stream);
            rewind($stream);
            $imageString = stream_get_contents($stream);
            fclose($stream);
            $width  = imagesx($image);
            $height = imagesy($image);
           
            $b64 = base64_encode($imageString);
        }
        $imageString = 'data: image/png;base64,'.$b64;
        if ($asDataUri==1) return $imageString;
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

define('DUMMY_QR_CODE_WIDTH',165);
define('DUMMY_QR_CODE_HEIGHT',165);
define('DUMMY_QR_CODE','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');
