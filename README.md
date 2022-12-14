![Ampletracks Logo](www/images/ampletracks-logo.svg "Ampletracks Logo")

# Introduction

Ampletracks is a flexible and heirarchical database specifically designed for storing data about material samples. It aims to balance the following:
- Ease of use
- Advanced features such as the ability to record various relationships between records
- Security
- Flexbility
- Cost effectiveness - both in terms of development cost and running cost

# Goals

Ampletracks has been designed to meet the following fundamental goals:
- Keep track of material samples and associated artefacts (physical or virtual) in order to reduce the risk of loss and speed up the process of locating them
- Record meta data about samples and associated artefacts systematically in a way that can be shared easily
- Record the relationships between samples and associated artefacts, and track their ancestry

# Features

Ampletracks currently has the following features:
- Handle an arbitrary number of record types e.g. samples, document, equipment 21 Define an arbitrary number data fields of varying types (text box, date, drop-down select box, radio buttons etc) for each record Type
- Define and display dependericles between Fields e.g. only show field B if the value in field A is greater than 10
- Create, browse, update, delete any number of records of any record type
- See the version history of any data field in any record
- Generate QRCoded labels which will direct any device to the corresponding record in Appletracks. This includes option for administrators to select a subset of data fields which are accessible to users without a logon to the system e.g. the phone number to ring if the sample is found
- Define a parent-child hierarchy between records (only of the same record type) and the option to mark the values of some data fields as being inherited from the parent record
- Define relationships that link one or more records to another record (even of a different record type) 28 Export record type definitions in XML format, and Import record type definitions created on other Ampletracks instances

# Glossary

The system is built around a number of entities:

- Record Types

    The representation of the different things you are storing data about
    
- Records

    An item of a given record type

- Data Fields

    The individual bits of data associated with a given record type

- Relationships

    A Link between a record and one or more other records (of the same type, or a different type)

- Users

    People who have a login to the system

- Permission

    The right to perform a specific action (list, view, create, update, delete), to a specific entity

- Role

    A named bundle of permissions that can be assigned to a user

- Project

    An organisational unit which can have users and records associated with it

# Installation

See the Wiki section: [Installation](https://github.com/Ampletracks/Ampletracks/wiki/Installing-Ampletracks)
