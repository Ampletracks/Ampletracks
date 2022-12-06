$(function () {
    $('td.actions').each(function () {
        let td = $(this);

        td.wrapInner('<ul></ul>');
        td.find('a').wrap('<li></li>');
        td.prepend(`
            <button>
                <svg viewBox="0 0 39 39" width="1.3em" height="1.3em">
                    <path fill="rgb(51, 51, 51)" d="M38.598,15.285 C38.800,16.219 38.936,17.177 39.0,18.156 C38.255,18.579 37.254,19.39 35.994,19.536 C35.991,20.246 35.943,20.946 35.852,21.632 C37.33,22.298 37.962,22.892 38.642,23.415 C38.445,24.371 38.179,25.301 37.849,26.200 C36.996,26.284 35.895,26.297 34.541,26.238 C34.251,26.880 33.921,27.500 33.554,28.95 C34.362,29.183 34.968,30.103 35.377,30.856 C34.812,31.643 34.190,32.385 33.516,33.78 C32.703,32.807 31.693,32.371 30.481,31.765 C29.959,32.231 29.408,32.664 28.830,33.62 C29.126,34.385 29.307,35.473 29.374,36.329 C28.545,36.815 27.677,37.242 26.775,37.604 C26.143,37.25 25.396,36.215 24.535,35.168 C23.872,35.379 23.192,35.551 22.496,35.678 C22.229,37.6 21.953,38.72 21.667,38.881 C20.962,38.958 20.247,38.999 19.522,38.999 C19.271,38.999 19.21,38.992 18.772,38.983 C18.430,38.197 18.77,37.153 17.716,35.847 C17.12,35.770 16.322,35.649 15.647,35.486 C14.863,36.593 14.176,37.455 13.586,38.77 C12.660,37.782 11.764,37.419 10.903,36.994 C10.909,36.135 11.11,35.38 11.212,33.696 C10.606,33.341 10.25,32.948 9.472,32.521 C8.306,33.211 7.330,33.719 6.538,34.47 C5.817,33.403 5.143,32.707 4.524,31.964 C4.878,31.182 5.417,30.222 6.145,29.78 C5.737,28.511 5.363,27.916 5.28,27.298 C3.682,27.454 2.582,27.520 1.726,27.498 C1.331,26.623 0.998,25.714 0.734,24.777 C1.375,24.207 2.258,23.548 3.388,22.800 C3.248,22.120 3.150,21.425 3.96,20.717 C1.805,20.312 0.775,19.926 0.2,19.557 C0.1,19.521 0.0,19.486 0.0,19.451 C0.0,18.504 0.69,17.574 0.198,16.664 C1.15,16.404 2.89,16.163 3.424,15.939 C3.574,15.248 3.767,14.572 4.0,13.916 C2.984,13.19 2.199,12.246 1.643,11.594 C2.33,10.704 2.488,9.849 3.1,9.35 C3.853,9.130 4.933,9.347 6.244,9.687 C6.660,9.122 7.109,8.583 7.592,8.76 C7.27,6.843 6.625,5.817 6.382,4.995 C7.95,4.344 7.856,3.747 8.660,3.207 C9.399,3.641 10.297,4.278 11.356,5.122 C11.962,4.775 12.593,4.466 13.244,4.197 C13.229,2.841 13.278,1.739 13.390,0.888 C14.298,0.588 15.236,0.352 16.197,0.187 C16.697,0.885 17.258,1.833 17.884,3.37 C18.423,2.984 18.969,2.956 19.522,2.956 C19.676,2.956 19.828,2.958 19.981,2.962 C20.519,1.717 21.11,0.731 21.458,0.0 C22.432,0.95 23.384,0.263 24.310,0.497 C24.482,1.337 24.610,2.432 24.693,3.786 C25.362,4.7 26.13,4.270 26.642,4.572 C27.639,3.654 28.488,2.954 29.194,2.468 C30.36,2.949 30.838,3.491 31.594,4.89 C31.410,4.927 31.82,5.979 30.607,7.249 C31.125,7.721 31.612,8.226 32.66,8.760 C33.350,8.327 34.411,8.33 35.254,7.876 C35.825,8.653 36.339,9.473 36.792,10.332 C36.284,11.22 35.557,11.850 34.608,12.817 C34.888,13.456 35.129,14.115 35.328,14.793 C36.676,14.921 37.765,15.84 38.598,15.285 ZM19.500,8.162 C13.248,8.162 8.180,13.237 8.180,19.498 C8.180,25.758 13.248,30.833 19.500,30.833 C25.752,30.833 30.820,25.758 30.820,19.498 C30.820,13.237 25.752,8.162 19.500,8.162 Z"/>
                </svg>
            </button>
        `);
    });

    $('ul.btn-list').find('> :not(li)').wrap('<li></li>');

    $('div.questionAndAnswerContainer div.questionAndAnswer').addClass('form-row');
    $('a.version').closest('div.answer').addClass('versioned');

    $('ul.error').closest('div.questionAndAnswer').addClass('error');

    $('div.errorSummary').remove().prependTo('div.mainContent div.form-grid:first-of-type');

    $('div.answer').find('span.checkbox').closest('div.answer').wrapInner('<div class="checkboxHolder"></div>');
    $($('div.checkboxHolder').find('> :not(span.checkbox)').get().reverse()).each(function () {
        $(this).insertAfter($(this).parent());
    });
    $('div.checkboxHolder').closest('div.questionAndAnswer').addClass('half');
});

