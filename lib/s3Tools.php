<?php
namespace S3Tools;

const API_ID_LENGTH = 43; // 43 characters is the length of an API ID - see lib/api/tools.php

include_once( LIB_DIR.'/api/tools.php' );

function sanitizeS3ObjectKey($object, $replaceWith = '-', $allowSlashes = true) {
    // List of disallowed characters (anything except allowed characters)
    $disallowedChars = '[^a-zA-Z0-9\-_\.\~\/\+\=\:\,\;\@\&\(\)\[\]\{\}\!\*\#\$\%\?\'\"\ ]';

    $temporaryReplaceWith = '<<<replaced>>>';
    // Replace disallowed characters with the specified replacement string
    $sanitized = preg_replace("/{$disallowedChars}+/i", $temporaryReplaceWith, $object);

    // If slashes are not allowed, remove them entirely
    if (!$allowSlashes) {
        $sanitized = str_replace('/', $temporaryReplaceWith, $sanitized);
    }

    // Collapse consecutive occurrences of the replacement character into one
    $sanitized = preg_replace("/(".$temporaryReplaceWith."){2,}/", $temporaryReplaceWith, $sanitized);

    // Replace the temporary placeholder with the specified replacement string
    $sanitized = str_replace($temporaryReplaceWith, $replaceWith, $sanitized);

    return $sanitized;
}

/**
 * Build a file path from a template, fetching any required data lazily.
 */
function buildFilePath($templateString, $dataLoaderCallback, $defaultLengthLimit = 16)
{
    // 1. Identify placeholders
    preg_match_all('/<([^>:]+)(?::(\d+))?>/', $templateString, $matches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER);

    $placeholders = [];
    foreach ($matches[1] as $placeholderInfo) {
        $placeholders[$placeholderInfo[0]] = true;
    }
    $uniquePlaceholders = array_keys($placeholders);

    // 2. Load placeholder data
    $placeholderValues = $dataLoaderCallback($uniquePlaceholders);

    // 3. Build output progressively so replacement strings aren’t re‑parsed
    $result  = '';
    $lastPos = 0;

    foreach ($matches[0] as $idx => $token) {
        $tokenText = $token[0];
        $tokenPos  = $token[1];
        $name      = $matches[1][$idx][0];
        $limitRaw  = $matches[2][$idx][0];

        // if the placeholder is an api ID change the default length to 43 to accommodate the API ID
        $limit = $defaultLengthLimit;
        if ($limitRaw !== '' && (int)$limitRaw > 0) {
            $limit = (int)$limitRaw;
        } else if (preg_match('/_api_id$/',$name)) {
            $limit = API_ID_LENGTH;
        }

        // Append static text before this placeholder
        $result .= sanitizeS3ObjectKey( substr($templateString, $lastPos, $tokenPos - $lastPos) );

        // Replacement value (or keep literal <placeholder> if none supplied)
        if (array_key_exists($name, $placeholderValues)) {
            $replacement = $placeholderValues[$name];
            if (strlen($replacement) > $limit) {
                $replacement = substr($replacement, 0, $limit);
            }
        } else {
            $replacement = "<{$name}>"; // leave literal placeholder
        }

        $result .= sanitizeS3ObjectKey($replacement, '-', $allowSlashes = true);
        $lastPos  = $tokenPos + strlen($tokenText);
    }

    // Trailing static text
    $result .= sanitizeS3ObjectKey(substr($templateString, $lastPos));

    return $result;
}

/**
 * Return the maximum possible length of the resolved template.
 */
function calculateMaxFilePathLength($templateString, $defaultLengthLimit = 16)
{
    preg_match_all('/<([^>:]+)(?::(\d+))?>/', $templateString, $matches, PREG_PATTERN_ORDER);

    $placeholderTokenLength = array_sum(array_map('strlen', $matches[0]));

    $maxPlaceholderLength = 0;
    foreach ($matches[2] as $idx=>$limitRaw) {
        $defaultLength = $defaultLengthLimit;
        // if the placeholder is an api ID change the default length to 43 to accommodate the API ID
        if (preg_match('/_api_id$/',$matches[1][$idx])) $defaultLength = API_ID_LENGTH;
        $maxPlaceholderLength += ($limitRaw !== '' && (int)$limitRaw > 0)
            ? (int)$limitRaw
            : $defaultLength;
    }

    $staticLength = strlen($templateString) - $placeholderTokenLength;
    return $staticLength + $maxPlaceholderLength;
}

