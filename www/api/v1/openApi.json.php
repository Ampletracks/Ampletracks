{
  "openapi": "3.0.2",
  "info": {
    "title": "Amletracks API v1",
    "description": "This is the documentation for the Ampletracks API\n\nSome useful links:\n\n* [The main API repository](https://github.com/Ampletracks/Ampletracks)",
    "termsOfService": "",
    "contact": {
      "email": ""
    },
    "license": {
      "name": "MIT",
      "url": "https://github.com/Ampletracks/Ampletracks/blob/main/LICENSE"
    },
    "version": "1.0"
  },
  "externalDocs": {
    "description": "",
    "url": ""
  },
  "servers": [
    {
      "url": "/api/v1"
    }
  ],
  "tags": [],
  "paths": {
    "/project": {
      "get": {
        "summary": "Returns a list of the currently defined projects",
        "description": "If no matching projects are found this returns a 200 (OK) response, but the data array will be empty and the number of records reported in the result metadata will be 0.",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "filters": {
                    "type": "object",
                    "properties": {
                      "name_contains": {
                        "type": "string",
                        "description": "If present and not empty, only projects whose name contains this string will be returned",
                        "internalName": "apiFilter_project:name_ct"
                      },
                      "name_equals": {
                        "type": "string",
                        "description": "If present and not empty, only projects whose name contains this string will be returned",
                        "internalName": "apiFilter_project:name_eq"
                      },
                      "memberUsrerId_equals": {
                        "type": "string",
                        "description": "If present and non-zero only projects assigned to the specified user will be returned"
                      }
                    },
                    "description": "If more than one filter is specified, only projects matching all filters will be returned",
                    "required": []
                  }
                }
              },
              "examples": {
                "example1": {
                  "value": {
                    "filters": {
                      "name_contains": "foo",
                      "name_equals": "My Project",
                      "memberUsrerId_equals": "xxxxxx"
                    }
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "data": {
                      "type": "array",
                      "items": {
                        "$ref": "#/components/schemas/Project Object",
                        "description": "Project details"
                      }
                    },
                    "metadata": {
                      "$ref": "#/components/schemas/Dataset Response Metadata",
                      "description": "Metadata about the response"
                    }
                  },
                  "required": [
                    "data",
                    "metadata"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        },
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "post": {
        "summary": "Create a new project",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "name": {
                    "type": "string",
                    "description": "The name of the new project",
                    "internalName": "project_name"
                  }
                },
                "required": [
                  "name"
                ]
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "id": {
                      "type": "string",
                      "description": "ID of the newly created project"
                    }
                  },
                  "required": [
                    "id"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        }
      }
    },
    "/project/{projectId}": {
      "get": {
        "summary": "Get details of a specific project",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "data": {
                      "type": "array",
                      "items": {
                        "$ref": "#/components/schemas/Project Object"
                      }
                    },
                    "metadata": {
                      "$ref": "#/components/schemas/Dataset Response Metadata"
                    }
                  },
                  "required": [
                    "data",
                    "metadata"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          },
          "404": {
            "$ref": "#/components/responses/Error: Not found"
          }
        },
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "parameters": [
        {
          "in": "path",
          "name": "projectId",
          "description": "The project ID",
          "schema": {
            "type": "string"
          },
          "required": true
        }
      ]
    },
    "/recordType": {
      "get": {
        "summary": "Get a list of recordTypes",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "filters": {
                    "type": "object",
                    "properties": {
                      "name_equals": {
                        "type": "string",
                        "description": "If present and not empty only record types whose name matches this string will be returned",
                        "internalName": "apiFilter_recordType:name_eq"
                      },
                      "name_contains": {
                        "type": "string",
                        "description": "If present and not empty only record types  whose name contains this string will be returned",
                        "internalName": "apiFilter_recordType:name_ct"
                      }
                    },
                    "required": [],
                    "description": "If more than one filter is specified, only record types matching all filters will be returned"
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "data": {
                      "type": "array",
                      "items": {
                        "$ref": "#/components/schemas/Record Type Object"
                      }
                    },
                    "metadata": {
                      "$ref": "#/components/schemas/Dataset Response Metadata",
                      "description": "Metadata about the response"
                    }
                  },
                  "required": [
                    "data",
                    "metadata"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        },
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "post": {
        "summary": "Create a new record type",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "name": {
                    "type": "string",
                    "description": "The name of the recordType",
                    "internalName": "recordType_name"
                  },
                  "publicPreviewMessage": {
                    "type": "string",
                    "description": "The message displayed to non-logged-in users when they view a record of this type.",
                    "internalName": "recordType_publicPreviewMessage"
                  },
                  "builtInFieldsToDisplay": {
                    "type": "string",
                    "description": "A pipe delimited list of the built in fields to display of the list page. Valid built in fields for inclusion in this list are: id,labelId,project,path,relationships.",
                    "internalName": "recordType_builtInFieldsToDisplay",
                    "default": "id|labelId|project|path|relationships"
                  },
                  "colour": {
                    "type": "string",
                    "description": "The hex code (including leading #) of the colour to be used when drawing records of this type on node graphs.",
                    "internalName": "recordType_colour"
                  }
                },
                "required": [
                  "name"
                ]
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "id": {
                      "type": "string",
                      "description": "ID of the newly created project object"
                    }
                  },
                  "required": [
                    "id"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        }
      }
    },
    "/recordType/{recordTypeId}": {
      "get": {
        "summary": "Get the details of the datafields and other information about a recordType",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "data": {
                      "type": "array",
                      "items": {
                        "$ref": "#/components/schemas/Record Type Object"
                      }
                    },
                    "metadata": {
                      "$ref": "#/components/schemas/Dataset Response Metadata",
                      "description": "Metadata about the response"
                    }
                  },
                  "required": [
                    "data",
                    "metadata"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        },
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "parameters": [
        {
          "in": "path",
          "name": "recordTypeId",
          "description": "The record type ID",
          "schema": {
            "type": "string"
          },
          "required": true
        }
      ]
    },
    "/role": {
      "get": {
        "summary": "Get a list of user roles that have been defined",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "filters": {
                    "type": "object",
                    "properties": {
                      "name_equals": {
                        "type": "string",
                        "description": "If present and not empty only roles whose name matches this string will be returned",
                        "internalName": "apiFilter_role:name_eq"
                      },
                      "name_contains": {
                        "type": "string",
                        "description": "If present and not empty only roles whose name contains this string will be returned",
                        "internalName": "apiFilter_role:name_ct"
                      }
                    },
                    "description": "If more than one filter is specified, only roles matching all filters will be returned"
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "data": {
                      "type": "array",
                      "items": {
                        "$ref": "#/components/schemas/Role Object"
                      }
                    },
                    "metadata": {
                      "$ref": "#/components/schemas/Dataset Response Metadata",
                      "description": "Metadata about the response"
                    }
                  },
                  "required": [
                    "data",
                    "metadata"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        },
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "post": {
        "summary": "Create a new role",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "name": {
                    "type": "string",
                    "description": "The name of the new role",
                    "internalName": "role_name"
                  }
                },
                "required": [
                  "name"
                ]
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "id": {
                      "type": "string",
                      "description": "ID of the newly created role"
                    }
                  },
                  "required": [
                    "id"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        }
      }
    },
    "/role/{roleId}": {
      "get": {
        "summary": "Get the details of the permissions a given role provides",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "data": {
                      "type": "array",
                      "items": {
                        "$ref": "#/components/schemas/Role Object"
                      }
                    },
                    "metadata": {
                      "$ref": "#/components/schemas/Dataset Response Metadata",
                      "description": "Metadata about the response"
                    }
                  },
                  "required": [
                    "data",
                    "metadata"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        },
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "parameters": [
        {
          "in": "path",
          "name": "roleId",
          "description": "The role ID",
          "schema": {
            "type": "string"
          },
          "required": true
        }
      ]
    },
    "/record": {
      "get": {
        "summary": "Get a list of summary data about records (with optional filtering)",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "filters": {
                    "type": "object",
                    "properties": {
                      "name_equals": {
                        "type": "string",
                        "description": "If present, only records whose name matches this string will be returned. The name is taken from the contents of the \"Primary Data Field\" for  the record type.",
                        "internalName": "apiFilter_name:data_eq"
                      },
                      "name_contains": {
                        "type": "string",
                        "description": "If present, only records whose name contains this string will be returned.  The name is taken from the contents of the \"Primary Data Field\" for  the record type.",
                        "internalName": "apiFilter_name:data_ct"
                      },
                      "ownerId_equals": {
                        "type": "string",
                        "description": "If present and non-zero, only records belonging to the user with this ID will be returned",
                        "internalName": "apiFilter_owner:apiId_eq"
                      },
                      "projectId_equals": {
                        "type": "string",
                        "description": "If present and non-zero, only records belonging to the project with this ID will be returned",
                        "internalName": "apiFilter_project:apiId_eq"
                      },
                      "recordTypeId_equals": {
                        "type": "string",
                        "description": "If present and non-zero, only records of this type will be returned",
                        "internalName": "apiFilter_recordType:apiId_eq"
                      },
                      "path_startsWith": {
                        "type": "string",
                        "description": "If present and not empty, only records whose absolute path starts with this string will be returned i.e. pass the absolute path of a record (INCLUDING THE TRAILING SLASH) to find all descendants of that record",
                        "internalName": "apiFilter_record:path_sw"
                      }
                    },
                    "description": "If more than one filter is specified, only records matching all filters will be returned",
                    "required": []
                  }
                },
                "required": []
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "data": {
                      "type": "array",
                      "items": {
                        "$ref": "#/components/schemas/Record Summary Object"
                      }
                    },
                    "metadata": {
                      "$ref": "#/components/schemas/Dataset Response Metadata",
                      "description": "Metadata about the response"
                    }
                  },
                  "required": [
                    "data",
                    "metadata"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        },
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "post": {
        "summary": "Create a new record",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {},
        "security": [
          {
            "api_key": []
          }
        ]
      }
    },
    "/record/{recordId}": {
      "get": {
        "summary": "Get complete data about a single record",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "data": {
                      "type": "array",
                      "items": {
                        "$ref": "#/components/schemas/Record Object"
                      }
                    },
                    "metadata": {
                      "$ref": "#/components/schemas/Dataset Response Metadata",
                      "description": "Metadata about the response"
                    }
                  },
                  "required": [
                    "data",
                    "metadata"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        },
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "patch": {
        "summary": "Update record metadata (but not data stored in data fields)",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "data": {
                      "type": "array",
                      "items": {}
                    },
                    "metadata": {
                      "$ref": "#/components/schemas/Dataset Response Metadata",
                      "description": "Metadata about the response"
                    }
                  },
                  "required": [
                    "data",
                    "metadata"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        }
      },
      "delete": {
        "summary": "Delete the record",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "data": {
                      "type": "array",
                      "items": {}
                    },
                    "metadata": {
                      "$ref": "#/components/schemas/Dataset Response Metadata",
                      "description": "Metadata about the response"
                    }
                  },
                  "required": [
                    "data",
                    "metadata"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        }
      },
      "parameters": [
        {
          "in": "path",
          "name": "recordId",
          "description": "Record ID",
          "schema": {
            "type": "string"
          },
          "required": true
        }
      ]
    },
    "/record/{recordId}/dataField/{dataFieldId}": {
      "get": {
        "summary": "Get the raw content of the specified field ready - i.e. if it is an image field content-type will be image/<imageType> and the binary image data will be served.",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {
          "200": {
            "description": "The response will be raw data with an appropriate mime type header",
            "headers": {}
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        },
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "put": {
        "summary": "Replace the data in this data field for this record",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "data": {
                      "type": "array",
                      "items": {}
                    },
                    "metadata": {
                      "$ref": "#/components/schemas/Dataset Response Metadata",
                      "description": "Metadata about the response"
                    }
                  },
                  "required": [
                    "data",
                    "metadata"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        }
      },
      "delete": {
        "summary": "Delete the data in this data field for this record",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "data": {
                      "type": "array",
                      "items": {}
                    },
                    "metadata": {
                      "$ref": "#/components/schemas/Dataset Response Metadata",
                      "description": "Metadata about the response"
                    }
                  },
                  "required": [
                    "data",
                    "metadata"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        }
      },
      "parameters": [
        {
          "in": "path",
          "name": "recordId",
          "description": "Record ID",
          "schema": {
            "type": "string"
          },
          "required": true
        },
        {
          "in": "path",
          "name": "dataFieldId",
          "description": "Data field ID",
          "schema": {
            "type": "string"
          },
          "required": true
        }
      ]
    },
    "/record/{recordId}/relationship/": {
      "post": {
        "summary": "Create a new relationship from the specified record to another record",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {},
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "parameters": [
        {
          "in": "path",
          "name": "recordId",
          "description": "Record ID",
          "schema": {
            "type": "string"
          },
          "required": true
        }
      ]
    },
    "/record/{recordId}/label": {
      "post": {
        "summary": "Create a new label and associate it with this record",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {},
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "parameters": [
        {
          "in": "path",
          "name": "recordId",
          "description": "Record ID",
          "schema": {
            "type": "string"
          },
          "required": true
        }
      ]
    },
    "/record/{recordId}/label/{labelId}": {
      "post": {
        "summary": "This is ONLY for assigning an existing unassigned label to an existing record",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {},
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "delete": {
        "summary": "Dissassociate the label from the record",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {}
      },
      "parameters": [
        {
          "in": "path",
          "name": "recordId",
          "description": "Record ID",
          "schema": {
            "type": "string"
          },
          "required": true
        },
        {
          "in": "path",
          "name": "labelId",
          "description": "Label ID",
          "schema": {
            "type": "string"
          },
          "required": true
        }
      ]
    },
    "/user": {
      "get": {
        "summary": "Get a list of users with optional filtering",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "filters": {
                    "type": "object",
                    "properties": {
                      "disabledState_equals": {
                        "type": "boolean",
                        "description": "Set to true for only disabled users, set to false for only enabled users. Omit this field to get both enabled and disabled users",
                        "internalName": "apiFilter_disabledStateFilter"
                      },
                      "firstName_equals": {
                        "type": "string",
                        "internalName": "apiFilter_user:firstName_eq"
                      },
                      "lastName_equals": {
                        "type": "string",
                        "internalName": "apiFilter_user:lastName_eq"
                      },
                      "firstName_contains": {
                        "type": "string",
                        "internalName": "apiFilter_user:firstName_ct"
                      },
                      "lastName_contains": {
                        "type": "string",
                        "internalName": "apiFilter_user:lastName_ct"
                      },
                      "email_equals": {
                        "type": "string",
                        "internalName": "apiFilter_user:email_eq"
                      },
                      "email_contains": {
                        "type": "string",
                        "internalName": "apiFilter_user:email_ct"
                      },
                      "last_login_after": {
                        "$ref": "#/components/schemas/Unix Timestamp Field",
                        "description": "If set then only users who have logged in after this time will be returned",
                        "internalName": "apiFilter_user:lastLoggedInAt_gt"
                      },
                      "last_login_before": {
                        "$ref": "#/components/schemas/Unix Timestamp Field",
                        "description": "If set then only users who have logged in before this time will be returned",
                        "internalName": "apiFilter_user:lastLoggedInAt_lt"
                      }
                    }
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "data": {
                      "type": "array",
                      "items": {
                        "$ref": "#/components/schemas/User Object",
                        "description": "User details"
                      }
                    },
                    "metadata": {
                      "$ref": "#/components/schemas/Dataset Response Metadata",
                      "description": "Metadata about the response"
                    }
                  },
                  "required": [
                    "data",
                    "metadata"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        },
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "post": {
        "summary": "Create a new user",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "firstName": {
                    "type": "string",
                    "description": "The first name of the user",
                    "internalName": "user_firstName"
                  },
                  "lastName": {
                    "type": "string",
                    "description": "The last name of the user",
                    "internalName": "user_lastName"
                  },
                  "email": {
                    "type": "string",
                    "description": "The enail address of the user",
                    "internalName": "user_email"
                  },
                  "password": {
                    "type": "string",
                    "description": "The first name of the user",
                    "internalName": "password"
                  },
                  "mobile": {
                    "type": "string",
                    "description": "The mobile number of the user",
                    "internalName": "user_mobile"
                  }
                },
                "required": [
                  "firstName",
                  "lastName",
                  "email",
                  "password"
                ]
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "",
            "headers": {},
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "id": {
                      "type": "string",
                      "description": "ID of the newly created user"
                    }
                  },
                  "required": [
                    "id"
                  ]
                }
              }
            }
          },
          "400": {
            "$ref": "#/components/responses/Error: Bad request"
          },
          "401": {
            "$ref": "#/components/responses/Error: Unauthorised request"
          },
          "403": {
            "$ref": "#/components/responses/Error: Forbidden"
          }
        }
      }
    },
    "/user/{userId}": {
      "get": {
        "summary": "Get details of the specified user",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {},
        "security": [
          {
            "api_key": []
          }
        ]
      },
      "patch": {
        "summary": "Update the user attributes",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {}
      },
      "delete": {
        "summary": "Delete the user specified",
        "description": "",
        "operationId": "",
        "tags": [],
        "parameters": [],
        "requestBody": {},
        "responses": {}
      },
      "parameters": [
        {
          "in": "path",
          "name": "userId",
          "description": "User ID",
          "schema": {
            "type": "string"
          },
          "required": true
        }
      ]
    }
  },
  "components": {
    "schemas": {
      "Project Object": {
        "type": "object",
        "properties": {
          "name": {
            "type": "string",
            "description": "Name of the project"
          },
          "id": {
            "type": "string",
            "description": "Project ID"
          }
        },
        "required": [
          "name",
          "id"
        ]
      },
      "Response Error": {
        "type": "object",
        "properties": {
          "code": {
            "type": "integer",
            "description": "Error code"
          },
          "message": {
            "type": "string",
            "description": "Human readable error message"
          }
        },
        "required": [
          "status",
          "code",
          "message"
        ]
      },
      "Dataset Response Metadata": {
        "type": "object",
        "properties": {
          "numRecords": {
            "type": "integer",
            "description": "Total number of records in the requested dataset"
          },
          "numPages": {
            "type": "integer",
            "description": "Number of pages the dataset has been broken into"
          },
          "nextPageURL": {
            "type": "string",
            "description": "The URL of the next page of data"
          },
          "pageNumber": {
            "type": "integer",
            "description": "The page number for this page of data"
          }
        },
        "required": [
          "numRecords",
          "pageNumber",
          "numPages"
        ]
      },
      "User Object": {
        "type": "object",
        "properties": {
          "id": {
            "type": "string",
            "description": "User ID"
          },
          "firstName": {
            "type": "string",
            "description": "First name"
          },
          "lastName": {
            "type": "string",
            "description": "Last name"
          },
          "email": {
            "type": "string",
            "description": "Email address",
            "format": "email"
          },
          "lastLoggedInAt": {
            "$ref": "#/components/schemas/Unix Timestamp Field",
            "description": "Unix timestamp of user's last login - this will be zero if the user has never logged in"
          },
          "lastLoginIp": {
            "type": "string",
            "description": "IP address that user last logged in from",
            "format": "ipv4"
          },
          "mobile": {
            "type": "string",
            "description": "Mobile number"
          },
          "projects": {
            "type": "array",
            "items": {
              "type": "integer",
              "description": "Project ID"
            },
            "description": "List of projects IDs for the projects this user is a member of"
          },
          "createdAt": {
            "$ref": "#/components/schemas/Unix Timestamp Field",
            "description": "Unix timestamp of user creation"
          },
          "isDisabled": {
            "type": "boolean",
            "description": "Whether the user is currently disabled or not"
          },
          "roles": {
            "type": "array",
            "items": {
              "type": "string",
              "description": "Role ID"
            },
            "description": "List of role IDs for the roles assigned to this user"
          }
        },
        "required": [
          "id",
          "firstName",
          "lastName",
          "email",
          "projects",
          "createdAt",
          "isDisabled",
          "roles",
          "lastLoginIp",
          "lastLoggedInAt"
        ]
      },
      "Unix Timestamp Field": {
        "type": "integer",
        "description": "Unix Timestamp"
      },
      "Record Type Object": {
        "type": "object",
        "properties": {
          "id": {
            "type": "integer",
            "description": "The internal ID of this record type"
          },
          "name": {
            "type": "string",
            "description": "The name of this record type"
          },
          "primaryDataFieldId ": {
            "type": "integer",
            "description": "The ID of the primary data field - usually the \"name\" or \"title\" field"
          },
          "publicPreviewMessage": {
            "type": "string",
            "description": "The HTML message shown at the top of the page when an unauthorized user scans a label or follows a share link for a record of this type. for "
          },
          "colour": {
            "type": "string",
            "description": "Hex code of the colour used to represent this record type on node graphs in the Ampletracks UI"
          }
        },
        "required": [
          "name",
          "primaryDataFieldId ",
          "publicPreviewMessage",
          "id",
          "publicPreviewMessage",
          "projectId",
          "colour"
        ]
      },
      "Role Object": {
        "type": "object",
        "properties": {
          "id": {
            "type": "integer"
          },
          "name": {
            "type": "string"
          }
        },
        "required": [
          "id",
          "name"
        ]
      },
      "Record Summary Object": {
        "type": "object",
        "properties": {
          "name": {
            "type": "string",
            "description": "This is taken from whatever field is identified as the \"Primary Data Field\" in the record type"
          },
          "id": {
            "type": "integer",
            "description": "The record ID"
          },
          "projectId": {
            "type": "integer",
            "description": "The ID of the project this record belongs to"
          },
          "parentRecordId": {
            "type": "integer",
            "description": "The ID of the parent record - or zero if this is a root record"
          },
          "path": {
            "type": "string",
            "description": "The absolute path of this record i.e. /<root record ID>/<record ID>/.../<this record ID>"
          },
          "ownerId": {
            "type": "integer",
            "description": "The ID of the user who owns this record"
          },
          "recordTypeId": {
            "type": "integer",
            "description": "The ID of the record type of this record"
          }
        },
        "required": [
          "name",
          "id",
          "projectId",
          "parentRecordId",
          "path",
          "ownerId",
          "recordTypeId"
        ]
      },
      "Record Object": {
        "type": "object",
        "properties": {
          "summary": {
            "$ref": "#/components/schemas/Record Summary Object"
          },
          "data": {
            "type": "object",
            "properties": {
              "<data field API name>": {
                "type": "object",
                "properties": {
                  "dataFieldId": {
                    "type": "string",
                    "description": "The data Field ID"
                  },
                  "order": {
                    "type": "integer",
                    "description": "An integer denoting the order in which fields are displayed on the form. This need not be incremental i.e. there will be gaps. Higher numbers mean that this field appears lower down the page. "
                  },
                  "dataFieldType": {
                    "type": "string",
                    "description": "One of: Integer,Textbox,Textarea,Select,Date,Duration,Email Address,URL,Upload,Image,Float,Type To Search,Suggested Textbox,Chemical Formula"
                  },
                  "value": {
                    "type": "string",
                    "description": "The value of this field for this record. This will not be present for Upload and Image field types. For these field types you must use GET /record/{recordId}/dataField/{dataFieldId}"
                  },
                  "isHidden": {
                    "type": "boolean",
                    "description": "Whether this field was visible when the record was last saved (fields can be hidden if they are dependent on the value in some other field in the record)"
                  },
                  "isInherited": {
                    "type": "boolean",
                    "description": "Whether the value for this field was inherited from the parent"
                  },
                  "isValid": {
                    "type": "boolean",
                    "description": "Whether the value for this field was considered valid when the record was last saved"
                  }
                },
                "description": "The data field API name is specified on the data field management page in the Ampletracks UI. Only data field which have a non-empty API name will be included in the API response",
                "required": [
                  "dataFieldId",
                  "dataFieldType",
                  "isHidden",
                  "isInherited",
                  "isValid",
                  "order"
                ]
              }
            },
            "required": [
              "<data field API name>"
            ]
          }
        },
        "required": [
          "summary",
          "data"
        ]
      }
    },
    "requestBodies": {},
    "securitySchemes": {
      "api_key": {
        "type": "apiKey",
        "name": "api_key",
        "in": "header"
      }
    },
    "parameters": {},
    "responses": {
      "Error: Unauthorised request": {
        "description": "This error is returned when there is a problem with the API credentials supplied",
        "headers": {},
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/Response Error"
            }
          }
        }
      },
      "Error: Bad request": {
        "description": "This error is returned if there is something inherently wrong with the request parameters i.e. the body JSON is invalid, or a field within the body JSON cannot be coerced to the right type.",
        "headers": {},
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/Response Error"
            }
          }
        }
      },
      "Error: Not found": {
        "description": "The requested item was not found. This might be because no such record exists, or it might be that it does exist but you don't have permission to list these items and thus it is effectively non-existent for you.",
        "headers": {},
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/Response Error"
            }
          }
        }
      },
      "Error: Forbidden": {
        "description": "This error is returned when the credentials supplied are correct, but the user does not have permission to perform the requested action",
        "headers": {},
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/Response Error"
            }
          }
        }
      }
    }
  }
}