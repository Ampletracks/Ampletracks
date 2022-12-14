
//override built in window.alert
function alertPopup( message, onClose ) {
    $.alertable.alert(message).then(function(){ if (onClose) onClose() });
}

function askYesNo(question,answerCallback,cancelCallback) {
	$.alertable.prompt(question, {
		prompt: 
			'<div style="float:right"><input type="hidden" name="result" id="yesNoDialogResult" value="foo" />'+
			'<button onClick="$(\'#yesNoDialogResult\').val(\'cancel\')" class="alertable-cancel">Cancel</button>'+
			'<input onClick="$(\'#yesNoDialogResult\').val(\'yes\')" type="submit" class="alertable-ok" value="Yes">'+
			'<input onClick="$(\'#yesNoDialogResult\').val(\'no\')" name="result" type="submit" class="alertable-ok" value="No"></div>',
		modal:
			'<form class="alertable"><div class="alertable-message"></div><div class="alertable-prompt"></div></form>'
	}).then(function(data){
		if (data.result=='cancel') {
			if (cancelCallback) cancelCallback();
		} else if (answerCallback) answerCallback(data.result=='yes');
	},function(){
		if (cancelCallback) cancelCallback();
	});
}

// Extract variables that are encoded as a query string after the # in the URL
function parseHashParameters() {
    let queryString = window.location.hash;
    if (queryString.length==0) return {};
    
    var pairs = queryString.substr(1).split('&');
    let vars = {};
    for (var i = 0; i < pairs.length; i++) {
        var pair = pairs[i].split('=');
        vars[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || '');
    }
    return vars;
}