$(function(){
    // If we add any <input type=submit> buttons within the filter form the browser will automatically make these the default
    // action if the user hits enter in any of the search boxes. This isn't generally what we want
    // So... we add an <input type=submit> button right at the start of the form
    $('#filterForm').prepend('<input type="submit" style="display:none"/>');

    // OLD STUFF
    return;

    $('td.actions').wrapInner('<div class="wrapper"></div>').on('mouseover', function() {
        var cell = $(this),
        menu = $('> .wrapper', cell);

        // grab the menu item's position relative to its positioned parent
        var menuPos = cell.offset();

        console.log(menuPos);
        var cellWidth = cell.outerWidth();
        var menuWidth = menu.outerWidth();
        if (menuWidth<cellWidth) menuWidth=cellWidth;

        // place the submenu in the correct position relevant to the cell
        menu.css({
            width: menuWidth,
        });
    });

    $('main .top.buttons').detach().appendTo('header div.subHeading').wrap('<nav class="top buttons"></nav>').addClass('nav-wrapper').wrapInner('<ul></ul>');

    // When the buttons are moved into the nav bar they are taken out of the form
    $('.buttons.top,.buttons.bottom').find('input[type=submit],button[type=submit]').on('click',function(){
        let self = $(this);
        self.clone().appendTo('#filterForm,#dataEntryForm').css({position:'absolute',left:'-999px'}).trigger('click');
        return false;
    });

    $('h1').detach().appendTo('header div.subheading');

    // Question and answer stuff

    // Set the width of the questions to be the same...
    $('.questionAndAnswer').parent().each(function(){
        var self = $(this);
        ['question'].forEach(function(thing){
            var maxWidth = self.find('.questionAndAnswer .'+thing).map(function(){return parseInt($(this).width())}).sort(function(a,b){return a<b?1:-1})[0];
            self.find('.questionAndAnswer .'+thing).width(maxWidth);
        });
    });

    function wrapAdjacentSiblings(selector,wrapper,checkCallback) {
        $(':not('+selector+') + '+selector+', * > '+selector+':first-of-type').each(function() {
            var self = $(this);
            if (checkCallback && !checkCallback.call(self)) return;
            self.nextUntil(':not('+selector+')').addBack().wrapAll(wrapper);
        });
    }

    // Wrap groups of questionAndAnswer divs in a container
    // But not if they're already in one!
    wrapAdjacentSiblings('div.questionAndAnswer','<div class="questionAndAnswerContainer" />',function(){
        return !this.parent().is('div.questionAndAnswerContainer');
    });

    $('.questionAndAnswer:has(.error)').addClass('error');

    $('.numbersOnly').keypress(function(e){
        if (e.charCode>0 && e.charCode!=13 && (e.charCode<48 || e.charCode>57)) {
            e.preventDefault();
        }
    });

    /*
    $('table.main').wrap('<div class="tablesaw-overflow"></div>').attr('data-tablesaw-mode','columntoggle').attr('data-tablesaw-minimap','');
    $('th.persist').attr('data-tablesaw-priority','persist');
    $('table.main th:not(.persist)').attr('data-tablesaw-priority','999');

    jQuery(document).trigger('enhance.tablesaw');
    */

    //$('div.buttons.bottom').detach().insertAfter('main');

    window.setTimeout(function(){
        $('div.rowCount.top').detach().appendTo('header div.subheading');
        $('div.rowCount.bottom').detach().appendTo('div.buttons.bottom');
    },10);

});
