<?

function parseChemicalFormulaToElements($formula) {
    $elements = "(H|He|Li|Be|B|C|N|O|F|Ne|Na|Mg|Al|Si|P|S|Cl|Ar|K|Ca|Sc|Ti|V|Cr|Mn|Fe|Co|Ni|Cu|Zn|Ga|Ge|As|Se|Br|Kr|Rb|Sr|Y|Zr|Nb|Mo|Tc|Ru|Rh|Pd|Ag|Cd|In|Sn|Sb|Te|I|Xe|Cs|Ba|La|Ce|Pr|Nd|Pm|Sm|Eu|Gd|Tb|Dy|Ho|Er|Tm|Yb|Lu|Hf|Ta|W|Re|Os|Ir|Pt|Au|Hg|Tl|Pb|Bi|Po|At|Rn|Fr|Ra|Ac|Th|Pa|U|Np|Pu|Am|Cm|Bk|Cf|Es|Fm|Md|No|Lr)";

    $re = "/^".str_repeat("(?:$elements\d*)?",10)."\$/";
    preg_match($re, $formula, $matches);

    if (!count($matches)) return false;
    
    array_shift($matches);
    return $matches;
}

function parseChemicalStringToElements($input) {

    $input = trim($input);
    // See if it is a formula first
    $elements = parseChemicalFormulaToElements( $input );
    
    if (empty($elements)) {

        static $multiwordMappings = [
            'stainless steel'        => ['Fe', 'C', 'Cr', 'Ni'],
            'nickel silver'          => ['Cu', 'Ni', 'Zn'],
            'sterling silver'        => ['Ag', 'Cu'],
        ];
        
        static $mappings = [
            // Compounds
            'steel'                  => ['Fe', 'C'],
            'bronze'                 => ['Cu', 'Sn'],
            'pewter'                 => ['Sn', 'Cu', 'Sb'],
            'brass'                  => ['Cu', 'Zn'],
            'solder'                 => ['Sn', 'Pb'], // or adjust for lead-free solders.
            'duralumin'              => ['Al', 'Cu', 'Mn', 'Mg'],

            // Elements
            "hydrogen" => "H",
            "helium" => "He",
            "lithium" => "Li",
            "beryllium" => "Be",
            "boron" => "B",
            "carbon" => "C",
            "nitrogen" => "N",
            "oxygen" => "O",
            "fluorine" => "F",
            "neon" => "Ne",
            "sodium" => "Na",
            "magnesium" => "Mg",
            "aluminium" => "Al",
            "silicon" => "Si",
            "phosphorus" => "P",
            "sulfur" => "S",
            "chlorine" => "Cl",
            "argon" => "Ar",
            "potassium" => "K",
            "calcium" => "Ca",
            "scandium" => "Sc",
            "titanium" => "Ti",
            "vanadium" => "V",
            "chromium" => "Cr",
            "manganese" => "Mn",
            "iron" => "Fe",
            "cobalt" => "Co",
            "nickel" => "Ni",
            "copper" => "Cu",
            "zinc" => "Zn",
            "gallium" => "Ga",
            "germanium" => "Ge",
            "arsenic" => "As",
            "selenium" => "Se",
            "bromine" => "Br",
            "krypton" => "Kr",
            "rubidium" => "Rb",
            "strontium" => "Sr",
            "yttrium" => "Y",
            "zirconium" => "Zr",
            "niobium" => "Nb",
            "molybdenum" => "Mo",
            "technetium" => "Tc",
            "ruthenium" => "Ru",
            "rhodium" => "Rh",
            "palladium" => "Pd",
            "silver" => "Ag",
            "cadmium" => "Cd",
            "indium" => "In",
            "tin" => "Sn",
            "antimony" => "Sb",
            "tellurium" => "Te",
            "iodine" => "I",
            "xenon" => "Xe",
            "caesium" => "Cs",
            "barium" => "Ba",
            "lanthanum" => "La",
            "cerium" => "Ce",
            "praseodymium" => "Pr",
            "neodymium" => "Nd",
            "promethium" => "Pm",
            "samarium" => "Sm",
            "europium" => "Eu",
            "gadolinium" => "Gd",
            "terbium" => "Tb",
            "dysprosium" => "Dy",
            "holmium" => "Ho",
            "erbium" => "Er",
            "thulium" => "Tm",
            "ytterbium" => "Yb",
            "lutetium" => "Lu",
            "hafnium" => "Hf",
            "tantalum" => "Ta",
            "tungsten" => "W",
            "rhenium" => "Re",
            "osmium" => "Os",
            "iridium" => "Ir",
            "platinum" => "Pt",
            "gold" => "Au",
            "mercury" => "Hg",
            "thallium" => "Tl",
            "lead" => "Pb",
            "bismuth" => "Bi",
            "polonium" => "Po",
            "astatine" => "At",
            "radon" => "Rn",
            "francium" => "Fr",
            "radium" => "Ra",
            "actinium" => "Ac",
            "thorium" => "Th",
            "protactinium" => "Pa",
            "uranium" => "U",
            "neptunium" => "Np",
            "plutonium" => "Pu",
            "americium" => "Am",
            "curium" => "Cm",
            "berkelium" => "Bk",
            "californium" => "Cf",
            "einsteinium" => "Es",
            "fermium" => "Fm",
            "mendelevium" => "Md",
            "nobelium" => "No",
            "lawrencium" => "Lr",
            "rutherfordium" => "Rf",
            "dubnium" => "Db",
            "seaborgium" => "Sg",
            "bohrium" => "Bh",
            "hassium" => "Hs",
            "meitnerium" => "Mt",
            "darmstadtium" => "Ds",
            "roentgenium" => "Rg",
            "copernicium" => "Cn",
            "nihonium" => "Nh",
            "flerovium" => "Fl",
            "moscovium" => "Mc",
            "livermorium" => "Lv",
            "tennessine" => "Ts",
            "oganesson" => "Og",

            // Simple anions
            "fluoride" => ["F"],
            "chloride" => ["Cl"],
            "bromide" => ["Br"],
            "iodide" => ["I"],
            "oxide" => ["O"],
            "peroxide" => ["O"],
            "sulfide" => ["S"],
            "selenide" => ["Se"],
            "telluride" => ["Te"],
            "nitride" => ["N"],
            "phosphide" => ["P"],
            "carbide" => ["C"],
            "hydride" => ["H"],
            
            // Oxyanions
            "hydroxide" => ["O", "H"],
            "carbonate" => ["C", "O"],
            "bicarbonate" => ["H", "C", "O"],
            "sulfate" => ["S", "O"],
            "sulfite" => ["S", "O"],
            "thiosulfate" => ["S", "O"],
            "nitrate" => ["N", "O"],
            "nitrite" => ["N", "O"],
            "phosphate" => ["P", "O"],
            "chromate" => ["Cr", "O"],
            "dichromate" => ["Cr", "O"],
            "permanganate" => ["Mn", "O"],
            "cyanide" => ["C", "N"],
            "thiocyanate" => ["S", "C", "N"],
            "oxalate" => ["C", "O"],
            "acetate" => ["C", "H", "O"],
            "tartrate" => ["C", "H", "O"],
            "formate" => ["C", "H", "O"],
            "citrate" => ["C", "H", "O"],
            
            // Halogen oxyanions
            "hypochlorite" => ["Cl", "O"],
            "chlorite" => ["Cl", "O"],
            "chlorate" => ["Cl", "O"],
            "perchlorate" => ["Cl", "O"],
            "hypobromite" => ["Br", "O"],
            "bromite" => ["Br", "O"],
            "bromate" => ["Br", "O"],
            "perbromate" => ["Br", "O"],
            "hypoiodite" => ["I", "O"],
            "iodite" => ["I", "O"],
            "iodate" => ["I", "O"],
            "periodate" => ["I", "O"],
            
            // Miscellaneous
            "arsenate" => ["As", "O"],
            "arsenite" => ["As", "O"],
            "borate" => ["B", "O"],
            "silicate" => ["Si", "O"],
            "aluminate" => ["Al", "O"],
            "ferrate" => ["Fe", "O"],
            "stannate" => ["Sn", "O"],
            "plumbate" => ["Pb", "O"]
        ];

        $input = preg_replace('/[^a-z]/',' ',strtolower($input));
        $input = preg_replace('/  +/',' ',$input);
        foreach($multiwordMappings as $name=>$elements) {
            $newName = str_replace(' ','_',$name);
            $mappings[ $newName ] = $elements;
            $input = str_replace( $name, $newName, $input );
        }

        $words = explode(' ',$input);
        $elements = [];
        foreach( $words as $word ) {
            if (!isset($mappings[$word])) continue;
            if (is_array($mappings[$word])) $elements = array_merge($elements, $mappings[$word]);
            else $elements[] = $mappings[$word];
        }
    }
    return array_unique($elements);
}

