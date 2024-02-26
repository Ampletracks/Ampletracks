

function alertPopup( message, onClose ) {
    modal({
        text:message,
        onClose: onClose
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

        let modalOptions = {};
        
        var confirm = self.attr('confirm');
        if (confirm.indexOf('#'===0)) {
            modalOptions.html = $(confirm);
        } else {
            modalOptions.text = confirm;
        }
       
        modalOptions.buttons = [
            { align: 'right', text: 'OK'},
            { align: 'left', text: 'Cancel'}
        ];

        var href = self.attr('href');
        if (href) {
            modalOptions.events = {
                onOk: function(data) {
                    let qs = $.param(data);
                    if (qs.length) {
                        if (href.indexOf('?')<0) href += '?';
                        href += qs;
                    }
                    window.location.href=href;
                }
            }
        }

        modal(modalOptions);
        
        return false;
    });

	// fancy ajax deletion functionality - for deleteing one item from a list when deletePrompt attribute is set
	function prompter(prompt,desiredResponse,carryOn) {
		if (prompt!='') {
			if (desiredResponse) prompt(prompt,function(sure) {
				if (sure==desiredResponse) {
					return carryOn(sure);
				}
			});
			else confirm(prompt,function() {
				return carryOn();
			});
		}
	}

	$("a[deletePrompt]").on('click',function(){

		var self = $(this);
		var href = self.attr('href');
		
		prompter(self.attr('deletePrompt'),self.attr('deleteResponse'),function(sure){
			if (self.attr('deleteResponse')) href += '&sure='+sure;
			$.get(href,function(data){
				if (data != 'OK') {
					if (data.match(/^\s*<html/)) {
						var newWindow = window.open('');
						newWindow.document.write(data);
					} else {
						alert(data);
					}
				} else {
					self.closest('tr').remove();
				}
			});
            return true;
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

   // Make a copy of any button with a class of defaultButton and stick it at the front of the form
   // but off the side of the screen. This makes this the default action for the form when
   // enter is pressed
   $('form').prepend(function() {
      let self = $(this);
      let existingButton = self.find('input.defaultButton');
      let defaultButton;
      // If there is no default then create a standard submit button
      defaultButton = existingButton ? existingButton.clone() : $('<input type="submit" />');
      defaultButton.css({
         position:    'absolute',
         left:        '-999px',
         top:        '-999px',
         height:        0,
         width:        0
      });
      return defaultButton;
   });

      // Submit filters when they are changed
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

   var validationRestoreInputs = [];

   $('body').on('click','form :button[type=submit]',function() {
      // Chrome does a stupid thing where it tries (and fails) to flag validation warnings
      // against invisible fields - this stops the form from submitting
      // To fix this we remove the max and min attributes from any hidden fields
      // BUT.... if form submission fails we need to put them back
      // This is tricky because there is no specific event that fires when a form FAILS to submit
      // However it looks like the blur event will fire on the submit event if the form fails
      // So when that fires we put everything back

      $(this).closest('form').find(':input[min],:input[max]').each(function(){
         let self = $(this);
         if (self.closest(':hidden').length) {
            self.data('min',self.attr('min'));
            self.data('max',self.attr('max'));
            self.attr('min',false).attr('max',false);
            validationRestoreInputs.push(self);
         }
      });
   });

   $('body').on('blur','form :button[type=submit]',function() {
      for( let input of validationRestoreInputs ) {
            input.attr('min',input.data('min'));
            input.attr('max',input.data('max'));
            console.log('restored:',input);
      }
      validationRestoreInputs=[];
   });

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

   $('body').on('click','.flashMessage.notify',function(){
      $(this).fadeOut();
   });
	
   // Auto expand textareas
   $('textarea.autoexpandHeight').autogrow({vertical: true, horizontal: false});
   $('textarea.autoexpandWidth').autogrow({vertical: false, horizontal: true});

   // Support for handheld scanner
   var keypressBuffer = '';
   var lastKeypressTime = 0;
   var bufferResetTime = 500; // 500ms
   var bufferMaxLength = 10;
   var lastFocusedInput = null;
   var lastInputValue = '';

   function createScannerRegex() {
      var currentDomain = window.location.hostname;

      // Check if current domain is 'mpltr.ac', if so, use it, otherwise use a generic domain matcher
      var domainPattern = `(mpltr\\.ac|${currentDomain.replace(/\./g, '\\.')})`;

      // Construct the full regex pattern
      var regexPattern = `(https?:\/\/${domainPattern}\/[A-Za-z0-9]+)\n$`;


      return new RegExp(regexPattern);
   }

   var scannerRegex = createScannerRegex();
   
   // Detect focus on input fields and textareas
   $(':input').focus(function() {
      lastFocusedInput = this; // Store reference to currently focused input
   }).blur(function() {
      lastFocusedInput = null; // Clear reference when focus is lost
   });

   $(document).keypress(function(event) {
      // Replace carriage return with line feed
      if (event.which==13) event.which=10;
      var key = String.fromCharCode(event.which);

      var currentTime = new Date().getTime();

      // Reset keypressBuffer if time between keypresses is more than bufferResetTime
      if (currentTime - lastKeypressTime > bufferResetTime) {
         if (lastFocusedInput) {
            lastInputValue = $(lastFocusedInput).val(); // Save the current value of the input
         }
         keypressBuffer = '';
      }

      // Add key to keypressBuffer if it's a character
      if (event.which==10 || (event.which >= 32 && event.which <= 126)) {
         keypressBuffer += key;

         // Check keypressBuffer length and regex match
         if (keypressBuffer.length >= bufferMaxLength) {
            let result;
            if (result = keypressBuffer.match(scannerRegex)) {
               if (lastFocusedInput) {
                  $(lastFocusedInput).val(lastInputValue); // Restore the original value of the input
               }
               window.location.href = result[1]; // Redirect if regex matches
               event.preventDefault();
               return false;
            }
         }
      }

      lastKeypressTime = currentTime; // Update lastKeypressTime
   });


});

/*!
  Non-Sucking Autogrow 1.1.6
  license: MIT
  author: Roman Pushkin
  https://github.com/ro31337/jquery.ns-autogrow
*/
(function(){var e;!function(t,l){return t.fn.autogrow=function(i){if(null==i&&(i={}),null==i.horizontal&&(i.horizontal=!0),null==i.vertical&&(i.vertical=!0),null==i.debugx&&(i.debugx=-1e4),null==i.debugy&&(i.debugy=-1e4),null==i.debugcolor&&(i.debugcolor="yellow"),null==i.flickering&&(i.flickering=!0),null==i.postGrowCallback&&(i.postGrowCallback=function(){}),null==i.verticalScrollbarWidth&&(i.verticalScrollbarWidth=e()),i.horizontal!==!1||i.vertical!==!1)return this.filter("textarea").each(function(){var e,n,r,o,a,c,d;if(e=t(this),!e.data("autogrow-enabled"))return e.data("autogrow-enabled"),a=e.height(),c=e.width(),o=1*e.css("lineHeight")||0,e.hasVerticalScrollBar=function(){return e[0].clientHeight<e[0].scrollHeight},n=t('<div class="autogrow-shadow"></div>').css({position:"absolute",display:"inline-block","background-color":i.debugcolor,top:i.debugy,left:i.debugx,"max-width":e.css("max-width"),padding:e.css("padding"),fontSize:e.css("fontSize"),fontFamily:e.css("fontFamily"),fontWeight:e.css("fontWeight"),lineHeight:e.css("lineHeight"),resize:"none","word-wrap":"break-word"}).appendTo(document.body),i.horizontal===!1?n.css({width:e.width()}):(r=e.css("font-size"),n.css("padding-right","+="+r),n.normalPaddingRight=n.css("padding-right")),d=function(t){return function(l){var r,d,s;return d=t.value.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\n /g,"<br/>&nbsp;").replace(/"/g,"&quot;").replace(/'/g,"&#39;").replace(/\n$/,"<br/>&nbsp;").replace(/\n/g,"<br/>").replace(/ {2,}/g,function(e){return Array(e.length-1).join("&nbsp;")+" "}),/(\n|\r)/.test(t.value)&&(d+="<br />",i.flickering===!1&&(d+="<br />")),n.html(d),i.vertical===!0&&(r=Math.max(n.height()+o,a),e.height(r)),i.horizontal===!0&&(n.css("padding-right",n.normalPaddingRight),i.vertical===!1&&e.hasVerticalScrollBar()&&n.css("padding-right","+="+i.verticalScrollbarWidth+"px"),s=Math.max(n.outerWidth(),c),e.width(s)),i.postGrowCallback(e)}}(this),e.change(d).keyup(d).keydown(d),t(l).resize(d),d()})}}(window.jQuery,window),e=function(){var e,t,l,i;return e=document.createElement("p"),e.style.width="100%",e.style.height="200px",t=document.createElement("div"),t.style.position="absolute",t.style.top="0px",t.style.left="0px",t.style.visibility="hidden",t.style.width="200px",t.style.height="150px",t.style.overflow="hidden",t.appendChild(e),document.body.appendChild(t),l=e.offsetWidth,t.style.overflow="scroll",i=e.offsetWidth,l===i&&(i=t.clientWidth),document.body.removeChild(t),l-i}}).call(this); 

