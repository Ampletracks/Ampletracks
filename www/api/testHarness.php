<?

$INPUTS = [
    'call' => [
        'body'      => 'TEXT',
        'endpoint'  => 'TEXT',
        'method'  => 'TEXT',
        'keyId'     => 'INT'
    ]
];

include('../../lib/core/startup.php');

if (ws('mode')=='call') {
    if (!ws('keyId')) inputError('keyId','You must select an API key');
    if (!ws('endpoint')) inputError('endpoint','You must specify the enpoint');
    if (!ws('method')) inputError('method','You must specify the method');

    if (!inputError()) {
        $apiKey = $DB->getValue('SELECT apiKey FROM userAPIKey WHERE userId=? AND deletedAt=0 AND id=?',$USER_ID,ws('keyId'));
        if (!$apiKey) inputError('keyId','You must select a valid API key');
    }

    if (inputError()) {
        http_response_code('400');
        $allErrors = inputError('*');
        $message = '';
        foreach( $allErrors as $field => $errors ) {
            foreach( $errors as $error ) {
                $message.=$field.': '.$error."\n";
            }
        }
        echo json_encode([
            'code'      =>'502',
            'message'   => $message
        ]);
        exit;
    }

    $url = 'https://'.$_SERVER['HTTP_HOST'].'/api/v1'.ws('endpoint');
    $mode = ws('mode') || 'GET';

    // Initialize cURL session
    $ch = curl_init($url);
    $jsonData = ws('body');

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Return the response as a string
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, ws('method'));  // Use GET method
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData );  // Set the request body to the JSON data
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer '.$apiKey,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ));
    if (IS_DEV) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Execute cURL request
    $response = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    // Check for cURL errors
    if (curl_errno($ch)) {
        // Return a "BAD GATEWAY" code
        http_response_code(502);
        echo json_encode( [
            'code'    => 502,
            'message' => curl_error($ch)
        ]);
    } else {
        $contentType = preg_replace('/;.*/','',$contentType);
        header("Content-type: $contentType");
        http_response_code($httpCode);
        echo $response;
    }

    exit;
}

$title = "API Test Harness";

$apiKeySelect = new formOptionbox('keyId');
$apiKeySelect->addLookup('SELECT name,id FROM userAPIKey WHERE deletedAt=0 AND userId=?',$USER_ID);

