# Example Record Type Setup

In this directory you will find some suggested record types for a 'barebones' Ampletracks setup.

The record specification is XML formatted. You can import the records on Ampletracks via the content management system (CMS) user interface (UI). No modification is necessary for the records to be imported.

## Records

We include records for [`Sample`](./record_types/RecordTypeStructure-Sample_20231031.xml), [`Equipment`](./record_types/RecordTypeStructure-Equipment_20231031.xml) and [`Dataset`](./record_types/RecordTypeStructure-Dataset_20231031.xml).

### Metadata

We have intentionally kept the metadata for these record types lightweight. Feel free to modify the data fields to your specifications and needs.

## Importing in Ampletracks

To import a record type specification to Ampletracks you need CMS administrator permissions, 'superuser' within Ampletracks, and the default permissions unless you specify new user types.

To import a record type:

1. Navigate to the grey banner menu on the Ampletracks UI
2. Hover over **Schema** on the left-hand
3. Select **Record Types** from the drop down menu
4. You will see 3 buttons to the right-hand side of the UI
5. Click on **Import**, you will be redirected to a new page
6. On the new page click on the **Choose file** button to select a file stored locally on your machine with the file system interaction dialog box
7. Click the **Import** button once you have selected the file

## Creating record type to record type relationships on the CMS

You can create a record type to record type relationship once you have at least one record type on your Ampletracks system. Record type to record type relationships are extrinsic. Record type to record type relationships are allowed between records of the same type and records of a different type.

To create a record type to record type relationship:

1. Navigate to the grey banner menu on the Ampletracks UI
2. Hover over **Schema** on the left-hand
3. Select **Relationships** from the drop down menu
4. You will see 2 buttons to the right-hand side of the UI
5. Click on **Add Relationship Pair**, you will be redirected to a new page
6. You will see a pane where you can select two types of records (whether the same or different) and specify the relationship between them in plain language
7. As an example we will specify a relationship between a **Sample** and a piece of **Equipment**
   1. In the top pane select **Equipment** from the drop down menu
   2. In the bottom pane select **Sample** from the drop down menu
   3. On the left-hand text box type in "was measured using", i.e. "**Sample** was measured using **Equipment**"
   4. On the right-hand text box type in "measured", i.e. "**Equipment** measured **Sample**"
   5. For how many relationships we can have between record type and record type the minimum is 1 and the maximum is 1000000, if you wanted a **Sample** to have a maximum of 10 relationships to a piece of **Equipment** you need to input 10 in the left-hand box underneath *max*