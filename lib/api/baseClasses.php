<?
namespace API;

require_once(LIB_DIR.'/api/tools.php');

// Use code to suggest an http return code for this exception
class ApiException extends \Exception {}

//interface APIQuery {
//    // vvvv Need this one? vvvv
//    //public static function loadRecordsById(array $ids);
//    public static function formatRecordForOutput(array $data);
//}

class IdStreamer {
    private $idFileId = '';
    private $idFileH = null;
    private $startIdx = 0;
    private $checkEntity = '';
    private $numIds = 0;

    private const baseIdFileDir = DATA_DIR.'/apiIdFiles/';
    private const bytesPerId = 4; // MySql int

    /**
     * $queryOrFileId - 'SELECT ...' should be a query which returns an id as the first (or, ideally only) item per row
     *                               it will load those ids into a file, the api id of which can be retrieved with getIdFileId()
     *                  'qry_...' will open a previously stored id file identified by the passed id
     * $checkEntity - the entity type, will be written to the start of the file for new queries or checked against the file otherwise
     * $startIdx - the index of the first id to return
     */
    public function __construct($queryOrFileId, $checkEntity, $startIdx) {
        $this->startIdx = $startIdx;
        $this->checkEntity = strtolower($checkEntity);

        $queryOrFileId = trim($queryOrFileId);
        if(stripos($queryOrFileId, 'SELECT') === 0 || is_array($queryOrFileId)) {
            $this->querySetupIdFile($queryOrFileId);
        } else {
            $this->idFileId = $queryOrFileId;
            $this->openIdFile();
        }
    }

    /**
     * Pad check entity in the file to the next highest 2 bytes to make hex debugging easier
     * Go to next highest as 'Z' in [un]pack needs at least one \0 so it'll truncate even-length strings
     */
    private function checkEntitySize() {
        $checkEntitySize = strlen($this->checkEntity) + 1;
        if($checkEntitySize % 2) {
            $checkEntitySize += 1;
        }
        return $checkEntitySize;
    }

    private function querySetupIdFile($querySql) {
        global $USER_ID, $DB;

        $currHour = date('H');
        $idFileDir = self::baseIdFileDir.'/'.$currHour;
        if(!is_dir($idFileDir)) {
            if(!mkdir($idFileDir, 0770, true)) {
                throw new ApiException('Failed', 500);
            }
        }
        $idFileName = sha1(openssl_random_pseudo_bytes(20), false);
        $this->idFileId = "qry_$idFileName$currHour";

        $wh = fopen($idFileDir.'/'.$idFileName, 'wb');
        if(!is_resource($wh)) {
            throw new ApiException('Failed', 500);
        }
        fwrite($wh, pack('Z'.$this->checkEntitySize(), $this->checkEntity));
        fwrite($wh, pack('N', (int)$USER_ID));

        $DB->returnArray();
        $idQuery = $DB->query($querySql);

        while($idQuery->fetchInto($idRow)) {
            fwrite($wh, pack('N', (int)$idRow[0]));
            $this->numIds++;
        }
        $idQuery->free();
        fclose($wh);

        $this->openIdFile();
    }

    private function openIdFile() {
        global $USER_ID;

        if(strlen($this->checkEntity) == 0) {
            throw new ApiException('Entity mismatch', 500);
        }

        [$idFilePrefix, $idFileName] = explode('_', $this->idFileId);
        if($idFilePrefix !=  'qry') {
            throw new ApiException('Bad id', 400);
        }
        $idFileDir = self::baseIdFileDir.substr($idFileName, -2);
        $idFileName = substr($idFileName, 0, -2);
        $idFilePath = $idFileDir.'/'.$idFileName;

        $this->idFileH = fopen($idFilePath, 'rb');
        if(!is_resource($this->idFileH)) {
          throw new ApiException('Bum!');
        }

        $headerSize = $this->checkEntitySize() + self::bytesPerId;
        $fileCheckData = unpack('Z'.$this->checkEntitySize().'entity/NuserId', fread($this->idFileH, $headerSize));
        if($fileCheckData['entity'] !== $this->checkEntity) {
            throw new ApiException('Entity mismatch', 500);
        }
        if($fileCheckData['userId'] != $USER_ID) {
            throw new ApiException('Wrong user', 403);
        }

        fseek($this->idFileH, $headerSize + $this->startIdx * self::bytesPerId);
        $this->numIds = (filesize($idFilePath) - $headerSize) / self::bytesPerId;
    }

    /**
     * Generator
     */
    public function getIds($maxCount) {
        if (!$this->numIds) return;

        for($i = 0; $i < $maxCount; $i++) {
            $rawIdData = fread($this->idFileH, self::bytesPerId);
            if(empty($rawIdData)) {
                break;
            }
            $idData = unpack('Nid', $rawIdData);
            yield $idData['id'];
        }
    }

    public function getNumIds() {
        return $this->numIds;
    }

    public function getIdFileId() {
        return $this->idFileId;
    }

    public function getPageUrl($page) {
        return "/{$this->checkEntity}/{$this->getIdFileId()}/page/$page";
    }

    public function __destruct() {
        if($this->idFileH) {
            fclose($this->idFileH);
        }
    }
}