include(VIEWS_DIR.'/header.php');
?><style>
        label, textarea, select, input, button {
            display: block;
            width: 100%;
            margin-bottom: 10px;
        }
    </style>
        <h1>API Test Harness</h1>
        <label for="api-key">API Key</label>
        <? $apiKeySelect->display('id="api-key"'); ?>

        <label for="endpoints">Endpoints</label>
        <select id="endpoints">
            <option value="">Select an endpoint</option>
        </select>

        <label for="path-parameters">Path Parameters</label>
        <div id="path-parameters"></div>

        <label for="methods">Methods</label>
        <select id="methods">
            <option value="">Select a method</option>
        </select>

        <label for="examples">Request Body Examples</label>
        <select id="examples">
            <option value="">Select an example</option>
        </select>

        <label for="request-body">Request Body</label>
        <textarea id="request-body"></textarea>

        <button id="submit">Submit</button>

        <h2>Response</h2>
        <label for="response-code">Response Code</label>
        <input type="text" id="response-code" readonly>

        <label for="response-body">Response Body</label>
        <textarea id="response-body" readonly></textarea>

    <script>
        const apiSpecUrl = 'v1/openApi.json';

        function loadApiSpec() {
            return $.ajax({
                url: apiSpecUrl,
                dataType: 'json'
            });
        }

        function populateEndpoints(apiSpec) {
            const endpoints = Object.keys(apiSpec.paths);
            endpoints.forEach(endpoint => {
                $('#endpoints').append(`<option value="${endpoint}">${endpoint}</option>`);
            });
        }

        function populateMethods(apiSpec, endpoint) {
            const methods = Object.keys(apiSpec.paths[endpoint]);
            $('#methods').empty().append('<option value="">Select a method</option>');
            methods.forEach(method => {
                if (method.toUpperCase()=='PARAMETERS') return;
                $('#methods').append(`<option value="${method}">${method.toUpperCase()}</option>`);
            });
        }

        function populateExamples(apiSpec, endpoint, method) {
            $('#examples').prop('disabled',true).empty().append('<option value="">Select an example</option>');

            const bodyContent = apiSpec.paths[endpoint][method].requestBody.content;
            if (!bodyContent) return;

            const examples = bodyContent['application/json'].examples;
            if (examples) {
                Object.keys(examples).forEach(example => {
                    console.log('xx',examples[example]);
                    const exampleData = examples[example].value;
                    console.log(exampleData);
                    if (exampleData) {
                        $('#examples').append(`<option value="${btoa(JSON.stringify(exampleData,null,2))}">${example}</option>`);
                    }
                });
                $('#examples').prop('disabled',false);
            } else {
                // Generate example from schema if no examples are available
                const schema = apiSpec.paths[endpoint][method].requestBody.content['application/json'].schema;
                const example = generateExampleFromSchema(schema);
                if (example) {
                    $('#examples').append(`<option value="${btoa(JSON.stringify(example,null,2))}">Generated Example</option>`);
                    $('#examples').prop('disabled',false);
                }
            }
        }

        function populatePathParameters(apiSpec, endpoint) {
            const pathParameters = apiSpec.paths[endpoint].parameters || [];
            $('#path-parameters').empty();
            pathParameters.forEach(param => {
                if (param.in === 'path') {
                    $('#path-parameters').append(`
                        <label for="param-${param.name}">${param.name}</label>
                        <input type="text" id="param-${param.name}" placeholder="Enter ${param.name}">
                    `);
                }
            });
        }
        function generateExampleFromSchema(schema) {
            if (schema.example) {
                return schema.example;
            }
            const example = {};
            if (schema.properties) {
                Object.keys(schema.properties).forEach(key => {
                    const property = schema.properties[key];
                    if (property.example) {
                        example[key] = property.example;
                    } else if (property.type === 'string') {
                        example[key] = 'string';
                    } else if (property.type === 'number' || property.type === 'integer') {
                        example[key] = 0;
                    } else if (property.type === 'boolean') {
                        example[key] = true;
                    } else if (property.type === 'array') {
                        example[key] = [generateExampleFromSchema(property.items)];
                    } else if (property.type === 'object') {
                        example[key] = generateExampleFromSchema(property);
                    }
                });
            }
            return example;
        }

        function updateRequestBody(exampleBase64) {
            const exampleJson = atob(exampleBase64);
            $('#request-body').val(exampleJson);
        }

        function displayResult( status, response ) {
            $('#response-code').val(status);
            let formattedText;
            try {
                formattedText = JSON.stringify($.parseJSON(response), null, 2);
            } catch (error) {
                formattedText = response;
            }
            $('#response-body').val(formattedText);
        }

        function handleSubmit(apiSpec) {
            const apiKey = $('#api-key').val();
            let endpoint = $('#endpoints').val();
            const method = $('#methods').val().toLowerCase();
            const requestBody = $('#request-body').val();

            // Replace path parameters in the endpoint
            const pathParameters = apiSpec.paths[endpoint].parameters || [];
            pathParameters.forEach(param => {
                if (param.in === 'path') {
                    const paramValue = $(`#param-${param.name}`).val();
                    endpoint = endpoint.replace(`{${param.name}}`, paramValue);
                }
            });

            $.ajax({
                url: '',
                method: 'POST',
                data: {
                    'mode'      : 'call',
                    'endpoint'  : endpoint,
                    'body'      : requestBody,
                    'method'    : method,
                    'keyId'     : apiKey
                },
                success: function(response, textStatus, xhr) {
                    displayResult( xhr.status, response );
                },
                error: function(xhr) {
                    displayResult( xhr.status, xhr.responseText );
                }
            });
        }

        $(document).ready(function() {
            loadApiSpec().then(apiSpec => {
                populateEndpoints(apiSpec);

                $('#endpoints').change(function() {
                    const endpoint = $(this).val();
                    populateMethods(apiSpec, endpoint);
                    populatePathParameters(apiSpec, endpoint);
                });

                $('#methods').change(function() {
                    const endpoint = $('#endpoints').val();
                    const method = $(this).val().toLowerCase();
                    populateExamples(apiSpec, endpoint, method);
                });

                $('#examples').change(function() {
                    const exampleBase64 = $(this).val();
                    if (exampleBase64) {
                        updateRequestBody(exampleBase64);
                    }
                });

                $('#submit').click(function() {
                    handleSubmit(apiSpec);
                });

                // Make Ctrl-R resubmit the request
                $(document).keydown(function(event) {
                    if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
                        event.preventDefault();
                        handleSubmit(apiSpec);
                    }
                });
            });
        });
    </script>
<?
include(VIEWS_DIR.'/footer.php');
