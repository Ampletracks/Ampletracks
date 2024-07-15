<?php

// The following code was generated by ChatGPT. See this conversation:
// https://chatgpt.com/share/7240b3dc-36b7-4f84-a9cb-56ee3f887554

/**
 * ApiInputValidator Class
 * 
 * This class is designed to validate API inputs based on the OpenAPI specification. 
 * It can ingest OpenAPI specifications, validate input data against these specifications, 
 * and retrieve valid inputs based on internal names.
 * 
 * Public Methods:
 * 
 * - __construct($endpointPath, $method = null):
 *   Constructor initializes the validator with the specified endpoint path and method. 
 *   If the method is not provided, it defaults to the request method.
 * 
 * - static ingestInputSpecifications($openApiSpecPath):
 *   This static method ingests the OpenAPI specification JSON file and stores the request body schemas 
 *   in the database. It returns true on success, or an array of errors on failure.
 * 
 * - errors():
 *   Returns an array of internal errors encountered during initialization.
 * 
 * - validateInput():
 *   Validates the input JSON data against the loaded schema. 
 *   Returns an array of validation errors found, or an empty array if the input is valid.
 * 
 * - getValidInputs():
 *   Returns a hash of valid inputs keyed off their internal names.
 * 
 * Example Usage:
 * 
 * // Ingesting OpenAPI Specifications
 * $result = ApiInputValidator::ingestInputSpecifications('/path/to/OpenAPI/Specification.json');
 * if ($result !== true) {
 *     echo "Ingestion failed with errors: " . join(', ', $result);
 *     exit;
 * }
 * 
 * // Validating API Inputs
 * $inputValidator = new ApiInputValidator('/api/endpoint/path');
 * $initializationErrors = $inputValidator->errors();
 * if ($initializationErrors) {
 *     echo "Error: " . join(',', $initializationErrors);
 *     exit;
 * }
 * $inputValidationErrors = $inputValidator->validateInput();
 * if ($inputValidationErrors) {
 *     echo "Input was not valid: " . join(',', $inputValidationErrors);
 * } else {
 *     $inputs = $inputValidator->getValidInputs();
 *     // Process the valid inputs...
 * }
 */

class ApiInputValidator {
    private $endpointPath;
    private $method;
    private $schema;
    private $internalErrors = [];
    private $validInputs = [];

    public function __construct($endpointPath, $method = null) {
        global $DB;
        $this->endpointPath = $endpointPath;
        $this->method = $method ?: $_SERVER['REQUEST_METHOD'];

        $schemaJSON = $DB->getValue('SELECT requestBodySchemaJson FROM apiInputSpecification WHERE endpointPath=? AND method=?', $this->endpointPath, $this->method);
        if ($schemaJSON) {
            $this->schema = json_decode($schemaJSON, true);
        } else {
            $this->internalErrors[] = "No schema found for endpoint {$this->endpointPath} and method {$this->method}";
        }
    }

    public static function ingestInputSpecifications($openApiSpecPath) {
        global $DB;
        $errors = [];

        if (!file_exists($openApiSpecPath)) {
            return ["File not found: $openApiSpecPath"];
        }

        $spec = json_decode(file_get_contents($openApiSpecPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ["Invalid JSON in specification file: " . json_last_error_msg()];
        }

        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $details) {
                if (isset($details['requestBody']['content']['application/json']['schema'])) {
                    $schema = $details['requestBody']['content']['application/json']['schema'];
                    $result = $DB->replace('apiInputSpecification', [
                        'endpointPath' => $path,
                        'method' => strtoupper($method)
                    ],[
                        'requestBodySchemaJson' => json_encode($schema)
                    ]);
                    if (!$result) {
                        echo "--$result";
                        $errors[] = "Failed to insert schema for endpoint {$path} and method " . strtoupper($method);
                    }
                }
            }
        }

        return empty($errors) ? true : $errors;
    }

    public function errors() {
        return $this->internalErrors;
    }

    public function validateInput() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['Invalid JSON input: ' . json_last_error_msg()];
        }

        $validationErrors = $this->validateSchema($this->schema, $input, '');
        return $validationErrors;
    }

    private function validateSchema($schema, $data, $path) {
        $errors = [];

        switch ($schema['type']) {
            case 'object':
                $objectErrors = $this->validateObject($schema, $data, $path);
                $errors = array_merge($errors, $objectErrors);
                break;
            case 'array':
                $arrayErrors = $this->validateArray($schema, $data, $path);
                $errors = array_merge($errors, $arrayErrors);
                break;
            case 'string':
                if (!is_string($data)) {
                    $errors[$path] = "Type mismatch: expected string";
                }
                break;
            case 'integer':
                if (!is_int($data)) {
                    $errors[$path] = "Type mismatch: expected integer";
                }
                break;
            case 'number':
                if (!is_numeric($data)) {
                    $errors[$path] = "Type mismatch: expected number";
                }
                break;
            case 'boolean':
                if (!is_bool($data)) {
                    $errors[$path] = "Type mismatch: expected boolean";
                }
                break;
            default:
                $errors[$path] = "Unknown type: {$schema['type']}";
        }

        return $errors;
    }

    private function validateObject($schema, $data, $path) {
        $errors = [];

        if (!is_array($data)) {
            $errors[$path] = "Type mismatch: expected object";
            return $errors;
        }

        foreach ($schema['required'] ?? [] as $requiredField) {
            if (!isset($data[$requiredField])) {
                $errors[$path . '/' . $requiredField] = "Required field is missing";
            }
        }

        foreach ($schema['properties'] ?? [] as $property => $propertySchema) {
            $propertyPath = $path ? $path . '/' . $property : $property;
            if (isset($data[$property])) {
                $propertyErrors = $this->validateSchema($propertySchema, $data[$property], $propertyPath);
                $errors = array_merge($errors, $propertyErrors);

                if (isset($propertySchema['internalName']) && empty($propertyErrors)) {
                    $this->validInputs[$propertySchema['internalName']] = $data[$property];
                }
            }
        }

        return $errors;
    }

    private function validateArray($schema, $data, $path) {
        $errors = [];

        if (!is_array($data)) {
            $errors[$path] = "Type mismatch: expected array";
            return $errors;
        }

        foreach ($data as $index => $item) {
            $itemPath = $path ? $path . '/' . $index : (string)$index;
            $itemErrors = $this->validateSchema($schema['items'], $item, $itemPath);
            $errors = array_merge($errors, $itemErrors);
        }

        return $errors;
    }

    public function getValidInputs( $prefix = '' ) {
        if (empty($prefix)) return $this->validInputs;

        $return = [];
        foreach( $this->validInputs as $key=>$value ) {
            if (strpos($key, $prefix) === 0) {
                $return[substr($key,strlen($prefix))] = $value;
            }
        }

        return $return;
    }
}