$(function(){

    // "Are you sure you want to leave this page" functionality
    var doCheckExit = $('body.checkExit').length;
    var dataChangedAt = null;

    // several automated things on page load can trigger the change function but aren't real changes
    // delay the application of this handler until a little while after page load
    window.setTimeout( function(){
        $('input,select,textarea').on('change',function (e) {
            if (!$(e.target).is(':visible')) return;
            if(dataChangedAt === null) dataChangedAt = new Date;
        }),
        2000
    });

    if(doCheckExit) {
        $(window).on('beforeunload', function () {
            var warningMessage;

            if(dataChangedAt !== null) {
                return  + (cms.checkExitEnd ? ' ' + dataChangedAt.getHours() + ':' + dataChangedAt.getMinutes() + ' ' + cms.checkExitEnd : '');
            }

            if(typeof exitCheckFunctions === 'object') {
                for(var checkIdx in exitCheckFunctions) {
                    if(typeof exitCheckFunctions[checkIdx] === 'function') {
                        warningMessage = exitCheckFunctions[checkIdx]();
                        if(warningMessage) return warningMessage;
                    }
                }
            }
        });
    }

    // Make sure they don't get an "Are you sure" when they clicke save/print/check etc.
    // although still allow custom page-specific checks
    $('form').on('submit',function(){
        dataChangedAt = null;
    });

	// add previewChildren="x" to any node to have only the first x child nodes displayed followed by a "view all Y..." link at the end
	$('[previewChildren]').each(function(){
		var self=$(this);
		var children = self.children();
		var toHide=children.slice(parseInt(self.attr('previewChildren')));
		if (toHide.length) {
			toHide.hide();
			$('<a class="previewChildren" href="#">view all '+children.length+'...</a>').appendTo(self).on('click',function(){
				$(this).hide();
				toHide.slideDown();
				return false;
			})
		}
	});

    // alert on click functionality
    $('body').on('click','a[confirm]',function(){
        let self = $(this);
        
        let okButton = self.attr('okbutton');
        let cancelButton = self.attr('cancelbutton');
        
        var html = false;
        var confirm = self.attr('confirm');
        if (confirm.indexOf('#'===0)) {
            confirm = $(confirm).clone();
            html = true;
        }
        
		let method='confirm';
		options = {html: html, 'okButton':okButton, cancelButton:cancelButton};
		if (html && confirm.find(':input').length) {
			options.prompt=''
			method='prompt';
		}
        $.alertable[method](confirm,{prompt: '', html: html, 'okButton':okButton, cancelButton:cancelButton}).then(function(data) {
			// data seems to be some weird kind of object which $.param doesn't understand
			// we reconstruct it here
			let newData = {};
			for (var key in data) {
				newData[key] = data[key];
			}
			let href = self.attr('href') || '';
			let qs = $.param(newData);
			if (qs.length) {
				if (href.indexOf('?')<0) href += '?';
				href += qs;
			}
			window.location.href=href;
        });
        
        return false;
    });

	// fancy ajax deletion functionality - for deleteing one item from a list when deletePrompt attribute is set
	function prompter(prompt,desiredResponse,carryOn) {
		if (prompt!='') {
			if (desiredResponse) $.alertable.prompt(prompt,{html:true}).then(function(sure) {
				if (sure==desiredResponse) {
					carryOn(sure);
				}
			});
			else $.alertable.confirm(prompt,{html:true}).then(function() {
				carryOn();
			});
		}
	}

	$("a[deletePrompt]").on('click',function(){

		var self = $(this);
		var href = self.attr('href');
		
		prompter(self.attr('deletePrompt'),self.attr('ddeleteResponse'),function(sure){
			if (self.attr('deleteResponse')) href += '&sure='+sure;
			$.get(href,function(data){
				if (data != 'OK') {
					if (data.match(/^\s*<html/)) {
						var newWindow = window.open('');
						newWindow.document.write(data);
					} else {
						$.alertable.alert(data);
					}
				} else {
					self.closest('tr').remove();
				}
			});
		});

		return false;

	});

  	// Support for confirm prompt on links
	$("a[confirmPrompt]").on('click',function(){
		var self = $(this);
		prompter(self.attr('confirmPrompt'),self.attr('confirmResponse'),function(){
			window.location.href=self.attr('href');
		});
		return false;
	});

	// Simple system to support moving bits of content on the page after it has rendered
	$('[moveTo]').each(function(){
		var self = $(this);
		var dest = $(self.attr('moveTo'));
		self.insertBefore(dest);
		dest.remove();
	});

	$('input.error').on('change',function(){
		var self = $(this);
		self.removeClass('error');
		self.next('ul.error').remove();
	});

    // setup timepickers if timepicker is loaded
    if ($().timepicker) {
        $('.timepicker').each( function() {
            setUpTimepicker($(this));
        });
        $('.timepicker[defaultFor]').on('change', function () {
            var sourceTimepicker = M.Timepicker.getInstance(this);
            var targetTimepickerIds = $(this).attr('defaultFor').split('|');
            for(var idx in targetTimepickerIds) {
                var targetTimepicker = M.Timepicker.getInstance($('#' + targetTimepickerIds[idx])[0]);
                targetTimepicker.options.defaultTime = sourceTimepicker.time;
            }
        });
    }

    // setup datepickers if datepicker is loaded
    if ($().datepicker) {
        $('.datepicker').each( function() {
            setUpDatepicker($(this));
        });
        $('.datepicker[defaultFor]').on('change', function () {
            var sourceDatepicker = M.Datepicker.getInstance(this);
            var targetDatepickerIds = $(this).attr('defaultFor').split('|');
            for(var idx in targetDatepickerIds) {
                var targetDatepicker = M.Datepicker.getInstance($('#' + targetDatepickerIds[idx])[0]);
                targetDatepicker.options.setDefaultDate = true;
                targetDatepicker.options.defaultDate = sourceDatepicker.date;
            }
        });
    }

	$('a[yesNoQuestion]').on('click',function(){
		let self = $(this);

		let yesNoField = self.attr('yesNoField');
		if (!yesNoField) yesNoField='yesNo';
		
		askYesNo(self.attr('yesNoQuestion'),function(result){
			window.location.href=self.attr('href')+'&'+encodeURIComponent(yesNoField)+'='+(result?'1':'0');
		});

		return false;
	});

	$('table.main th').find('input,select').on('change',function(){
		$('#filterForm').submit();
	});
	
	// Don't allow decimal points if the step size is an integer
	$('body').on('keypress','input[type=number][step]',function(e){
		var step = $(this).attr('step');
		if (step==Math.round(step) && e.charCode==46) e.preventDefault();
    });

	// Immediately reject invalid numerical input
	$('body').on('keypress','input[type=number]',function(e){
		var self = this;
		
		var oldValue = self.value;

		var originalValue = $(self).data('originalValue');
		if (typeof(originalValue) == 'undefined') {
			console.log('Setting value to...',oldValue);
			$(self).data('originalValue',oldValue);
		}
		
		// minus on its own is a special case because this happens when you start to type a negative number
		if (!isNaN(oldValue) && !(self.value=='' && e.key=='-')) window.setTimeout(function(){
			
			// Reject any changes which make the value invalid...
			if (!self.value) self.value=oldValue;
		},1);
    });
    
	$('body').on('change','input[type=number]',function(e){
		var self = this;

		var value=self.value;
		var oldValue = $(self).data('originalValue');
		
		if (
			isNaN(value) || 
			(self.min!='' && !isNaN(self.min) && value < +self.min ) ||
			(self.max!='' && !isNaN(self.max) && value > +self.max )
		) {
			// if the old value was empty and the new value is empty then don't flag an error
			if (!(value==='' && (typeof(oldValue)=='undefined' || oldValue===''))) {
				var msg = 'The value provided is not valid for this field. It must be ';
				if (self.min!='' && !isNaN(self.min)) {
					msg += 'greater than '+self.min+' and ';
				}
				if (self.max!='' && !isNaN(self.max)) {
					msg += 'less than '+self.max;
				}
				msg = msg.replace(/ and $/,'') + '.';
				
				alertPopup(msg,function(){
					console.log(oldValue);
					if (typeof(oldValue)!='undefined' && oldValue.length) self.value = oldValue;
				});
			}
		} else {
			console.log('setting value to',value);
			$(self).data('originalValue',value);
		}
		
	});

	if (typeof Jodit != 'undefined') {
		$('textarea.jodit').each(function () {
			var editor = new Jodit(this);
		})
	}

    $('form').on('submit',function() {
        var self = $(this);

		// If the form has told us too then rename and invisible fields on submit
		if (self.hasClass('renameInvisibleFieldsOnSubmit')) {
	        var fields = self.find('input,select,textarea').not('[type=HIDDEN],[type=hidden]');
			fields.filter(':hidden').each(function(){
				if (this.name.match(/^hidden:/)) return;
				this.name='hidden:'+this.name;
			});
			fields.filter(':visible').each(function(){
				this.name=this.name.replace(/^hidden:/,'');
			});
		}
		
		// Make all unselected checkboxes return the value '' instead of not being submitted at all
		// We used to change their value to '' and check them but this caused problems when the user pressed "back"
		//     because the browser would remember the final state and show the page with them all checked.
		// So what we do instead is create hidden form fields with the same name and add these to the form
		// The same goes for multiple select box options which aren't selected

        // First get rid of any dynamically generated hidden form fields from previous page loads (in case the user uses the back button)
        self.find('input.unselectedCheckboxFix:hidden').remove();
        self.find('input:checkbox:not([name^="filter_"]),select[multiple]:not([name^="filter_"])').not(':checked').each(function() {
            var self = $(this);
            var form = $(this.form);
            if (self.is('select') && self.find('option:selected').length) return;
            var input = $('<input type="text" ></input>').addClass('unselectedCheckboxFix').css({'position':'absolute','margin-left':'-1000px'}).prop('name',this.name).val('');
            // put the new input immediately after the one it is standing in for so that the overall order of the form fields is not messed up
            self.after(input);
        });

    });
 
	$('body').on('click','[clickToShow]',function(){
		let self = $(this);
		let selector = self.attr('clickToShow');
		if (!selector.length) return false;
		$(selector).show();
		self.remove();
		return false;
	});
	
/*!
  Non-Sucking Autogrow 1.1.6
  license: MIT
  author: Roman Pushkin
  https://github.com/ro31337/jquery.ns-autogrow
*/
(function(){var e;!function(t,l){return t.fn.autogrow=function(i){if(null==i&&(i={}),null==i.horizontal&&(i.horizontal=!0),null==i.vertical&&(i.vertical=!0),null==i.debugx&&(i.debugx=-1e4),null==i.debugy&&(i.debugy=-1e4),null==i.debugcolor&&(i.debugcolor="yellow"),null==i.flickering&&(i.flickering=!0),null==i.postGrowCallback&&(i.postGrowCallback=function(){}),null==i.verticalScrollbarWidth&&(i.verticalScrollbarWidth=e()),i.horizontal!==!1||i.vertical!==!1)return this.filter("textarea").each(function(){var e,n,r,o,a,c,d;if(e=t(this),!e.data("autogrow-enabled"))return e.data("autogrow-enabled"),a=e.height(),c=e.width(),o=1*e.css("lineHeight")||0,e.hasVerticalScrollBar=function(){return e[0].clientHeight<e[0].scrollHeight},n=t('<div class="autogrow-shadow"></div>').css({position:"absolute",display:"inline-block","background-color":i.debugcolor,top:i.debugy,left:i.debugx,"max-width":e.css("max-width"),padding:e.css("padding"),fontSize:e.css("fontSize"),fontFamily:e.css("fontFamily"),fontWeight:e.css("fontWeight"),lineHeight:e.css("lineHeight"),resize:"none","word-wrap":"break-word"}).appendTo(document.body),i.horizontal===!1?n.css({width:e.width()}):(r=e.css("font-size"),n.css("padding-right","+="+r),n.normalPaddingRight=n.css("padding-right")),d=function(t){return function(l){var r,d,s;return d=t.value.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\n /g,"<br/>&nbsp;").replace(/"/g,"&quot;").replace(/'/g,"&#39;").replace(/\n$/,"<br/>&nbsp;").replace(/\n/g,"<br/>").replace(/ {2,}/g,function(e){return Array(e.length-1).join("&nbsp;")+" "}),/(\n|\r)/.test(t.value)&&(d+="<br />",i.flickering===!1&&(d+="<br />")),n.html(d),i.vertical===!0&&(r=Math.max(n.height()+o,a),e.height(r)),i.horizontal===!0&&(n.css("padding-right",n.normalPaddingRight),i.vertical===!1&&e.hasVerticalScrollBar()&&n.css("padding-right","+="+i.verticalScrollbarWidth+"px"),s=Math.max(n.outerWidth(),c),e.width(s)),i.postGrowCallback(e)}}(this),e.change(d).keyup(d).keydown(d),t(l).resize(d),d()})}}(window.jQuery,window),e=function(){var e,t,l,i;return e=document.createElement("p"),e.style.width="100%",e.style.height="200px",t=document.createElement("div"),t.style.position="absolute",t.style.top="0px",t.style.left="0px",t.style.visibility="hidden",t.style.width="200px",t.style.height="150px",t.style.overflow="hidden",t.appendChild(e),document.body.appendChild(t),l=e.offsetWidth,t.style.overflow="scroll",i=e.offsetWidth,l===i&&(i=t.clientWidth),document.body.removeChild(t),l-i}}).call(this); 

    // Auto expand textareas
    $('textarea.autoexpandHeight').autogrow({vertical: true, horizontal: false});
    $('textarea.autoexpandWidth').autogrow({vertical: false, horizontal: true});
});
