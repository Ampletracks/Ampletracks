

  const Zip = fflate.Zip;
  const ZipPassThrough = fflate.ZipPassThrough;

  /**
   * @param {Function} fetchNextFile
   *   A function that accepts a callback. When it has the next file,
   *   it calls the callback with an object { zipPath, url }.
   *   If there are no more files, it calls the callback with null.
   *
   * @param {Function} updateProgress
   *   A function called each time a file finishes downloading.
   *   Signature: (zipPath) => void
   *
   * @param {Function} updateDownloadProgress
   *   A function called periodically during each file’s download.
   *   Signature: (zipPath, downloadedBytes, percent, totalSize) => void
   *
   *   - zipPath: The path/filename inside the ZIP
   *   - downloadedBytes: Number of bytes downloaded for this file so far
   *   - percent: Download percentage (if total size is known), otherwise null
   *   - totalSize: Total expected file size (if known), otherwise null
   */
  async function streamFetchToZip(fetchNextFile, updateProgress, updateDownloadProgress) {
    // Prompt the user where to save the .zip file
    const fileHandle = await window.showSaveFilePicker({
      suggestedName: "archive.zip",
      types: [{ accept: { "application/zip": [".zip"] } }],
    });
    const writable = await fileHandle.createWritable();

    // Create a new ZIP instance from fflate
    const zip = new Zip(function onZipData(err, data, final) {
      if (err) {
        throw err;
      }
      // Write the produced ZIP data to our chosen file
      writable.write(data);
      // If final is true, the ZIP is fully closed
      if (final) {
        writable.close();
      }
    });

    // Continuously fetch "next file" info until none remain
    while (true) {
      // fetchNextFile calls our callback with either {zipPath, url} or null
      const fileInfo = await new Promise(function (resolve) {
        fetchNextFile(resolve);
      });

      if (!fileInfo) {
        // No more files to process
        break;
      }

      const zipPath = fileInfo.zipPath;
      const url = fileInfo.url;

      // Fetch the file
      const response = await fetch(url);
      if (!response.ok) {
        console.error("Fetch failed (" + response.status + "): " + url);
        continue; // Skip to next file
      }
      if (!response.body) {
        console.error("No response body for: " + url);
        continue;
      }

      // If the server provides Content-Length, we can show a percentage
      const contentLengthHeader = response.headers.get("Content-Length");
      const totalSize = contentLengthHeader ? parseInt(contentLengthHeader, 10) : null;

      // Create a ZIP entry for this file
      console.log(zipPath);
      const zipEntry = new ZipPassThrough(zipPath);
      zip.add(zipEntry);

      // Thresholds for reporting download progress
      var THRESHOLD_BYTES = 100 * 1024; // 100 KB
      var THRESHOLD_TIME = 30 * 1000;   // 30 seconds

      var downloadedSoFar = 0;
      var lastReportedBytes = 0;
      var lastReportedTime = Date.now();

      function maybeReportProgress(force) {
        var now = Date.now();
        var bytesSinceLastReport = downloadedSoFar - lastReportedBytes;
        var timeSinceLastReport = now - lastReportedTime;

        // We report progress if:
        // 1) we've downloaded at least THRESHOLD_BYTES since last report
        // 2) or at least THRESHOLD_TIME ms have passed
        // 3) or if force == true (e.g. final call)
        if (
          force ||
          bytesSinceLastReport >= THRESHOLD_BYTES ||
          timeSinceLastReport >= THRESHOLD_TIME
        ) {
          var percent = null;
          if (totalSize) {
            percent = (downloadedSoFar / totalSize) * 100;
          }
          updateDownloadProgress(zipPath, downloadedSoFar, percent, totalSize);

          lastReportedBytes = downloadedSoFar;
          lastReportedTime = now;
        }
      }

      // Read the response body in chunks
      var reader = response.body.getReader();
      while (true) {
        var result = await reader.read();
        if (result.done) {
          // Mark the ZIP entry as finished
          zipEntry.push(new Uint8Array(0), true);

          // One last forced progress update (especially if we knew totalSize)
          if (totalSize) {
            downloadedSoFar = totalSize; // ensure we reflect full size
          }
          maybeReportProgress(true);

          // Notify that this file is fully processed
          updateProgress(zipPath);
          break;
        }

        // Write the chunk to the ZIP entry
        var chunk = result.value;
        zipEntry.push(chunk, false);

        // Update downloaded byte count
        downloadedSoFar += chunk.byteLength;
        maybeReportProgress(false);
      }
    }

    // Finalise the ZIP archive
    zip.end();
  }

  /**
   * Example usage of the above function.
   * This "fetchNextFile" function just pops the next file from an array.
   */
  var filesToDownload = [
    { zipPath: "docs/readme.txt", url: "/downloads/readme.txt" },
    { zipPath: "images/picture.jpg", url: "/downloads/picture.jpg" }
    // Add more items as needed
  ];

  function fetchNextFile(callback) {
    if (filesToDownload.length === 0) {
      callback(null); // no more files
    } else {
      var next = filesToDownload.shift();
      callback(next); // { zipPath, url }
    }
  }

  function onFileComplete(zipPath) {
    console.log("Finished writing: " + zipPath);
  }

  function onDownloadProgress(zipPath, downloadedBytes, percent, totalSize) {
    if (percent !== null) {
      console.log(
        "[" + zipPath + "] " +
        downloadedBytes + " bytes (" + percent.toFixed(1) + "% of " + totalSize + ")"
      );
    } else {
      console.log(
        "[" + zipPath + "] " +
        downloadedBytes + " bytes (unknown total)"
      );
    }
  }