/**
 * Converts a chemical composition string of the form:
 *   "<element><ratio><element><ratio>... <mode>"
 * where <mode> is one of "formula", "atomic", or "weight".
 *
 * Example inputs:
 *   "Fe2O3 formula"
 *   "Fe2O3 atomic"
 *   "Fe2O3 weight"
 *
 * The output is a string of HTML with numeric parts as subscripts,
 * and the ratios/percentages are converted according to the requested mode.
 */
function chemicalFormulaToHtml($inputString) {

    static $modeAliases = [
        'at' => 'atomic',
        'wt' => 'weight',
        'weight' => 'weight',
        'atomic' => 'atomic',
        'wt%' => 'weight',
        'at%' => 'atomic',
    ];

    // 1. Separate composition from mode
    //    We assume the last token after a space is always the mode.
    $parts = explode(' ', trim($inputString));
    if (count($parts) < 2) {
        // Fall back if we can't parse
        return htmlspecialchars($inputString);
    }
    $mode = strtolower(array_pop($parts)); // "formula", "atomic", or "weight"
    if (isset($modeAliases[$mode])) $mode = $modeAliases[$mode];
    else $mode='formula';

    $compositionString = implode(' ', $parts);

    // 2. Parse the composition into (element, ratio) pairs
    //    The ratio might be omitted (implies 1), or might be an integer/float.
    $pattern = '/([A-Z][a-z]?)(\d+(\.\d+)?)?/'; // e.g. "Fe2", "O3", "C", "Na1.5", etc.
    preg_match_all($pattern, $compositionString, $matches, PREG_SET_ORDER);

    $components = [];
    foreach ($matches as $match) {
        $elem  = $match[1];                          // e.g. "Fe"
        $ratio = isset($match[2]) ? (float) $match[2] : 1.0; // e.g. 2, or default to 1.0
        $components[] = [
            'element' => $elem,
            'ratio'   => $ratio,
        ];
    }

    if (empty($components)) {
        // If no valid matches, return as raw
        return htmlspecialchars($inputString);
    }

    // 3. Depending on the mode, calculate either:
    //    - formula: keep ratio as is
    //    - atomic: ratio -> (ratio / total) * 100
    //    - weight: ratio -> (ratio * atomicWeight / totalMass) * 100

    // A small table of atomic weights (extend as needed).
    // Replace values below with more precise ones if you like.
    static $atomicWeights = [
        'H'  => 1.008,
        'He' => 4.003,
        'Li' => 6.941,
        'Be' => 9.012,
        'B'  => 10.81,
        'C'  => 12.01,
        'N'  => 14.01,
        'O'  => 16.00,
        'F'  => 18.998,
        'Ne' => 20.180,
        'Na' => 22.990,
        'Mg' => 24.305,
        'Al' => 26.982,
        'Si' => 28.085,
        'P'  => 30.974,
        'S'  => 32.06,
        'Cl' => 35.45,
        'Ar' => 39.948,
        'K'  => 39.098,
        'Ca' => 40.078,
        'Sc' => 44.956,
        'Ti' => 47.867,
        'V'  => 50.942,
        'Cr' => 51.996,
        'Mn' => 54.938,
        'Fe' => 55.845,
        'Co' => 58.933,
        'Ni' => 58.693,
        'Cu' => 63.546,
        'Zn' => 65.38,
        'Ga' => 69.723,
        'Ge' => 72.630,
        'As' => 74.922,
        'Se' => 78.971,
        'Br' => 79.904,
        'Kr' => 83.798,
        'Rb' => 85.468,
        'Sr' => 87.62,
        'Y'  => 88.906,
        'Zr' => 91.224,
        'Nb' => 92.906,
        'Mo' => 95.95,
        'Tc' => 98, // No stable isotopes, standard atomic weight
        'Ru' => 101.07,
        'Rh' => 102.91,
        'Pd' => 106.42,
        'Ag' => 107.87,
        'Cd' => 112.41,
        'In' => 114.82,
        'Sn' => 118.71,
        'Sb' => 121.76,
        'Te' => 127.60,
        'I'  => 126.90,
        'Xe' => 131.29,
        'Cs' => 132.91,
        'Ba' => 137.33,
        'La' => 138.91,
        'Ce' => 140.12,
        'Pr' => 140.91,
        'Nd' => 144.24,
        'Pm' => 145, // No stable isotopes
        'Sm' => 150.36,
        'Eu' => 151.96,
        'Gd' => 157.25,
        'Tb' => 158.93,
        'Dy' => 162.50,
        'Ho' => 164.93,
        'Er' => 167.26,
        'Tm' => 168.93,
        'Yb' => 173.05,
        'Lu' => 174.97,
        'Hf' => 178.49,
        'Ta' => 180.95,
        'W'  => 183.84,
        'Re' => 186.21,
        'Os' => 190.23,
        'Ir' => 192.22,
        'Pt' => 195.08,
        'Au' => 196.97,
        'Hg' => 200.59,
        'Tl' => 204.38,
        'Pb' => 207.2,
        'Bi' => 208.98,
        'Po' => 209, // No stable isotopes
        'At' => 210, // No stable isotopes
        'Rn' => 222, // No stable isotopes
        'Fr' => 223, // No stable isotopes
        'Ra' => 226, // No stable isotopes
        'Ac' => 227, // No stable isotopes
        'Th' => 232.04,
        'Pa' => 231.04,
        'U'  => 238.03,
        'Np' => 237, // No stable isotopes
        'Pu' => 244, // No stable isotopes
        'Am' => 243, // No stable isotopes
        'Cm' => 247, // No stable isotopes
        'Bk' => 247, // No stable isotopes
        'Cf' => 251, // No stable isotopes
        'Es' => 252, // No stable isotopes
        'Fm' => 257, // No stable isotopes
        'Md' => 258, // No stable isotopes
        'No' => 259, // No stable isotopes
        'Lr' => 262, // No stable isotopes
        'Rf' => 267, // No stable isotopes
        'Db' => 270, // No stable isotopes
        'Sg' => 271, // No stable isotopes
        'Bh' => 270, // No stable isotopes
        'Hs' => 277, // No stable isotopes
        'Mt' => 278, // No stable isotopes
        'Ds' => 281, // No stable isotopes
        'Rg' => 282, // No stable isotopes
        'Cn' => 285, // No stable isotopes
        'Nh' => 286, // No stable isotopes
        'Fl' => 289, // No stable isotopes
        'Mc' => 290, // No stable isotopes
        'Lv' => 293, // No stable isotopes
        'Ts' => 294, // No stable isotopes
        'Og' => 294  // No stable isotopes
    ];

    // Sum for either atomic or weight
    if ($mode === 'atomic') {
        // Sum of all atomic ratios
        $totalRatio = 0;
        foreach ($components as $c) {
            $totalRatio += $c['ratio'];
        }
        // Convert each ratio to fraction of total * 100
        foreach ($components as &$c) {
            $c['ratio'] = $c['ratio'] / $totalRatio * 100.0;
        }
        unset($c); // break reference
    } elseif ($mode === 'weight') {
        // Sum of ratio * atomicWeight
        $totalMass = 0;
        foreach ($components as $c) {
            $aw = isset($atomicWeights[$c['element']]) ? $atomicWeights[$c['element']] : 0.0;
            $totalMass += $c['ratio'] * $aw;
        }
        // Convert each to fraction of total mass * 100
        foreach ($components as &$c) {
            $aw = isset($atomicWeights[$c['element']]) ? $atomicWeights[$c['element']] : 0.0;
            $massContribution = $c['ratio'] * $aw;
            // Avoid divide by zero
            $c['ratio'] = ($totalMass > 0) ? ($massContribution / $totalMass * 100.0) : 0;
        }
        unset($c);
    }
    // If $mode === 'formula', we do nothing special (ratios remain as is).

    // 4. Format the output: "Fe<sub>2</sub>O<sub>3</sub>", etc.
    //    For "atomic"/"weight" mode, the ratio is now a percentage. We’ll just
    //    round to two decimals, but convert to integer if it’s effectively whole.
    $htmlParts = [];
    foreach ($components as $c) {
        $element = $c['element'];
        $value   = $c['ratio'];

        // Round to four decimals
        $rounded = round($value, 4);

        // If the rounding yields something near an integer, use integer format
        if (abs($rounded - round($rounded)) < 1e-2) {
            $displayNumber = (string) round($rounded);
        } else {
            // Otherwise show up to 4 decimals
            $displayNumber = preg_replace('/(\\.\\d*[1-9]+)0+$/','$1',number_format($rounded, 4, '.', ''));
        }

        // If the display number is "1" in formula mode, you might opt to omit it:
        // but that is optional. For clarity, we’ll keep it.
        // Subscript only if not "1" or if you specifically always want a subscript.
        // We'll subscript everything to match typical notation of atomic formulae.
        $htmlParts[] = $element . '<sub>' . $displayNumber . '</sub> ';
    }

    // 5. Join them without spaces for typical chemical formula style
    $result = implode('', $htmlParts);

    $displayMode = [
        'weight' => 'wt.%',
        'atomic' => 'at.%',
        'formula' => ''
    ];
    $result .= $displayMode[$mode];
    return '<div class="chemicalFormula">'.$result.'</div>';
}

