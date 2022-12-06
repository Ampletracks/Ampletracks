// tiny jquery extension to support delayed src loading
$.fn.loadDelayed = function() {
    this.filter('[delayed-src]').each(function() {
        var self = $(this);
        self.attr('src',self.attr('delayed-src'));
        self.removeAttr('delayed-src');
    });

    return this;
}

// This is the uniqueId function borrowed from JQuery UI
var uniqueId = $.fn.extend({
  uniqueId: (function() {
    var uuid = 0;
    return function() {
      return this.each(function() {
        this.id = (function r() {
          return !$("#ui-id-" + (++uuid)).length ? "ui-id-" + uuid : r();
        })();
      });
    };
  })()
});

var setupDependentInputs;

$(function(){

	var debug = 0;
	var dependencyRegExp = /^(\S+)\s+(\S+)(?:\s+(.+))?/;
	
	var comparatorLookup = {
		'vi': {
			'alias' : [ ]
		}, 
		'sn': {
			'alias' : [ '~' ],
			'test' : function(value,testValue,comparator) {
				if (value==='') return false;
				return testValue!=value;
			}
		}, 
		'em' : {
			'alias' : [ ],
			'test' : function(value,testValue,comparator) {
				return value=='';
			}
		},
		'eq' : {
			'alias' : [ '=' ],
			'test' : function(value,testValue,comparator) {
				return testValue.toUpperCase()==value.toUpperCase();
			}
		}, 
		'lt' : {
			'alias'	: [ '<' ],
			'test' : function(value,testValue,comparator) {
				return (value != '') && applyNumericTest( value, testValue, function(a,b){ return a<b } );
			}
		},
		'gt' : {
			'alias'	: [ '>' ],
			'test' : function(value,testValue,comparator) {
				return (value != '') && applyNumericTest( value, testValue, function(a,b){ return a>b } );
			}
		},
		'bt' : {
			'alias'	: [  ],
			'cleanup': function(value) {
				var testValues = value.split(/,/);
				if (testValues.length<2) testValues.push('');
				return testValues;
			},
			'test' : function(value,testValue,comparator) {
				return (
					applyNumericTest( value, testValue[0], function(a,b){ return a>=b } ) &&
					applyNumericTest( value, testValue[1], function(a,b){ return a<=b } )
				);
			}
		},
		'in' : {
			'alias'	: [  ],
			'cleanup': function( value) { return value.split(/\|/); },
			'test' : function(value,testValue,comparator) {
				return jQuery.inArray(value,testValue) > -1;
			}
		},
		'cy' : {
			'alias'	: [ 'cl' ],
			'cleanup': function(value) { return value.toLowerCase().split(/\|/); },
			'test' : function(value,searchBits,comparator, multiple) {
				var compareValue = value.toLowerCase();
				for( i=searchBits.length-1; i>=0; i--) {
					if (!searchBits[i].length) continue;
					if (compareValue.indexOf(searchBits[i].toLowerCase())>-1) {
						if (comparator=='cy') {
							return true;
						}
					} else {
						if (comparator=='cl') {
							return false;
						}
					}						
				}
				// if we get to this point then either all bits succedded (cl) or all bits failed (cy)
				return comparator=='cl'?true:false;
			}
		}
	}
	
	for( var comparator in comparatorLookup) {
		for( var i = comparatorLookup[comparator]['alias'].length-1; i>=0; i-- ) {
			comparatorLookup[comparatorLookup[comparator]['alias'][i]] = comparatorLookup[comparator];
		}
	}
	
	var dependees = {};

	function applyNumericTest( a,b,testFunction ) {
        debug && console.log('Numeric test : '+a+' '+testFunction+' '+b);

		// If both values look like dates then convert both to numbers
		var dateBits = new Array();
        // The 0*'s in the regexp are to fix a weird bug in wkhtmltopdf whereby the parseint converts "08" into "0" and yet manages to convert "06" into 6
        // I assume it is somehow screwing up on leading zeros so we strip these off in the regexp
		var dateRegexp = /0*(\d{1,2})\D0*(\d{1,2})\D0*(\d\d(?:\d\d)?)/;
		if (
			(dateBits[0] = a.match(dateRegexp)) &&
			(dateBits[1] = b.match(dateRegexp))
		) {
            debug && console.log('Both test parameters are dates');
			for (var i=0; i<2; i++) {
				for (var j=1; j<4; j++) { dateBits[i][j] = parseInt(dateBits[i][j]) }
                debug && console.log('Datebits are '+dateBits[i][1]+' '+dateBits[i][2]+' '+dateBits[i][3]+' ');
				if (dateBits[i][3]<50) dateBits[i][3]+=2000;
				else if (dateBits[i][3]<100) dateBits[i][3]+=1900;
				dateBits[i][0] = dateBits[i][3]*10000+dateBits[i][2]*100+dateBits[i][1];
			}
			a=dateBits[0][0];
			b=dateBits[1][0];
            debug && console.log('Dates converted to '+a+' and '+b);
		} else {
			// They're not dates so parse them both as floats
            // replace colon's with decimal points so that durations (hh:mm or mm:ss) work as expected
            a=a.replace(':','.');
            b=b.replace(':','.');
			a = parseFloat(a);
			b = parseFloat(b);
			if (isNaN(a)) a=0;
			if (isNaN(b)) b=0;
		}

		return testFunction(a,b);
	}

	// ================================= DEPENDEES ===================================
	
	var dependeeIdCounter = 0;
	
	// the domObject may in fact be a collection of domObject (e.g. in the case of a radio button)
	function Dependee( domObject ) {
		debug && console.log('Creating dependee from:',domObject);
		var self = this;
		this.treatEmptyAsUnset = domObject.attr('treatEmptyAsUnset') && domObject.attr('treatEmptyAsUnset').toLowerCase()=='yes';
		this.id = dependeeIdCounter++;
		dependees[this.id] = this;
		this.testCache = {};
		this.dependents = {};
		this.domObject = domObject;
		this.type = 'normal';
		if (domObject.is('input[type=checkbox]')) this.type='checkbox';
		else if (domObject.is('input[type=radio]')) this.type='radio';
		else if (domObject.is('select') && domObject.prop('multiple')) this.type='multiple select';
        else if (domObject.is('input[type=hidden]')) this.type='hidden';

		this.visible = this.getVisibility();

		debug && console.log('Dependee type is '+this.type+' and is '+(this.multiple?'':'not')+' multiple');
		
		// Initialize internal version of the value of this dependee
		this.getValue();
		
		domObject
		.data('dependeeId',this.id)
		// Adding a class makes it easier to pick elements which are dependee's out of the DOM
		.addClass('isDependee')
		
		// add the onchange event for the dependee
		.on('change',function(){
			debug && console.log('OnChange triggered for dependee:',domObject);
			// Clear the internal test cache
			debug && console.log('Emptying test cache');
			self.testCache = {};
			
			// Store value
			self.getValue();

			// Iterate through the dependants checking how this change effects them
			debug && console.log('Current dependents of dependee are:',self.dependents);
			for ( var dependentId in self.dependents ) {
				debug && console.log('Checking dependant:',self.dependents[dependentId]);
				self.dependents[dependentId].check();
			}
		});
	}

	Dependee.prototype.getVisibility = function() {
        // <input type="HIDDEN"> are always treated as being visible
        if (this.type=='hidden') return true;
        return this.domObject.is(':visible');
    }

	Dependee.prototype.getValue = function() {

		var value = this.domObject.val();
		this.multiple=false;
		
		if (this.type=='checkbox' || this.type=='radio' ) {
			var checked = this.domObject.filter(':checked');
			if (checked.length==0) {
				value='';
			} else if (checked.length==1) {
				value=checked.val();
			} else {
				this.multiple=true;
				value ='|';
				checked.each(function(){ value+=$(this).val()+'|' });
			}
		}

		else if (this.type=='multiple select') {
			// Picklists are a special case because the options in them are never "selected"
			// We have to get the list of option values instead
			if (this.domObject.is('.picklistSelected')) {
				value = $.map(this.domObject.find('option'), function(option) {
					return option.value;
				});
			}

			if (value==null || value.length == 0) {
				value='';
			} else if (value.length==1) {
				value=value[0];
			} else {
				this.multiple=true;
				value='|'+value.join('|')+'|';
			}
		}

		if (value == null) value='';
        // if the input box has the class of "hint" it means the hint is still active, so the box is effectively empty
        if (this.domObject.hasClass('hint')) value='';

		this.value = value;
		debug && console.log('Setting value of the dependee to: '+value);
	}

	Dependee.prototype.onVisibilityChange = function() {
		debug && console.log('Onvisibility change called for:',this.domObject);
		var nowVisible = this.getVisibility();
		debug && console.log('Current visibility is: '+this.visible+' New visibility is: '+nowVisible);
		if (this.visible != nowVisible) {
			this.visible = nowVisible;
			debug && console.log('Visibility has changed!!');
			// Iterate through the dependants checking how this change effects them
			for ( var depenentId in this.dependents ) {
				this.dependents[depenentId].check();
			};
		}
	};
	
	Dependee.prototype.addDependent = function( dependent ) {
		debug && console.log('Adding dependent to dependee dependent=',dependent);
		this.dependents[ dependent.id ] = dependent;
	};

	Dependee.prototype.check = function( dependent, comparator, testValue, negate ) {
		var result;

		debug && console.log('Checking dependee; test,comparator,negate are: '+testValue+' '+comparator+' '+negate);
        debug && console.log('Dependee is: ',this);
		debug && console.log('Current value of dependee is: '+this.value);
		debug && console.log('Dependee thinks it is currently: '+(this.visible?'visible':'invisible'));
		
		// Visibility dependancy is a special (and fairly easy) case
		if (comparator=='vi') {
			// no point in caching this
			debug && console.log('Visibility check returned:',negate ? !this.visible : this.visible);
			return negate ? !this.visible : this.visible;
		} 
		
		// If we're not doing a visibility check and we can't even see this element then we treat it as being false
        // This visibility check doesn't apply if the dependency is self-referential i.e it depends on a child of this element
		if (!$.contains(dependent.domObject.get(0),this.domObject.get(0)) && !this.visible) {
		//if (!this.visible) {
			// no point in caching this
			debug && console.log('Dependee is invisible - returning false');
			return false;
		}

        // Empty is deemed to be unset for everything except =="" and "is empty" check
		if (this.value=='') {
			debug && console.log('Empty value detected');
            if ((comparator=='eq' && testValue=='') || comparator=='em') return !negate;
            // If they're doing an equality test for anything other than "" then we must have failed the check
            // BUT in this instance (unlike below) negating does matter
            if (comparator=='eq') return negate;

			debug && console.log('Treating empty value as false - returning false');
			// no point in caching this
			// Also - don't negate it - it is always false even if the test is negated
			return false;
		}
			
		// Next thing to do is check if we already have an answer in the test cache
		var cacheLookupKey = comparator+':'+testValue;
		if (typeof(this.testCache[cacheLookupKey])!='undefined') {
			debug && console.log('Found answer in test cache');
			return negate ? !this.testCache[cacheLookupKey] : this.testCache[cacheLookupKey];
		}
		
		var result = false;
		
		result = comparatorLookup[comparator]['test'](this.value,testValue,comparator,this.multiple);

		// Store the answer in the test cache
		debug && console.log('Storing result in testCache: '+cacheLookupKey+' = '+result);
		this.testCache[cacheLookupKey] = result;
		debug && console.log('Result (before possible negation) is '+result);
		
		return negate ? !result : result;
	}
	
	// ================================= DEPENDENTS ===================================

	function Dependent( domObject, elementLookupCallback ) {
		debug && console.log('Creating dependent:',domObject);
		// create dependency array...
		this.domObject = domObject;
		this.dependencies = new Array();
		this.id = domObject.get(0).id;
		this.hide();
        this.combinator = domObject.attr('dependencyCombinator')=='AND'?'AND':'OR'; 
		
		// parse dependencies
		var i='';
		
		while (true) {
			var dependency = domObject.attr('dependsOn'+i);
			i++;
			if (!dependency) {
				if (i==1) continue;
				break;
			}
			// parse out the parts
			var bits = dependencyRegExp.exec(dependency);
			if (!bits) return;
			debug && console.log('Parsed dependency:',bits);
			var dependsOn = bits[1];
			var dependsOnTest = bits[2];
			var dependsOnValue = typeof(bits[3])=='undefined' ? '' : bits[3];

			// detect and store negation
			var negate=false;
			if (dependsOnTest.substr(0,1)=='!') {
				negate=true;
				dependsOnTest = dependsOnTest.substr(1);
			}

			// reject any unrecognised comparators
			if (typeof(comparatorLookup[dependsOnTest])!='undefined') {

				// lookup dependee Dom element using callback
				var dependeeJqueryObject = elementLookupCallback(dependsOn);
                debug && console.log('Lookup on '+dependsOn+' returned ',dependeeJqueryObject);

				// skip this if we couldn't find the dependee
				if (!dependeeJqueryObject.length) continue;
				
				// run comparison value through the appropriate cleanup function
				if (typeof(comparatorLookup[dependsOnTest]['cleanup'])!='undefined') {
					debug && console.log('About to clean up comparator. Before = ',dependsOnValue);
					dependsOnValue=comparatorLookup[dependsOnTest]['cleanup'](dependsOnValue);
					debug && console.log('Finished cleaning up comparator. After = ',dependsOnValue);
				}
				
				//See if we already have a dependee object for the dependee Dom element
				var dependeeId = dependeeJqueryObject.data('dependeeId');
				
				if (!dependeeId) {
					//Create dependee object if not exists
					dependee = new Dependee( dependeeJqueryObject );
				} else {
					debug && console.log('Dependee already exists:',dependees[dependeeId]);
					dependee = dependees[dependeeId];
				}
				
                if (dependee) {
                    // Add the current object to the dependents list for the dependee 
				    dependee.addDependent(this);
				
				    // add dependee to dependencies array (together with comparison value, comparator and negation)
				    this.dependencies.push([dependee,dependsOnTest,dependsOnValue,negate]);
                }
			}
		}
	}

	Dependent.prototype.setupSubDependees = function() {
		// find and store all sub-dependees
		var self = this;
		self.subDependees = {};
		this.domObject.find('.isDependee').each(function(){
			var id = $(this).data('dependeeId');
			self.subDependees[id] = 1;
		});
	}

	Dependent.prototype.hide = function(){
		this.visible = false;
		this.domObject.hide();
		return this;
	}

	Dependent.prototype.show = function(){
		this.visible = true;
		this.domObject.show();
		return this;
	}
	
	Dependent.prototype.check = function(){
		var display = true;

		// Iterate through the dependencies
		for( var i = this.dependencies.length-1; i>=0; i-- ) {
			// call the check method on the dependency passing comparison value, comparator and negation
			display = this.dependencies[i][0].check(this,this.dependencies[i][1],this.dependencies[i][2],this.dependencies[i][3]);
            if (this.combinator=='AND') {
                if (!display) break;
            } else {
                if (display) break;
            }
		}

		var changed = false;
		if (display & !this.visible) {
			this.show();
            var iframe = this.domObject.find('iframe[delayed-src]').loadDelayed();
			changed = true
		} else if (!display & this.visible) {
			this.hide();
			changed=true;
		}
		if (changed) {
			// if this object is a dependee as well as a dependent then call onVisibilityChange for the dependee
			var dependeeId = this.domObject.data('dependeeId');
			if (dependeeId) dependees[dependeeId].onVisibilityChange();
			
			// call the onVisbilityChange event for all subdependees
			for ( subDependeeId in this.subDependees) {
				dependees[subDependeeId].onVisibilityChange();
			}
		}
	}
	
	function findDependee(locator) {
		var inputs = $('input,select,textarea,iframe');
        // First try lookup by input name
		var items = inputs.filter('[name="'+locator+'"]');
        // Then try adding [] to input name to support PHP array inputs
        if (!items.length) items = inputs.filter('[name="'+locator+'[]"]');
        // Then try lookup by ID
        if (!items.length) items = $(document.getElementById(locator));
		debug && console.log('Found dependee:',items);
		return items;
	}
	
	// ================================= CREATE DEPENDENTS ===================================
	var dependentObjects = {};
	
	$('[dependsOn],[dependsOn1],[dependsOn2]')
		// We need all the objects to have a unique ID so we can lookup them up
		.uniqueId()
		// for each object with dependencies create a dependant object and store it on the object or in a hash keyed on object ID
		.each(function(){
			dependentObjects[this.id] = new Dependent( $(this), findDependee );
		})
		// Once all the dependents ( and therefore the dependees) have been created we can search each for sub-dependees
		.each(function(){
			dependentObjects[this.id].setupSubDependees();
		});

	setupDependentInputs = function() {
		for (var id in dependentObjects) {
			// First hide, then check
			dependentObjects[id].hide().check();
		}
	}
	setupDependentInputs();
	

    $('iframe[delayed-src]:visible').loadDelayed();
		
})
