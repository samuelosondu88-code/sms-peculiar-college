<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

echo "Seeding CBT data...\n\n";

try {
    $db = getDB();

    // ========== SUBJECTS ==========
    $subjects = [
        ['English Language', 'ENG', 'WAEC/NECO/JAMB English Language'],
        ['Mathematics', 'MATH', 'WAEC/NECO/JAMB Mathematics'],
        ['Physics', 'PHY', 'WAEC/NECO/JAMB Physics'],
        ['Chemistry', 'CHM', 'WAEC/NECO/JAMB Chemistry'],
        ['Biology', 'BIO', 'WAEC/NECO/JAMB Biology'],
        ['Agricultural Science', 'AGRIC', 'WAEC/NECO/JAMB Agricultural Science'],
        ['Computer Studies', 'COMPS', 'WAEC/NECO/JAMB Computer Studies'],
        ['Civic Education', 'CIVIC', 'WAEC/NECO/JAMB Civic Education'],
        ['Economics', 'ECO', 'WAEC/NECO/JAMB Economics'],
        ['Government', 'GOV', 'WAEC/NECO/JAMB Government'],
        ['Literature in English', 'LIT', 'WAEC/NECO/JAMB Literature'],
        ['Christian Religious Studies', 'CRS', 'WAEC/NECO/JAMB CRS'],
        ['Geography', 'GEOG', 'WAEC/NECO/JAMB Geography'],
    ];

    $subjStmt = $db->prepare("INSERT IGNORE INTO cbt_subjects (name, code, description) VALUES (?, ?, ?)");
    foreach ($subjects as $s) {
        $subjStmt->execute($s);
    }
    echo "  Subjects created\n";

    // Map subject names to IDs
    $subjMap = [];
    $rows = $db->query("SELECT id, name FROM cbt_subjects")->fetchAll();
    foreach ($rows as $r) {
        $subjMap[$r['name']] = (int)$r['id'];
    }

    // ========== QUESTIONS ==========
    $questions = [];

    // --- ENGLISH LANGUAGE ---
    $eng = $subjMap['English Language'];
    $questions[] = [$eng, 'Choose the correct spelling:', 'Accommodate', 'Acommodate', 'Accomodate', 'Acomodate', 'A', 'Accommodate has double c and double m.'];
    $questions[] = [$eng, 'The word "ubiquitous" means:', 'Rare', 'Present everywhere', 'Hidden', 'Powerful', 'B', 'Ubiquitous means found everywhere.'];
    $questions[] = [$eng, 'Identify the figure of speech: "The wind whispered through the trees."', 'Simile', 'Metaphor', 'Personification', 'Hyperbole', 'C', 'Wind is given human quality of whispering.'];
    $questions[] = [$eng, 'She ___ to school every day.', 'go', 'goes', 'going', 'gone', 'B', 'Third person singular requires "goes".'];
    $questions[] = [$eng, 'Which is a synonym for "benevolent"?', 'Cruel', 'Generous', 'Stingy', 'Angry', 'B', 'Benevolent means well-meaning and kindly.'];
    $questions[] = [$eng, 'The antonym of "ancient" is:', 'Old', 'Modern', 'Historic', 'Aged', 'B', 'Ancient means very old; modern is the opposite.'];
    $questions[] = [$eng, '"The boy ran quickly." The word "quickly" is a(n):', 'Noun', 'Verb', 'Adjective', 'Adverb', 'D', 'Quickly modifies the verb "ran".'];
    $questions[] = [$eng, 'Choose the correct sentence:', 'He don\'t like it', 'He doesn\'t likes it', 'He doesn\'t like it', 'He don\'t likes it', 'C', 'Correct: does not (doesn\'t) + base verb.'];
    $questions[] = [$eng, 'A集体 of lions is called a:', 'Flock', 'Herd', 'Pride', 'Pack', 'C', 'A group of lions is a pride.'];
    $questions[] = [$eng, 'Which word is a preposition?', 'Because', 'Under', 'Quickly', 'Beautiful', 'B', 'Under is a preposition showing position.'];
    $questions[] = [$eng, 'The prefix "un-" in "unhappy" means:', 'Very', 'Not', 'Before', 'After', 'B', 'Un- means not.'];
    $questions[] = [$eng, '"I am going to the store." is in which tense?', 'Past', 'Present', 'Future', 'Present continuous', 'D', 'Am going = present continuous.'];
    $questions[] = [$eng, 'Which literary term means a recurring element?', 'Theme', 'Plot', 'Motif', 'Symbol', 'C', 'A motif is a recurring element.'];
    $questions[] = [$eng, 'The noun form of "strong" is:', 'Strongly', 'Strength', 'Strengthen', 'Stronger', 'B', 'Strength is the noun form.'];
    $questions[] = [$eng, 'Identify the conjunction: "She tried but she failed."', 'She', 'Tried', 'But', 'Failed', 'C', 'But joins two clauses.'];
    $questions[] = [$eng, 'A word that sounds like its meaning is called:', 'Onomatopoeia', 'Oxymoron', 'Alliteration', 'Assonance', 'A', 'Onomatopoeia: words that sound like their meaning.'];
    $questions[] = [$eng, 'Which is correct?', 'Its a good day', 'It\'s a good day', 'Its\' a good day', 'Its\'s a good day', 'B', 'It\'s = it is.'];
    $questions[] = [$eng, 'The opposite of "optimistic" is:', 'Happy', 'Pessimistic', 'Realistic', 'Idealistic', 'B', 'Pessimistic is the opposite of optimistic.'];
    $questions[] = [$eng, 'Which is a compound sentence?', 'I slept', 'I ate and I slept', 'Eating quickly', 'The sleeping cat', 'B', 'Two independent clauses joined by "and".'];
    $questions[] = [$eng, '"She sings beautifully." The word "beautifully" modifies:', 'She', 'Sings', 'The sentence', 'None', 'B', 'Adverb modifying verb "sings".'];
    $questions[] = [$eng, 'Choose the correct plural: "child"', 'Childs', 'Childes', 'Children', 'Childrens', 'C', 'Irregular plural: children.'];
    $questions[] = [$eng, 'What is a sonnet?', 'A 14-line poem', 'A 10-line poem', 'A story', 'A play', 'A', 'A sonnet has 14 lines.'];
    $questions[] = [$eng, 'The main character in a story is the:', 'Antagonist', 'Protagonist', 'Narrator', 'Author', 'B', 'Protagonist is the main character.'];
    $questions[] = [$eng, 'Which punctuation mark ends a question?', '.', '!', '?', ',', 'C', 'Question mark ends a question.'];
    $questions[] = [$eng, '"Break a leg" is an example of:', 'Simile', 'Metaphor', 'Idiom', 'Proverb', 'C', 'It\'s an idiomatic expression.'];
    $questions[] = [$eng, 'Which word is an adjective?', 'Quickly', 'Calculate', 'Happiness', 'Beautiful', 'D', 'Beautiful describes a noun.'];
    $questions[] = [$eng, 'The structure of a story is its:', 'Plot', 'Theme', 'Character', 'Setting', 'A', 'Plot is the sequence of events.'];
    $questions[] = [$eng, 'Oral literature is transmitted through:', 'Writing', 'Speech', 'Printing', 'Recording', 'B', 'Oral means spoken.'];
    $questions[] = [$eng, 'How many vowel sounds in English?', '5', '12', '20', '26', 'C', 'English has about 20 vowel sounds.'];
    $questions[] = [$eng, 'Which sentence is imperative?', 'I like tea', 'Do you like tea?', 'Bring me tea', 'Tea is good', 'C', 'Imperative gives a command.'];

    // --- MATHEMATICS ---
    $math = $subjMap['Mathematics'];
    $questions[] = [$math, 'What is 25% of 200?', '25', '40', '50', '75', 'C', '200 × 25/100 = 50'];
    $questions[] = [$math, 'Simplify: 3(x + 2) - 2(x - 1)', 'x + 4', 'x + 8', '5x + 4', 'x - 4', 'B', '3x+6-2x+2 = x+8'];
    $questions[] = [$math, 'What is the square root of 144?', '10', '11', '12', '14', 'C', '12 × 12 = 144'];
    $questions[] = [$math, 'If 2x + 5 = 15, what is x?', '3', '5', '7', '10', 'B', '2x = 10, x = 5'];
    $questions[] = [$math, 'What is the area of a circle with radius 7cm? (π = 22/7)', '144 cm²', '154 cm²', '164 cm²', '174 cm²', 'B', 'πr² = 22/7 × 7 × 7 = 154'];
    $questions[] = [$math, 'Convert 0.75 to a fraction in lowest terms:', '1/2', '2/3', '3/4', '4/5', 'C', '0.75 = 75/100 = 3/4'];
    $questions[] = [$math, 'What is the LCM of 6 and 8?', '14', '16', '24', '48', 'C', 'LCM(6,8) = 24'];
    $questions[] = [$math, 'Simplify: 2³ × 2⁴', '2⁷', '2¹²', '4⁷', '8¹²', 'A', '2³⁺⁴ = 2⁷'];
    $questions[] = [$math, 'What is the mean of 2, 5, 8, 11, 14?', '6', '7', '8', '9', 'C', 'Sum=40, n=5, mean=8'];
    $questions[] = [$math, 'A triangle with all sides equal is called:', 'Isosceles', 'Scalene', 'Equilateral', 'Right', 'C', 'Equilateral has all sides equal.'];
    $questions[] = [$math, 'What is the value of sin 90°?', '0', '1/2', '1', 'Undefined', 'C', 'sin 90° = 1'];
    $questions[] = [$math, 'Solve: 5! (5 factorial)', '25', '60', '120', '125', 'C', '5×4×3×2×1 = 120'];
    $questions[] = [$math, 'What is the perimeter of a rectangle 8cm by 5cm?', '26 cm', '40 cm', '13 cm', '20 cm', 'A', '2(8+5) = 26 cm'];
    $questions[] = [$math, 'If y = 3x - 2, what is y when x = 4?', '8', '10', '12', '14', 'B', 'y = 3(4)-2 = 10'];
    $questions[] = [$math, 'Which is a prime number?', '15', '21', '23', '27', 'C', '23 has only factors 1 and 23.'];
    $questions[] = [$math, 'What is the volume of a cube of side 3cm?', '9 cm³', '18 cm³', '27 cm³', '36 cm³', 'C', '3×3×3 = 27 cm³'];
    $questions[] = [$math, 'Convert 101₂ to decimal:', '3', '4', '5', '6', 'C', '1×4 + 0×2 + 1×1 = 5'];
    $questions[] = [$math, 'What is ⅗ + ¼?', '⅘', '⁷⁄₂₀', '¹⁷⁄₂₀', '⅞', 'C', '12/20 + 5/20 = 17/20'];
    $questions[] = [$math, 'The sum of angles in a triangle is:', '90°', '180°', '270°', '360°', 'B', 'All triangles sum to 180°.'];
    $questions[] = [$math, 'What is 15% of 60?', '6', '9', '12', '15', 'B', '60 × 15/100 = 9'];
    $questions[] = [$math, 'Simplify: √36 + √49', '13', '85', '7', '12', 'A', '6 + 7 = 13'];
    $questions[] = [$math, 'The reciprocal of ⅔ is:', '2/3', '4/6', '3/2', '6/4', 'C', 'Reciprocal of a/b is b/a.'];
    $questions[] = [$math, 'What type of angle is 120°?', 'Acute', 'Right', 'Obtuse', 'Reflex', 'C', 'Obtuse: between 90° and 180°.'];
    $questions[] = [$math, 'If x² = 49, x could be:', '6', '7', '8', '9', 'B', '7² = 49'];
    $questions[] = [$math, 'How many faces does a cube have?', '4', '6', '8', '12', 'B', 'A cube has 6 faces.'];
    $questions[] = [$math, 'What is 10⁰?', '0', '1', '10', '100', 'B', 'Any number to power 0 = 1.'];
    $questions[] = [$math, 'Which fraction is largest?', '1/2', '2/3', '3/4', '5/8', 'C', '3/4 = 0.75 is largest.'];
    $questions[] = [$math, 'The product of -3 and -4 is:', '-12', '-7', '7', '12', 'D', 'Negative × negative = positive.'];
    $questions[] = [$math, 'What is the mode of 2,3,3,5,7,7,7?', '2', '3', '5', '7', 'D', '7 appears most frequently.'];
    $questions[] = [$math, 'Evaluate: 3 + 6 ÷ 3 × 2', '6', '7', '8', '12', 'B', '6÷3=2, 2×2=4, 3+4=7 (BODMAS)'];

    // --- PHYSICS ---
    $phy = $subjMap['Physics'];
    $questions[] = [$phy, 'What is the SI unit of force?', 'Newton', 'Joule', 'Watt', 'Pascal', 'A', 'Force is measured in Newtons.'];
    $questions[] = [$phy, 'Speed equals:', 'Distance × time', 'Distance / time', 'Time / distance', 'Acceleration × time', 'B', 'Speed = distance/time.'];
    $questions[] = [$phy, 'What is the acceleration due to gravity?', '8.8 m/s²', '9.8 m/s²', '10.8 m/s²', '11.8 m/s²', 'B', 'g ≈ 9.8 m/s² on Earth.'];
    $questions[] = [$phy, 'Energy possessed by a moving object is:', 'Potential', 'Chemical', 'Kinetic', 'Thermal', 'C', 'Kinetic energy = motion energy.'];
    $questions[] = [$phy, 'The unit of power is:', 'Joule', 'Newton', 'Watt', 'Volt', 'C', 'Power is measured in Watts.'];
    $questions[] = [$phy, 'Sound travels fastest in:', 'Air', 'Water', 'Vacuum', 'Solid', 'D', 'Sound travels fastest in solids.'];
    $questions[] = [$phy, 'What is the boiling point of water at sea level?', '90°C', '100°C', '110°C', '120°C', 'B', 'Water boils at 100°C at sea level.'];
    $questions[] = [$phy, 'The instrument used to measure current is:', 'Voltmeter', 'Ammeter', 'Thermometer', 'Barometer', 'B', 'Ammeter measures current.'];
    $questions[] = [$phy, 'Which type of mirror is used in cars?', 'Plane', 'Convex', 'Concave', 'Spherical', 'B', 'Convex mirrors give wider field of view.'];
    $questions[] = [$phy, 'Ohm\'s law relates:', 'Current and voltage', 'Force and mass', 'Energy and power', 'Speed and distance', 'A', 'V = IR'];
    $questions[] = [$phy, 'The unit of frequency is:', 'Hertz', 'Newton', 'Joule', 'Watt', 'A', 'Frequency is measured in Hertz (Hz).'];
    $questions[] = [$phy, 'Which is a renewable energy source?', 'Coal', 'Oil', 'Solar', 'Natural gas', 'C', 'Solar energy is renewable.'];
    $questions[] = [$phy, 'A man lifts 10kg mass 2m high. Work done? (g=10)', '20 J', '100 J', '200 J', '2000 J', 'C', 'W = mgh = 10×10×2 = 200 J'];
    $questions[] = [$phy, 'The process by which heat travels through a vacuum is:', 'Conduction', 'Convection', 'Radiation', 'Evaporation', 'C', 'Radiation does not require a medium.'];
    $questions[] = [$phy, 'What happens to pressure as depth increases in a fluid?', 'Decreases', 'Stays same', 'Increases', 'Becomes zero', 'C', 'Pressure increases with depth.'];
    $questions[] = [$phy, 'Which color has the longest wavelength?', 'Blue', 'Green', 'Red', 'Violet', 'C', 'Red has the longest visible wavelength.'];
    $questions[] = [$phy, 'The law of inertia is also known as:', 'Newton\'s 1st Law', 'Newton\'s 2nd Law', 'Newton\'s 3rd Law', 'Law of Gravitation', 'A', 'Newton\'s first law = law of inertia.'];
    $questions[] = [$phy, 'What is the unit of electric charge?', 'Ampere', 'Volt', 'Coulomb', 'Ohm', 'C', 'Charge is measured in Coulombs.'];
    $questions[] = [$phy, 'A real image is always:', 'Upright', 'Inverted', 'Virtual', 'Magnified', 'B', 'Real images are inverted.'];
    $questions[] = [$phy, 'Which wave requires a medium?', 'Light', 'Radio', 'Sound', 'X-ray', 'C', 'Sound is mechanical, needs a medium.'];
    $questions[] = [$phy, 'The S.I unit of work is:', 'Newton', 'Joule', 'Watt', 'Pascal', 'B', 'Work is measured in Joules.'];
    $questions[] = [$phy, 'Efficiency is always:', 'Less than 100%', 'Equal to 100%', 'More than 100%', 'Zero', 'A', 'Due to energy losses, efficiency < 100%.'];
    $questions[] = [$phy, 'What device converts electrical to mechanical energy?', 'Generator', 'Motor', 'Transformer', 'Dynamo', 'B', 'Motor converts electrical to mechanical.'];
    $questions[] = [$phy, 'The melting point of ice is:', '-4°C', '0°C', '4°C', '100°C', 'B', 'Ice melts at 0°C.'];
    $questions[] = [$phy, 'Which scientist proposed the wave theory of light?', 'Newton', 'Einstein', 'Huygens', 'Planck', 'C', 'Huygens proposed wave theory.'];
    $questions[] = [$phy, 'The center of gravity of a uniform rod is at its:', 'Midpoint', 'End', 'One-third', 'Quarter', 'A', 'For uniform rod, CG is at midpoint.'];
    $questions[] = [$phy, 'Hooke\'s law relates to:', 'Gravity', 'Elasticity', 'Friction', 'Pressure', 'B', 'Hooke\'s law: F = kx (spring force).'];
    $questions[] = [$phy, 'What is the refractive index of air?', '0', '1', '1.5', '2', 'B', 'Refractive index of air ≈ 1.'];
    $questions[] = [$phy, 'The cell of a simple circuit provides:', 'Current', 'Resistance', 'Voltage', 'Capacitance', 'C', 'Cell provides voltage (EMF).'];
    $questions[] = [$phy, 'Which property of a sound wave determines loudness?', 'Frequency', 'Amplitude', 'Wavelength', 'Speed', 'B', 'Amplitude determines loudness.'];

    // --- CHEMISTRY ---
    $chem = $subjMap['Chemistry'];
    $questions[] = [$chem, 'What is the chemical symbol for gold?', 'Go', 'Gd', 'Au', 'Ag', 'C', 'Gold = Au (from Latin "aurum").'];
    $questions[] = [$chem, 'The atomic number of carbon is:', '4', '6', '8', '12', 'B', 'Carbon has atomic number 6.'];
    $questions[] = [$chem, 'Water is composed of:', 'H + O', 'H₂ + O', 'H₂O', 'HO₂', 'C', 'Water = H₂O.'];
    $questions[] = [$chem, 'Which gas is most abundant in the atmosphere?', 'Oxygen', 'Carbon dioxide', 'Nitrogen', 'Argon', 'C', 'Nitrogen is ~78% of air.'];
    $questions[] = [$chem, 'What is the pH of a neutral solution?', '0', '3', '7', '14', 'C', 'Neutral pH = 7.'];
    $questions[] = [$chem, 'Which element is a halogen?', 'Sodium', 'Chlorine', 'Calcium', 'Iron', 'B', 'Chlorine is a halogen (Group 17).'];
    $questions[] = [$chem, 'The process of solid turning directly to gas is:', 'Melting', 'Evaporation', 'Sublimation', 'Condensation', 'C', 'Solid to gas = sublimation.'];
    $questions[] = [$chem, 'What is the molar mass of water?', '16 g/mol', '17 g/mol', '18 g/mol', '20 g/mol', 'C', 'H₂O = 2×1 + 16 = 18 g/mol.'];
    $questions[] = [$chem, 'Which acid is found in lemon?', 'Sulfuric acid', 'Citric acid', 'Hydrochloric acid', 'Nitric acid', 'B', 'Lemons contain citric acid.'];
    $questions[] = [$chem, 'The smallest particle of an element is:', 'Atom', 'Molecule', 'Electron', 'Proton', 'A', 'Atoms are the basic unit of elements.'];
    $questions[] = [$chem, 'What type of bond is NaCl?', 'Covalent', 'Ionic', 'Metallic', 'Hydrogen', 'B', 'NaCl is an ionic bond.'];
    $questions[] = [$chem, 'Which gas is produced during photosynthesis?', 'CO₂', 'O₂', 'N₂', 'H₂', 'B', 'Plants release oxygen during photosynthesis.'];
    $questions[] = [$chem, 'The periodic table is arranged by:', 'Mass', 'Atomic number', 'Density', 'Melting point', 'B', 'Arranged by increasing atomic number.'];
    $questions[] = [$chem, 'What is rust?', 'FeO', 'Fe₂O₃', 'Fe₃O₄', 'Fe₂O₃·xH₂O', 'D', 'Rust is hydrated iron(III) oxide.'];
    $questions[] = [$chem, 'A catalyst does what?', 'Starts a reaction', 'Slows a reaction', 'Speeds up a reaction', 'Stops a reaction', 'C', 'Catalyst speeds up reaction rate.'];
    $questions[] = [$chem, 'Which fuel is a fossil fuel?', 'Ethanol', 'Hydrogen', 'Coal', 'Wood', 'C', 'Coal is a fossil fuel.'];
    $questions[] = [$chem, 'The color of litmus in acid is:', 'Blue', 'Red', 'Green', 'Purple', 'B', 'Acid turns litmus red.'];
    $questions[] = [$chem, 'What is Avogadro\'s number?', '6.02 × 10²²', '6.02 × 10²³', '6.02 × 10²⁴', '6.02 × 10²¹', 'B', 'Avogadro\'s number = 6.02 × 10²³.'];
    $questions[] = [$chem, 'Which is a noble gas?', 'Nitrogen', 'Oxygen', 'Helium', 'Hydrogen', 'C', 'Helium is a noble gas (Group 18).'];
    $questions[] = [$chem, 'The formula for sulfuric acid is:', 'HCl', 'HNO₃', 'H₂SO₄', 'H₂CO₃', 'C', 'Sulfuric acid = H₂SO₄.'];
    $questions[] = [$chem, 'Isotopes have the same number of:', 'Neutrons', 'Protons', 'Electrons', 'Nucleons', 'B', 'Isotopes have same number of protons.'];
    $questions[] = [$chem, 'Which process separates mixtures by boiling point?', 'Filtration', 'Distillation', 'Chromatography', 'Decantation', 'B', 'Distillation separates by boiling point.'];
    $questions[] = [$chem, 'What is the oxidation number of oxygen in H₂O?', '0', '-1', '-2', '+2', 'C', 'Oxygen typically has -2 oxidation state.'];
    $questions[] = [$chem, 'Which element is liquid at room temperature?', 'Silver', 'Mercury', 'Lead', 'Zinc', 'B', 'Mercury is liquid at room temperature.'];
    $questions[] = [$chem, 'The chemical name for table salt is:', 'Sodium carbonate', 'Sodium chloride', 'Sodium sulfate', 'Sodium nitrate', 'B', 'Table salt = NaCl = sodium chloride.'];
    $questions[] = [$chem, 'What is the empirical formula of glucose?', 'C₆H₁₂O₆', 'CH₂O', 'C₂H₄O₂', 'C₃H₆O₃', 'B', 'Glucose empirical formula = CH₂O.'];
    $questions[] = [$chem, 'A solution with pH 2 is:', 'Strongly acidic', 'Weakly acidic', 'Neutral', 'Strongly basic', 'A', 'pH 2 is strongly acidic.'];
    $questions[] = [$chem, 'Which gas is used in fire extinguishers?', 'Oxygen', 'Nitrogen', 'CO₂', 'Hydrogen', 'C', 'CO₂ is used in fire extinguishers.'];
    $questions[] = [$chem, 'What is the mass number of an atom with 6 protons, 6 neutrons?', '6', '8', '12', '18', 'C', 'Mass number = 6 + 6 = 12.'];
    $questions[] = [$chem, 'Bases turn litmus paper:', 'Red', 'Blue', 'Green', 'Yellow', 'B', 'Bases turn litmus blue.'];

    // --- BIOLOGY ---
    $bio = $subjMap['Biology'];
    $questions[] = [$bio, 'The basic unit of life is:', 'Atom', 'Cell', 'Tissue', 'Organ', 'B', 'The cell is the basic unit of life.'];
    $questions[] = [$bio, 'Which organ pumps blood?', 'Lungs', 'Brain', 'Heart', 'Liver', 'C', 'The heart pumps blood.'];
    $questions[] = [$bio, 'Plants make food through:', 'Respiration', 'Photosynthesis', 'Digestion', 'Fermentation', 'B', 'Photosynthesis converts light to chemical energy.'];
    $questions[] = [$bio, 'How many bones are in the adult human body?', '106', '206', '306', '406', 'B', 'Adult humans have 206 bones.'];
    $questions[] = [$bio, 'The largest organ in the human body is:', 'Liver', 'Brain', 'Skin', 'Lungs', 'C', 'The skin is the largest organ.'];
    $questions[] = [$bio, 'What type of organism is bacteria?', 'Eukaryote', 'Prokaryote', 'Virus', 'Fungus', 'B', 'Bacteria are prokaryotes (no nucleus).'];
    $questions[] = [$bio, 'Which vitamin is produced when skin is exposed to sunlight?', 'A', 'B', 'C', 'D', 'D', 'Sunlight helps produce vitamin D.'];
    $questions[] = [$bio, 'The powerhouse of the cell is the:', 'Nucleus', 'Ribosome', 'Mitochondria', 'Golgi body', 'C', 'Mitochondria produce ATP (energy).'];
    $questions[] = [$bio, 'Which blood type is the universal donor?', 'A', 'B', 'AB', 'O', 'D', 'O negative is universal donor.'];
    $questions[] = [$bio, 'What is the scientific name for humans?', 'Homo erectus', 'Homo sapiens', 'Homo habilis', 'Homo neanderthalensis', 'B', 'Homo sapiens = wise man.'];
    $questions[] = [$bio, 'Which part of the plant absorbs water?', 'Leaves', 'Stem', 'Roots', 'Flowers', 'C', 'Roots absorb water and minerals.'];
    $questions[] = [$bio, 'The sun is the primary source of ___ on Earth.', 'Light only', 'Energy', 'Water', 'Air', 'B', 'Sun is the primary energy source.'];
    $questions[] = [$bio, 'Which disease is caused by a virus?', 'Malaria', 'Typhoid', 'HIV/AIDS', 'Cholera', 'C', 'HIV is a viral infection.'];
    $questions[] = [$bio, 'The process by which organisms evolve over time is:', 'Natural selection', 'Artificial selection', 'Mutation', 'Adaptation', 'A', 'Natural selection drives evolution.'];
    $questions[] = [$bio, 'What is the function of red blood cells?', 'Fight infection', 'Carry oxygen', 'Clot blood', 'Produce antibodies', 'B', 'Red blood cells transport oxygen.'];
    $questions[] = [$bio, 'A group of the same species in an area is a:', 'Community', 'Ecosystem', 'Population', 'Biome', 'C', 'Population = same species in one area.'];
    $questions[] = [$bio, 'Which hormone regulates blood sugar?', 'Adrenaline', 'Insulin', 'Estrogen', 'Testosterone', 'B', 'Insulin regulates blood glucose.'];
    $questions[] = [$bio, 'The human chromosome number is:', '23', '46', '48', '24', 'B', 'Humans have 46 chromosomes (23 pairs).'];
    $questions[] = [$bio, 'Which gas do plants absorb during photosynthesis?', 'O₂', 'CO₂', 'N₂', 'H₂', 'B', 'Plants absorb CO₂ for photosynthesis.'];
    $questions[] = [$bio, 'The part of the brain responsible for balance is:', 'Cerebrum', 'Cerebellum', 'Medulla', 'Hypothalamus', 'B', 'Cerebellum controls balance and coordination.'];
    $questions[] = [$bio, 'Which is NOT a type of blood vessel?', 'Artery', 'Vein', 'Capillary', 'Nephron', 'D', 'Nephron is in the kidney.'];
    $questions[] = [$bio, 'Fungi are:', 'Autotrophs', 'Heterotrophs', 'Producers', 'Photosynthetic', 'B', 'Fungi are heterotrophs (decomposers).'];
    $questions[] = [$bio, 'The enzyme in saliva that breaks down starch is:', 'Pepsin', 'Amylase', 'Lipase', 'Trypsin', 'B', 'Salivary amylase breaks down starch.'];
    $questions[] = [$bio, 'What is the function of the kidney?', 'Digest food', 'Filter blood', 'Pump blood', 'Store bile', 'B', 'Kidneys filter waste from blood.'];
    $questions[] = [$bio, 'Which scientist developed the three-domain system?', 'Darwin', 'Linnaeus', 'Woese', 'Mendel', 'C', 'Carl Woese proposed three domains.'];
    $questions[] = [$bio, 'A homozygous dominant genotype is written as:', 'AA', 'Aa', 'aa', 'AB', 'A', 'Homozygous dominant = AA.'];
    $questions[] = [$bio, 'Which vitamin deficiency causes scurvy?', 'Vitamin A', 'Vitamin B', 'Vitamin C', 'Vitamin D', 'C', 'Vitamin C deficiency causes scurvy.'];
    $questions[] = [$bio, 'The waxy layer on plant leaves is the:', 'Epidermis', 'Cuticle', 'Stomata', 'Mesophyll', 'B', 'Cuticle is the waxy protective layer.'];
    $questions[] = [$bio, 'Organisms that make their own food are called:', 'Consumers', 'Decomposers', 'Producers', 'Herbivores', 'C', 'Producers (autotrophs) make their own food.'];
    $questions[] = [$bio, 'What type of reproduction involves two parents?', 'Asexual', 'Sexual', 'Budding', 'Fission', 'B', 'Sexual reproduction requires two parents.'];

    // --- AGRICULTURAL SCIENCE ---
    $agr = $subjMap['Agricultural Science'];
    $questions[] = [$agr, 'The practice of growing crops for food is called:', 'Horticulture', 'Crop production', 'Animal husbandry', 'Fishery', 'B', 'Crop production = growing crops.'];
    $questions[] = [$agr, 'Which is a cereal crop?', 'Beans', 'Maize', 'Cassava', 'Yam', 'B', 'Maize is a cereal grain.'];
    $questions[] = [$agr, 'The rearing of fish is known as:', 'Agriculture', 'Apiculture', 'Aquaculture', 'Sericulture', 'C', 'Aquaculture is fish farming.'];
    $questions[] = [$agr, 'What is the primary source of farm power in Nigeria?', 'Tractor', 'Animal', 'Manual labor', 'Electricity', 'C', 'Most Nigerian farms rely on manual labor.'];
    $questions[] = [$agr, 'Soil containing equal parts sand, silt, clay is:', 'Sandy', 'Clayey', 'Loamy', 'Silty', 'C', 'Loam has balanced proportions.'];
    $questions[] = [$agr, 'Which vitamin is abundant in citrus fruits?', 'A', 'B', 'C', 'D', 'C', 'Citrus fruits are rich in Vitamin C.'];
    $questions[] = [$agr, 'The process of removing water from crops is:', 'Threshing', 'Winnowing', 'Drying', 'Storage', 'C', 'Drying removes moisture from crops.'];
    $questions[] = [$agr, 'Which fertilizer component promotes leaf growth?', 'Nitrogen', 'Phosphorus', 'Potassium', 'Calcium', 'A', 'Nitrogen promotes vegetative growth.'];
    $questions[] = [$agr, 'The indigenous breed of cattle in Nigeria is:', 'Holstein', 'White Fulani', 'Jersey', 'Hereford', 'B', 'White Fulani is a Nigerian cattle breed.'];
    $questions[] = [$agr, 'What is the function of a harrow?', 'Plant seeds', 'Break soil clods', 'Harvest crops', 'Irrigate', 'B', 'Harrow breaks up soil clods.'];
    $questions[] = [$agr, 'Which is a leguminous crop?', 'Rice', 'Groundnut', 'Millet', 'Sorghum', 'B', 'Groundnut is a legume.'];
    $questions[] = [$agr, 'The pH range for optimal crop growth is:', '1-3', '4-5', '6-7.5', '8-10', 'C', 'Most crops grow best at pH 6-7.5.'];
    $questions[] = [$agr, 'Bush burning is a type of:', 'Irrigation', 'Land clearing', 'Harvesting', 'Planting', 'B', 'Bush burning clears land for farming.'];
    $questions[] = [$agr, 'The study of soil is called:', 'Biology', 'Pedology', 'Geology', 'Ecology', 'B', 'Pedology is the study of soil.'];
    $questions[] = [$agr, 'Which animal is a ruminant?', 'Pig', 'Goat', 'Rabbit', 'Dog', 'B', 'Goats are ruminants with four stomachs.'];
    $questions[] = [$agr, 'The young of a cow is called:', 'Lamb', 'Kid', 'Calf', 'Foal', 'C', 'A young cow is a calf.'];
    $questions[] = [$agr, 'What is the main cause of soil erosion?', 'Fertilizer', 'Water runoff', 'Planting', 'Mulching', 'B', 'Water runoff causes soil erosion.'];
    $questions[] = [$agr, 'Which method preserves fish by smoking?', 'Canning', 'Freezing', 'Smoking', 'Salting', 'C', 'Smoking preserves and flavors fish.'];
    $questions[] = [$agr, 'The bee-keeping industry is called:', 'Agriculture', 'Sericulture', 'Apiculture', 'Aquaculture', 'C', 'Apiculture = bee keeping.'];
    $questions[] = [$agr, 'Organic manure is derived from:', 'Chemicals', 'Plants and animals', 'Minerals', 'Plastic', 'B', 'Organic manure from living matter.'];
    $questions[] = [$agr, 'Which crop is used for textile fibers?', 'Maize', 'Cotton', 'Cassava', 'Yam', 'B', 'Cotton produces textile fibers.'];
    $questions[] = [$agr, 'The process of grafting is a form of:', 'Asexual reproduction', 'Sexual reproduction', 'Pollination', 'Fertilization', 'A', 'Grafting is vegetative propagation.'];
    $questions[] = [$agr, 'What is the term for animals eating grass?', 'Carnivorous', 'Herbivorous', 'Omnivorous', 'Insectivorous', 'B', 'Herbivores eat grass/plants.'];
    $questions[] = [$agr, 'Which disease affects poultry?', 'Trypanosomiasis', 'Newcastle disease', 'Anthrax', 'Foot and mouth', 'B', 'Newcastle disease affects chickens.'];
    $questions[] = [$agr, 'The hormone that promotes fruit ripening is:', 'Auxin', 'Gibberellin', 'Ethylene', 'Cytokinin', 'C', 'Ethylene promotes fruit ripening.'];
    $questions[] = [$agr, 'A young female cow that has not calved is a:', 'Heifer', 'Cow', 'Calf', 'Bullock', 'A', 'Heifer = young female cow.'];
    $questions[] = [$agr, 'Which farming practice prevents soil exhaustion?', 'Monocropping', 'Crop rotation', 'Bush burning', 'Overgrazing', 'B', 'Crop rotation maintains soil fertility.'];
    $questions[] = [$agr, 'The food storage organ of yam is a:', 'Root', 'Stem tuber', 'Bulb', 'Rhizome', 'B', 'Yam is a stem tuber.'];
    $questions[] = [$agr, 'What is the main component of soil organic matter?', 'Sand', 'Humus', 'Clay', 'Silt', 'B', 'Humus is decomposed organic matter.'];
    $questions[] = [$agr, 'The process of injecting vaccine into animals is:', 'Drenching', 'Vaccination', 'Dipping', 'Spraying', 'B', 'Vaccination protects against diseases.'];

    // --- COMPUTER STUDIES ---
    $comp = $subjMap['Computer Studies'];
    $questions[] = [$comp, 'What does CPU stand for?', 'Central Process Unit', 'Central Processing Unit', 'Computer Personal Unit', 'Central Program Unit', 'B', 'CPU = Central Processing Unit.'];
    $questions[] = [$comp, 'Which device is used for long-term storage?', 'RAM', 'CPU', 'Hard drive', 'Monitor', 'C', 'Hard drive stores data permanently.'];
    $questions[] = [$comp, '1 Gigabyte equals:', '1024 Bytes', '1024 KB', '1024 MB', '1024 TB', 'C', '1 GB = 1024 MB.'];
    $questions[] = [$comp, 'Which is a programming language?', 'HTML', 'Python', 'HTTP', 'FTP', 'B', 'Python is a programming language.'];
    $questions[] = [$comp, 'The brain of the computer is the:', 'Monitor', 'Keyboard', 'CPU', 'Mouse', 'C', 'CPU is the computer\'s brain.'];
    $questions[] = [$comp, 'What does "www" stand for?', 'World Wide Web', 'World Web Wide', 'Wide World Web', 'Web World Wide', 'A', 'WWW = World Wide Web.'];
    $questions[] = [$comp, 'Which is an input device?', 'Monitor', 'Printer', 'Keyboard', 'Speaker', 'C', 'Keyboard is an input device.'];
    $questions[] = [$comp, 'The device that connects a computer to the internet is a:', 'Router', 'Monitor', 'Keyboard', 'Printer', 'A', 'Router connects to the internet.'];
    $questions[] = [$comp, 'Which software is an operating system?', 'Microsoft Word', 'Windows 10', 'Photoshop', 'Chrome', 'B', 'Windows 10 is an OS.'];
    $questions[] = [$comp, 'What is the full meaning of USB?', 'Universal Serial Bus', 'Universal System Bus', 'United Serial Bus', 'Universal Series Bus', 'A', 'USB = Universal Serial Bus.'];
    $questions[] = [$comp, 'Which number system does a computer use?', 'Decimal', 'Binary', 'Octal', 'Hexadecimal', 'B', 'Computers use binary (0,1).'];
    $questions[] = [$comp, 'Which key is used to delete text to the left?', 'Delete', 'Backspace', 'Enter', 'Shift', 'B', 'Backspace deletes to the left.'];
    $questions[] = [$comp, 'A byte consists of how many bits?', '4', '6', '8', '16', 'C', '1 byte = 8 bits.'];
    $questions[] = [$comp, 'Which is a type of computer virus?', 'Trojan', 'Router', 'Modem', 'Browser', 'A', 'Trojan is a type of malware.'];
    $questions[] = [$comp, 'What is the function of a printer?', 'Input data', 'Process data', 'Output data', 'Store data', 'C', 'Printer produces hard copy output.'];
    $questions[] = [$comp, 'Which is not a web browser?', 'Chrome', 'Firefox', 'Windows', 'Safari', 'C', 'Windows is an OS, not a browser.'];
    $questions[] = [$comp, 'The language computers understand directly is:', 'Python', 'Java', 'Machine language', 'HTML', 'C', 'Machine language is binary.'];
    $questions[] = [$comp, 'Which device converts analog to digital signals?', 'Modem', 'Router', 'Switch', 'Hub', 'A', 'Modem modulates/demodulates signals.'];
    $questions[] = [$comp, 'What is an algorithm?', 'A computer', 'A step-by-step solution', 'A programming language', 'A type of data', 'B', 'Algorithm = step-by-step procedure.'];
    $questions[] = [$comp, 'Which component stores data temporarily?', 'Hard drive', 'SSD', 'RAM', 'ROM', 'C', 'RAM is temporary (volatile) memory.'];
    $questions[] = [$comp, 'The ".com" in a web address stands for:', 'Computer', 'Commercial', 'Company', 'Communication', 'B', '.com = commercial.'];
    $questions[] = [$comp, 'What is a database?', 'A collection of programs', 'An organized data collection', 'A type of computer', 'A network device', 'B', 'Database = organized data collection.'];
    $questions[] = [$comp, 'Which key combination copies text?', 'Ctrl+V', 'Ctrl+C', 'Ctrl+X', 'Ctrl+A', 'B', 'Ctrl+C copies selected text.'];
    $questions[] = [$comp, 'What does LAN stand for?', 'Large Area Network', 'Local Area Network', 'Long Area Network', 'Light Area Network', 'B', 'LAN = Local Area Network.'];
    $questions[] = [$comp, 'Which is a social media platform?', 'Windows', 'Facebook', 'Microsoft Word', 'Excel', 'B', 'Facebook is a social media platform.'];
    $questions[] = [$comp, 'The main circuit board of a computer is the:', 'CPU', 'RAM', 'Motherboard', 'Hard drive', 'C', 'Motherboard connects all components.'];
    $questions[] = [$comp, 'What is phishing?', 'A type of fish', 'An online scam', 'A programming language', 'A hardware device', 'B', 'Phishing is a cyber scam.'];
    $questions[] = [$comp, 'Which programming paradigm uses objects?', 'Functional', 'Procedural', 'Object-oriented', 'Declarative', 'C', 'OOP uses objects and classes.'];
    $questions[] = [$comp, 'What is the cloud?', 'A weather system', 'Internet-based storage', 'A type of OS', 'A hardware device', 'B', 'Cloud = internet-based services.'];
    $questions[] = [$comp, 'The first computer programmer was:', 'Charles Babbage', 'Ada Lovelace', 'Alan Turing', 'Bill Gates', 'B', 'Ada Lovelace is considered first programmer.'];

    // --- CIVIC EDUCATION ---
    $civ = $subjMap['Civic Education'];
    $questions[] = [$civ, 'The highest law of Nigeria is the:', 'Constitution', 'Decree', 'Edict', 'Bill', 'A', 'The Constitution is the supreme law.'];
    $questions[] = [$civ, 'How many local governments are in Nigeria?', '744', '754', '764', '774', 'D', 'Nigeria has 774 LGAs.'];
    $questions[] = [$civ, 'The three arms of government are:', 'Federal, State, Local', 'Executive, Legislature, Judiciary', 'Army, Navy, Airforce', 'Senate, Reps, Governor', 'B', 'The three arms: Executive, Legislature, Judiciary.'];
    $questions[] = [$civ, 'Who is the head of state in Nigeria?', 'Senate President', 'Chief Justice', 'President', 'Governor', 'C', 'The President is the head of state.'];
    $questions[] = [$civ, 'A citizen\'s duty is called:', 'Obligation', 'Right', 'Freedom', 'Privilege', 'A', 'Civic duties are obligations of citizens.'];
    $questions[] = [$civ, 'Which right allows citizens to vote?', 'Right to life', 'Freedom of speech', 'Right to vote', 'Right to education', 'C', 'Suffrage is the right to vote.'];
    $questions[] = [$civ, 'The NYSC is for graduates aged:', 'Below 18', '18-30', '30-40', 'Above 40', 'B', 'NYSC is for graduates under 30.'];
    $questions[] = [$civ, 'Federalism means:', 'One central government', 'Power shared between central and states', 'No government', 'Military rule', 'B', 'Federalism shares power between levels.'];
    $questions[] = [$civ, 'A person who belongs to a country is a:', 'Citizen', 'Foreigner', 'Resident', 'Visitor', 'A', 'A citizen legally belongs to a country.'];
    $questions[] = [$civ, 'Which is a fundamental human right?', 'Right to drive', 'Right to life', 'Right to party', 'Right to travel', 'B', 'Right to life is fundamental.'];
    $questions[] = [$civ, 'The Nigerian flag has how many colors?', '2', '3', '4', '5', 'A', 'Green-white-green = 2 colors.'];
    $questions[] = [$civ, 'Values are:', 'Standards of behavior', 'Money', 'Laws', 'Books', 'A', 'Values guide behavior and choices.'];
    $questions[] = [$civ, 'The independence day of Nigeria is:', 'May 29', 'October 1', 'June 12', 'January 1', 'B', 'Nigeria gained independence Oct 1, 1960.'];
    $questions[] = [$civ, 'Which is a form of democracy?', 'Monarchy', 'Dictatorship', 'Representative', 'Aristocracy', 'C', 'Representative democracy is common.'];
    $questions[] = [$civ, 'Corruption is a:', 'Social vice', 'Good practice', 'Legal activity', 'Virtue', 'A', 'Corruption is a social evil/vice.'];
    $questions[] = [$civ, 'The agency fighting corruption in Nigeria is:', 'NISER', 'EFCC', 'NITDA', 'NIMC', 'B', 'EFCC fights corruption in Nigeria.'];
    $questions[] = [$civ, 'Traffic lights: red means:', 'Go', 'Stop', 'Ready', 'Slow down', 'B', 'Red light means stop.'];
    $questions[] = [$civ, 'HIV/AIDS is most commonly transmitted through:', 'Air', 'Water', 'Unprotected sex', 'Food', 'C', 'HIV is mainly sexually transmitted.'];
    $questions[] = [$civ, 'The National Anthem promotes:', 'Hatred', 'Division', 'Unity', 'War', 'C', 'Anthem promotes patriotism and unity.'];
    $questions[] = [$civ, 'Which body conducts elections in Nigeria?', 'INEC', 'EFCC', 'NCC', 'NAICOM', 'A', 'INEC conducts elections in Nigeria.'];
    $questions[] = [$civ, 'Rule of law means:', 'The president is above the law', 'Everyone is equal before the law', 'Judges make all laws', 'No laws exist', 'B', 'Rule of law = equality before law.'];
    $questions[] = [$civ, 'The age for voting in Nigeria is:', '16', '17', '18', '21', 'C', 'Voting age in Nigeria is 18.'];
    $questions[] = [$civ, 'Culture refers to:', 'Way of life', 'Money', 'Laws', 'Politics', 'A', 'Culture is the way of life of a people.'];
    $questions[] = [$civ, 'Which is a negative behavior?', 'Honesty', 'Drug abuse', 'Hard work', 'Respect', 'B', 'Drug abuse is harmful behavior.'];
    $questions[] = [$civ, 'The legislature at federal level is:', 'National Assembly', 'State Assembly', 'House of Chiefs', 'Cabinet', 'A', 'National Assembly = Senate + House of Reps.'];
    $questions[] = [$civ, 'Human trafficking is:', 'Legal business', 'Modern slavery', 'Tourism', 'Transportation', 'B', 'Human trafficking is modern slavery.'];
    $questions[] = [$civ, 'Interpersonal relationship requires:', 'Communication', 'Isolation', 'Fighting', 'Jealousy', 'A', 'Good communication is key to relationships.'];
    $questions[] = [$civ, 'Self-reliance means:', 'Depending on others', 'Relying on oneself', 'Begging', 'Stealing', 'B', 'Self-reliance is independence.'];
    $questions[] = [$civ, 'Which constitution gave Nigeria full independence?', 'Lyttleton', 'Independence Constitution 1960', '1999 Constitution', 'Macpherson', 'B', 'The 1960 Constitution granted independence.'];
    $questions[] = [$civ, 'Tolerance means:', 'Fighting others', 'Respecting differences', 'Discrimination', 'Superiority', 'B', 'Tolerance is respect for diversity.'];

    // --- ECONOMICS ---
    $eco = $subjMap['Economics'];
    $questions[] = [$eco, 'Economics is the study of:', 'Money', 'Scarcity and choice', 'Politics', 'History', 'B', 'Economics studies scarcity and choice.'];
    $questions[] = [$eco, 'The law of demand states that price and quantity demanded are:', 'Directly related', 'Inversely related', 'Not related', 'Equal', 'B', 'Price rises, demand falls (inverse).'];
    $questions[] = [$eco, 'Which is a factor of production?', 'Money', 'Land', 'Price', 'Demand', 'B', 'Land, labor, capital, entrepreneurship.'];
    $questions[] = [$eco, 'When demand exceeds supply, price tends to:', 'Fall', 'Stay the same', 'Rise', 'Disappear', 'C', 'Excess demand pushes prices up.'];
    $questions[] = [$eco, 'The total value of goods and services in a country is:', 'GDP', 'GNP', 'NNP', 'PPP', 'A', 'GDP = Gross Domestic Product.'];
    $questions[] = [$eco, 'Inflation means:', 'Fall in prices', 'Rise in general price level', 'More jobs', 'Less money', 'B', 'Inflation is sustained price increase.'];
    $questions[] = [$eco, 'A market with one seller is a:', 'Monopoly', 'Oligopoly', 'Perfect competition', 'Duopoly', 'A', 'Monopoly = single seller.'];
    $questions[] = [$eco, 'The central bank of Nigeria is:', 'First Bank', 'CBN', 'UBA', 'Access Bank', 'B', 'CBN = Central Bank of Nigeria.'];
    $questions[] = [$eco, 'Tax on goods imported is called:', 'Income tax', 'Tariff', 'Value Added Tax', 'Excise duty', 'B', 'Tariff is tax on imports.'];
    $questions[] = [$eco, 'Opportunity cost is:', 'Total cost of production', 'Next best alternative forgone', 'Marginal cost', 'Fixed cost', 'B', 'Opportunity cost = forgone alternative.'];
    $questions[] = [$eco, 'Which is a direct tax?', 'VAT', 'Import duty', 'Income tax', 'Excise tax', 'C', 'Income tax is paid directly to government.'];
    $questions[] = [$eco, 'The reward for labor is:', 'Profit', 'Interest', 'Rent', 'Wages', 'D', 'Labor earns wages/salary.'];
    $questions[] = [$eco, 'Microeconomics studies:', 'Individual economic units', 'The whole economy', 'International trade', 'Money supply', 'A', 'Micro = individual units.'];
    $questions[] = [$eco, 'A budget deficit occurs when:', 'Revenue = Expenditure', 'Revenue > Expenditure', 'Revenue < Expenditure', 'No revenue', 'C', 'Deficit = spending more than revenue.'];
    $questions[] = [$eco, 'The rate at which CBN lends to banks is:', 'Interest rate', 'Discount rate', 'Monetary rate', 'Bank rate', 'B', 'Discount rate = CBN lending rate.'];
    $questions[] = [$eco, 'Demand-pull inflation is caused by:', 'Rising costs', 'Excess demand', 'Low supply', 'High taxes', 'B', 'Demand-pull = too much money chasing few goods.'];
    $questions[] = [$eco, 'Which is a public company?', 'Nike', 'NNPC', 'Adidas', 'Coca-Cola', 'B', 'NNPC is a government-owned corporation.'];
    $questions[] = [$eco, 'The function of money as a store of value means:', 'Used for loans', 'Used for saving', 'Used for exchange', 'Used for accounting', 'B', 'Money stores value for future use.'];
    $questions[] = [$eco, 'A production possibility curve shows:', 'Consumer preferences', 'Maximum output combinations', 'Demand patterns', 'Cost curves', 'B', 'PPC shows maximum production possibilities.'];
    $questions[] = [$eco, 'What is the most basic economic problem?', 'Inflation', 'Scarcity', 'Unemployment', 'Poverty', 'B', 'Scarcity is the fundamental economic problem.'];
    $questions[] = [$eco, 'Elasticity measures:', 'Consumer income', 'Responsiveness to price changes', 'Producer cost', 'Tax revenue', 'B', 'Price elasticity measures responsiveness.'];
    $questions[] = [$eco, 'An increase in minimum wage usually:', 'Increases demand', 'Decreases inflation', 'Increases purchasing power', 'Reduces unemployment', 'C', 'Higher minimum wage boosts purchasing power.'];
    $questions[] = [$eco, 'Trade between countries is called:', 'Domestic trade', 'International trade', 'Local trade', 'Retail trade', 'B', 'International trade crosses borders.'];
    $questions[] = [$eco, 'The stock exchange is a market for:', 'Currency', 'Shares and stocks', 'Bonds only', 'Commodities', 'B', 'Stock exchange trades securities.'];
    $questions[] = [$eco, 'A monopolist can:', 'Set any price', 'Set price above marginal cost', 'Ignore demand', 'Produce infinite output', 'B', 'Monopolist has market power, sets P>MC.'];
    $questions[] = [$eco, 'Which organization promotes global economic cooperation?', 'UNICEF', 'WHO', 'IMF', 'FAO', 'C', 'IMF promotes global economic stability.'];
    $questions[] = [$eco, 'Privatization means:', 'Government sells state enterprises', 'Government takes over businesses', 'Companies merge', 'New taxes', 'A', 'Privatization = transfer to private sector.'];
    $questions[] = [$eco, 'The multiplier effect relates to:', 'Changes in investment and income', 'Population growth', 'Price changes', 'Tax revenue', 'A', 'Multiplier = change in income from investment.'];
    $questions[] = [$eco, 'Comparative advantage explains:', 'Why countries trade', 'Why prices rise', 'Why jobs exist', 'Why taxes are paid', 'A', 'Comparative advantage drives trade between nations.'];
    $questions[] = [$eco, 'OPEC is an organization of:', 'Oil exporting countries', 'African nations', 'European countries', 'All UN members', 'A', 'OPEC = Organization of Petroleum Exporting Countries.'];

    // --- GOVERNMENT ---
    $gov = $subjMap['Government'];
    $questions[] = [$gov, 'Government is defined as:', 'The study of politics', 'The institution that governs a state', 'A political party', 'The civil service', 'B', 'Government exercises authority over a state.'];
    $questions[] = [$gov, 'Nigeria operates what system of government?', 'Unitary', 'Federal', 'Confederal', 'Parliamentary', 'B', 'Nigeria is a federation.'];
    $questions[] = [$gov, 'The head of the Senate in Nigeria is:', 'President', 'Chief Justice', 'Senate President', 'Speaker', 'C', 'Senate President leads the Senate.'];
    $questions[] = [$gov, 'A constitution is:', 'A set of laws', 'A political party', 'An election', 'A court', 'A', 'Constitution = basic laws of a country.'];
    $questions[] = [$gov, 'Which is a characteristic of democracy?', 'One-party rule', 'Free and fair elections', 'Military rule', 'Censorship', 'B', 'Free and fair elections are key to democracy.'];
    $questions[] = [$gov, 'The executive arm of government:', 'Makes laws', 'Interprets laws', 'Implements laws', 'Amends laws', 'C', 'Executive implements and enforces laws.'];
    $questions[] = [$gov, 'Political party\'s main function is to:', 'Make money', 'Win elections and govern', 'Fight wars', 'Run schools', 'B', 'Parties contest elections to form government.'];
    $questions[] = [$gov, 'A pressure group aims to:', 'Win elections', 'Influence government decisions', 'Form government', 'Collect taxes', 'B', 'Pressure groups influence policy.'];
    $questions[] = [$gov, 'Separation of powers means:', 'All powers to one person', 'Powers divided among three arms', 'No government', 'Military rule', 'B', 'Powers divided among Executive, Legislature, Judiciary.'];
    $questions[] = [$gov, 'Which is a function of the judiciary?', 'Make laws', 'Adjudicate disputes', 'Collect taxes', 'Declare war', 'B', 'Judiciary interprets laws and settles disputes.'];
    $questions[] = [$gov, 'Citizenship can be acquired by:', 'Birth only', 'Registration and naturalization', 'Purchase', 'Marriage', 'B', 'Citizenship by birth, registration, or naturalization.'];
    $questions[] = [$gov, 'Public opinion is:', 'Government policy', 'Views of the population', 'Media reports', 'Court judgments', 'B', 'Public opinion = collective views of citizens.'];
    $questions[] = [$gov, 'The civil service is:', 'Elected officials', 'Permanent government employees', 'Military personnel', 'Political appointees', 'B', 'Civil servants are permanent government employees.'];
    $questions[] = [$gov, 'A bicameral legislature has:', 'One house', 'Two houses', 'Three houses', 'No houses', 'B', 'Bicameral means two chambers.'];
    $questions[] = [$gov, 'The Nigerian police force is headed by:', 'Inspector General', 'Commissioner', 'Director', 'Superintendent', 'A', 'IGP heads the Nigeria Police Force.'];
    $questions[] = [$gov, 'The highest court in Nigeria is:', 'Court of Appeal', 'Federal High Court', 'Supreme Court', 'Sharia Court', 'C', 'Supreme Court is the apex court.'];
    $questions[] = [$gov, 'An election conducted to fill a vacant seat is:', 'Primary', 'Run-off', 'Bye-election', 'General election', 'C', 'Bye-election fills a vacant seat.'];
    $questions[] = [$gov, 'Nigeria became a republic in:', '1960', '1963', '1966', '1979', 'B', 'Nigeria became a republic in 1963.'];
    $questions[] = [$gov, 'Which is a feature of federalism?', 'Centralized power', 'Decentralization', 'Single government', 'No constitution', 'B', 'Federalism decentralizes power among regions.'];
    $questions[] = [$gov, 'The legislature at state level is:', 'National Assembly', 'State House of Assembly', 'Senate', 'House of Reps', 'B', 'State legislatures are Houses of Assembly.'];
    $questions[] = [$gov, 'A coup d\'etat means:', 'Democratic election', 'Military takeover', 'Peaceful transition', 'Judicial review', 'B', 'Coup = takeover by military force.'];
    $questions[] = [$gov, 'The Universal Declaration of Human Rights was in:', '1945', '1948', '1950', '1960', 'B', 'UDHR was adopted in 1948.'];
    $questions[] = [$gov, 'Checks and balances prevent:', 'Development', 'The abuse of power', 'Elections', 'Taxation', 'B', 'Checks and balances limit governmental power.'];
    $questions[] = [$gov, 'The colonial master of Nigeria was:', 'France', 'Britain', 'Spain', 'Portugal', 'B', 'Britain colonized Nigeria.'];
    $questions[] = [$gov, 'Nationalism in Nigeria was led by:', 'Lord Lugard', 'Nnamdi Azikiwe', 'Queen Elizabeth', 'Winston Churchill', 'B', 'Azikiwe was a leading nationalist.'];
    $questions[] = [$gov, 'The merging of Northern and Southern Nigeria was in:', '1900', '1914', '1922', '1945', 'B', 'Nigeria was amalgamated in 1914.'];
    $questions[] = [$gov, 'The annual budget is presented by:', 'Chief Justice', 'President', 'Senate President', 'Governor', 'B', 'President presents budget to National Assembly.'];
    $questions[] = [$gov, 'The Greenwood is:', 'A political party', 'The National Assembly building', 'The President\'s office', 'A court', 'C', 'The Greenwood is the President\'s office complex.'];
    $questions[] = [$gov, 'Voting by proxy means:', 'Voting in person', 'Voting through a representative', 'Online voting', 'Postal voting', 'B', 'Proxy voting is through an authorized person.'];
    $questions[] = [$gov, 'The media in democracy serves as:', 'Entertainment only', 'Watchdog of government', 'Government spokesperson', 'Tax collector', 'B', 'Media holds government accountable.'];

    // --- LITERATURE IN ENGLISH ---
    $lit = $subjMap['Literature in English'];
    $questions[] = [$lit, 'The author of "Things Fall Apart" is:', 'Chinua Achebe', 'Wole Soyinka', 'Ngugi wa Thiong\'o', 'Ben Okri', 'A', 'Achebe wrote Things Fall Apart (1958).'];
    $questions[] = [$lit, 'A poem of 14 lines is a:', 'Sonnet', 'Ode', 'Elegy', 'Ballad', 'A', 'A sonnet has 14 lines.'];
    $questions[] = [$lit, 'The character opposing the protagonist is the:', 'Hero', 'Antagonist', 'Narrator', 'Author', 'B', 'Antagonist opposes the protagonist.'];
    $questions[] = [$lit, 'Who wrote "The Lion and the Jewel"?', 'Chinua Achebe', 'Wole Soyinka', 'Femi Osofisan', 'Zulu Sofola', 'B', 'Soyinka wrote The Lion and the Jewel.'];
    $questions[] = [$lit, 'Drama is written to be:', 'Read silently', 'Performed on stage', 'Sung', 'Recited', 'B', 'Drama is meant for performance.'];
    $questions[] = [$lit, '"She sells sea shells by the sea shore" is alliteration. True or false?', 'True', 'False', 'Neither', 'Both', 'A', 'Repetition of initial /s/ sound.'];
    $questions[] = [$lit, 'The main idea of a literary work is the:', 'Plot', 'Theme', 'Style', 'Setting', 'B', 'Theme is the central idea.'];
    $questions[] = [$lit, 'Who wrote "The Great Gatsby"?', 'Ernest Hemingway', 'F. Scott Fitzgerald', 'Mark Twain', 'Charles Dickens', 'B', 'Fitzgerald wrote The Great Gatsby.'];
    $questions[] = [$lit, 'A character who remains unchanged is:', 'Dynamic', 'Static', 'Flat', 'Round', 'B', 'Static character does not change.'];
    $questions[] = [$lit, 'The time and place of a story is the:', 'Plot', 'Theme', 'Setting', 'Conflict', 'C', 'Setting = time and place.'];
    $questions[] = [$lit, 'The line "Hope is the thing with feathers" uses:', 'Simile', 'Metaphor', 'Hyperbole', 'Irony', 'B', 'Hope is metaphorically compared to a bird.'];
    $questions[] = [$lit, 'An elegy is a poem of:', 'Joy', 'Love', 'Mourning', 'Celebration', 'C', 'Elegy is a poem of lament/mourning.'];
    $questions[] = [$lit, 'Shakespeare wrote about how many plays?', '27', '37', '47', '57', 'B', 'Shakespeare wrote 37 plays.'];
    $questions[] = [$lit, 'A short moral story with animals is a:', 'Fable', 'Parable', 'Myth', 'Legend', 'A', 'Fables feature talking animals with morals.'];
    $questions[] = [$lit, 'The narrator knows all characters\' thoughts in:', 'First person', 'Third person limited', 'Omniscient narrator', 'Second person', 'C', 'Omniscient narrator knows everything.'];
    $questions[] = [$lit, '"The world is a stage" is what figurative device?', 'Simile', 'Metaphor', 'Personification', 'Irony', 'B', 'Metaphor compares world to a stage.'];
    $questions[] = [$lit, 'The author of "Weep Not, Child" is:', 'Chinua Achebe', 'Ngugi wa Thiong\'o', 'Wole Soyinka', 'Ayi Kwei Armah', 'B', 'Ngugi wrote Weep Not, Child.'];
    $questions[] = [$lit, 'In drama, a long speech by one character is a:', 'Dialogue', 'Soliloquy', 'Aside', 'Monologue', 'D', 'Monologue = long speech by one character.'];
    $questions[] = [$lit, 'The sequence of events in a story forms the:', 'Theme', 'Plot', 'Character', 'Setting', 'B', 'Plot = sequence of events.'];
    $questions[] = [$lit, 'Who wrote "The Old Man and the Sea"?', 'Mark Twain', 'Ernest Hemingway', 'William Faulkner', 'John Steinbeck', 'B', 'Hemingway wrote The Old Man and the Sea.'];
    $questions[] = [$lit, 'A group of lines in a poem is a:', 'Paragraph', 'Stanza', 'Verse', 'Canto', 'B', 'Stanza = group of lines in poetry.'];
    $questions[] = [$lit, 'Irony involves:', 'A direct statement', 'A contrast between appearance and reality', 'Exaggeration', 'Repetition', 'B', 'Irony is contrast between expectation and reality.'];
    $questions[] = [$lit, 'The mood of a literary work is the:', 'Feeling conveyed to reader', 'Main idea', 'Story\'s location', 'Character\'s name', 'A', 'Mood = emotional atmosphere.'];
    $questions[] = [$lit, 'Who is the first African Nobel laureate in Literature?', 'Chinua Achebe', 'Wole Soyinka', 'Nadine Gordimer', 'Doris Lessing', 'B', 'Soyinka won Nobel in Literature 1986.'];
    $questions[] = [$lit, 'A literary work that ridicules human folly is:', 'Tragedy', 'Comedy', 'Satire', 'Farce', 'C', 'Satire uses humor to criticize.'];
    $questions[] = [$lit, 'The character\'s struggle against nature is an example of:', 'Internal conflict', 'External conflict', 'Character conflict', 'Social conflict', 'B', 'Man vs nature = external conflict.'];
    $questions[] = [$lit, 'The writer\'s choice of words is called:', 'Tone', 'Style', 'Diction', 'Syntax', 'C', 'Diction = word choice.'];
    $questions[] = [$lit, '"Farewell to Arms" was written by:', 'Ernest Hemingway', 'F. Scott Fitzgerald', 'John Steinbeck', 'William Faulkner', 'A', 'Hemingway wrote A Farewell to Arms.'];
    $questions[] = [$lit, 'A short witty saying is an:', 'Epigram', 'Oxymoron', 'Epitaph', 'Euphemism', 'A', 'Epigram = short witty statement.'];
    $questions[] = [$lit, 'A character who contrasts with another is a:', 'Protagonist', 'Antagonist', 'Foil', 'Narrator', 'C', 'Foil character highlights traits of another.'];

    // --- CHRISTIAN RELIGIOUS STUDIES ---
    $crs = $subjMap['Christian Religious Studies'];
    $questions[] = [$crs, 'How many books are in the Bible?', '27', '39', '66', '73', 'C', 'Bible has 66 books (39 OT, 27 NT).'];
    $questions[] = [$crs, 'Who built the ark?', 'Abraham', 'Moses', 'Noah', 'David', 'C', 'Noah built the ark (Genesis 6).'];
    $questions[] = [$crs, 'The first book of the Bible is:', 'Exodus', 'Genesis', 'Leviticus', 'Deuteronomy', 'B', 'Genesis is the first book.'];
    $questions[] = [$crs, 'Who is the first man according to the Bible?', 'Adam', 'Eve', 'Cain', 'Abel', 'A', 'Adam was the first man (Genesis 2).'];
    $questions[] = [$crs, 'The Ten Commandments were given on:', 'Mount Sinai', 'Mount Zion', 'Mount Carmel', 'Mount Olives', 'A', 'The Law was given at Mount Sinai (Exodus 20).'];
    $questions[] = [$crs, 'Who is the mother of Jesus?', 'Mary Magdalene', 'Mary', 'Elizabeth', 'Martha', 'B', 'Mary was the mother of Jesus.'];
    $questions[] = [$crs, 'Jesus was born in:', 'Nazareth', 'Jerusalem', 'Bethlehem', 'Galilee', 'C', 'Jesus was born in Bethlehem (Luke 2).'];
    $questions[] = [$crs, 'How many disciples did Jesus choose?', '7', '10', '12', '70', 'C', 'Jesus chose 12 apostles (Luke 6).'];
    $questions[] = [$crs, 'Who betrayed Jesus?', 'Peter', 'John', 'Judas', 'Thomas', 'C', 'Judas Iscariot betrayed Jesus.'];
    $questions[] = [$crs, 'The fruit of the Spirit includes:', 'Pride', 'Love', 'Greed', 'Anger', 'B', 'Love is a fruit of the Spirit (Galatians 5).'];
    $questions[] = [$crs, 'The first miracle of Jesus was:', 'Raising the dead', 'Water to wine', 'Feeding 5000', 'Healing the blind', 'B', 'First miracle: water to wine (John 2).'];
    $questions[] = [$crs, 'Who wrote most of the Psalms?', 'Solomon', 'Moses', 'David', 'Samuel', 'C', 'David wrote many Psalms.'];
    $questions[] = [$crs, 'The Golden Rule says:', 'Love your neighbor', 'Do to others as you would have them do to you', 'Honor your parents', 'Pray always', 'B', 'The Golden Rule is in Matthew 7:12.'];
    $questions[] = [$crs, 'Paul was previously known as:', 'Simon', 'Saul', 'Stephen', 'Silas', 'B', 'Paul was formerly Saul (Acts 9).'];
    $questions[] = [$crs, 'Which gospel is the longest?', 'Matthew', 'Mark', 'Luke', 'John', 'C', 'Luke is the longest gospel.'];
    $questions[] = [$crs, 'The last book of the Bible is:', 'Jude', 'Revelation', 'Ephesians', 'Hebrews', 'B', 'Revelation is the last book.'];
    $questions[] = [$crs, 'God made a covenant with Abraham through:', 'Baptism', 'Circumcision', 'The Law', 'Sacrifice', 'B', 'Circumcision was the sign of the covenant (Gen 17).'];
    $questions[] = [$crs, 'The Holy Spirit descended on Jesus at:', 'His birth', 'His baptism', 'His crucifixion', 'His resurrection', 'B', 'Holy Spirit descended at Jesus\' baptism (Matt 3).'];
    $questions[] = [$crs, "Jesus' primary message was about:", 'The Kingdom of God', 'The Roman Empire', 'Politics', 'Wealth', 'A', 'Jesus preached about the Kingdom of God.'];
    $questions[] = [$crs, 'The longest Psalm is:', 'Psalm 1', 'Psalm 23', 'Psalm 119', 'Psalm 150', 'C', 'Psalm 119 is the longest chapter.'];
    $questions[] = [$crs, 'The parable of the Good Samaritan teaches:', 'Prayer', 'Love for neighbor', 'Fasting', 'Generosity', 'B', 'The parable teaches love for all people.'];
    $questions[] = [$crs, 'Who led Israel out of Egypt?', 'Abraham', 'Moses', 'Joshua', 'Aaron', 'B', 'Moses led the Exodus from Egypt.'];
    $questions[] = [$crs, 'The first king of Israel was:', 'David', 'Solomon', 'Saul', 'Samuel', 'C', 'Saul was the first king of Israel.'];
    $questions[] = [$crs, 'Jesus was crucified under:', 'Pilate', 'Herod', 'Caiaphas', 'Felix', 'A', 'Pontius Pilate presided over Jesus\' trial.'];
    $questions[] = [$crs, 'The church began on the day of:', 'Passover', 'Pentecost', 'Tabernacles', 'Unleavened Bread', 'B', 'The church began at Pentecost (Acts 2).'];
    $questions[] = [$crs, 'To be born again means:', 'Physical rebirth', 'Spiritual rebirth through faith', 'Reincarnation', 'Getting a new name', 'B', 'Born again = spiritual regeneration (John 3).'];
    $questions[] = [$crs, 'Faith is described as:', 'Belief in science', 'Assurance of things hoped for', 'Good works', 'Knowledge', 'B', 'Faith is substance of things hoped for (Heb 11:1).'];
    $questions[] = [$crs, 'The woman caught in adultery was brought to:', 'Peter', 'Jesus', 'Paul', 'John', 'B', 'The woman was brought to Jesus (John 8).'];
    $questions[] = [$crs, 'The Lord\'s Prayer begins with:', 'Our Father', 'Hallowed be Thy name', 'Give us this day', 'Lead us not', 'A', 'The Lord\'s Prayer: "Our Father in heaven".'];
    $questions[] = [$crs, 'How many days did God create the world?', '5', '6', '7', '10', 'B', 'God created the world in 6 days (Genesis 1).'];

    // --- GEOGRAPHY ---
    $geo = $subjMap['Geography'];
    $questions[] = [$geo, 'The largest continent is:', 'Africa', 'Asia', 'North America', 'Europe', 'B', 'Asia is the largest continent.'];
    $questions[] = [$geo, 'The longest river in the world is:', 'Niger', 'Nile', 'Amazon', 'Yangtze', 'B', 'The Nile is the world\'s longest river.'];
    $questions[] = [$geo, 'The highest mountain is:', 'Mount Kenya', 'Mount Kilimanjaro', 'Mount Everest', 'Mount Fuji', 'C', 'Mount Everest is the highest.'];
    $questions[] = [$geo, 'The deepest ocean is:', 'Atlantic', 'Indian', 'Pacific', 'Arctic', 'C', 'The Pacific is the deepest ocean.'];
    $questions[] = [$geo, 'Latitude lines run:', 'East-west', 'North-south', 'Diagonally', 'Circular', 'A', 'Latitude lines run east-west (horizontal).'];
    $questions[] = [$geo, 'The capital of Nigeria is:', 'Lagos', 'Abuja', 'Kano', 'Ibadan', 'B', 'Abuja is the capital of Nigeria.'];
    $questions[] = [$geo, 'Which is a sedimentary rock?', 'Granite', 'Basalt', 'Limestone', 'Marble', 'C', 'Limestone is a sedimentary rock.'];
    $questions[] = [$geo, 'The savanna vegetation is characterized by:', 'Dense forest', 'Grasses and scattered trees', 'Desert', 'Ice', 'B', 'Savanna has grasses and scattered trees.'];
    $questions[] = [$geo, 'The Prime Meridian passes through:', 'Paris', 'London', 'New York', 'Tokyo', 'B', 'Prime Meridian passes through Greenwich, London.'];
    $questions[] = [$geo, 'Which is a type of rainfall?', 'Convectional', 'Tidal', 'Solar', 'Wind', 'A', 'Convectional rainfall is common in tropics.'];
    $questions[] = [$geo, 'The Tropic of Cancer is at:', '23.5°N', '23.5°S', '66.5°N', '0°', 'A', 'Tropic of Cancer is 23.5°N.'];
    $questions[] = [$geo, 'Population density is:', 'Birth rate minus death rate', 'People per unit area', 'Total population', 'Migration rate', 'B', 'Population density = people/area.'];
    $questions[] = [$geo, 'This is the outermost layer of the Earth:', 'Mantle', 'Core', 'Crust', 'Lithosphere', 'C', 'The crust is Earth\'s outermost layer.'];
    $questions[] = [$geo, 'The delta region of Nigeria is known for:', 'Oil production', 'Gold mining', 'Farming', 'Fishing', 'A', 'Niger Delta is rich in oil.'];
    $questions[] = [$geo, 'Maps are drawn with orientation of:', 'South at top', 'North at top', 'East at top', 'West at top', 'B', 'Maps are oriented with north at top.'];
    $questions[] = [$geo, 'Wind erosion is common in:', 'Rainforest', 'Desert', 'Tundra', 'Mountains', 'B', 'Wind erosion is common in arid regions.'];
    $questions[] = [$geo, 'The scale 1:100,000 means:', '1cm = 1km', '1cm = 100km', '1cm = 10km', '1cm = 0.1km', 'A', '1:100,000 = 1cm = 1km.'];
    $questions[] = [$geo, 'A natural harbor is a:', 'Man-made port', 'Deep sheltered bay', 'River mouth', 'Lake', 'B', 'Natural harbor is a deep sheltered bay.'];
    $questions[] = [$geo, 'The movement of people from rural to urban is:', 'Immigration', 'Emigration', 'Rural-urban migration', 'Commuting', 'C', 'Rural-urban migration is movement to cities.'];
    $questions[] = [$geo, 'Which grid line is at 0° latitude?', 'Prime Meridian', 'Equator', 'Tropic of Cancer', 'Arctic Circle', 'B', 'Equator is at 0° latitude.'];
    $questions[] = [$geo, 'The core of the Earth is mainly:', 'Iron and nickel', 'Rock', 'Water', 'Carbon', 'A', 'Earth\'s core is iron and nickel.'];
    $questions[] = [$geo, 'Economic geography studies:', 'Rivers', 'Resources and economic activities', 'Climate', 'Rocks', 'B', 'Economic geography studies resource use.'];
    $questions[] = [$geo, 'Which is not a type of map scale?', 'Statement', 'Linear', 'Representative fraction', 'Bar scale', 'B', 'Linear is not a scale type.'];
    $questions[] = [$geo, 'Settlement where houses are close together is:', 'Dispersed', 'Nucleated', 'Linear', 'Radial', 'B', 'Nucleated = closely clustered settlement.'];
    $questions[] = [$geo, 'The main cause of desertification is:', 'Afforestation', 'Overgrazing', 'Flooding', 'Earthquake', 'B', 'Overgrazing contributes to desertification.'];
    $questions[] = [$geo, 'A narrow strip of land connecting two landmasses is:', 'Peninsula', 'Isthmus', 'Strait', 'Delta', 'B', 'Isthmus connects two larger land areas.'];
    $questions[] = [$geo, 'The ozone layer protects Earth from:', 'Heat', 'UV radiation', 'Rain', 'Wind', 'B', 'Ozone absorbs harmful UV radiation.'];
    $questions[] = [$geo, 'Topography refers to:', 'Climate patterns', 'Surface features of land', 'Ocean currents', 'Vegetation', 'B', 'Topography = land surface features.'];
    $questions[] = [$geo, 'The largest lake in Africa is:', 'Lake Victoria', 'Lake Chad', 'Lake Tanganyika', 'Lake Malawi', 'A', 'Lake Victoria is Africa\'s largest lake.'];
    $questions[] = [$geo, 'Contour lines connect points of equal:', 'Rainfall', 'Temperature', 'Elevation', 'Population', 'C', 'Contour lines = equal elevation.'];

    // ========== INSERT QUESTIONS ==========
    $qStmt = $db->prepare("INSERT INTO cbt_questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $count = 0;
    foreach ($questions as $q) {
        $qStmt->execute($q);
        $count++;
    }
    echo "  $count questions created\n";

    // ========== CREATE SAMPLE EXAMS ==========
    $examStmt = $db->prepare("INSERT INTO cbt_exams (title, subject_id, duration_minutes, total_questions, pass_score, instructions, is_published, created_by) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");

    $eqStmt = $db->prepare("INSERT INTO cbt_exam_questions (exam_id, question_id, question_order) VALUES (?, ?, ?)");

    $subjectRows = $db->query("SELECT id, name FROM cbt_subjects")->fetchAll();

    foreach ($subjectRows as $subj) {
        $sid = (int)$subj['id'];
        $sname = $subj['name'];

        $examStmt->execute([
            "Mock $sname Exam",
            $sid,
            30,
            30,
            50.00,
            "Answer all 30 questions. Each question carries equal marks. Duration: 30 minutes. Select the best option for each question.",
        ]);

        $examId = (int)$db->lastInsertId();

        $questionRows = $db->query("SELECT id FROM cbt_questions WHERE subject_id = $sid ORDER BY RAND() LIMIT 30")->fetchAll();
        $order = 1;
        foreach ($questionRows as $qr) {
            $eqStmt->execute([$examId, (int)$qr['id'], $order]);
            $order++;
        }

        echo "  Exam created: Mock $sname Exam ($order-1 questions)\n";
    }

    echo "\nCBT seeding complete!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
