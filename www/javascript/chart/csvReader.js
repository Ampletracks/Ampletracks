// See https://chatgpt.com/c/675323db-88b0-8004-a2a0-a357e684c84f
/**
 * CSVReader
 *
 * @description
 * A simple CSV-to-workbook converter that mimics the interface of an XLSX workbook as returned by SheetJS.
 * This enables you to use CSV data in code that expects a SheetJS-like workbook object, allowing a drop-in 
 * replacement with minimal changes. The resulting "workbook" contains a single sheet named "Sheet1", and 
 * each cell is assigned a coordinate (e.g., A1, B1, etc.) in a manner compatible with XLSX utils.
 *
 * @usage
 * const reader = new CSVReader();
 * const workbook = reader.read(csvArrayBuffer);
 * // Now 'workbook.SheetNames' and 'workbook.Sheets["Sheet1"]' can be used as if reading from XLSX.
 *
 * @notes
 * - All cell values are treated as strings ('t':'s'). If you need type inference (e.g., numeric parsing),
 *   you can extend the code to attempt parsing values as numbers.
 * - The workbook will have a '!ref' property defining its range, similar to XLSX sheets.
 * - Only a single sheet named 'Sheet1' is created, and empty lines or cells beyond the last filled cell 
 *   are not included.
 */

function CSVReader() {
    // Helper function to convert column number (0-based) to Excel column letters (A, B, C, ... AA, AB, ...)
    function colNumToLetters(colNum) {
        let letters = '';
        let temp = colNum;
        while (temp >= 0) {
            letters = String.fromCharCode((temp % 26) + 65) + letters;
            temp = Math.floor(temp / 26) - 1;
        }
        return letters;
    }

    // Reads the CSV data from an ArrayBuffer, returns a workbook-like object
    this.read = function(arrayBuffer, options) {
        // Convert arrayBuffer to string (UTF-8)
        const decoder = new TextDecoder('utf-8');
        const csvText = decoder.decode(new Uint8Array(arrayBuffer));

        // Split into lines
        const lines = csvText.split(/\r\n|\n/);

        // Parse each line into cells
        let maxCol = 0;
        const sheet = {};
        lines.forEach((line, rowIndex) => {
            if (!line.trim()) return; // Skip empty lines if any
            const cells = line.split(',');
            cells.forEach((cellValue, colIndex) => {
                const colLetter = colNumToLetters(colIndex);
                const cellAddress = colLetter + (rowIndex + 1);
                // Store value as a string cell; you could parse numerics if desired
                sheet[cellAddress] = { t: 's', v: cellValue };
                if (colIndex > maxCol) maxCol = colIndex;
            });
        });

        // Determine sheet range
        const rowCount = lines.length;
        if (rowCount > 0) {
            const lastColLetter = colNumToLetters(maxCol);
            sheet['!ref'] = `A1:${lastColLetter}${rowCount}`;
        } else {
            // Empty CSV
            sheet['!ref'] = 'A1:A1';
        }

        const workbook = {
            SheetNames: ['Sheet1'],
            Sheets: {
                'Sheet1': sheet
            }
        };

        return workbook;
    };
}

// Example usage:
// (In your code, you would use CSVReader or XLSX library interchangeably.)
// const reader = new CSVReader();
// const workbook = reader.read(csvArrayBuffer);
// console.log(workbook.SheetNames); // ['Sheet1']
// console.log(workbook.Sheets['Sheet1']); // { A1: {t:'s', v:'...'}, ... , '!ref': 'A1:...' }

