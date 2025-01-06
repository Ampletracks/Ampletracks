// See https://chatgpt.com/c/675440af-386c-8004-b792-650815877227

/**
 * @function defineChart
 * @description
 * Dynamically generates a web interface for defining or editing an ECharts chart configuration.
 * Reads the initial configuration from a specified DOM input element, updates the configuration in real-time based
 * on user input, and writes the updated configuration back to the DOM element.
 *
 * @param {HTMLElement} container - The DOM element where the chart configuration interface will be injected.
 * @param {HTMLElement} inputDom - A DOM element (e.g., a `<textarea>`) used to read the initial configuration and store updates.
 * @param {Object} chartOptions - Optional. An object defining customisation options for the form.
 *
 * @property {Array} chartOptions.themes - Available theme options for the chart (default: ["light", "dark"]).
 * @property {Array} chartOptions.pointTypes - Available point styles for series (default: ["circle", "diamond", "rect"]).
 * @property {Array} chartOptions.lineTypes - Available line styles for series (default: ["solid", "dashed", "dotted"]).
 *
 * @example
 * // ECharts standard options
 * const chartOptions = {
 *     themes: ['light', 'dark', 'vintage'],
 *     pointTypes: ['circle', 'rect', 'roundRect', 'triangle', 'diamond', 'pin', 'arrow'],
 *     lineTypes: ['solid', 'dashed', 'dotted'],
 * };
 *
 * // Create an input DOM element to store the configuration
 * const inputDom = document.createElement('textarea');
 * inputDom.style.display = 'none';
 * document.body.appendChild(inputDom);
 *
 * // Optionally, initialise with an existing configuration
 * inputDom.value = JSON.stringify({
 *     title: "Sample Chart",
 *     theme: "light",
 *     showLegend: true,
 *     series: [
 *         {
 *             type: "line",
 *             title: "Sample Series",
 *             points: "circle",
 *             line: "solid",
 *             smooth: true,
 *             xData: { location: "A1" },
 *             yData: { location: "B1" },
 *         },
 *     ],
 * });
 *
 * // Call the function to build the form
 * const container = document.getElementById("chartFormContainer");
 * defineChart(container, inputDom, chartOptions);
 *
 * @example
 * // Output JSON Example
 * {
 *   "title": "Chart Title",
 *   "theme": "light",
 *   "showLegend": true,
 *   "series": [
 *       {
 *           "type": "line",
 *           "title": "Series 1",
 *           "points": "circle",
 *           "line": "solid",
 *           "smooth": true,
 *           "xData": { "location": "A1" },
 *           "yData": { "location": "B1" }
 *       }
 *   ]
 * }
 *
 * @features
 * 1. **Themes and Styles:**
 *    - Allows selecting from predefined themes, point types, and line styles.
 *
 * 2. **Data Series Management:**
 *    - Supports adding and removing an arbitrary number of series.
 *    - Ensures at least one series is always present.
 *
 * 3. **Validation:**
 *    - Marks required fields (e.g., X and Y data locations) to ensure valid configuration.
 *
 * 4. **Dynamic JSON Updates:**
 *    - Reflects all changes to the chart configuration immediately in the output JSON.
 *
 * @dependencies
 * - **jQuery**: The function requires jQuery for DOM manipulation.
 */
