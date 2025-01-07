// Wrap everything in a function to stop it polluting the main namespace
const [ChemicalInput, ChemicalInputs] = function(){

// Small utility class for escaping user input before inserting it into the DOM
function escapeHTML(text) {
  var map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };

  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

/**
 * 
 *    FavouriteLibrary Class
 *    Handles loading, saving and updating the list of favourites
 * 
 */ 

class FavouriteLibrary {
    constructor(loadHandler, saveHandler) {
        this.favourites = new Map();
        this.updateCallbacks = [];
        this.saveHandler = saveHandler;

        // Load initial favourites
        loadHandler((favourites) => {
            this.favourites = new Map([...favourites.entries()].sort());
            this._updateAllWidgets();
        });
    }

    register(updateCallback) {
        this.updateCallbacks.push(updateCallback);

        // If favourites are already loaded, update immediately
        if (this.favourites.size > 0) {
            updateCallback(new Map([...this.favourites.entries()].sort()));
        } else {
            // If still loading, send a temporary "Loading..." message
            updateCallback(new Map([["Loading...", ""]]));
        }
    }

    save(name, formula) {
        if (formula === "" || formula === null) {
            // Delete the favourite if it exists
            this.favourites.delete(name);
        } else {
            // Create or update the favourite
            this.favourites.set(name, formula);
        }

        // Update the sorted favourites
        this.favourites = new Map([...this.favourites.entries()].sort());

        // Call save handler
        this.saveHandler(name, formula);

        // Update all registered widgets
        this._updateAllWidgets();
    }

    _updateAllWidgets() {
        this.updateCallbacks.forEach(callback => callback(new Map([...this.favourites.entries()].sort())));
    }
}

/**
 * 
 *    ChemicalInput Class
 *    Single chemical input.
 * 
 */ 
const ChemicalInput = class {
  
  constructor(_el,favouriteLibrary=null) {

    let actions = '';
    if (favouriteLibrary) actions = `
      <div class="chemical-input__actions">
        <div class="chemical-input__actions__faves-wrap">
          <select class="chemical-input__faves"></select>
          <input class="chemical-input__faves-name" type="text" />
          <button type="button" data-action="save">Save</button>
          <button type="button" data-action="delete">Delete</button>
        </div>
      </div>
    `;

    // Generate the DOM.
    const dom = `<div class="chemical-input">
      <div class="chemical-input__table-wrap">
        <div class="periodic-table"></div>
      </div>

      <div class="chemical-input__chain-header">
        <ul class="chemical-input__mode">
          <li><button data-mode="at">at.%</button></li>
          <li><button data-mode="wt">wt.%</button></li>
          <li><button data-mode="formula">Formula</button></li>
        </ul>
      </div>

      <div class="chemical-input__chain">
        <div class="chemical-input__chain__elements"></div>
        <button class="chemical-input__chain__add" aria-label="Add item">
          <svg viewBox="0 0 448 512"><path d="M256 80c0-17.7-14.3-32-32-32s-32 14.3-32 32V224H48c-17.7 0-32 14.3-32 32s14.3 32 32 32H192V432c0 17.7 14.3 32 32 32s32-14.3 32-32V288H400c17.7 0 32-14.3 32-32s-14.3-32-32-32H256V80z"/></svg>
        </button>
      </div>

      ${actions}
    </div>`;
    _el.insertAdjacentHTML('beforebegin', dom);
    const el = _el.previousSibling;
    _el.setAttribute('type', 'hidden');
    el.prepend(_el);

    // Cache DOM elements.
    this.chainInput = _el;
    this.periodicTable = el.querySelector('.periodic-table');
    this.chainModeBtns = el.querySelectorAll('.chemical-input__mode button');
    this.chainElements = el.querySelector('.chemical-input__chain__elements');
    this.chainAddBtn = el.querySelector('.chemical-input__chain__add');
    if (favouriteLibrary) {
      this.actionsEl = el.querySelector('.chemical-input__actions');
      this.favesDropdown = el.querySelector('.chemical-input__faves');
      this.favesName = el.querySelector('.chemical-input__faves-name');
      this.favesSaveBtn = el.querySelector('button[data-action="save"]');
      this.favesDeleteBtn = this.actionsEl.querySelector('button[data-action="delete"]');
    }

    // Props.
    this.tableElements = null;
    this.chainData = [];
    this.mode = `formula`;
    this.favouriteLibrary = favouriteLibrary;
    this.currentlyLoaded = null;

    /**
     * 
     *  UI listeners.
     * 
     */ 
    [...this.chainModeBtns].forEach(mode => mode.addEventListener('click', this.onModeClick.bind(this)));
    this.chainElements.addEventListener('click', this.onChainElementsClick.bind(this));
    this.chainElements.addEventListener('focusout', this.onChainElementsBlur.bind(this));
    this.chainAddBtn.addEventListener('click', this.onChainAddClick.bind(this));
    if (favouriteLibrary) {
      this.favesDropdown.addEventListener('change', this.onActionChange.bind(this));
      this.favesDeleteBtn.addEventListener('click', this.deleteFave.bind(this));
      this.favesSaveBtn.addEventListener('click', this.saveFave.bind(this));
    }

    // Keyboard listener.
    document.addEventListener('keydown', e => {
      const focusedChainElement = this.chainElements.querySelector('.periodic-table__element:focus');
      const symbol = focusedChainElement?.dataset.symbol;

      // make sure that pressing enter on the number input box doesn't submit the form
      if (e.key=='Enter' && e.target.classList.contains('pt-count')) {
        e.target.blur();
        e.preventDefault();
        return false;
      }

        
      if (focusedChainElement) {
        console.log(e.key); 
        switch (e.key) {
          case 'ArrowUp':
            e.preventDefault();
            this.elementUpDown(focusedChainElement, 'up');
            break;
          case 'ArrowDown':
            e.preventDefault();
            this.elementUpDown(focusedChainElement, 'down');
            break;
          case 'ArrowLeft':
            e.preventDefault();
            this.elementLeft(focusedChainElement);
            break;
          case 'ArrowRight':
            e.preventDefault();
            this.elementRight(focusedChainElement);
            break;
          case 'Delete':
          case 'Backspace':
            e.preventDefault();
            this.elementRemove(focusedChainElement);
            break;
        }

        // Reapply focus to active element.
        this.chainElements.querySelector(`[data-symbol="${symbol}"]`)?.focus();
      }
    });

    if (favouriteLibrary) {
      var self = this;
      favouriteLibrary.register( function( favourites ){
        // This is the update callback. This is called whenever the list of favourites needs to be updated
        // That includes immediately on registration
        self.updateActions( favourites );
      });
    }

    // Lets go!
    this.parseInput();
  }

  parseInput() {
    this.chainData = decodeChain(this.chainInput.value);

    // Set mode?
    const regex = /([a-z]{1,2}\s*[0-9\.]+[,\s]?|(?:atomic|weight|formula|at|wt))/gi;
    let chemArray = this.chainInput.value.toUpperCase().match(regex);
    if (!chemArray) chemArray=[];
    const possibleMode = chemArray[chemArray.length - 1];
    console.log(chemArray);
    switch (possibleMode) {
      case 'ATOMIC':
      case 'AT':
        this.selectMode('at');
        break;

      case 'WEIGHT':
      case 'WT':
        this.selectMode('wt');
        break;

      case 'FORMULA':
      default:
        this.selectMode('formula');
    }

    if (this.chainData.length) {
      this.renderChain();
    }
  }

  renderTable(data = json) {
    let items = ``;
    
    data.forEach(item => {
      const inChain = this.chainData.find(elem => elem.symbol == item.symbol);
      const disabled = (inChain) ? `disabled` : ``;
      items += `<button class="periodic-table__element ${disabled}" data-symbol="${item.symbol}" data-series="${item.series}">
        <span class="number">${item.number}</span>
        <span class="weight">${item.weight}</span>
        <span class="symbol">${item.symbol}</span>
        <span class="name">${item.name}</span>
      </button>`;

      // A vertical gap before the lanthenides.
      if (item.symbol == `Og`) {
        items += `<div class="periodic-table__vertical-spacer"></div>`;
      }
    });

    // Add the close btn.
    items += `<button class="periodic-table__close-btn">Close</button>`;

    this.periodicTable.innerHTML = items;
    
    // Periodic table listeners.
    this.tableElements = this.periodicTable.querySelectorAll('.periodic-table__element');
    [...this.tableElements].forEach(element => {
      element.addEventListener('click', e => {
        e.preventDefault();
        this.addElementToChain(element);
      });
    });

    // Close btn listener.
    const closeBtn = this.periodicTable.querySelector('.periodic-table__close-btn');
    closeBtn.addEventListener('click', e => {
      this.removeTable();
    })
  }

  removeTable() {
    this.tableElements = null;
    this.periodicTable.innerHTML = ``;
  }

  selectMode(_mode) {
    this.mode = _mode;
    [...this.chainModeBtns].forEach(modeBtn => {
      if (modeBtn.dataset.mode === this.mode) {
        modeBtn.classList.add('active');
      } else {
        modeBtn.classList.remove('active');
      }
    });
    this.renderChain();
  }

  renderChain() {
    let chainDivs = ``;
    this.chainData.forEach(elem => {
      let count;
      switch(this.mode) {
        case 'at': 
          count = elem.count / this.getTotalCount();
          break;
        case 'wt':
          count = (Number(elem.weight) * elem.count) / this.getTotalWeight();
          break;
        case 'formula':
        default:
          count = elem.count;
      }
      
      chainDivs += `<div class="periodic-table__element-wrap"><button class="periodic-table__element" data-symbol="${elem.symbol}" data-series="${elem.series}">
          <span class="number">${elem.number}</span>
          <span class="weight">${elem.weight}</span>
          <span class="symbol">${elem.symbol}</span>
          <span class="name">${elem.name}</span>
          <input class="pt-count" value="${parseFloat(count.toFixed(7))}" />
        </button>
        <ul>
          <li data-action="left"><button>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l192 192c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256 246.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-192 192z"/></svg></button></li>
          <li data-action="right"><button><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M310.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-192 192c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L242.7 256 73.4 86.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l192 192z"/></svg></button></li>
          <li data-action="up"><button><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M233.4 105.4c12.5-12.5 32.8-12.5 45.3 0l192 192c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L256 173.3 86.6 342.6c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3l192-192z"/></svg></button></li>
          <li data-action="down"><button><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg></button></li>
          <li data-action="remove"><button><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M376.6 84.5c11.3-13.6 9.5-33.8-4.1-45.1s-33.8-9.5-45.1 4.1L192 206 56.6 43.5C45.3 29.9 25.1 28.1 11.5 39.4S-3.9 70.9 7.4 84.5L150.3 256 7.4 427.5c-11.3 13.6-9.5 33.8 4.1 45.1s33.8 9.5 45.1-4.1L192 306 327.4 468.5c11.3 13.6 31.5 15.4 45.1 4.1s15.4-31.5 4.1-45.1L233.7 256 376.6 84.5z"/></svg></button></li>
        <ul>
      </div>`;
    });
    this.chainElements.innerHTML = chainDivs;
    
    // Update the input value.
    this.chainInput.value = encodeChain(this.chainData, this.mode);
  }

  clearChain() {
    this.chainData = [];
    this.renderChain();
  }

  addElementToChain(tableElement) {
    const selected = {
      number: tableElement.querySelector('.number').innerHTML,
      name: tableElement.querySelector('.name').innerHTML,
      symbol: tableElement.querySelector('.symbol').innerHTML,
      weight: tableElement.querySelector('.weight').innerHTML,
      series: tableElement.dataset.series,
      count: (this.mode === 'wt') ? 0 : 1
    };
    this.chainData.push(selected);
    this.renderChain();
    // Re-render the table to disable the element they just added
    this.renderTable();
  }

  elementLeft(element) {
    const from = this.chainData.findIndex(elem => elem.symbol == element.querySelector('.symbol').innerHTML);
    const to = from - 1;
    if (to >= 0) {
      move( this.chainData, from, to);
      this.renderChain();
    }
  }

  elementRight(element) {
    const from = this.chainData.findIndex(elem => elem.symbol == element.querySelector('.symbol').innerHTML);
    const to = from + 1;
    if (to <= this.chainData.length) {
      move( this.chainData, from, to);
      this.renderChain();
    }
  }

  elementUpDown(chainElement, direction) {   
    const chainDataItem = this.chainData.find(el => el.symbol === chainElement.querySelector('.symbol').innerHTML);
    let currentPercentage, amount = null;

    // Calculate the count increase.
    switch (this.mode) {
      case 'wt':
        // Increase/decrease weight percentage by 1%.
        currentPercentage = (Number(chainDataItem.weight) * chainDataItem.count) / this.getTotalWeight();
        amount = (direction == 'up') ? 0.01 : -0.01;
        this.adjustElementByPercentage(chainDataItem, currentPercentage + amount);
        break;

      case 'at': 
        // Increase/decrease atom percentage by 1%.
        currentPercentage = chainDataItem.count / this.getTotalCount();
        amount = (direction == 'up') ? 0.01 : -0.01;
        this.adjustElementByPercentage(chainDataItem, currentPercentage + amount);
        break;

      case 'formula':
        // Increase/decrease count by 1.
        amount = (direction == 'up') ? 1 : -1;
        this.adjustElementByCount(chainDataItem, chainDataItem.count + amount);
        break;
    }
  }

  elementRemove(element) {
    const symbol = element.querySelector('.symbol').innerHTML;
    this.chainData = this.chainData.filter(elem => elem.symbol !== symbol);
    this.renderChain();
  }

  // AT and WT % modes.
  adjustElementByPercentage(chainDataItem, newPercentage) {

    // Calculate the amount to adjustBy.
    const totalCount = this.getTotalCount();
    const totalWeight = this.getTotalWeight();
    let currentPercentage, adjustBy = null;
    switch(this.mode) {
      case 'at': 
        currentPercentage = chainDataItem.count / totalCount;
        adjustBy = newPercentage - currentPercentage;
        break;
      case 'wt':
        currentPercentage = (Number(chainDataItem.weight) * chainDataItem.count) / totalWeight;
        adjustBy = newPercentage - currentPercentage;
        break;
    }

    // Get all the elements in the chain and capture their current percentage values.
    let elemsByPercentage = this.chainData.map(_el => { 
      let percentage = null;
      switch(this.mode) {
        case 'at': percentage = _el.count / totalCount; break;
        case 'wt': percentage = (Number(_el.weight) * _el.count) / totalWeight; break;
      }
      return {
        ..._el,
        "percentage": percentage
      }
    });
    // Order the new array by percentages descending.
    elemsByPercentage.sort((a,b) => b.percentage - a.percentage); 

    // Now loop through all elements to distribute the amended percentage values.
    const elemsByNewPercentage = elemsByPercentage.map(el => {
      if (el.symbol === chainDataItem.symbol) {
        // Focused element, we apply the newPercentage value.
        el.percentage = newPercentage;
      } else if (adjustBy) {
        // Non-focussed element, we remove some or all of the added percentage.
        if (el.percentage > adjustBy) {
          // Remove all of the adjust from this item.
          el.percentage = el.percentage - adjustBy;
          adjustBy = 0;
        } else {
          // Remove some of the adjust from this item.
          adjustBy = adjustBy - el.percentage;
          el.percentage = 0;
        }
      }
      return el;
    });

    // Finally we need to calculate the new formula count values based off the amended percentages.
    this.chainData = this.chainData.map(el => {
      let newElement, newCount = null;
      switch(this.mode) {
        case 'at': 
          newElement = elemsByNewPercentage.find(_el => _el.symbol == el.symbol);
          newCount = newElement.percentage * totalCount;
          break;
        case 'wt':
          newElement = elemsByNewPercentage.find(_el => _el.symbol == el.symbol);
          newCount = (newElement.percentage * totalWeight) / Number(el.weight);
          break;
      }
      return {
        ...el,
        count: newCount,
      }
    });

    // Clean up - remove the temp percentage props on the this.chainData array.
    this.chainData.forEach(el => delete el.percentage);
    this.renderChain();
  }

  // Formula mode.
  adjustElementByCount(chainDataItem, newCount) {
    this.chainData = this.chainData.map(el => {
      if (el.symbol == chainDataItem.symbol) {
        return {
          ...el,
          count: newCount
        }
      } else {
        return el
      }
    });
    this.renderChain();
  }

  onChainElementsClick(e) {
    e.preventDefault();
    const chainElement = e.target.closest('.periodic-table__element-wrap');
    if (chainElement) {
      const action = e.target.closest('[data-action]')?.dataset.action;
      if (action) {
        switch (action) {
          case 'left': this.elementLeft(chainElement); break;
          case 'right': this.elementRight(chainElement); break;
          case 'up': this.elementUpDown(chainElement, 'up'); break;
          case 'down': this.elementUpDown(chainElement, 'down'); break;
          case 'remove': this.elementRemove(chainElement); break;
        }
      }
    } 
  }

  onChainElementsBlur(e) {
    // Check if blur occured on the element count input.
    if (e.target.classList.contains('pt-count')) {
      const chainElement = e.target.closest('.periodic-table__element-wrap');
      const chainDataItem = this.chainData.find(item => item.symbol == chainElement.querySelector('.symbol').innerHTML);
      switch(this.mode) {
        case 'at': this.adjustElementByPercentage(chainDataItem, Number(e.target.value)); break;
        case 'wt': this.adjustElementByPercentage(chainDataItem, Number(e.target.value)); break;
        case 'formula': this.adjustElementByCount(chainDataItem, Number(e.target.value)); break;
      }
    }
  }

  onChainAddClick(e) {
    e.preventDefault();
    const addBtn = e.target.closest('.chemical-input__chain__add');
    if (addBtn) {
      this.renderTable();
    }
  }

  onModeClick(e) { 
    e.preventDefault();
    this.selectMode(e.target.dataset.mode); 
  }

  updateActions(favourites,initialize=true) {
    // if favourites are provided then save them here for later
    if (favourites) {
      this.favourites = favourites;
    } else {
      // if no favourites provided then load the ones we previously saved
      favourites = this.favourites;
    }
    let opts = `<option value="__init">My library...</option>`;
    opts += `<option value="__clear">Clear formula</option>`;
    opts += `<option value="__save-as">Save formula as...</option>`;
    favourites.forEach((formula,name) => {
      opts += `<option value="${escapeHTML(formula)}">${escapeHTML(name)}</option>`;
    });
    
    this.favesDropdown.innerHTML = opts;
    if (initialize) this.favesDropdown.value = `__init`;

    this.favesName.style.display='none';
    if (this.currentlyLoaded) {
      this.favesSaveBtn.style.display='block';
      this.favesDeleteBtn.style.display='block';
      let optionToSelect = Array.from(this.favesDropdown.options).find(option => option.text === this.currentlyLoaded);
      if (optionToSelect) optionToSelect.selected = true;
    } else {
      this.favesSaveBtn.style.display='none';
      this.favesDeleteBtn.style.display='none';
    }
  }

  selectFave() {
    this.chainData = decodeChain(this.favesDropdown.value);
    // Get the selected option's index
    let selectedIndex = this.favesDropdown.selectedIndex;
    this.currentlyLoaded = this.favesDropdown.options[selectedIndex].text;
    console.log(this.currentlyLoaded);
    this.updateActions(null,false);
    this.renderChain();
  }

  // Enables naming of the new fave before saving.
  saveFaveAs() {
    this.favesName.style.display='block';
    this.favesSaveBtn.style.display='block';
    this.favesDeleteBtn.style.display='none';
    this.favesName.focus();

    // Use formula string as temp name but with the final format specifier removed
    this.favesName.value = encodeChain(this.chainData, this.mode).replace(/\s\S+$/,'');
  }

  // Sends the fave to the API.
  saveFave() {
    // See if we are in save-as mode
    if (this.favesName.style.display=='block') {
      // need to do save-as
      let newName = this.favesName.value.trim();
      if (newName.length) {
        this.currentlyLoaded = newName;
        this.favouriteLibrary.save(newName,this.chainInput.value);
      }
    } else {
      // just a plain save
      this.favouriteLibrary.save(this.currentlyLoaded,this.chainInput.value);
    }
  }

  deleteFave() {
    let toDelete = this.currentlyLoaded;
    this.currentlyLoaded = null;
    this.favouriteLibrary.save(toDelete,null);
  }

  onActionChange() {
    switch(this.favesDropdown.value) {

      case '__init':
        // if something is currently loaded then select the dropdown back to this
        if (this.currentlyLoaded) this.updateActions();
        break;
      
      case '__clear': 
        this.clearChain();
        this.currentlyLoaded = null;
        this.updateActions();
        break;

      case '__save-as':
        this.saveFaveAs();
        break;
      
      default: 
        this.selectFave();
    }
  }

  getTotalCount() {
    return this.chainData.reduce((a, b) => { return a + b.count; }, 0);
  }

  getTotalWeight() {
    return this.chainData.reduce((a, b) => { return a + (Number(b.weight) * b.count); }, 0);
  }
}

/**
 * 
 *    ChemicalInputs Class
 *    Manages all single chemical input instances.
 * 
 */ 
const ChemicalInputs = class {
  
  constructor(options) {
    console.log('ChemicalInputs', options);

    this.options = options;
    this.items = [];
    this.faves = null;
    
    this.favouriteLibrary = null;

    if (options.favouritesLoadHandler && options.favouritesSaveHandler) {
      this.favouriteLibrary = new FavouriteLibrary( options.favouritesLoadHandler, options.favouritesSaveHandler );
    }

    this.addAllInputs();
  }

  addAllInputs() {
    // Instantiate all inputs.
    const optInputs = this.options.inputs ? this.options.inputs : [];
    const otherInputs = document.querySelectorAll('[type="chemical"],[data-type="chemical"]');
    // Merge all inputs - No duplicates!
    const inputs = [...new Set([...optInputs, ...otherInputs].flat())];
    this.add(inputs);
  }

  add(inputs) {
    if (Array.isArray(inputs)) {
      [...inputs].forEach(input => {
        const el = new ChemicalInput(input, this.favouriteLibrary);
        this.items.push(el);
      });
    } else {
      const el = new ChemicalInput(inputs, this.favouriteLibrary);
      this.items.push(el);
    }
  }
}

/**
 * 
 *    Utils.
 * 
 */ 
const decodeChain = (string) => {
  const regex = /([a-z]{1,2}\s*[0-9\.]+[,\s]?|(?:atomic|weight|formula|at|wt))/gi;
  let chemArray = string.toUpperCase().match(regex);

  if (!chemArray) chemArray = [];
  const possibleMode = chemArray[chemArray.length - 1];
  switch (possibleMode) {
    case 'ATOMIC':
    case 'AT':
    case 'WEIGHT':
    case 'WT':
    case 'FORMULA':
      chemArray.pop();
      break;
  }

  return chemArray.map(a => {
    const symbol = a.replace(/[^a-zA-Z]+/g, '').toUpperCase();
    const elemFromPeriodicTable = json.find(e => e.symbol.toUpperCase() == symbol);
    console.log(elemFromPeriodicTable);
    const count = a.replace(/^\D+/g, '').replace(',', '');
    return {
      ...elemFromPeriodicTable,
      count: Number(count)
    }
  });
}

const encodeChain = (json, mode) => {
  let string = ``;
  json.forEach(elem => {
    string += elem.symbol + elem.count;
  });
  if (mode) {
    string += ` ${mode}`;
  }
  return string;
}

function move(array, from, to) {
  array.splice(to, 0, array.splice(from, 1)[0]);
}

const json = [
  {
      "number": "1",
      "name": "Hydrogen",
      "symbol": "H",
      "weight": 1.0078,
      "series": "nonmetal",
      "count": 1
  },
  {
      "number": "2",
      "name": "Helium",
      "symbol": "He",
      "weight": 4.0026,
      "series": "noble-gas",
      "count": 1
  },
  {
      "number": "3",
      "name": "Lithium",
      "symbol": "Li",
      "weight": 6.941,
      "series": "alkali-metal",
      "count": 1
  },
  {
      "number": "4",
      "name": "Beryllium",
      "symbol": "Be",
      "weight": 9.0122,
      "series": "alkaline-earth-metal",
      "count": 1
  },
  {
      "number": "5",
      "name": "Boron",
      "symbol": "B",
      "weight": 10.81,
      "series": "metalloid",
      "count": 1
  },
  {
      "number": "6",
      "name": "Carbon",
      "symbol": "C",
      "weight": 12.01,
      "series": "nonmetal",
      "count": 1
  },
  {
      "number": "7",
      "name": "Nitrogen",
      "symbol": "N",
      "weight": 14.01,
      "series": "nonmetal",
      "count": 1
  },
  {
      "number": "8",
      "name": "Oxygen",
      "symbol": "O",
      "weight": 16.00,
      "series": "nonmetal",
      "count": 1
  },
  {
      "number": "9",
      "name": "Fluorine",
      "symbol": "F",
      "weight": 19.00,
      "series": "nonmetal",
      "count": 1
  },
  {
      "number": "10",
      "name": "Neon",
      "symbol": "Ne",
      "weight": 20.18,
      "series": "noble-gas",
      "count": 1
  },
  {
      "number": "11",
      "name": "Sodium",
      "symbol": "Na",
      "weight": 22.99,
      "series": "alkali-metal",
      "count": 1
  },
  {
      "number": "12",
      "name": "Magnesium",
      "symbol": "Mg",
      "weight": 24.31,
      "series": "alkaline-earth-metal",
      "count": 1
  },
  {
      "number": "13",
      "name": "Aluminum",
      "symbol": "Al",
      "weight": 26.98,
      "series": "post-transition-metal",
      "count": 1
  },
  {
      "number": "14",
      "name": "Silicon",
      "symbol": "Si",
      "weight": 28.09,
      "series": "metalloid",
      "count": 1
  },
  {
      "number": "15",
      "name": "Phosphorus",
      "symbol": "P",
      "weight": 30.97,
      "series": "nonmetal",
      "count": 1
  },
  {
      "number": "16",
      "name": "Sulfur",
      "symbol": "S",
      "weight": 32.07,
      "series": "nonmetal",
      "count": 1
  },
  {
      "number": "17",
      "name": "Chlorine",
      "symbol": "Cl",
      "weight": 35.45,
      "series": "nonmetal",
      "count": 1
  },
  {
      "number": "18",
      "name": "Argon",
      "symbol": "Ar",
      "weight": 39.95,
      "series": "noble-gas",
      "count": 1
  },
  {
      "number": "19",
      "name": "Potassium",
      "symbol": "K",
      "weight": 39.10,
      "series": "alkali-metal",
      "count": 1
  },
  {
      "number": "20",
      "name": "Calcium",
      "symbol": "Ca",
      "weight": 40.08,
      "series": "alkaline-earth-metal",
      "count": 1
  },
  {
      "number": "21",
      "name": "Scandium",
      "symbol": "Sc",
      "weight": 44.96,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "22",
      "name": "Titanium",
      "symbol": "Ti",
      "weight": 47.87,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "23",
      "name": "Vanadium",
      "symbol": "V",
      "weight": 50.94,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "24",
      "name": "Chromium",
      "symbol": "Cr",
      "weight": 51.99,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "25",
      "name": "Manganese",
      "symbol": "Mn",
      "weight": 54.94,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "26",
      "name": "Iron",
      "symbol": "Fe",
      "weight": 55.85,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "27",
      "name": "Cobalt",
      "symbol": "Co",
      "weight": 58.93,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "28",
      "name": "Nickel",
      "symbol": "Ni",
      "weight": 58.69,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "29",
      "name": "Copper",
      "symbol": "Cu",
      "weight": 63.55,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "30",
      "name": "Zinc",
      "symbol": "Zn",
      "weight": 65.38,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "31",
      "name": "Gallium",
      "symbol": "Ga",
      "weight": 69.72,
      "series": "post-transition-metal",
      "count": 1
  },
  {
      "number": "32",
      "name": "Germanium",
      "symbol": "Ge",
      "weight": 72.63,
      "series": "metalloid",
      "count": 1
  },
  {
      "number": "33",
      "name": "Arsenic",
      "symbol": "As",
      "weight": 74.92,
      "series": "metalloid",
      "count": 1
  },
  {
      "number": "34",
      "name": "Selenium",
      "symbol": "Se",
      "weight": 78.96,
      "series": "nonmetal",
      "count": 1
  },
  {
      "number": "35",
      "name": "Bromine",
      "symbol": "Br",
      "weight": 79.90,
      "series": "nonmetal",
      "count": 1
  },
  {
      "number": "36",
      "name": "Krypton",
      "symbol": "Kr",
      "weight": 83.80,
      "series": "noble-gas",
      "count": 1
  },
  {
      "number": "37",
      "name": "Rubidium",
      "symbol": "Rb",
      "weight": 85.47,
      "series": "alkali-metal",
      "count": 1
  },
  {
      "number": "38",
      "name": "Strontium",
      "symbol": "Sr",
      "weight": 87.62,
      "series": "alkaline-earth-metal",
      "count": 1
  },
  {
      "number": "39",
      "name": "Yttrium",
      "symbol": "Y",
      "weight": 88.91,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "40",
      "name": "Zirconium",
      "symbol": "Zr",
      "weight": 91.22,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "41",
      "name": "Niobium",
      "symbol": "Nb",
      "weight": 92.91,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "42",
      "name": "Molybdenum",
      "symbol": "Mo",
      "weight": 95.94,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "43",
      "name": "Technetium",
      "symbol": "Tc",
      "weight": 98.00,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "44",
      "name": "Ruthenium",
      "symbol": "Ru",
      "weight": 101.1,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "45",
      "name": "Rhodium",
      "symbol": "Rh",
      "weight": 102.9,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "46",
      "name": "Palladium",
      "symbol": "Pd",
      "weight": 106.4,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "47",
      "name": "Silver",
      "symbol": "Ag",
      "weight": 107.9,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "48",
      "name": "Cadmium",
      "symbol": "Cd",
      "weight": 112.4,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "49",
      "name": "Indium",
      "symbol": "In",
      "weight": 114.8,
      "series": "post-transition-metal",
      "count": 1
  },
  {
      "number": "50",
      "name": "Tin",
      "symbol": "Sn",
      "weight": 118.7,
      "series": "post-transition-metal",
      "count": 1
  },
  {
      "number": "51",
      "name": "Antimony",
      "symbol": "Sb",
      "weight": 121.8,
      "series": "metalloid",
      "count": 1
  },
  {
      "number": "52",
      "name": "Tellurium",
      "symbol": "Te",
      "weight": 127.6,
      "series": "metalloid",
      "count": 1
  },
  {
      "number": "53",
      "name": "Iodine",
      "symbol": "I",
      "weight": 126.9,
      "series": "nonmetal",
      "count": 1
  },
  {
      "number": "54",
      "name": "Xenon",
      "symbol": "Xe",
      "weight": 131.3,
      "series": "noble-gas",
      "count": 1
  },
  {
      "number": "55",
      "name": "Cesium",
      "symbol": "Cs",
      "weight": 132.9,
      "series": "alkali-metal",
      "count": 1
  },
  {
      "number": "56",
      "name": "Barium",
      "symbol": "Ba",
      "weight": 137.3,
      "series": "alkaline-earth-metal",
      "count": 1
  },
  {
      "number": "57",
      "name": "Lanthanum",
      "symbol": "La",
      "weight": 138.9,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "72",
      "name": "Hafnium",
      "symbol": "Hf",
      "weight": 178.5,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "73",
      "name": "Tantalum",
      "symbol": "Ta",
      "weight": 180.95,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "74",
      "name": "Tungsten",
      "symbol": "W",
      "weight": 183.84,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "75",
      "name": "Rhenium",
      "symbol": "Re",
      "weight": 186.21,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "76",
      "name": "Osmium",
      "symbol": "Os",
      "weight": 190.23,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "77",
      "name": "Iridium",
      "symbol": "Ir",
      "weight": 192.22,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "78",
      "name": "Platinum",
      "symbol": "Pt",
      "weight": 195.08,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "79",
      "name": "Gold",
      "symbol": "Au",
      "weight": 196.97,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "80",
      "name": "Mercury",
      "symbol": "Hg",
      "weight": 200.59,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "81",
      "name": "Thallium",
      "symbol": "Tl",
      "weight": 204.38,
      "series": "post-transition-metal",
      "count": 1
  },
  {
      "number": "82",
      "name": "Lead",
      "symbol": "Pb",
      "weight": 207.2,
      "series": "post-transition-metal",
      "count": 1
  },
  {
      "number": "83",
      "name": "Bismuth",
      "symbol": "Bi",
      "weight": 208.98,
      "series": "post-transition-metal",
      "count": 1
  },
  {
      "number": "84",
      "name": "Polonium",
      "symbol": "Po",
      "weight": 209.98,
      "series": "post-transition-metal",
      "count": 1
  },
  {
      "number": "85",
      "name": "Astatine",
      "symbol": "At",
      "weight": 210,
      "series": "post-transition-metal",
      "count": 1
  },
  {
      "number": "86",
      "name": "Radon",
      "symbol": "Rn",
      "weight": 222,
      "series": "noble-gas",
      "count": 1
  },
  {
      "number": "87",
      "name": "Francium",
      "symbol": "Fr",
      "weight": 223,
      "series": "alkali-metal",
      "count": 1
  },
  {
      "number": "88",
      "name": "Radium",
      "symbol": "Ra",
      "weight": 226,
      "series": "alkaline-earth-metal",
      "count": 1
  },
  {
      "number": "89",
      "name": "Actinium",
      "symbol": "Ac",
      "weight": 227,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "104",
      "name": "Rutherfordium",
      "symbol": "Rf",
      "weight": 267.12,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "105",
      "name": "Dubnium",
      "symbol": "Db",
      "weight": 270.13,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "106",
      "name": "Seaborgium",
      "symbol": "Sg",
      "weight": 271.13,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "107",
      "name": "Bohrium",
      "symbol": "Bh",
      "weight": 270.13,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "108",
      "name": "Hassium",
      "symbol": "Hs",
      "weight": 277.15,
      "series": "transition-metal",
      "count": 1
  },
  {
      "number": "109",
      "name": "Meitnerium",
      "symbol": "Mt",
      "weight": 278.16,
      "series": "unknown",
      "count": 1
  },
  {
      "number": "110",
      "name": "Darmstadtium",
      "symbol": "Ds",
      "weight": 281.16,
      "series": "unknown",
      "count": 1
  },
  {
      "number": "111",
      "name": "Roentgenium",
      "symbol": "Rg",
      "weight": 280.16,
      "series": "unknown",
      "count": 1
  },
  {
      "number": "112",
      "name": "Copernicium",
      "symbol": "Cn",
      "weight": 285.18,
      "series": "unknown",
      "count": 1
  },
  {
      "number": "113",
      "name": "Nihonium",
      "symbol": "Nh",
      "weight": 284.18,
      "series": "unknown",
      "count": 1
  },
  {
      "number": "114",
      "name": "Flerovium",
      "symbol": "Fl",
      "weight": 289.19,
      "series": "unknown",
      "count": 1
  },
  {
      "number": "115",
      "name": "Moscovium",
      "symbol": "Mc",
      "weight": 288.19,
      "series": "unknown",
      "count": 1
  },
  {
      "number": "116",
      "name": "Livermorium",
      "symbol": "Lv",
      "weight": 293.20,
      "series": "unknown",
      "count": 1
  },
  {
      "number": "117",
      "name": "Tennessine",
      "symbol": "Ts",
      "weight": 294.21,
      "series": "unknown",
      "count": 1
  },
  {
      "number": "118",
      "name": "Oganesson",
      "symbol": "Og",
      "weight": 294,
      "series": "unknown",
      "count": 1
  },
  {
      "number": "58",
      "name": "Cerium",
      "symbol": "Ce",
      "weight": 140.1,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "59",
      "name": "Praseodymium",
      "symbol": "Pr",
      "weight": 140.9,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "60",
      "name": "Neodymium",
      "symbol": "Nd",
      "weight": 144.2,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "61",
      "name": "Promethium",
      "symbol": "Pm",
      "weight": 145.0,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "62",
      "name": "Samarium",
      "symbol": "Sm",
      "weight": 150.4,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "63",
      "name": "Europium",
      "symbol": "Eu",
      "weight": 152.0,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "64",
      "name": "Gadolinium",
      "symbol": "Gd",
      "weight": 157.3,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "65",
      "name": "Terbium",
      "symbol": "Tb",
      "weight": 158.9,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "66",
      "name": "Dysprosium",
      "symbol": "Dy",
      "weight": 162.5,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "67",
      "name": "Holmium",
      "symbol": "Ho",
      "weight": 164.9,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "68",
      "name": "Erbium",
      "symbol": "Er",
      "weight": 167.3,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "69",
      "name": "Thulium",
      "symbol": "Tm",
      "weight": 168.9,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "70",
      "name": "Ytterbium",
      "symbol": "Yb",
      "weight": 173.0,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "71",
      "name": "Lutetium",
      "symbol": "Lu",
      "weight": 175.0,
      "series": "lanthanide",
      "count": 1
  },
  {
      "number": "90",
      "name": "Thorium",
      "symbol": "Th",
      "weight": 232.04,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "91",
      "name": "Protactinium",
      "symbol": "Pa",
      "weight": 231.04,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "92",
      "name": "Uranium",
      "symbol": "U",
      "weight": 238.03,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "93",
      "name": "Neptunium",
      "symbol": "Np",
      "weight": 237.05,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "94",
      "name": "Plutonium",
      "symbol": "Pu",
      "weight": 244.06,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "95",
      "name": "Americium",
      "symbol": "Am",
      "weight": 243.06,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "96",
      "name": "Curium",
      "symbol": "Cm",
      "weight": 247.07,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "97",
      "name": "Berkelium",
      "symbol": "Bk",
      "weight": 247.07,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "98",
      "name": "Californium",
      "symbol": "Cf",
      "weight": 251.08,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "99",
      "name": "Einsteinium",
      "symbol": "Es",
      "weight": 252.08,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "100",
      "name": "Fermium",
      "symbol": "Fm",
      "weight": 257.10,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "101",
      "name": "Mendelevium",
      "symbol": "Md",
      "weight": 258.10,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "102",
      "name": "Nobelium",
      "symbol": "No",
      "weight": 259.10,
      "series": "actinide",
      "count": 1
  },
  {
      "number": "103",
      "name": "Lawrencium",
      "symbol": "Lr",
      "weight": 262.11,
      "series": "actinide",
      "count": 1
  }
];

return( [ChemicalInput, ChemicalInputs] );
}();