function buildS3FilePathCallback ($placeholderNames, $recordId, $uploadedFilename, $uploadedAt, $uniqueId, &$usage ) {
    global $DB;
    $values               = [];
    $recordDataFieldsNeed = [];
    $wantsMainQuery       = false;

    // These are effectively free, so we might as well chuck them in whether we need them or not
    $row = [
        'unique_id'             => $uniqueId,
        'uploaded_file_name'    => $uploadedFilename,
        'uploaded_file_extension' => pathinfo($uploadedFilename, PATHINFO_EXTENSION),
        'uploaded_file_basename'  => pathinfo($uploadedFilename, PATHINFO_FILENAME),
        'uploaded_at_year'      => date('Y', $uploadedAt),
        'uploaded_at_month'     => date('m', $uploadedAt),
        'uploaded_at_day'       => date('d', $uploadedAt),
        'uploaded_at_hour'      => date('H', $uploadedAt),
        'uploaded_at_minute'    => date('i', $uploadedAt),
    ];

    // ------- First step: classify placeholders & set usage flags ---------

    foreach ($placeholderNames as $ph) {
        if (preg_match('/^record_data_(.+)$/', $ph, $m)) {
            // record_data_ placeholders -> need separate query later
            $recordDataFieldsNeed[] = $m[1];
        } else if (isset($row[$ph])) {
            // nothing to do here
        } else if (preg_match('/record_created_at_(year|month|day|hour|minute)$/', $ph)) {
            $wantsMainQuery = true;
        } else {
            // Non‑data placeholder -> main query required
            $wantsMainQuery = true;

            // Set usage flags
            // derive flag key using a single regex (strip _name or _api_id)
            $prefix = preg_replace('/_(?:(?:first_|last_)?name|api_id|title)$/', '', $ph, 1, $count);
            if ($count) { // suffix was found and removed
                $flagKey = toCamelCase('uses_'.$prefix);
                if (isset($usage[$flagKey])) {
                    $usage[$flagKey] = true;
                }
            }
        }
    }

    // If record‑data placeholders were present they imply record usage
    if ($recordDataFieldsNeed) {
        $usage['usesRecord'] = true;
    }


    // ------- Second step: build the row data -----------------------------

    // ------- Main query (single row) --------------------------------------
    if ($wantsMainQuery) {
        $recordData = $DB->getRow(
            'SELECT
                project.id                     AS project_id,
                project.apiId                  AS project_api_id,
                project.name                   AS project_name,
                record.id                      AS record_id,
                record.apiId                   AS record_api_id,
                recordType.id                  AS record_type_id,
                recordType.apiId               AS record_type_api_id,
                recordType.name                AS record_type_name,
                IFNULL(user.id,0)                                                         AS owner_id,
                IFNULL(user.apiId,"")                                                     AS owner_api_id,
                IFNULL(user.firstName,"no_owner")                                         AS owner_first_name,
                IFNULL(user.lastName,"no_owner")                                          AS owner_last_name,
                IF(ISNULL(user.id),"no_owner",CONCAT(user.firstName, " ", user.lastName)) AS owner_name,
                IFNULL(recordData.data, "--no_title--")    AS record_title,
                FROM_UNIXTIME(record.createdAt,"%Y") AS record_created_at_year,
                FROM_UNIXTIME(record.createdAt,"%m") AS record_created_at_month,
                FROM_UNIXTIME(record.createdAt,"%d") AS record_created_at_day,
                FROM_UNIXTIME(record.createdAt,"%H") AS record_created_at_hour,
                FROM_UNIXTIME(record.createdAt,"%i") AS record_created_at_minute
            FROM record
                INNER JOIN recordType ON record.typeId = recordType.id
                LEFT JOIN user     ON user.id     = record.ownerId
                LEFT JOIN project  ON project.id  = record.projectId
                LEFT JOIN recordData ON recordData.recordId = record.id AND recordData.dataFieldId = recordType.primaryDataFieldId
            WHERE record.id = ?
            ',
            $recordId
        );

        $row = array_merge($row, $recordData);
    }

    // ------- Record‑data field query  -------------------------------------
    if ($recordDataFieldsNeed) {
        $recordData = $DB->getHash(
            "SELECT CONCAT('record_data_',dataField.name) AS key, recordData.data AS value
                FROM dataField
                    INNER JOIN recordData ON recordData.dataFieldId = dataField.id
                WHERE recordData.recordId = ?
                    AND dataField.name IN (?)",
            $recordId, $recordDataFieldsNeed
        );

        $row = array_merge($row, $recordData);
    }

    // Apply API‑ID fix‑up if needed
    foreach ($row as $key => $value) {
        if (preg_match('/^(.*)_api_id$/', $key, $matches)) {
            $prefix     = $matches[1];                         // e.g. project / owner / record_type

            if (!$row[$prefix . '_id']) {
                // If the ID is not set then we can't do anything with this
                continue;
            }
            // getAPIId will add create the apiId if empty and also add the API prefix
            // owner is a reference to the user table, so we need to change the prefix
            $entity = ($prefix=='owner') ? 'user' : $prefix;

            $row[$key] = \API\getAPIId(
                toCamelCase($entity),
                $row[$prefix . '_id']  ?? null,
                $row[$key]
            );
        }
    }

    return $row;
}

function buildS3FilePath( $pathTemplate, $recordId, $originalFilename, $uploadedAt, $uniqueId, &$usage ) {

    // Initialise usage flags
    $usage = [
        'usesProject'     => false,
        'usesRecord'      => false,
        'usesRecordType'  => false,
        'usesOwner'       => false,
    ];

    $path = getConfig('S3 upload path prefix');

    $path .= '/'.buildFilePath(
        $pathTemplate,
        function (array $placeholderNames) use ($recordId, $originalFilename, $uploadedAt, $uniqueId, &$usage) {
            return buildS3FilePathCallback($placeholderNames, $recordId, $originalFilename, $uploadedAt, $uniqueId, $usage);
        }
    );

    // Remove any leading or trailing slashes
    $path = trim($path, '/');
    // Remove any trailing slashes
    $path = rtrim($path, '/');
    // Remove any double slashes
    $path = preg_replace('/\/+/', '/', $path);

    return $path;
}