function defineChart(container, inputDom, chartOptions = {}) {
    const {
        themes = ["light", "dark"],
        pointTypes = ["circle", "diamond", "rect"],
        lineTypes = ["solid", "dashed", "dotted"],
    } = chartOptions;

    // Parse the existing configuration from the input DOM
    const existingChartDefinition = JSON.parse(inputDom.value || '{}');

    $(container).empty(); // Clear the container
    const defaultSeries = {
        type: 'line',
        title: '',
        points: 'circle',
        line: 'solid',
        smooth: false,
        dataOrientation: 'column',
        style: { line: { width: 1, color: '#000000' }, point: { size: 5, color: '#000000' } },
        xData: { location: '' },
        yData: { location: '', which: 'left' },
    };

    let seriesCount = 0;

    const addSeries = (series = defaultSeries, canDelete = true) => {
        seriesCount++;
        const seriesHtml = `
            <div class="series" data-series-id="${seriesCount}">
                <h3>Series Definition</h3>
                <label>Type:</label>
                <select class="series-type">
                    <option value="line" ${series.type === 'line' ? 'selected' : ''}>Line</option>
                    <option value="scatter" ${series.type === 'scatter' ? 'selected' : ''}>Scatter</option>
                    <option value="bar" ${series.type === 'bar' ? 'selected' : ''}>Bar</option>
                </select><br>

                <label>Title:</label>
                <input type="text" class="series-title" value="${series.title}" placeholder="Optional (default: '')"><br>

                <label>Points:</label>
                <select class="series-points">
                    ${pointTypes.map(pt => `<option value="${pt}" ${series.points === pt ? 'selected' : ''}>${pt}</option>`).join('')}
                </select><br>

                <label>Line Type:</label>
                <select class="series-line">
                    ${lineTypes.map(lt => `<option value="${lt}" ${series.line === lt ? 'selected' : ''}>${lt}</option>`).join('')}
                </select><br>

                <label>Data Orientation:</label>
                <select class="series-data-orientation">
                    <option value="column" ${series.dataOrientation === 'column' ? 'selected' : ''}>Column</option>
                    <option value="row" ${series.dataOrientation === 'row' ? 'selected' : ''}>Row</option>
                </select><br>

                <label>Smooth:</label>
                <input type="checkbox" class="series-smooth" ${series.smooth ? 'checked' : ''}><br>

                <label>X Data Location:</label>
                <input type="text" class="series-xData-location" value="${series.xData.location}" placeholder="Required"><br>

                <label>Y Data Location:</label>
                <input type="text" class="series-yData-location" value="${series.yData.location}" placeholder="Required"><br>

                <label>Y Axis:</label>
                <select class="series-yData-which">
                    <option value="left" ${series.yData.which === 'left' ? 'selected' : ''}>Left</option>
                    <option value="right" ${series.yData.which === 'right' ? 'selected' : ''}>Right</option>
                </select><br>

                <div class="button-group">
                    ${canDelete ? '<button class="remove-series">Remove</button>' : ''}
                </div>
            </div>
        `;
        $("#seriesContainer").append(seriesHtml);
    };

    // Populate the form
    const formHtml = `
        <label for="chartTitle">Chart Title:</label>
        <input type="text" id="chartTitle" value="${existingChartDefinition.title || ''}" placeholder="Optional (default: '')"><br>

        <label for="theme">Theme:</label>
        <select id="theme">
            ${themes.map(theme => `<option value="${theme}" ${existingChartDefinition.theme === theme ? 'selected' : ''}>${theme}</option>`).join('')}
        </select><br>

        <label for="showLegend">Show Legend:</label>
        <input type="checkbox" id="showLegend" ${existingChartDefinition.showLegend !== false ? 'checked' : ''}><br>

        <h3>X-Axis Configuration</h3>
        <label for="xAxisTitle">X-Axis Title:</label>
        <input type="text" id="xAxisTitle" value="${existingChartDefinition.xAxis?.title || ''}" placeholder="Optional (default: '')"><br>

        <label for="xAxisMin">X-Axis Minimum:</label>
        <input type="number" id="xAxisMin" value="${existingChartDefinition.xAxis?.min || ''}" placeholder="Optional"><br>

        <label for="xAxisMax">X-Axis Maximum:</label>
        <input type="number" id="xAxisMax" value="${existingChartDefinition.xAxis?.max || ''}" placeholder="Optional"><br>

        <label for="xAxisTicks">X-Axis Tick Interval:</label>
        <input type="number" id="xAxisTicks" value="${existingChartDefinition.xAxis?.ticks || ''}" placeholder="Optional (default: 10)"><br>

        <h3>Y-Axis Left Configuration</h3>
        <label for="yAxisLeftTitle">Y-Axis Left Title:</label>
        <input type="text" id="yAxisLeftTitle" value="${existingChartDefinition.yAxisLeft?.title || ''}" placeholder="Optional (default: '')"><br>

        <label for="yAxisLeftMin">Y-Axis Left Minimum:</label>
        <input type="number" id="yAxisLeftMin" value="${existingChartDefinition.yAxisLeft?.min || ''}" placeholder="Optional"><br>

        <label for="yAxisLeftMax">Y-Axis Left Maximum:</label>
        <input type="number" id="yAxisLeftMax" value="${existingChartDefinition.yAxisLeft?.max || ''}" placeholder="Optional"><br>

        <label for="yAxisLeftTicks">Y-Axis Left Tick Interval:</label>
        <input type="number" id="yAxisLeftTicks" value="${existingChartDefinition.yAxisLeft?.ticks || ''}" placeholder="Optional (default: 10)"><br>

        <h3>Y-Axis Right Configuration</h3>
        <label for="yAxisRightTitle">Y-Axis Right Title:</label>
        <input type="text" id="yAxisRightTitle" value="${existingChartDefinition.yAxisRight?.title || ''}" placeholder="Optional (default: '')"><br>

        <label for="yAxisRightMin">Y-Axis Right Minimum:</label>
        <input type="number" id="yAxisRightMin" value="${existingChartDefinition.yAxisRight?.min || ''}" placeholder="Optional"><br>

        <label for="yAxisRightMax">Y-Axis Right Maximum:</label>
        <input type="number" id="yAxisRightMax" value="${existingChartDefinition.yAxisRight?.max || ''}" placeholder="Optional"><br>

        <label for="yAxisRightTicks">Y-Axis Right Tick Interval:</label>
        <input type="number" id="yAxisRightTicks" value="${existingChartDefinition.yAxisRight?.ticks || ''}" placeholder="Optional (default: 10)"><br>

        <div id="seriesContainer"></div>
        <button id="addSeries">Add Series</button>
    `;
    $(container).append(formHtml);

    // Initialise series
    if (existingChartDefinition.series && existingChartDefinition.series.length > 0) {
        existingChartDefinition.series.forEach((series, index) => {
            addSeries(series, existingChartDefinition.series.length > 1);
        });
    } else {
        addSeries(); // Add at least one series
    }

    const generateConfig = () => {
        const config = {
            title: $("#chartTitle").val(),
            theme: $("#theme").val(),
            showLegend: $("#showLegend").is(":checked"),
            xAxis: {
                title: $("#xAxisTitle").val(),
                min: Number($("#xAxisMin").val()) || undefined,
                max: Number($("#xAxisMax").val()) || undefined,
                ticks: Number($("#xAxisTicks").val()) || undefined,
            },
            yAxisLeft: {
                title: $("#yAxisLeftTitle").val(),
                min: Number($("#yAxisLeftMin").val()) || undefined,
                max: Number($("#yAxisLeftMax").val()) || undefined,
                ticks: Number($("#yAxisLeftTicks").val()) || undefined,
            },
            yAxisRight: {
                title: $("#yAxisRightTitle").val(),
                min: Number($("#yAxisRightMin").val()) || undefined,
                max: Number($("#yAxisRightMax").val()) || undefined,
                ticks: Number($("#yAxisRightTicks").val()) || undefined,
            },
            series: [],
        };

        $("#seriesContainer .series").each(function () {
            const series = {
                type: $(this).find(".series-type").val(),
                title: $(this).find(".series-title").val(),
                points: $(this).find(".series-points").val(),
                line: $(this).find(".series-line").val(),
                dataOrientation: $(this).find(".series-data-orientation").val(),
                smooth: $(this).find(".series-smooth").is(":checked"),
                xData: { location: $(this).find(".series-xData-location").val() },
                yData: {
                    location: $(this).find(".series-yData-location").val(),
                    which: $(this).find(".series-yData-which").val(),
                },
            };
            config.series.push(series);
        });

        inputDom.value = JSON.stringify(config, null, 2); // Write back to the input DOM

        // Trigger change event for hidden inputs
        $(inputDom).filter('[type=hidden]').trigger('change');
    };

    // Attach event handlers for real-time updates
    $(container).on("change", "input, select", generateConfig);

    // Add series functionality
    $("#addSeries").click(() => {
        addSeries(undefined, true);
        generateConfig(); // Regenerate config when a series is added
    });

    // Remove series functionality
    $(container).on("click", ".remove-series", function () {
        $(this).closest(".series").remove();
        generateConfig(); // Regenerate config when a series is removed
    });

    // Initial configuration generation
    generateConfig();
}
