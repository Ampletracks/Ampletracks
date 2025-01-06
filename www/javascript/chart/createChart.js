/**
 * @function graphXlsx
 * @description
 * Fetches one or more XLSX files, caches their parsed workbooks, extracts data for multiple series,
 * and renders an ECharts chart. Multiple series can reference different `src` URLs, and each unique
 * file is loaded only once and then cached. After the chart is rendered, workbook objects are cleared
 * from memory.
 *
 * **Key Features:**
 * - Supports multiple data sources: each series can specify its own `src` to override the top-level `src`.
 * - Caches each loaded workbook keyed by `src`, so if multiple series share the same file, it isn't re-fetched.
 * - Automatically detects if the X-axis should be a category or value axis.
 * - Allows per-series chart types, line/point display configurations, and styling.
 * - Fetches titles and labels from cell references where needed.
 * - Adjusts axis bounds and ticks for clean numeric axes.
 *
 * All of this code was created by ChatGPT, for more context on the creation of this code see the associated conversation thread:
 *   https://chatgpt.com/share/67543029-5f10-8004-bdf2-8ad084fa357d
 *
 * @param {Object} options - Configuration object
 * @param {string} options.src - URL of the primary XLSX, or CSV file to fetch. Series may override this with their own src.
 * @param {HTMLElement} options.target - The DOM element that will contain the generated chart.
 * @param {string} [options.theme='light'] - ECharts theme.
 *
 * @param {Object} [options.tooltip] - Tooltip configuration.
 * @param {boolean} [options.tooltip.show=true] - Whether tooltips are shown.
 * @param {string} [options.tooltip.formatter='{b}: {c}'] - Tooltip format string.
 *
 * @param {string} [options.title] - Chart title (can be cell reference).
 * @param {string} [options.worksheet] - Default worksheet name if cell references don't specify one.
 * @param {boolean} [options.showLegend=true] - Whether to show the legend.
 *
 * @param {Object} [options.xAxis] - X-axis configuration.
 * @param {string} [options.xAxis.title] - X-axis title (cell reference allowed).
 * @param {number} [options.xAxis.min] - Minimum X value (value axis only).
 * @param {number} [options.xAxis.max] - Maximum X value (value axis only).
 * @param {number} [options.xAxis.ticks] - Tick interval. For value axes: numeric spacing; for category axes: label skipping.
 *
 * @param {Object} [options.yAxisLeft] - Left Y-axis configuration.
 * @param {string} [options.yAxisLeft.title] - Title (cell reference allowed).
 * @param {number} [options.yAxisLeft.min] - Min Y for the left axis.
 * @param {number} [options.yAxisLeft.max] - Max Y for the left axis.
 * @param {number} [options.yAxisLeft.ticks] - Tick interval for the left Y-axis.
 *
 * @param {Object} [options.yAxisRight] - Right Y-axis configuration.
 * @param {string} [options.yAxisRight.title] - Title (cell reference allowed).
 * @param {number} [options.yAxisRight.min] - Min Y for the right axis.
 * @param {number} [options.yAxisRight.max] - Max Y for the right axis.
 * @param {number} [options.yAxisRight.ticks] - Tick interval for the right Y-axis.
 *
 * @param {Array} options.series - Array of data series configurations.
 * @param {string} [options.series[].src] - Optional URL of an XLSX, or CSV file for this series. If not provided, uses top-level src.
 * @param {string} [options.series[].type='line'] - Chart type for this series ('line', 'scatter', 'bar', etc.).
 * @param {string} [options.series[].title] - Series title (cell reference allowed).
 * @param {boolean} [options.series[].smooth=false] - If true, renders lines smoothly (for line charts).
 * @param {Object} [options.series[].style] - Style config for line and points.
 * @param {Object} [options.series[].style.line] - Line style (e.g., {width:2, color:'#ff0000'}).
 * @param {Object} [options.series[].style.point] - Point style (e.g., {size:2, color:'#00ff00'}).
 * @param {string} [options.series[].worksheet] - Worksheet for this series' data (overrides top-level if given).
 * @param {string} [options.series[].dataOrientation='column'] - Data orientation ('row' or 'column').
 * @param {Object} options.series[].xData - X data configuration (start cell reference).
 * @param {string} options.series[].xData.location - Starting cell for X data.
 * @param {string} [options.series[].xData.title] - X-axis title for this series (cell reference allowed).
 * @param {Object} options.series[].yData - Y data configuration.
 * @param {string} options.series[].yData.location - Starting cell for Y data.
 * @param {string} [options.series[].yData.title] - Y-axis title (cell reference allowed).
 * @param {string} [options.series[].yData.which='left'] - Which Y-axis ('left' or 'right').
 * @param {string} [options.series[].color] - Hex color for the series.
 * @param {boolean|string} [options.series[].points=true] - Points display (false=none, true=circle, string=symbol).
 * @param {boolean|Object} [options.series[].line=true] - Line display:
 *                                                       false = no line (scatter)
 *                                                       true = default line
 *                                                       object = custom line style.
 *
 * @param {function} [options.onError] - Error callback, defaults to console.error.
 * @param {function} [options.onLoaded] - Callback before loading starts, useful for showing a spinner or message.
 *
 * @example
 * graphXlsx({
 *   src: 'data1.xlsx',
 *   target: document.getElementById('chartContainer'),
 *   theme: 'light',
 *   title: '=Sheet1!A1',
 *   showLegend: true,
 *   xAxis: { title: '=Sheet1!B2', ticks: 10 },
 *   yAxisLeft: { title: '=Sheet1!C2', ticks: 5 },
 *   series: [
 *     {
 *       type: 'scatter',
 *       title: '=Sheet1!A10',
 *       worksheet: 'Sheet1',
 *       dataOrientation: 'column',
 *       xData: { location: 'A5' },
 *       yData: { location: 'B5', which: 'left' },
 *       points: false,
 *       line: true, // turns scatter into line+points
 *       smooth: true,
 *       style: { line: { width: 2, color: '#ff0000' } }
 *     },
 *     {
 *       type: 'bar',
 *       title: '=OtherSheet!A1',
 *       src: 'data2.xlsx',
 *       xData: { location: 'C5' },
 *       yData: { location: 'D5', which: 'right' },
 *       points: 'diamond',
 *       line: false
 *     }
 *   ],
 *   onError: function(err) {
 *     console.error('Error loading chart:', err);
 *   },
 *   onLoaded: function() {
 *     console.log('Loading XLSX data...');
 *   }
 * });
 */

// A global cache for storing workbook objects keyed by their file src.
// This allows us to avoid re-downloading or re-parsing the same file multiple times.
const globalWorkbookCache = {};

function graphXlsx(options) {
    const {
        src,
        target,
        theme = 'light',
        tooltip = { show: true, formatter: '{b}: {c}' },
        title: chartTitle,
        worksheet: defaultWorksheet,
        showLegend = true,
        xAxis = {},
        yAxisLeft = {},
        yAxisRight = {},
        series = [],
        onError = function(error) { console.error(error); },
        onLoaded = function() {}
    } = options;

    function parseCellRef(cellRef) {
        const match = cellRef.match(/^([A-Za-z]+)(\d+)$/);
        if (!match) return null;
        const colLetters = match[1].toUpperCase();
        const rowNumber = parseInt(match[2], 10);
        let colNumber = 0;
        for (let i = 0; i < colLetters.length; i++) {
            colNumber = colNumber * 26 + (colLetters.charCodeAt(i) - 64);
        }
        return { c: colNumber - 1, r: rowNumber - 1 };
    }

    function getWorksheetName(wsNameOverride, workbook) {
        if (wsNameOverride && workbook.Sheets[wsNameOverride]) {
            return wsNameOverride;
        }
        if (defaultWorksheet && workbook.Sheets[defaultWorksheet]) {
            return defaultWorksheet;
        }
        const sheetNames = workbook.SheetNames;
        return sheetNames[0];
    }

    function resolveValueFromCell(value, workbook, wsName) {
        if (typeof value !== 'string') return value;
        const trimmed = value.trim();
        if (trimmed.startsWith('=')) {
            const eqVal = trimmed.substring(1);
            let sheetRef = wsName;
            let cellRef = eqVal;
            if (eqVal.includes('!')) {
                const parts = eqVal.split('!');
                sheetRef = parts[0];
                cellRef = parts[1];
            }

            const wsn = getWorksheetName(sheetRef, workbook);
            const ws = workbook.Sheets[wsn];
            if (!ws) return null;

            return (ws[cellRef] && ws[cellRef].v != null) ? ws[cellRef].v : null;
        } else {
            return value;
        }
    }

    function extractDataSeries(workbook, wsName, startCellRef, orientation = 'column') {
        const wsn = getWorksheetName(wsName, workbook);
        const ws = workbook.Sheets[wsn];
        const start = parseCellRef(startCellRef);
        if (!start) return [];

        const data = [];
        if (orientation === 'column') {
            let row = start.r;
            while (true) {
                const cellAddress = XLSX.utils.encode_cell({ r: row, c: start.c });
                const cell = ws[cellAddress];
                if (!cell || cell.v == null || cell.v === '') break;
                data.push(cell.v);
                row++;
            }
        } else if (orientation === 'row') {
            let col = start.c;
            while (true) {
                const cellAddress = XLSX.utils.encode_cell({ r: start.r, c: col });
                const cell = ws[cellAddress];
                if (!cell || cell.v == null || cell.v === '') break;
                data.push(cell.v);
                col++;
            }
        }
        return data;
    }

    function createChart(sourceToWorkbookMap) {
        const mainWorkbook = sourceToWorkbookMap[src];

        const resolvedTitle = resolveValueFromCell(chartTitle, mainWorkbook, defaultWorksheet);
        const resolvedXAxisTitle = resolveValueFromCell(xAxis.title, mainWorkbook, defaultWorksheet);
        const resolvedYAxisLeftTitle = resolveValueFromCell(yAxisLeft.title, mainWorkbook, defaultWorksheet);
        const resolvedYAxisRightTitle = resolveValueFromCell(yAxisRight.title, mainWorkbook, defaultWorksheet);

        const chartOption = {
            title: {
                text: resolvedTitle || '',
                show: !!resolvedTitle,
                top: 0
            },
            tooltip: {
                show: tooltip.show,
                formatter: tooltip.formatter || '{b}: {c}',
                trigger: 'axis'
            },
            legend: {
                show: showLegend,
                type: 'scroll',
                top: 40,
                left: 'left'
            },
            xAxis: {
                name: resolvedXAxisTitle || '',
                nameLocation: 'middle',
                nameGap: 30,
                min: xAxis.min !== undefined ? xAxis.min : null,
                max: xAxis.max !== undefined ? xAxis.max : null
            },
            yAxis: [
                {
                    type: 'value',
                    name: resolvedYAxisLeftTitle || '',
                    min: yAxisLeft.min !== undefined ? yAxisLeft.min : null,
                    max: yAxisLeft.max !== undefined ? yAxisLeft.max : null,
                    interval: (yAxisLeft.ticks !== undefined && yAxisLeft.ticks !== 0) ? yAxisLeft.ticks : null,
                    position: 'left'
                },
                {
                    type: 'value',
                    name: resolvedYAxisRightTitle || '',
                    min: yAxisRight.min !== undefined ? yAxisRight.min : null,
                    max: yAxisRight.max !== undefined ? yAxisRight.max : null,
                    interval: (yAxisRight.ticks !== undefined && yAxisRight.ticks !== 0) ? yAxisRight.ticks : null,
                    position: 'right'
                }
            ],
            grid: {
                top: 100,
                right: 20,
                bottom: 30,
                left: 50
            },
            series: []
        };

        let longestX = 0;
        let chosenXData = null;
        const allSeriesData = [];

        // Gather data for each series
        series.forEach((s) => {
            const seriesSrc = s.src || src;
            const workbookForSeries = sourceToWorkbookMap[seriesSrc];
            const resolvedSeriesTitle = resolveValueFromCell(s.title, workbookForSeries, s.worksheet);

            const xDataStartRef = s.xData && s.xData.location;
            const xDataOrientation = s.dataOrientation || 'column';
            const xValues = xDataStartRef ? extractDataSeries(workbookForSeries, s.worksheet, xDataStartRef, xDataOrientation) : [];

            const yDataStartRef = s.yData && s.yData.location;
            const yDataOrientation = s.dataOrientation || 'column';
            const yValues = yDataStartRef ? extractDataSeries(workbookForSeries, s.worksheet, yDataStartRef, yDataOrientation) : [];

            allSeriesData.push({
                seriesOpts: s,
                title: resolvedSeriesTitle,
                xValues,
                yValues
            });

            if (xValues && xValues.length > longestX) {
                longestX = xValues.length;
                chosenXData = xValues;
            }
        });

        // Determine axis type
        let xAxisType = 'category';
        if (chosenXData && chosenXData.length > 0) {
            const allNumeric = chosenXData.every(val => typeof val === 'number' && !isNaN(val));
            xAxisType = allNumeric ? 'value' : 'category';
        } else {
            // no xData from chosen series
            let maxLength = 0;
            allSeriesData.forEach(d => {
                if (d.yValues && d.yValues.length > maxLength) {
                    maxLength = d.yValues.length;
                }
            });
            xAxisType = 'category';
            chartOption.xAxis.data = Array.from({ length: maxLength }, (_, i) => i + 1);
        }
        chartOption.xAxis.type = xAxisType;

        // If value axis, set min/max & interval
        if (xAxisType === 'value') {
            let allXNumeric = [];
            allSeriesData.forEach(d => {
                if (d.xValues && d.xValues.length > 0) {
                    const numericVals = d.xValues.filter(v => typeof v === 'number' && !isNaN(v));
                    allXNumeric = allXNumeric.concat(numericVals);
                }
            });

            if (allXNumeric.length > 0) {
                let dataMin = Math.min(...allXNumeric);
                let dataMax = Math.max(...allXNumeric);
                const range = dataMax - dataMin;
                const hasTicks = (xAxis.ticks !== undefined && xAxis.ticks > 0);

                if (range > 20 && hasTicks) {
                    dataMin = Math.floor(dataMin / xAxis.ticks) * xAxis.ticks;
                    dataMax = Math.ceil(dataMax / xAxis.ticks) * xAxis.ticks;
                }
                if (xAxis.min === undefined) chartOption.xAxis.min = dataMin;
                if (xAxis.max === undefined) chartOption.xAxis.max = dataMax;
            }

            if (xAxis.ticks !== undefined && xAxis.ticks !== 0) {
                chartOption.xAxis.interval = xAxis.ticks;
            }
        } else if (xAxisType === 'category' && chosenXData && chosenXData.length > 0) {
            chartOption.xAxis.data = chosenXData;
            if (xAxis.ticks !== undefined && xAxis.ticks !== 0) {
                chartOption.xAxis.axisLabel = chartOption.xAxis.axisLabel || {};
                chartOption.xAxis.axisLabel.interval = xAxis.ticks;
            }
        }

        // Build ECharts series
        allSeriesData.forEach((d) => {
            const s = d.seriesOpts;
            const yAxisIndex = (s.yData && s.yData.which === 'right') ? 1 : 0;

            const lineStyle = (s.style && s.style.line) || {};
            const itemStyle = (s.style && s.style.point) || {};
            const seriesColor = s.color || lineStyle.color || itemStyle.color || undefined;

            let finalSeriesData = d.yValues;
            if (xAxisType === 'value') {
                const length = Math.min(d.xValues.length, d.yValues.length);
                finalSeriesData = [];
                for (let i = 0; i < length; i++) {
                    finalSeriesData.push([d.xValues[i], d.yValues[i]]);
                }
            }

            let showSymbol = s.points === false ? false : true;
            let symbol = s.points === false ? 'none' : (typeof s.points === 'string' ? s.points : 'circle');

            let seriesType = s.type || 'line';
            if (seriesType === 'scatter' || seriesType === 'line') {
                if (s.line === false) {
                    seriesType = 'scatter';
                } else if (s.line === true || (typeof s.line === 'object')) {
                    seriesType = 'line';
                    if (typeof s.line === 'object') {
                        Object.assign(lineStyle, s.line);
                    }
                }
            }

            chartOption.series.push({
                name: d.title,
                type: seriesType,
                smooth: s.smooth === true,
                data: finalSeriesData,
                yAxisIndex: yAxisIndex,
                showSymbol: showSymbol,
                symbol: symbol,
                lineStyle: {
                    width: lineStyle.width !== undefined ? lineStyle.width : 2,
                    color: seriesColor
                },
                itemStyle: {
                    color: seriesColor
                }
            });
        });

        // Render the chart
        const chart = echarts.init(target, theme);
        chart.setOption(chartOption);

        // *** We no longer delete from sourceToWorkbookMap here to keep data cached globally. ***
    }

    onLoaded();

    // Collect all unique src URLs
    const srcSet = new Set([src]);
    series.forEach(s => {
        if (s.src) {
            srcSet.add(s.src);
        }
    });

    const sourceToWorkbookMap = {};
    const promises = [];

    // We'll create an instance of CSVReader here
    const csvReader = new CSVReader();

    srcSet.forEach(fileSrc => {
        // If already in global cache, use it directly
        if (globalWorkbookCache[fileSrc]) {
            sourceToWorkbookMap[fileSrc] = globalWorkbookCache[fileSrc];
        } else {
            // Otherwise, fetch and parse
            promises.push(
                fetch(fileSrc)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Failed to load: ${fileSrc}`);
                        }
                        return response.blob().then(blob => {
                            const contentType = blob.type.toLowerCase();
                            return blob.arrayBuffer().then(buffer => {
                                let workbook;
                                if (contentType.includes('text/csv')) {
                                    // Use CSVReader
                                    workbook = csvReader.read(buffer, {});
                                } else {
                                    // Assume XLSX
                                    workbook = XLSX.read(new Uint8Array(buffer), { type: 'array' });
                                }
                                // Store in both local and global caches
                                sourceToWorkbookMap[fileSrc] = workbook;
                                globalWorkbookCache[fileSrc] = workbook;
                            });
                        });
                    })
            );
        }
    });

    Promise.all(promises)
        .then(() => createChart(sourceToWorkbookMap))
        .catch(err => onError(err));
}
