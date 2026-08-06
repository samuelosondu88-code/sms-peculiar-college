<?php
/**
 * Dummy CBT/exam question bank for testing the Teacher Exams flow.
 *
 * Seeds 20 MCQ per CORE subject for BECE (JSS level) and WAEC (SS level)
 * into the teacher question bank (exam_questions), owned by the teacher
 * allocated to each subject row. Also backfills subjects.teacher_id from
 * subject_allocations (valid teachers only) so the exam UI works for all
 * teacher accounts.
 *
 * Run from CLI:  php database/seed_core_exam_bank.php
 * Re-runnable:   existing rows tagged [dummy-exam-bank] are replaced.
 * Remove later:  DELETE FROM exam_questions WHERE explanation LIKE '%[dummy-exam-bank]%';
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';

$MARK = '[dummy-exam-bank]';

/* ------------------------------------------------------------------ *
 *  QUESTION BANK
 *  Format: [question_text, option_a, option_b, option_c, option_d,
 *           correct_letter, explanation]
 *  Key = exact subjects.name, value = ['JSS'|'SS' => [20 questions]]
 * ------------------------------------------------------------------ */
$bank = [

/* ============================== BECE / JSS ============================== */

'Mathematics' => ['JSS' => [
    ['Which of the following numbers is the smallest?', '0.2', '0.02', '0.002', '0.202', 'C', '0.002 has the least value among the options.'],
    ['What is 25% of 80?', '16', '20', '25', '40', 'B', '25% of 80 = (25/100) x 80 = 20.'],
    ['Simplify 12 + 3 x 4.', '60', '24', '15', '36', 'B', 'Multiplication before addition: 12 + 12 = 24.'],
    ['If x + 7 = 15, what is x?', '7', '8', '15', '22', 'B', 'x = 15 - 7 = 8.'],
    ['The perimeter of a square of side 5 cm is:', '10 cm', '15 cm', '20 cm', '25 cm', 'C', 'Perimeter = 4 x side = 4 x 5 = 20 cm.'],
    ['Convert 3/4 to a decimal.', '0.34', '0.75', '0.25', '0.43', 'B', '3 divided by 4 = 0.75.'],
    ['What is the next number in the sequence 2, 4, 6, 8, ...?', '9', '10', '12', '16', 'B', 'Even numbers increase by 2, so the next is 10.'],
    ['The area of a rectangle 6 cm long and 4 cm wide is:', '10 cm2', '20 cm2', '24 cm2', '12 cm2', 'C', 'Area = length x width = 6 x 4 = 24 cm2.'],
    ['Round 3.67 to one decimal place.', '3.6', '3.7', '4.0', '3.67', 'B', 'The second decimal digit (7) rounds the first up to 3.7.'],
    ['7 x 8 = ?', '49', '54', '56', '63', 'C', '7 x 8 = 56.'],
    ['Which fraction is equivalent to 1/2?', '1/4', '2/4', '3/8', '2/3', 'B', '2/4 simplifies to 1/2.'],
    ['The value of 5^3 (five cubed) is:', '15', '25', '125', '225', 'C', '5^3 = 5 x 5 x 5 = 125.'],
    ['How many sides has a triangle?', '2', '3', '4', '5', 'B', 'A triangle has three sides.'],
    ['Find 3/5 of 60.', '12', '20', '36', '45', 'C', '3/5 of 60 = (3 x 60)/5 = 36.'],
    ['The mean (average) of 4, 6 and 8 is:', '5', '6', '7', '8', 'B', 'Sum = 18; 18 / 3 = 6.'],
    ['How many metres are there in one kilometre?', '10', '100', '1000', '10000', 'C', '1 km = 1000 m.'],
    ['If x/4 = 3, then x = ?', '7', '12', '3', '14', 'B', 'x = 3 x 4 = 12.'],
    ['The simplest form of 8/12 is:', '1/3', '2/3', '3/4', '4/6', 'B', 'Divide numerator and denominator by 4 to get 2/3.'],
    ['The sum of the interior angles of a triangle is:', '90 degrees', '120 degrees', '180 degrees', '360 degrees', 'C', 'The angles of a triangle always add up to 180 degrees.'],
    ['45 - 18 = ?', '27', '33', '23', '37', 'A', '45 - 18 = 27.'],
]],

'English Language' => ['JSS' => [
    ['Choose the correct plural of "child".', 'Childs', 'Children', 'Childes', 'Child', 'B', '"Children" is the correct irregular plural.'],
    ['The opposite of "brave" is:', 'Bold', 'Cowardly', 'Strong', 'Noisy', 'B', 'Cowardly means lacking courage, the opposite of brave.'],
    ['She ___ to school every day.', 'go', 'goes', 'going', 'gone', 'B', 'Third person singular takes "goes".'],
    ['A person who teaches is called a:', 'Doctor', 'Teacher', 'Farmer', 'Driver', 'B', 'A teacher is someone who teaches.'],
    ['A synonym of "happy" is:', 'Sad', 'Angry', 'Joyful', 'Tired', 'C', 'Joyful means feeling or showing happiness.'],
    ['The past tense of "eat" is:', 'eaten', 'eat', 'ate', 'eating', 'C', 'Ate is the simple past tense of eat.'],
    ['We ___ to the market yesterday.', 'go', 'gone', 'went', 'going', 'C', 'Yesterday signals the past, so "went" is correct.'],
    ['Which of the following words is a noun?', 'Run', 'Book', 'Quickly', 'Under', 'B', 'A book is a thing; the others are not nouns.'],
    ['The opposite of "early" is:', 'Late', 'Soon', 'Always', 'Fast', 'A', 'Late is the opposite of early.'],
    ['Choose the correctly spelled word.', 'Beleive', 'Believe', 'Belive', 'Beleeve', 'B', '"Believe" is the correct spelling.'],
    ['In "My father is a doctor", the word "father" is a:', 'Verb', 'Noun', 'Adjective', 'Adverb', 'B', 'Father names a person, so it is a noun.'],
    ['The plural of "box" is:', 'Boxs', 'Boxen', 'Boxes', 'Boxies', 'C', 'Words ending in -x add -es: boxes.'],
    ['She is taller ___ her sister.', 'then', 'than', 'from', 'of', 'B', '"Than" is used for comparisons.'],
    ['The opposite of "never" is:', 'Always', 'Often', 'Rarely', 'Never', 'A', 'Always is the direct opposite of never.'],
    ['The cat is ___ the table.', 'in', 'on', 'at', 'to', 'B', 'The cat is on the table describes position.'],
    ['A word that names a person, place or thing is a:', 'Verb', 'Adjective', 'Noun', 'Pronoun', 'C', 'Nouns name persons, places, or things.'],
    ['There ___ many books in the library.', 'is', 'are', 'am', 'was', 'B', 'Plural subject "books" takes "are".'],
    ['I ___ my homework yesterday.', 'do', 'did', 'done', 'doing', 'B', 'Yesterday requires the past form "did".'],
    ['A synonym of "quick" is:', 'Slow', 'Fast', 'Late', 'Lazy', 'B', 'Fast means the same as quick.'],
    ['Please give ___ the book.', 'I', 'me', 'my', 'mine', 'B', 'The object pronoun "me" is correct here.'],
]],

'Basic Science' => ['JSS' => [
    ['The unit of force is the:', 'Joule', 'Newton', 'Watt', 'Pascal', 'B', 'Force is measured in newtons (N).'],
    ['Which of the following is a living thing?', 'Stone', 'Plant', 'Sand', 'Water', 'B', 'Plants grow, feed and reproduce, so they are living.'],
    ['The process by which green plants make their food is called:', 'Respiration', 'Digestion', 'Photosynthesis', 'Evaporation', 'C', 'Photosynthesis uses light to make food in leaves.'],
    ['Water boils at:', '0 degrees C', '50 degrees C', '100 degrees C', '212 degrees C', 'C', 'At sea level, water boils at 100 degrees C.'],
    ['Which organ pumps blood round the body?', 'Liver', 'Kidney', 'Heart', 'Lungs', 'C', 'The heart pumps blood to all parts of the body.'],
    ['The basic unit of life is the:', 'Tissue', 'Cell', 'Organ', 'Atom', 'B', 'The cell is the smallest living unit.'],
    ['Which of these is a non-renewable energy source?', 'Solar', 'Wind', 'Coal', 'Hydro', 'C', 'Coal takes millions of years to form, so it is non-renewable.'],
    ['The three states of matter are:', 'solid, liquid, gas', 'solid, liquid, fire', 'ice, water, steam', 'metal, wood, air', 'A', 'Matter exists as solid, liquid or gas.'],
    ['Friction is a force that:', 'helps things move faster', 'resists motion', 'causes gravity', 'slows down light', 'B', 'Friction opposes the motion of surfaces in contact.'],
    ['Which sense organ is used to see light?', 'Ear', 'Nose', 'Eye', 'Tongue', 'C', 'The eye detects light.'],
    ['A magnet attracts:', 'Wood', 'Plastic', 'Iron', 'Paper', 'C', 'Magnets attract magnetic materials such as iron.'],
    ['Which of the following helps to prevent malaria?', 'Playing in stagnant water', 'Sleeping under a treated net', 'Drinking dirty water', 'Skipping meals', 'B', 'Treated nets protect against mosquito bites.'],
    ['The sun is a:', 'Planet', 'Star', 'Moon', 'Comet', 'B', 'The sun is the star at the centre of our solar system.'],
    ['Which gas do humans breathe in to live?', 'Carbon dioxide', 'Oxygen', 'Nitrogen', 'Hydrogen', 'B', 'Oxygen is needed for respiration.'],
    ['Evaporation is the change of a liquid into a:', 'Solid', 'Gas', 'Liquid', 'Plasma', 'B', 'Evaporation turns liquid into gas.'],
    ['Plants absorb water through their:', 'Leaves', 'Roots', 'Flowers', 'Stems', 'B', 'Roots take up water from the soil.'],
    ['The unit of energy is the:', 'Newton', 'Joule', 'Ampere', 'Kelvin', 'B', 'Energy is measured in joules (J).'],
    ['Which of the following is a vertebrate?', 'Snail', 'Earthworm', 'Frog', 'Insect', 'C', 'A frog has a backbone, so it is a vertebrate.'],
    ['Sound travels fastest in:', 'Air', 'Water', 'Solids', 'A vacuum', 'C', 'Sound travels fastest through solids.'],
    ['The main function of the heart is to:', 'digest food', 'pump blood', 'breathe air', 'filter urine', 'B', 'The heart pumps blood around the body.'],
]],

'Basic Technology' => ['JSS' => [
    ['Which of the following is a cutting tool?', 'Hammer', 'Hacksaw', 'Spanner', 'Paintbrush', 'B', 'A hacksaw is used to cut metal.'],
    ['The instrument used to measure length is a:', 'Thermometer', 'Metre rule', 'Weighing scale', 'Voltmeter', 'B', 'A metre rule measures length.'],
    ['Electric current flows through:', 'Insulators', 'Conductors', 'Resistors', 'Magnets', 'B', 'Conductors allow electric current to pass through.'],
    ['Which material is a good conductor of electricity?', 'Rubber', 'Copper', 'Glass', 'Wood', 'B', 'Copper is a metal and conducts electricity well.'],
    ['The component that opposes electric current is a:', 'Resistor', 'Battery', 'Switch', 'Bulb', 'A', 'A resistor resists the flow of current.'],
    ['Timber used for building is obtained from:', 'Rocks', 'Trees', 'Sand', 'Water', 'B', 'Timber comes from trees.'],
    ['The drawing instrument used to draw circles is a:', 'Ruler', 'Compass', 'Protractor', 'Set square', 'B', 'A compass is used to draw circles and arcs.'],
    ['Which of these is a safety rule in the workshop?', 'Run around the machines', 'Wear safety goggles', 'Play with tools', 'Ignore a fire alarm', 'B', 'Goggles protect the eyes from flying particles.'],
    ['The main material used in making window glass is:', 'Sand', 'Clay', 'Iron', 'Wood', 'A', 'Glass is made mainly from silica (sand).'],
    ['A screwdriver is used for:', 'cutting wood', 'tightening and loosening screws', 'measuring length', 'painting surfaces', 'B', 'A screwdriver drives screws.'],
    ['Which of the following is a hand tool?', 'Drilling machine', 'Hammer', 'Lathe', 'Power saw', 'B', 'A hammer is a simple hand tool.'],
    ['The SI unit of electric current is the:', 'Volt', 'Ampere', 'Ohm', 'Watt', 'B', 'Electric current is measured in amperes (A).'],
    ['Plastic is an example of an:', 'Conductor', 'Insulator', 'Alloy', 'Magnet', 'B', 'Plastic does not allow electricity to pass easily.'],
    ['Which of the following is a construction material?', 'Cement', 'Petrol', 'Sugar', 'Salt', 'A', 'Cement is used in building construction.'],
    ['A drawing that shows the top view of an object is the:', 'Plan view', 'Elevation', 'Isometric drawing', 'Oblique drawing', 'A', 'The plan view is seen from above.'],
    ['The tool used to drive nails into wood is a:', 'Hammer', 'Pliers', 'File', 'Chisel', 'A', 'A hammer is used for nailing.'],
    ['Which of these energy sources is renewable?', 'Coal', 'Crude oil', 'Solar', 'Natural gas', 'C', 'Solar energy is naturally replenished.'],
    ['A multimeter is used to measure:', 'temperature', 'electrical values', 'pressure', 'speed', 'B', 'A multimeter measures voltage, current and resistance.'],
    ['The joining of metals using heat is called:', 'Soldering', 'Welding', 'Painting', 'Drilling', 'B', 'Welding joins metals by melting them together.'],
    ['In a workshop, safety is the responsibility of:', 'visitors only', 'everyone in the workshop', 'the cleaner only', 'no one', 'B', 'Everyone must observe workshop safety rules.'],
]],

'Citizenship & Heritage Studies' => ['JSS' => [
    ['The national flower of Nigeria is the:', 'Rose', 'Costus spectabilis', 'Hibiscus', 'Orchid', 'B', 'Costus spectabilis is Nigeria\'s national flower.'],
    ['The colours on the Nigerian national flag are:', 'red and white', 'green and white', 'blue and white', 'green and yellow', 'B', 'The flag has two green and one white band.'],
    ['The capital city of Nigeria is:', 'Lagos', 'Abuja', 'Port Harcourt', 'Kaduna', 'B', 'Abuja is the Federal Capital Territory.'],
    ['Nigeria gained independence in the year:', '1958', '1960', '1963', '1970', 'B', 'Nigeria became independent on 1 October 1960.'],
    ['The head of the Federal Government of Nigeria is the:', 'President', 'Governor', 'Minister', 'Senator', 'A', 'The President heads the Federal Government.'],
    ['The national pledge is a promise of:', 'loyalty to the country', 'wealth to the family', 'fame in school', 'power over others', 'A', 'The pledge declares loyalty and commitment to Nigeria.'],
    ['The currency of Nigeria is the:', 'Cedi', 'Naira', 'Shilling', 'Franc', 'B', 'Nigeria\'s currency is the Naira (N).'],
    ['The highest court in Nigeria is the:', 'High Court', 'Court of Appeal', 'Supreme Court', 'Magistrate Court', 'C', 'The Supreme Court is the apex court.'],
    ['How many states make up Nigeria?', '34', '35', '36', '37', 'C', 'Nigeria has 36 states and the FCT.'],
    ['A good citizen should:', 'break the law', 'obey the law', 'avoid paying taxes', 'litter public places', 'B', 'Obeying the law is a duty of a good citizen.'],
    ['Nigeria is divided into how many geo-political zones?', '4', '5', '6', '7', 'C', 'Nigeria has six geo-political zones.'],
    ['A state governor heads the:', 'Federal Government', 'State Government', 'Local Government', 'National Assembly', 'B', 'The governor heads the state government.'],
    ['The eagle on the Nigerian coat of arms stands for:', 'peace', 'strength', 'unity', 'wealth', 'B', 'The eagle represents the strength of the nation.'],
    ['The black shield on the coat of arms represents:', 'crude oil', 'Nigeria\'s good soil', 'peace', 'national unity', 'B', 'The black shield stands for the fertile soil of Nigeria.'],
    ['Voting is a right and responsibility of:', 'children', 'citizens', 'visitors', 'tourists', 'B', 'Citizens vote in elections.'],
    ['The white colour in the Nigerian flag stands for:', 'Peace', 'Unity', 'Wealth', 'Strength', 'A', 'White symbolises peace.'],
    ['The green colour in the Nigerian flag stands for:', 'Peace', 'Agriculture and wealth', 'Unity', 'Industry', 'B', 'Green represents agriculture and natural wealth.'],
    ['A person qualifies to vote in Nigeria at the age of:', '16', '17', '18', '21', 'C', 'The voting age in Nigeria is 18.'],
    ['The first president of Nigeria\'s Fourth Republic was:', 'Olusegun Obasanjo', 'Shehu Shagari', 'Nnamdi Azikiwe', 'Goodluck Jonathan', 'A', 'Obasanjo won the 1999 election, starting the Fourth Republic.'],
    ['National symbols of Nigeria include the flag, anthem and:', 'coat of arms', 'currency', 'stadium', 'airport', 'A', 'The coat of arms is a national symbol.'],
]],

'National Values' => ['JSS' => [
    ['Honesty means:', 'telling the truth', 'stealing', 'cheating', 'lying', 'A', 'An honest person always tells the truth.'],
    ['Which of the following is a positive value?', 'Stealing', 'Respect', 'Lying', 'Cheating', 'B', 'Respect for others is a positive value.'],
    ['Hard work generally leads to:', 'failure', 'success', 'poverty', 'trouble', 'B', 'Diligence and hard work bring success.'],
    ['Respecting other people means:', 'treating them with dignity', 'bullying them', 'ignoring them', 'mocking them', 'A', 'Respect means showing dignity to others.'],
    ['Which of these shows good citizenship?', 'Littering', 'Paying taxes', 'Stealing', 'Quarrelling', 'B', 'Paying taxes is a civic duty of citizens.'],
    ['Discipline means:', 'doing what is right', 'disobeying rules', 'sleeping in class', 'fighting', 'A', 'Discipline is the ability to do the right thing.'],
    ['A value that promotes peace is:', 'Hatred', 'Tolerance', 'Greed', 'Envy', 'B', 'Tolerance allows people to live together peacefully.'],
    ['Helping the weak is an act of:', 'Cruelty', 'Kindness', 'Selfishness', 'Laziness', 'B', 'Kindness means caring for the weak.'],
    ['The habit of being punctual means:', 'arriving on time', 'coming late', 'skipping school', 'leaving early', 'A', 'Punctuality is arriving at the right time.'],
    ['Which behaviour should be avoided?', 'Honesty', 'Cheating in exams', 'Diligence', 'Courtesy', 'B', 'Cheating is dishonest and punishable.'],
    ['Teamwork means:', 'working together', 'working alone', 'fighting', 'competing unfairly', 'A', 'Teamwork is cooperation towards a common goal.'],
    ['Integrity means:', 'doing right even when no one is watching', 'cheating quietly', 'hiding the truth', 'taking bribes', 'A', 'Integrity is uprightness and honesty.'],
    ['Which of these is an act of patriotism?', 'Defending the nation', 'Vandalising property', 'Smuggling', 'Tax evasion', 'A', 'Patriotism is love and defence of one\'s country.'],
    ['Sharing with others shows:', 'Greed', 'Generosity', 'Envy', 'Pride', 'B', 'Generosity is the willingness to share.'],
    ['Respect for elders is part of our:', 'Culture and values', 'Ignorance', 'School uniform', 'Examinations', 'A', 'Respecting elders is a cherished cultural value.'],
    ['A disciplined student:', 'follows the rules', 'causes trouble', 'truants', 'fights', 'A', 'Discipline means obeying rules and regulations.'],
    ['Contentment means:', 'being satisfied with what one has', 'always wanting more', 'being greedy', 'cheating to gain', 'A', 'Contentment is satisfaction with one\'s lot.'],
    ['Which of these destroys unity?', 'Cooperation', 'Gossip', 'Love', 'Tolerance', 'B', 'Gossip breeds misunderstanding and disunity.'],
    ['Courage means:', 'facing fear boldly', 'running away', 'hiding', 'giving up', 'A', 'Courage is the ability to face danger or fear.'],
    ['Obeying traffic rules shows:', 'Responsibility', 'Carelessness', 'Ignorance', 'Recklessness', 'A', 'Obeying road signs shows responsibility.'],
]],

'Business Studies' => ['JSS' => [
    ['The money used to start or run a business is called:', 'Capital', 'Profit', 'Loss', 'Credit', 'A', 'Capital is money invested in a business.'],
    ['A person who buys goods to sell to consumers is a:', 'Producer', 'Retailer', 'Manufacturer', 'Farmer', 'B', 'A retailer sells directly to final consumers.'],
    ['Which of the following is a source of income?', 'Salary', 'Debt', 'Expense', 'Loss', 'A', 'A salary is money earned for work done.'],
    ['A shop where drugs are sold is a:', 'Butchery', 'Pharmacy', 'Bakery', 'Bookshop', 'B', 'Drugs are sold in a pharmacy.'],
    ['The person who owns and runs a business is the:', 'Customer', 'Entrepreneur', 'Police', 'Banker', 'B', 'An entrepreneur owns and manages a business.'],
    ['Banking is the business of:', 'keeping and lending money', 'selling clothes', 'cooking food', 'building houses', 'A', 'Banks keep deposits and give loans.'],
    ['Profit is calculated as:', 'selling price - cost price', 'cost price - selling price', 'sales + expenses', 'capital - loan', 'A', 'Profit = selling price minus cost price.'],
    ['A receipt is a document showing:', 'money received', 'a school report', 'a birth date', 'a medical test', 'A', 'A receipt confirms payment received.'],
    ['A machine used to type documents is a:', 'Computer', 'Refrigerator', 'Grinding machine', 'Sewing machine', 'A', 'Computers are used for typing documents.'],
    ['Talking on the telephone is an example of:', 'Oral communication', 'Written communication', 'Body language', 'Traffic signs', 'A', 'Speech over the phone is oral communication.'],
    ['Which of the following is a means of transport?', 'Lorry', 'Telephone', 'Television', 'Newspaper', 'A', 'A lorry is used to transport goods.'],
    ['A wholesaler sells goods to:', 'Retailers', 'Farmers', 'Consumers only', 'Government', 'A', 'Wholesalers sell in bulk to retailers.'],
    ['Keeping accurate records of business transactions is called:', 'Bookkeeping', 'Cooking', 'Farming', 'Teaching', 'A', 'Bookkeeping is the systematic recording of transactions.'],
    ['Loss of goods by fire is a business:', 'Risk', 'Profit', 'Bonus', 'Salary', 'A', 'Fire loss is a business risk to be insured.'],
    ['Shares are bought and sold at the:', 'Stock exchange', 'Market store', 'Motor park', 'Police station', 'A', 'The stock exchange trades in shares.'],
    ['Money paid to workers for their services is called:', 'Wages', 'Interest', 'Dividend', 'Rent', 'A', 'Wages are payment for labour.'],
    ['Advertising helps a business to:', 'attract customers', 'reduce profit', 'hide goods', 'confuse buyers', 'A', 'Advertising promotes products to customers.'],
    ['Which of the following is a service occupation?', 'Teaching', 'Farming', 'Baking', 'Mining', 'A', 'Teaching provides a service to society.'],
    ['The person in charge of an office is the:', 'Manager', 'Visitor', 'Student', 'Messenger', 'A', 'A manager supervises an office.'],
    ['When expenses are greater than income, a business makes a:', 'Loss', 'Profit', 'Gain', 'Bonus', 'A', 'Excess of expenses over income is a loss.'],
]],

'Cultural & Creative Arts' => ['JSS' => [
    ['The art of painting on a wall is called:', 'Mural painting', 'Still life', 'Caricature', 'Sketching', 'A', 'Mural painting is done directly on walls.'],
    ['Which of these is a primary colour?', 'Green', 'Purple', 'Red', 'Orange', 'C', 'Red, blue and yellow are primary colours.'],
    ['A person who acts in a play is called an:', 'Actor', 'Artist', 'Architect', 'Author', 'A', 'An actor performs in a drama.'],
    ['A drawing of a person\'s head and shoulders is a:', 'Portrait', 'Landscape', 'Cartoon', 'Diagram', 'A', 'A portrait shows a person\'s likeness.'],
    ['Which of these instruments is a percussion instrument?', 'Flute', 'Violin', 'Drum', 'Guitar', 'C', 'A drum is beaten, so it is percussion.'],
    ['The art of shaping clay into objects is called:', 'Pottery', 'Knitting', 'Weaving', 'Printing', 'A', 'Pottery is the craft of making clay objects.'],
    ['Mixing red and yellow gives:', 'Green', 'Orange', 'Purple', 'Brown', 'B', 'Red + yellow = orange.'],
    ['Traditional Nigerian dance is often performed during:', 'Festivals', 'Examinations', 'Sermons', 'Fasting', 'A', 'Dances feature at cultural festivals.'],
    ['Which tool is used for carving wood?', 'Chisel', 'Broom', 'Kettle', 'Frying pan', 'A', 'A chisel is used to carve wood.'],
    ['The person who writes a play is a:', 'Playwright', 'Plumber', 'Painter', 'Pilot', 'A', 'A playwright writes drama scripts.'],
    ['Which of the following is an art material?', 'Paint and brush', 'Bread and tea', 'Book and pen only', 'Bucket and mop', 'A', 'Paints and brushes are used in art.'],
    ['A gentle song sung to put a baby to sleep is a:', 'Lullaby', 'Anthem', 'Ballad', 'Hymn', 'A', 'A lullaby soothes babies to sleep.'],
    ['Which of the following is a secondary colour?', 'Yellow', 'Blue', 'Green', 'Red', 'C', 'Green is made by mixing blue and yellow.'],
    ['The art of folding paper into shapes is called:', 'Origami', 'Sculpture', 'Mosaic', 'Collage', 'A', 'Origami is the Japanese art of paper folding.'],
    ['Drawing the outline of an object is called:', 'Sketching', 'Painting', 'Printing', 'Baking', 'A', 'A sketch captures the outline of a subject.'],
    ['Nigerian traditional music often features the:', 'Talking drum', 'Piano', 'Organ', 'Saxophone', 'A', 'The talking drum is central to Nigerian music.'],
    ['Which of these is a performing art?', 'Dance', 'Painting', 'Drawing', 'Sculpture', 'A', 'Dance is performed before an audience.'],
    ['A place where works of art are displayed is a:', 'Gallery', 'Kitchen', 'Garage', 'Clinic', 'A', 'Artists exhibit works in a gallery.'],
    ['Perspective drawing is used to show:', 'Depth and distance', 'Colour only', 'Texture only', 'Sound', 'A', 'Perspective creates depth on a flat surface.'],
    ['A costume is what an actor:', 'wears', 'eats', 'reads', 'draws', 'A', 'Costumes are the clothes worn in a performance.'],
]],

'Physical & Health Education' => ['JSS' => [
    ['Which of the following is a warm-up activity?', 'Jogging', 'Sleeping', 'Eating', 'Reading', 'A', 'Jogging gently prepares the body for exercise.'],
    ['The number of players in a football team is:', '9', '10', '11', '12', 'C', 'A football team fields eleven players.'],
    ['Regular exercise helps the body to:', 'stay healthy', 'become weak', 'sleep more', 'grow slow', 'A', 'Exercise keeps the body fit and healthy.'],
    ['Which of these is a personal hygiene practice?', 'Brushing the teeth', 'Eating junk food', 'Refusing to bathe', 'Littering', 'A', 'Brushing teeth keeps the mouth clean.'],
    ['First aid is given to:', 'an injured person', 'a healthy person', 'a sleeping person', 'a playing child', 'A', 'First aid is immediate help for the injured.'],
    ['A balance beam is used in:', 'Gymnastics', 'Football', 'Swimming', 'Boxing', 'A', 'The balance beam is a gymnastics apparatus.'],
    ['Which food gives quick energy?', 'Rice', 'Meat', 'Water', 'Salt', 'A', 'Carbohydrates like rice provide quick energy.'],
    ['The playing area in football is called a:', 'Pitch', 'Court', 'Ring', 'Pool', 'A', 'Football is played on a pitch.'],
    ['Which of these is a basketball skill?', 'Dribbling', 'Serving', 'Bowling', 'Tacking', 'A', 'Dribbling is a basic basketball skill.'],
    ['Drinking clean water helps to prevent:', 'Disease', 'Strength', 'Growth', 'Hunger', 'A', 'Clean water prevents water-borne diseases.'],
    ['The official who controls a football match is the:', 'Referee', 'Coach', 'Captain', 'Spectator', 'A', 'The referee enforces the rules of the game.'],
    ['Which of these is an indoor game?', 'Table tennis', 'Football', 'Athletics', 'Polo', 'A', 'Table tennis is played indoors.'],
    ['Physical fitness means the body is:', 'strong and healthy', 'weak and tired', 'sickly', 'always hungry', 'A', 'Fitness is the ability to carry out daily tasks.'],
    ['Sleep is important for:', 'rest and growth', 'late nights', 'avoiding exercise', 'eating', 'A', 'Sleep allows the body to rest and grow.'],
    ['Which of these is a safety rule in games?', 'Follow the rules', 'Push opponents', 'Play roughly', 'Ignore the referee', 'A', 'Following rules prevents injuries.'],
    ['Warming up before exercise helps to prevent:', 'Injury', 'Victory', 'Fasting', 'Drowsiness', 'A', 'Warm-ups reduce the risk of injury.'],
    ['Running regularly exercises the:', 'heart and lungs', 'eyes and ears', 'teeth and tongue', 'hair and nails', 'A', 'Running strengthens the heart and lungs.'],
    ['Good personal hygiene prevents the spread of:', 'Germs', 'Wealth', 'Happiness', 'Books', 'A', 'Hygiene stops germs from spreading.'],
    ['A tennis match is played on a:', 'Court', 'Pitch', 'Track', 'Ring', 'A', 'Tennis is played on a court.'],
    ['Which of these is a good eating habit?', 'Eating a balanced diet', 'Skipping breakfast', 'Eating only sweets', 'Overeating daily', 'A', 'A balanced diet keeps the body healthy.'],
]],

'Religious Studies' => ['JSS' => [
    ['The first book of the Bible is:', 'Exodus', 'Genesis', 'Psalms', 'Leviticus', 'B', 'Genesis is the first book of the Bible.'],
    ['Christians worship in a:', 'Church', 'Mosque', 'Shrine', 'Temple only', 'A', 'Christians gather in a church.'],
    ['Muslims worship in a:', 'Church', 'Mosque', 'Synagogue', 'School', 'B', 'Muslims pray in a mosque.'],
    ['The holy book of Christians is the:', 'Qur\'an', 'Bible', 'Torah', 'Hadith', 'B', 'The Bible is the Christian holy book.'],
    ['The holy book of Islam is the:', 'Bible', 'Qur\'an', 'Torah', 'Psalms', 'B', 'The Qur\'an is revealed to Prophet Muhammad.'],
    ['Prayer is a way of:', 'talking to God', 'sleeping', 'eating', 'playing', 'A', 'Prayer is communication with God.'],
    ['Which prophet is honoured in both Christianity and Islam?', 'Abraham', 'Only Jesus', 'Only Muhammad', 'None', 'A', 'Abraham is a prophet in both faiths.'],
    ['The Ten Commandments were given to:', 'Moses', 'David', 'Solomon', 'Adam', 'A', 'Moses received the Ten Commandments.'],
    ['"Love your neighbour as yourself" was taught by:', 'Jesus', 'Herod', 'Pilate', 'Caesar', 'A', 'Jesus taught love of neighbour.'],
    ['The founder of Christianity is:', 'Muhammad', 'Jesus Christ', 'Abraham', 'Peter', 'B', 'Christianity is centred on Jesus Christ.'],
    ['The month of fasting in Islam is called:', 'Eid', 'Ramadan', 'Hajj', 'Salah', 'B', 'Muslims fast during Ramadan.'],
    ['Giving to the needy shows:', 'Compassion', 'Pride', 'Selfishness', 'Greed', 'A', 'Charity shows compassion for others.'],
    ['Christians traditionally worship on:', 'Sunday', 'Monday', 'Wednesday', 'Saturday only', 'A', 'Sunday is the Christian day of worship.'],
    ['Muslims are required to pray how many times daily?', '3', '4', '5', '7', 'C', 'Islam prescribes five daily prayers.'],
    ['The place of worship in traditional African religion is a:', 'Shrine', 'School', 'Bank', 'Market', 'A', 'Traditional worshippers use shrines.'],
    ['Honesty and kindness are examples of:', 'Good virtues', 'Bad habits', 'Vices', 'Crimes', 'A', 'Honesty and kindness are virtues.'],
    ['The leader of congregational prayer in Islam is the:', 'Imam', 'Bishop', 'Pastor', 'Rabbi', 'A', 'The imam leads prayers in the mosque.'],
    ['The accounts of the life of Jesus are found in the:', 'Gospels', 'Prophets', 'Epistles', 'Wisdom books', 'A', 'The four Gospels tell the life of Jesus.'],
    ['Respect for parents is taught in:', 'all religions', 'no religion', 'school only', 'law only', 'A', 'Every religion teaches respect for parents.'],
    ['The festival that marks the end of Ramadan is:', 'Eid-el-Fitr', 'Eid-el-Kabir', 'Maulud', 'Christmas', 'A', 'Eid-el-Fitr celebrates the end of Ramadan.'],
]],

'French' => ['JSS' => [
    ['The French word "Bonjour" means:', 'Good morning', 'Good night', 'Goodbye', 'Thank you', 'A', 'Bonjour means good morning or hello.'],
    ['"Merci" in French means:', 'Sorry', 'Thank you', 'Please', 'Welcome', 'B', 'Merci means thank you.'],
    ['"Au revoir" means:', 'Goodbye', 'Good morning', 'How are you?', 'My name is', 'A', 'Au revoir is used to say goodbye.'],
    ['"Oui" means:', 'Yes', 'No', 'Maybe', 'Never', 'A', 'Oui is the French word for yes.'],
    ['"Non" means:', 'Yes', 'No', 'Please', 'Hello', 'B', 'Non means no.'],
    ['How do you say "one" in French?', 'Deux', 'Un', 'Trois', 'Quatre', 'B', 'Un is the French word for one.'],
    ['"Madame" is used to address a:', 'Married woman', 'Man', 'Child', 'Dog', 'A', 'Madame means Mrs or Madam.'],
    ['"Monsieur" means:', 'Miss', 'Mr or Sir', 'Madam', 'Teacher', 'B', 'Monsieur means Mr or Sir.'],
    ['"L\'école" means:', 'The house', 'The school', 'The church', 'The market', 'B', 'L\'école is the school.'],
    ['"Le livre" means:', 'The book', 'The pen', 'The table', 'The door', 'A', 'Le livre is the book.'],
    ['"Je m\'appelle" means:', 'My name is', 'How are you?', 'I am fine', 'Goodbye', 'A', 'Je m\'appelle introduces your name.'],
    ['"Comment ça va?" means:', 'What is your name?', 'How are you?', 'Where are you from?', 'How old are you?', 'B', 'It asks how someone is doing.'],
    ['"S\'il vous plaît" means:', 'Please', 'Thank you', 'Excuse me', 'Goodbye', 'A', 'S\'il vous plaît means please.'],
    ['"Pardon" means:', 'Thank you', 'Sorry or excuse me', 'Good morning', 'Yes', 'B', 'Pardon is used to apologise or get attention.'],
    ['"La classe" means:', 'The class', 'The kitchen', 'The garden', 'The shop', 'A', 'La classe is the classroom or class.'],
    ['"Le professeur" means:', 'The teacher', 'The student', 'The doctor', 'The driver', 'A', 'Le professeur is the teacher.'],
    ['"Bonjour madame" is used to greet a:', 'Lady', 'Gentleman', 'Baby', 'Pet', 'A', 'Madame addresses a female.'],
    ['"Deux" means:', 'Two', 'One', 'Three', 'Four', 'A', 'Deux is the number two.'],
    ['"Trois" means:', 'Two', 'Three', 'Four', 'Five', 'B', 'Trois is the number three.'],
    ['"Où est la bibliothèque?" means:', 'Where is the library?', 'Where is the market?', 'What time is it?', 'Where do you live?', 'A', 'Bibliothèque means library.'],
]],

'Digital Literacy' => ['JSS' => [
    ['A computer is an electronic device that:', 'processes data', 'cooks food', 'washes clothes', 'sweeps floors', 'A', 'Computers process data into information.'],
    ['Which of these is an input device?', 'Keyboard', 'Monitor', 'Printer', 'Speaker', 'A', 'A keyboard sends data into the computer.'],
    ['The "brain" of the computer is the:', 'CPU', 'Monitor', 'Mouse', 'Printer', 'A', 'The CPU carries out processing.'],
    ['Which of these is an output device?', 'Mouse', 'Monitor', 'Keyboard', 'Scanner', 'B', 'A monitor displays output to the user.'],
    ['The screen of a computer is called the:', 'Monitor', 'Keyboard', 'CPU', 'Hard drive', 'A', 'The monitor is the display screen.'],
    ['Computer programs are also called:', 'Software', 'Hardware', 'Furniture', 'Stationery', 'A', 'Programs/instructions are software.'],
    ['The mouse is used to:', 'point and click', 'print documents', 'store data', 'cool the system', 'A', 'The mouse controls the pointer.'],
    ['Which of these is used to store data?', 'Flash drive', 'Printer', 'Speaker', 'Keyboard', 'A', 'A flash drive stores files.'],
    ['The internet is used to:', 'connect computers worldwide', 'print paper', 'boil water', 'plant crops', 'A', 'The internet links computers globally.'],
    ['Which of these is a search engine?', 'Google', 'Microsoft Word', 'Paint', 'Calculator', 'A', 'Google searches the web.'],
    ['E-mail is used to:', 'send electronic messages', 'print letters', 'draw pictures', 'play music', 'A', 'Email delivers messages electronically.'],
    ['The device that prints documents is a:', 'Printer', 'Scanner', 'Monitor', 'Mouse', 'A', 'A printer produces paper copies.'],
    ['Turning on a computer is called:', 'Booting', 'Shutting down', 'Printing', 'Scanning', 'A', 'Starting the computer is booting.'],
    ['Which of these is a word processing program?', 'Microsoft Word', 'Facebook', 'Chrome', 'Photoshop', 'A', 'Word processors like MS Word create documents.'],
    ['Passwords are used to:', 'protect accounts', 'decorate screens', 'increase speed', 'print pages', 'A', 'Passwords secure user accounts.'],
    ['Which of these is a social media platform?', 'Facebook', 'Calculator', 'Paint', 'Notepad', 'A', 'Facebook is a social media network.'],
    ['A file is a:', 'unit of stored data', 'type of keyboard', 'screen saver', 'printer paper', 'A', 'Files hold data on the computer.'],
    ['Deleting unnecessary files helps to:', 'free up space', 'speed printing', 'damage the PC', 'remove the keyboard', 'A', 'Deleting files frees storage space.'],
    ['Which symbol is found in all e-mail addresses?', '@', '#', '$', '&', 'A', 'The @ symbol separates user from domain.'],
    ['Being safe online means:', 'not sharing personal details', 'meeting strangers', 'clicking unknown links', 'sharing passwords', 'A', 'Protecting personal data keeps you safe online.'],
]],

/* ============================== WAEC / SS ============================== */
/* Mathematics and English Language also have JSS banks above; the ".SS"
   keys are merged into the same subject key below to avoid overwriting. */

'Mathematics.SS' => ['SS' => [
    ['Solve the equation 3x - 5 = 10.', 'x = 3', 'x = 5', 'x = 7', 'x = 15', 'B', '3x = 15, so x = 5.'],
    ['Simplify (2x^2 y^3)^2.', '4x^4 y^6', '2x^4 y^6', '4x^2 y^3', '2x^2 y^6', 'A', 'Square each factor: 2^2 = 4, x^(2x2), y^(3x2).'],
    ['Evaluate log base 10 of 100.', '1', '2', '10', '100', 'B', '10^2 = 100, so log10(100) = 2.'],
    ['If f(x) = 2x + 1, find f(3).', '5', '6', '7', '9', 'C', 'f(3) = 2(3) + 1 = 7.'],
    ['Solve the quadratic equation x^2 - 5x + 6 = 0.', 'x = 1 or 6', 'x = 2 or 3', 'x = -2 or -3', 'x = 1 or 5', 'B', 'Factors: (x-2)(x-3) = 0.'],
    ['The gradient of the line y = 3x + 2 is:', '2', '3', '5', '-3', 'B', 'In y = mx + c, m is the gradient (3).'],
    ['The value of sin 30 degrees is:', '0.5', '0.707', '1', '0.866', 'A', 'sin 30 = 1/2 = 0.5.'],
    ['The area of a circle of radius 7 cm (take pi = 22/7) is:', '154 cm2', '44 cm2', '616 cm2', '22 cm2', 'A', 'Area = pi r^2 = (22/7)(49) = 154.'],
    ['Simplify (2/3) divided by (4/9).', '3/2', '2/3', '8/27', '4/9', 'A', '2/3 x 9/4 = 18/12 = 3/2.'],
    ['The mean of 3, 5, 7, 9 and 11 is:', '5', '6', '7', '9', 'C', 'Sum = 35; 35/5 = 7.'],
    ['If 2^x = 32, find x.', '4', '5', '6', '16', 'B', '2^5 = 32, so x = 5.'],
    ['The sum of the interior angles of a pentagon is:', '360 degrees', '450 degrees', '540 degrees', '720 degrees', 'C', '(n-2) x 180 = 3 x 180 = 540 for n = 5.'],
    ['Solve: 4x + 3 = 2x + 11.', 'x = 2', 'x = 4', 'x = 7', 'x = 8', 'B', '4x - 2x = 11 - 3; 2x = 8; x = 4.'],
    ['The inverse of the function y = 2x is:', 'y = x/2', 'y = 2/x', 'y = -2x', 'y = x^2', 'A', 'Swapping variables and solving gives y = x/2.'],
    ['A cone has how many faces?', '1', '2', '3', '4', 'B', 'A cone has a curved face and a circular base.'],
    ['The probability of getting a head when a fair coin is tossed is:', '1', '1/2', '1/4', '2/3', 'B', 'There is one head out of two outcomes.'],
    ['Simplify the square root of 144.', '12', '14', '72', '24', 'A', '12 x 12 = 144.'],
    ['The nth term of the sequence 3, 5, 7, 9, ... is:', '2n + 1', '2n - 1', 'n + 2', '3n', 'A', 'First differences are 2 and the first term is 3.'],
    ['Express 0.125 as a fraction in its lowest terms.', '1/8', '1/4', '5/4', '125/10', 'A', '0.125 = 125/1000 = 1/8.'],
    ['The volume of a cube of edge 3 cm is:', '9 cm3', '18 cm3', '27 cm3', '81 cm3', 'C', 'Volume = 3 x 3 x 3 = 27 cm3.'],
]],

'English Language.SS' => ['SS' => [
    ['Neither the teacher nor the students ___ present.', 'was', 'were', 'is', 'are', 'B', 'With neither...nor, the verb agrees with the nearer noun (students).'],
    ['Choose the word nearest in meaning to "obstinate".', 'Weak', 'Stubborn', 'Honest', 'Careless', 'B', 'Obstinate means stubbornly refusing to change.'],
    ['One of the boys ___ absent yesterday.', 'were', 'was', 'are', 'be', 'B', 'The subject is "one", which is singular.'],
    ['Choose the word opposite in meaning to "gullible".', 'Naive', 'Skeptical', 'Foolish', 'Trusting', 'B', 'Gullible means easily deceived; skeptical is its opposite.'],
    ['If I ___ you, I would have attended the lecture.', 'am', 'was', 'were', 'be', 'C', 'The subjunctive "were" is used after "if I".'],
    ['Choose the correctly spelled word.', 'Accomodation', 'Accommodation', 'Acommodation', 'Acomodation', 'B', '"Accommodation" has double m and double c.'],
    ['The news ___ good today.', 'are', 'were', 'is', 'have', 'C', '"News" is an uncountable singular noun.'],
    ['Choose the word nearest in meaning to "lucid".', 'Vague', 'Clear', 'Dull', 'Dark', 'B', 'Lucid means clear and easily understood.'],
    ['He has been living in Lagos ___ 2010.', 'for', 'since', 'from', 'during', 'B', 'A point in time (2010) takes "since".'],
    ['Choose the word opposite in meaning to "diligent".', 'Hardworking', 'Industrious', 'Lazy', 'Careful', 'C', 'Diligent means hardworking; lazy is its opposite.'],
    ['Each of the candidates ___ to write the examination.', 'have', 'has', 'are', 'were', 'B', '"Each" takes a singular verb.'],
    ['Choose the word nearest in meaning to "savage".', 'Gentle', 'Wild', 'Kind', 'Civilised', 'B', 'Savage means wild or fierce.'],
    ['The teacher, as well as the students, ___ going on the trip.', 'are', 'were', 'is', 'have', 'C', '"As well as" does not change the singular subject.'],
    ['The word "abstain" means:', 'take part in', 'refrain from', 'enjoy', 'accept', 'B', 'Abstain means to hold back from something.'],
    ['Choose the correct sentence.', 'She don\'t like tea.', 'She doesn\'t likes tea.', 'She does not like tea.', 'She do not like tea.', 'C', 'The correct negative form is "does not like".'],
    ['Choose the word nearest in meaning to "enormous".', 'Tiny', 'Huge', 'Average', 'Narrow', 'B', 'Enormous means very large or huge.'],
    ['Hardly had he arrived ___ it started raining.', 'when', 'than', 'that', 'then', 'A', 'The pattern is "Hardly... when...".'],
    ['Choose the word opposite in meaning to "transparent".', 'Clear', 'Opaque', 'Shiny', 'Bright', 'B', 'Opaque does not allow light through.'],
    ['The meeting was postponed ___ the chairman was absent.', 'because', 'so', 'but', 'although', 'A', '"Because" gives the reason for the postponement.'],
    ['Choose the correctly punctuated sentence.', 'Mr Okafor our teacher is kind.', 'Mr. Okafor, our teacher, is kind.', 'Mr Okafor, our teacher is kind.', 'Mr. Okafor our teacher, is kind.', 'B', 'The appositive "our teacher" is set off by commas.'],
]],

'Physics' => ['SS' => [
    ['The SI unit of force is the:', 'Joule', 'Newton', 'Watt', 'Pascal', 'B', 'Force is measured in newtons (N).'],
    ['Velocity is defined as:', 'distance per unit time', 'displacement per unit time', 'speed in any direction', 'rate of change of acceleration', 'B', 'Velocity is the rate of change of displacement.'],
    ['The acceleration due to gravity on Earth is approximately:', '9.8 m/s2', '8.9 m/s2', '10.8 m/s2', '3.0 m/s2', 'A', 'g is about 9.8 m/s2.'],
    ['The unit of power is the:', 'Joule', 'Newton', 'Watt', 'Volt', 'C', 'Power is measured in watts (W).'],
    ['Ohm\'s law is expressed as:', 'V = IR', 'P = VI', 'F = ma', 'E = mc2', 'A', 'Voltage equals current times resistance.'],
    ['Which of the following is a vector quantity?', 'Mass', 'Speed', 'Velocity', 'Energy', 'C', 'Velocity has both magnitude and direction.'],
    ['Sound waves cannot travel through:', 'air', 'water', 'steel', 'a vacuum', 'D', 'Sound needs a material medium.'],
    ['The unit of electric current is the:', 'Volt', 'Ampere', 'Ohm', 'Watt', 'B', 'Current is measured in amperes (A).'],
    ['Energy is measured in:', 'Newtons', 'Joules', 'Pascals', 'Hertz', 'B', 'Energy and work are measured in joules.'],
    ['The law of conservation of energy states that energy:', 'is created from nothing', 'can be destroyed', 'cannot be created or destroyed', 'is stored only in fuel', 'C', 'Energy is transformed, never destroyed.'],
    ['The first law of reflection states that the angle of incidence:', 'equals the angle of reflection', 'is always 90 degrees', 'is double the angle of reflection', 'depends on the colour of light', 'A', 'Angle of incidence equals angle of reflection.'],
    ['The instrument used to measure electric current is an:', 'Voltmeter', 'Ammeter', 'Ohmmeter', 'Barometer', 'B', 'An ammeter measures current in series.'],
    ['Frequency is measured in:', 'Hertz', 'Metres', 'Joules', 'Seconds', 'A', 'Frequency is measured in hertz (Hz).'],
    ['The weight of a body is calculated as:', 'mass x gravity', 'mass / gravity', 'mass + gravity', 'gravity / mass', 'A', 'Weight W = mg.'],
    ['The unit of electrical resistance is the:', 'Volt', 'Ohm', 'Ampere', 'Watt', 'B', 'Resistance is measured in ohms.'],
    ['A concave mirror is used to:', 'diverge light only', 'converge light and magnify images', 'reflect no light', 'absorb all light', 'B', 'Concave mirrors converge light rays.'],
    ['The speed of light in a vacuum is approximately:', '3 x 10^8 m/s', '3 x 10^6 m/s', '330 m/s', '3 x 10^10 m/s', 'A', 'Light travels at 3 x 10^8 m/s in a vacuum.'],
    ['Momentum is defined as:', 'mass x velocity', 'mass x acceleration', 'force x distance', 'mass / velocity', 'A', 'Momentum p = mv.'],
    ['Which of the following is a transverse wave?', 'Sound', 'Light', 'Ultrasound', 'Seismic P-wave', 'B', 'Light is a transverse electromagnetic wave.'],
    ['Hooke\'s law states that extension is proportional to:', 'force', 'mass', 'temperature', 'time', 'A', 'Within limits, extension is proportional to force.'],
]],

'Chemistry' => ['SS' => [
    ['The atomic number of an element is the number of:', 'Protons', 'Neutrons', 'Electrons only', 'Nucleons', 'A', 'Atomic number equals the number of protons.'],
    ['The chemical symbol for sodium is:', 'So', 'Na', 'S', 'N', 'B', 'Sodium is Na, from the Latin natrium.'],
    ['Water is composed of:', 'hydrogen and oxygen', 'hydrogen and nitrogen', 'oxygen and carbon', 'salt and air', 'A', 'Water is H2O: hydrogen and oxygen.'],
    ['The pH of a neutral solution is:', '0', '7', '10', '14', 'B', 'pH 7 is neutral.'],
    ['The chemical formula of sodium chloride is:', 'NaCl', 'NaCl2', 'Na2Cl', 'NaO', 'A', 'Sodium chloride is NaCl.'],
    ['The valency of oxygen is:', '1', '2', '3', '4', 'B', 'Oxygen forms two bonds.'],
    ['Which of the following is a chemical change?', 'Melting ice', 'Rusting of iron', 'Boiling water', 'Dissolving sugar', 'B', 'Rusting forms a new substance, iron oxide.'],
    ['The gas commonly used in fire extinguishers is:', 'Oxygen', 'Carbon dioxide', 'Hydrogen', 'Ammonia', 'B', 'CO2 smothers fire by excluding oxygen.'],
    ['An element with atomic number 6 is:', 'Carbon', 'Nitrogen', 'Oxygen', 'Boron', 'A', 'Carbon has atomic number 6.'],
    ['The symbol for gold is:', 'Go', 'Gd', 'Au', 'Ag', 'C', 'Gold is Au, from the Latin aurum.'],
    ['Acids turn blue litmus paper:', 'Red', 'Blue', 'Green', 'Colourless', 'A', 'Acids turn blue litmus red.'],
    ['The mole is the SI unit of:', 'mass', 'amount of substance', 'volume', 'energy', 'B', 'The mole measures amount of substance.'],
    ['Which of the following is a noble gas?', 'Oxygen', 'Neon', 'Chlorine', 'Hydrogen', 'B', 'Neon is in group 18 (noble gases).'],
    ['The number of neutrons in carbon-12 is:', '6', '8', '12', '14', 'A', 'Mass 12 minus atomic number 6 = 6 neutrons.'],
    ['Bases turn red litmus paper:', 'Blue', 'Red', 'Yellow', 'Green', 'A', 'Bases turn red litmus blue.'],
    ['The chemical formula of water is:', 'H2O', 'CO2', 'O2', 'HO2', 'A', 'Water is H2O.'],
    ['Which of the following elements is a metal?', 'Oxygen', 'Iron', 'Sulphur', 'Chlorine', 'B', 'Iron is a metal.'],
    ['A salt is formed when an acid reacts with a:', 'Base', 'Non-metal', 'Gas', 'Noble gas', 'A', 'Acid + base neutralisation gives salt and water.'],
    ['The relative atomic mass of hydrogen is:', '1', '2', '12', '16', 'A', 'Hydrogen has relative atomic mass 1.'],
    ['The change of a liquid into a gas is called:', 'Condensation', 'Evaporation', 'Sublimation', 'Freezing', 'B', 'Evaporation converts liquid to gas.'],
]],

'Biology' => ['SS' => [
    ['The powerhouse of the cell is the:', 'Nucleus', 'Mitochondrion', 'Ribosome', 'Golgi body', 'B', 'The mitochondrion produces ATP.'],
    ['Photosynthesis takes place in the:', 'Mitochondrion', 'Chloroplast', 'Nucleus', 'Ribosome', 'B', 'Chloroplasts contain chlorophyll for photosynthesis.'],
    ['The organelle that controls cell activities is the:', 'Nucleus', 'Cytoplasm', 'Cell wall', 'Vacuole', 'A', 'The nucleus directs cell activities.'],
    ['Which of the following is a unicellular organism?', 'Amoeba', 'Earthworm', 'Mushroom', 'Mango tree', 'A', 'Amoeba is a single-celled organism.'],
    ['The blood cells that fight infection are the:', 'Red blood cells', 'White blood cells', 'Platelets', 'Plasma', 'B', 'White blood cells defend against disease.'],
    ['Cell division for growth and repair is called:', 'Mitosis', 'Meiosis', 'Osmosis', 'Diffusion', 'A', 'Mitosis produces identical cells for growth.'],
    ['The functional unit of the kidney is the:', 'Neuron', 'Nephron', 'Alveolus', 'Villus', 'B', 'Nephrons filter blood in the kidney.'],
    ['Which of the following is a carbohydrate?', 'Starch', 'Oil', 'Meat', 'Salt', 'A', 'Starch is a carbohydrate.'],
    ['The male reproductive cell is the:', 'Ovum', 'Sperm', 'Zygote', 'Embryo', 'B', 'The sperm is the male gamete.'],
    ['Enzymes are best described as:', 'biological catalysts', 'energy molecules', 'structural proteins', 'vitamins', 'A', 'Enzymes speed up reactions in cells.'],
    ['The enzyme that digests starch in the mouth is:', 'Salivary amylase', 'Pepsin', 'Lipase', 'Trypsin', 'A', 'Salivary amylase begins starch digestion.'],
    ['The green pigment in plants is:', 'Chlorophyll', 'Haemoglobin', 'Melanin', 'Keratin', 'A', 'Chlorophyll makes plants green.'],
    ['The system that transports blood is the:', 'Circulatory system', 'Digestive system', 'Nervous system', 'Skeletal system', 'A', 'Blood is carried by the circulatory system.'],
    ['Gaseous exchange in the lungs occurs in the:', 'Alveoli', 'Bronchi', 'Trachea', 'Larynx', 'A', 'Alveoli are the site of gas exchange.'],
    ['The basic unit of the nervous system is the:', 'Neuron', 'Nephron', 'Alveolus', 'Axon only', 'A', 'Neurons transmit nerve impulses.'],
    ['Which of the following is a fungus?', 'Mushroom', 'Moss', 'Fern', 'Alga', 'A', 'Mushrooms are fungi.'],
    ['The nutrient that supplies the body with the most energy is:', 'Carbohydrates', 'Proteins', 'Vitamins', 'Mineral salts', 'A', 'Carbohydrates are the main energy source.'],
    ['The removal of waste products from the body is called:', 'Excretion', 'Digestion', 'Respiration', 'Circulation', 'A', 'Excretion removes metabolic waste.'],
    ['The human heart has how many chambers?', '2', '3', '4', '5', 'C', 'The heart has two atria and two ventricles.'],
    ['Osmosis is the movement of water across a:', 'semi-permeable membrane', 'impermeable membrane', 'nuclear membrane only', 'cell wall', 'A', 'Osmosis is water movement through a semi-permeable membrane.'],
]],

'Further Mathematics' => ['SS' => [
    ['The expansion of (1 + x)^3 is:', '1 + 3x + 3x^2 + x^3', '1 + 2x + x^2', '1 + 3x + x^3', '1 + x^3', 'A', 'Binomial expansion of (1+x)^3.'],
    ['If z = 3 + 4i, the modulus of z is:', '5', '7', '12', '25', 'A', '|z| = sqrt(9 + 16) = 5.'],
    ['The derivative of x^3 is:', '3x^2', '3x', 'x^2', 'x^4/4', 'A', 'd/dx x^n = nx^(n-1).'],
    ['The determinant of the matrix [[1,2],[3,4]] is:', '-2', '2', '10', '-10', 'A', '1x4 - 2x3 = 4 - 6 = -2.'],
    ['The integral of x with respect to x is:', 'x^2/2 + c', 'x + c', '2x + c', 'x^2 + c', 'A', 'Integral of x dx = x^2/2 + c.'],
    ['The sum of the first n terms of an arithmetic progression is:', 'n/2 [2a + (n-1)d]', 'a + (n-1)d', 'n(a + d)', 'ar^(n-1)', 'A', 'Standard formula for the sum of an AP.'],
    ['A quadratic equation has at most how many roots?', '1', '2', '3', '4', 'B', 'A quadratic has at most two roots.'],
    ['The identity sin^2(x) + cos^2(x) equals:', '1', '0', '2', 'sin x', 'A', 'This is a fundamental trigonometric identity.'],
    ['The modulus of 2 - 2i is:', '2', '2 square root of 2', '4', '0', 'B', 'sqrt(4 + 4) = sqrt(8) = 2 sqrt 2.'],
    ['The gradient of the tangent to y = x^2 at x = 1 is:', '1', '2', '3', '0', 'B', 'dy/dx = 2x = 2 at x = 1.'],
    ['The value of 5! (5 factorial) is:', '25', '120', '125', '15', 'B', '5! = 5x4x3x2x1 = 120.'],
    ['The equation of a straight line with gradient m and intercept c is:', 'y = mx + c', 'y = c/m', 'x = my + c', 'y = m/c', 'A', 'Gradient-intercept form of a line.'],
    ['The inverse of a matrix exists only if its determinant is:', 'Non-zero', 'Zero', 'Negative', 'One', 'A', 'A matrix is invertible when the determinant is non-zero.'],
    ['The sum of the roots of ax^2 + bx + c = 0 is:', '-b/a', 'b/a', 'c/a', 'a/b', 'A', 'Sum of roots = -b/a.'],
    ['The nth term of a geometric progression is:', 'ar^(n-1)', 'a + (n-1)d', 'n/2(a+l)', 'a r^n', 'A', 'GP nth term = ar^(n-1).'],
    ['Evaluate the integral from 0 to 1 of 2x dx.', '1', '2', '0', '4', 'A', 'Integral = [x^2] from 0 to 1 = 1.'],
    ['If tan(x) = 1 and x is acute, x equals:', '30 degrees', '45 degrees', '60 degrees', '90 degrees', 'B', 'tan 45 = 1.'],
    ['The degree of the polynomial x^5 + 2x^3 + 1 is:', '3', '5', '6', '1', 'B', 'The highest power is 5.'],
    ['The distance between the points (0,0) and (3,4) is:', '5', '7', '1', '12', 'A', 'sqrt(9 + 16) = 5.'],
    ['The derivative of sin x is:', 'cos x', '-cos x', '-sin x', 'sec x', 'A', 'd/dx sin x = cos x.'],
]],

'Civic Education' => ['SS' => [
    ['A citizen of a country is a person who:', 'belongs to the country and enjoys its rights', 'visits the country', 'works in the country only', 'was born abroad', 'A', 'Citizenship confers rights and duties.'],
    ['The process by which a foreigner becomes a citizen is called:', 'Naturalisation', 'Denaturalisation', 'Expatriation', 'Enfranchisement', 'A', 'Naturalisation grants citizenship to aliens.'],
    ['Which of the following is a civic responsibility?', 'Voting', 'Littering', 'Tax evasion', 'Arson', 'A', 'Voting is a fundamental civic duty.'],
    ['The rule of law means:', 'everyone is subject to the law', 'only the poor obey the law', 'leaders are above the law', 'the law is optional', 'A', 'No one is above the law.'],
    ['Which of these is an attribute of a good citizen?', 'Patriotism', 'Indiscipline', 'Greed', 'Apathy', 'A', 'Patriotism is loyalty to one\'s country.'],
    ['Nationalism means:', 'love and devotion to one\'s country', 'hatred of other countries', 'travelling abroad', 'learning foreign languages', 'A', 'Nationalism is patriotic loyalty.'],
    ['The body responsible for conducting elections in Nigeria is:', 'INEC', 'EFCC', 'NAFDAC', 'NUC', 'A', 'INEC organises Nigerian elections.'],
    ['Which of the following is a fundamental human right?', 'Right to life', 'Right to cheat', 'Right to steal', 'Right to fight', 'A', 'The right to life is fundamental.'],
    ['Fundamental human rights in Nigeria are guaranteed by the:', 'Constitution', 'Police force', 'Press', 'Markets', 'A', 'The Constitution protects human rights.'],
    ['Civil society organisations include:', 'NGOs and advocacy groups', 'political parties only', 'the army', 'the judiciary', 'A', 'Civil society includes non-governmental groups.'],
    ['Democracy is best defined as government:', 'by the people', 'of the rich', 'by the military', 'by a monarch', 'A', 'Democracy means rule by the people.'],
    ['Which of the following is a function of government?', 'Providing public services', 'Selling in markets', 'Farming only', 'Racing cars', 'A', 'Governments provide services for citizens.'],
    ['Queuing up patiently at the bank is an example of:', 'Orderliness', 'Rudeness', 'Impatience', 'Lawlessness', 'A', 'Orderliness is orderly behaviour in public.'],
    ['The value of selflessness means:', 'putting others before oneself', 'seeking personal gain', 'ignoring others', 'being greedy', 'A', 'Selflessness is service above self.'],
    ['The agency that fights corruption in Nigeria is the:', 'EFCC', 'NIMET', 'NAFDAC', 'NPA', 'A', 'The EFCC investigates economic crimes.'],
    ['An election is a process of:', 'choosing leaders', 'collecting taxes', 'making laws', 'administering justice', 'A', 'Elections choose political leaders.'],
    ['The Constitution is the:', 'supreme law of the land', 'code of the army', 'book of the court', 'list of citizens', 'A', 'The Constitution is supreme.'],
    ['Which of the following is a fundamental duty of citizens?', 'Obeying the law', 'Vandalising property', 'Avoiding taxes', 'Spreading rumours', 'A', 'Citizens must obey the law.'],
    ['Political apathy means:', 'lack of interest in politics', 'active participation', 'joining a party', 'campaigning', 'A', 'Apathy is indifference to politics.'],
    ['The three arms of government are:', 'legislature, executive and judiciary', 'army, navy and air force', 'local, state and federal', 'Senate, House and Court', 'A', 'Government is divided into three arms.'],
]],

'Economics' => ['SS' => [
    ['Economics is the study of:', 'allocation of scarce resources', 'money only', 'politics', 'geography', 'A', 'Economics deals with scarcity and choice.'],
    ['Scarcity in economics means:', 'limited resources against unlimited wants', 'lack of money', 'too many goods', 'abundant resources', 'A', 'Scarcity arises from limited resources.'],
    ['The factor of production that provides human effort is:', 'Labour', 'Land', 'Capital', 'Enterprise', 'A', 'Labour is human effort in production.'],
    ['Demand is defined as:', 'quantity consumers are willing and able to buy', 'quantity producers make', 'the price of goods', 'the stock in a warehouse', 'A', 'Demand includes willingness and ability to buy.'],
    ['The law of demand states that price and quantity demanded:', 'move in opposite directions', 'move in the same direction', 'are unrelated', 'are always equal', 'A', 'Higher price lowers demand.'],
    ['A market structure with a single seller is a:', 'Monopoly', 'Perfect competition', 'Oligopoly', 'Monopsony', 'A', 'A monopoly has one seller.'],
    ['Inflation is:', 'a persistent rise in the general price level', 'a fall in prices', 'an increase in wages', 'a rise in production', 'A', 'Inflation erodes the value of money.'],
    ['Gross Domestic Product (GDP) measures:', 'total value of goods and services produced', 'total government spending', 'total exports', 'total savings', 'A', 'GDP is the value of national output.'],
    ['The reward for capital is:', 'Interest', 'Rent', 'Profit', 'Wages', 'A', 'Capital earns interest.'],
    ['Opportunity cost is:', 'the next best alternative forgone', 'the money spent', 'total cost', 'sunk cost', 'A', 'Opportunity cost is the foregone alternative.'],
    ['Supply refers to:', 'quantity producers offer at various prices', 'quantity consumers demand', 'the price level', 'total demand', 'A', 'Supply is what producers offer.'],
    ['Which of the following is a direct tax?', 'Personal income tax', 'Value Added Tax', 'Import duty', 'Excise duty', 'A', 'Income tax is levied directly on income.'],
    ['The central bank of Nigeria is the:', 'CBN', 'UBA', 'GTBank', 'ECOWAS Bank', 'A', 'The Central Bank of Nigeria is the apex bank.'],
    ['A budget is a:', 'financial plan of income and expenditure', 'record of sales', 'bank statement', 'price list', 'A', 'Budgets plan income and spending.'],
    ['The reward for land is:', 'Rent', 'Interest', 'Wages', 'Profit', 'A', 'Land earns rent.'],
    ['Equilibrium price occurs where:', 'demand equals supply', 'demand is greater than supply', 'supply is greater than demand', 'price is zero', 'A', 'Equilibrium clears the market.'],
    ['Which of the following is a renewable resource?', 'Forests', 'Crude oil', 'Coal', 'Natural gas', 'A', 'Forests can be regenerated.'],
    ['Unemployment means:', 'people able and willing to work without jobs', 'people resting', 'workers on strike', 'retired persons', 'A', 'Unemployment is joblessness among able workers.'],
    ['Trade between countries is called:', 'International trade', 'Local trade', 'Retail trade', 'Home trade', 'A', 'International trade crosses borders.'],
    ['The reward for enterprise (entrepreneur) is:', 'Profit', 'Rent', 'Interest', 'Wages', 'A', 'Entrepreneurs earn profit.'],
]],

'Government' => ['SS' => [
    ['Government as an institution refers to:', 'the machinery of the state', 'a political party', 'a group of students', 'the media', 'A', 'Government is the organised machinery of the state.'],
    ['The arm of government that makes laws is the:', 'Legislature', 'Executive', 'Judiciary', 'Police', 'A', 'The legislature enacts laws.'],
    ['A system where power is shared between central and regional governments is:', 'Federalism', 'Confederalism', 'Dictatorship', 'Monarchy', 'A', 'Federalism shares powers between levels of government.'],
    ['The head of state in a presidential system is the:', 'President', 'Prime Minister', 'Speaker', 'Chief Justice', 'A', 'The President is both head of state and government.'],
    ['The party that forms government must win a:', 'majority of seats', 'minority of votes', 'referendum', 'coup', 'A', 'The majority party forms the government.'],
    ['Sovereignty means:', 'supreme power of the state', 'limited power', 'shared power', 'local power', 'A', 'The state exercises supreme authority.'],
    ['A constitution is:', 'the supreme law of the land', 'a book of history', 'a list of parties', 'a trade agreement', 'A', 'The constitution is supreme.'],
    ['The principle of separation of powers was popularised by:', 'Montesquieu', 'Locke', 'Rousseau', 'Marx', 'A', 'Montesquieu developed the doctrine.'],
    ['The judiciary is responsible for:', 'interpreting the law', 'making laws', 'executing laws', 'collecting taxes', 'A', 'Courts interpret the law.'],
    ['Universal adult suffrage means:', 'all adults can vote', 'only men can vote', 'only the rich can vote', 'no one votes', 'A', 'Suffrage extends to all adults.'],
    ['The executive arm of government is responsible for:', 'implementing laws', 'interpreting laws', 'passing laws', 'repealing laws', 'A', 'The executive implements policy.'],
    ['A bicameral legislature has:', 'two chambers', 'one chamber', 'three chambers', 'no chamber', 'A', 'Bicameral means two houses.'],
    ['Checks and balances are designed to prevent:', 'abuse of power', 'voting', 'elections', 'law making', 'A', 'They limit each arm of government.'],
    ['The electorate refers to:', 'all registered voters', 'politicians only', 'government officials', 'the judiciary', 'A', 'The electorate comprises voters.'],
    ['Public opinion is the:', 'views of the people on issues', 'opinion of the president', 'view of the media only', 'report of the police', 'A', 'Public opinion is citizens\' collective view.'],
    ['The primary aim of a political party is to:', 'win elections and govern', 'make profit', 'organise festivals', 'build schools', 'A', 'Parties seek political power.'],
    ['The First Republic of Nigeria began in:', '1960', '1963', '1966', '1979', 'A', 'The First Republic started in 1960.'],
    ['Delegated legislation is law made by:', 'the executive or its agencies', 'the legislature only', 'the judiciary', 'political parties', 'A', 'Parliament delegates law-making to bodies.'],
    ['A coup is a:', 'forceful takeover of government', 'peaceful election', 'judicial ruling', 'referendum', 'A', 'Coups are unconstitutional seizures of power.'],
    ['Fundamental human rights are protected by the:', 'Constitution', 'Civil service', 'Political parties', 'Trade unions', 'A', 'The Constitution protects these rights.'],
]],

'Geography' => ['SS' => [
    ['The largest continent in the world is:', 'Asia', 'Africa', 'Europe', 'America', 'A', 'Asia is the largest continent.'],
    ['The line of longitude at 0 degrees is the:', 'Greenwich Meridian', 'Equator', 'Tropic of Cancer', 'International Date Line', 'A', '0 degrees longitude is the Prime Meridian.'],
    ['Which of the following is a sedimentary rock?', 'Limestone', 'Granite', 'Basalt', 'Marble', 'A', 'Limestone forms from sediment.'],
    ['The scientific study of weather is called:', 'Meteorology', 'Geology', 'Astronomy', 'Seismology', 'A', 'Meteorology studies weather.'],
    ['The Nile River flows through which of these countries?', 'Egypt', 'Ghana', 'South Africa', 'Brazil', 'A', 'The Nile passes through Egypt.'],
    ['The line of latitude at 0 degrees is the:', 'Equator', 'Greenwich Meridian', 'Tropic of Capricorn', 'North Pole', 'A', 'The Equator is 0 degrees latitude.'],
    ['Which of the following is a renewable resource?', 'Solar energy', 'Crude oil', 'Coal', 'Natural gas', 'A', 'Solar energy is renewable.'],
    ['The largest ocean in the world is the:', 'Pacific', 'Atlantic', 'Indian', 'Arctic', 'A', 'The Pacific is the largest ocean.'],
    ['Population density is measured as:', 'people per unit area', 'total births', 'total deaths', 'people per family', 'A', 'Density = population / area.'],
    ['The vegetation of the Sahara region is mainly:', 'Desert', 'Tropical rainforest', 'Savanna', 'Mangrove', 'A', 'The Sahara is a desert region.'],
    ['Rainfall is measured with a:', 'Rain gauge', 'Thermometer', 'Barometer', 'Hygrometer', 'A', 'A rain gauge collects rainfall.'],
    ['Relief on a map is shown using:', 'Contour lines', 'Roads', 'Rivers', 'Cities', 'A', 'Contours show elevation.'],
    ['The capital of Nigeria is:', 'Abuja', 'Lagos', 'Kano', 'Ibadan', 'A', 'Abuja is the federal capital.'],
    ['Soil erosion is caused mainly by:', 'Wind and water', 'Sunlight only', 'Rocks', 'Machines', 'A', 'Wind and water erode the soil.'],
    ['The Tropic of Cancer is located at approximately:', '23.5 degrees North', '0 degrees', '23.5 degrees South', '66.5 degrees North', 'A', 'The Tropic of Cancer is 23.5 N.'],
    ['Which mineral is most abundant in the Niger Delta?', 'Crude oil', 'Gold', 'Iron ore', 'Tin', 'A', 'Crude oil is found in the Niger Delta.'],
    ['The scale of a map shows:', 'the ratio of map distance to ground distance', 'the size of the map', 'the number of colours', 'the map title', 'A', 'Scale relates map to ground.'],
    ['Tropical rainforest is characterised by:', 'dense evergreen trees', 'scanty grass', 'bare rocks', 'snow', 'A', 'Rainforests are dense and evergreen.'],
    ['The longest river in the world is the:', 'Nile', 'Amazon', 'Niger', 'Mississippi', 'A', 'The Nile is the longest river.'],
    ['Urbanisation means:', 'movement of people to cities', 'movement to villages', 'construction of roads', 'rural farming', 'A', 'Urbanisation is cityward migration.'],
]],

'Literature in English' => ['SS' => [
    ['A poem of fourteen lines is a:', 'Sonnet', 'Ballad', 'Limerick', 'Ode', 'A', 'A sonnet has fourteen lines.'],
    ['The main character in a literary work is the:', 'Protagonist', 'Antagonist', 'Narrator', 'Audience', 'A', 'The protagonist is the central character.'],
    ['"He is as strong as a lion" is an example of a:', 'Simile', 'Metaphor', 'Hyperbole', 'Alliteration', 'A', 'Similes use "like" or "as".'],
    ['A biography of a person written by that person is an:', 'Autobiography', 'Obituary', 'Epitaph', 'Biography', 'A', 'An autobiography is self-written.'],
    ['A play is structurally divided into:', 'Acts and scenes', 'Stanzas and lines', 'Chapters and verses', 'Parts and scenes', 'A', 'Plays are divided into acts and scenes.'],
    ['The central idea or message of a literary work is its:', 'Theme', 'Plot', 'Setting', 'Tone', 'A', 'Theme is the main message.'],
    ['A novel is an example of:', 'Prose fiction', 'Poetry', 'Drama', 'Epic', 'A', 'Novels are prose fiction.'],
    ['"The wind whispered through the trees" is an example of:', 'Personification', 'Simile', 'Irony', 'Alliteration', 'A', 'The wind is given human qualities.'],
    ['The person who tells the story in a narrative is the:', 'Narrator', 'Protagonist', 'Character', 'Reader', 'A', 'The narrator relates the story.'],
    ['A short story usually has:', 'a single plot and few characters', 'many subplots', 'no characters', 'no theme', 'A', 'Short stories are compact and focused.'],
    ['Drama as a genre is meant to be:', 'performed on stage', 'read silently only', 'sung', 'danced', 'A', 'Drama is written for performance.'],
    ['Exaggeration used for effect is a figure of speech called:', 'Hyperbole', 'Understatement', 'Irony', 'Paradox', 'A', 'Hyperbole is deliberate exaggeration.'],
    ['The sequence of events in a story is the:', 'Plot', 'Setting', 'Theme', 'Diction', 'A', 'Plot is the arrangement of events.'],
    ['A biography is a life story written by:', 'another person', 'the subject', 'a publisher', 'a reviewer', 'A', 'Biographies are written by others.'],
    ['"The classroom was a zoo" is an example of a:', 'Metaphor', 'Simile', 'Pun', 'Euphemism', 'A', 'It compares directly without "like" or "as".'],
    ['The writer of a play is called a:', 'Playwright', 'Novelist', 'Poet', 'Editor', 'A', 'A playwright writes plays.'],
    ['Which of the following is a genre of literature?', 'Poetry', 'Grammar', 'Spelling', 'Reading', 'A', 'Poetry is one of the literary genres.'],
    ['The repetition of initial consonant sounds is:', 'Alliteration', 'Assonance', 'Rhyme', 'Onomatopoeia', 'A', 'Alliteration repeats initial sounds.'],
    ['The atmosphere or feeling of a literary work is its:', 'Mood', 'Plot', 'Character', 'Theme', 'A', 'Mood is the emotional atmosphere.'],
    ['A long narrative poem about heroic deeds is an:', 'Epic', 'Sonnet', 'Limerick', 'Elegy', 'A', 'Epics recount heroic exploits.'],
]],

'Commerce' => ['SS' => [
    ['Commerce is concerned with the:', 'exchange of goods and services', 'production only', 'consumption only', 'advertising only', 'A', 'Commerce facilitates the exchange of goods and services.'],
    ['Trade refers to the:', 'buying and selling of goods and services', 'production of goods', 'transport of goods', 'storage of goods', 'A', 'Trade is exchange through buying and selling.'],
    ['The chain of distribution in order is:', 'producer, wholesaler, retailer, consumer', 'wholesaler, producer, consumer, retailer', 'retailer, producer, wholesaler, consumer', 'consumer, retailer, producer, wholesaler', 'A', 'Goods move from producer to consumer through middlemen.'],
    ['The document that is evidence of ownership of goods carried by sea is the:', 'Bill of lading', 'Invoice', 'Receipt', 'Cheque', 'A', 'The bill of lading is a document of title.'],
    ['A cheque is a:', 'written order to a bank to pay a sum', 'promise to pay in future', 'receipt of payment', 'price list', 'A', 'A cheque orders the bank to pay.'],
    ['The main purpose of advertising is to:', 'promote goods and services', 'increase costs', 'reduce sales', 'hide products', 'A', 'Advertising promotes products.'],
    ['Which of the following is a means of payment?', 'Bank draft', 'Stock', 'Share', 'Debenture', 'A', 'Bank drafts are used to make payments.'],
    ['Insurance is best described as a:', 'device for transferring risk', 'means of saving', 'way of investing', 'form of taxation', 'A', 'Insurance transfers risk from the insured to the insurer.'],
    ['A person who buys goods for personal use is a:', 'Consumer', 'Producer', 'Wholesaler', 'Agent', 'A', 'The consumer is the final user.'],
    ['The stock exchange deals in:', 'Shares and securities', 'Farm products', 'Household goods', 'Crude oil', 'A', 'The stock exchange trades securities.'],
    ['An agent who buys and sells on behalf of others for commission is a:', 'Broker', 'Wholesaler', 'Retailer', 'Hawker', 'A', 'Brokers transact for commission.'],
    ['Wholesale trade involves selling goods in:', 'large quantities', 'small units', 'single pieces', 'hidden lots', 'A', 'Wholesalers sell in bulk.'],
    ['Which of the following aids communication in commerce?', 'Postal and telecommunication services', 'Roads only', 'Warehouses', 'Banks', 'A', 'Communication aids connect traders.'],
    ['Which of the following is a mode of transport?', 'Rail', 'Invoice', 'Warehouse', 'Cheque', 'A', 'Rail is a transport mode.'],
    ['Hire purchase means paying for goods:', 'in instalments', 'in full at once', 'never', 'in kind', 'A', 'Hire purchase spreads payment over time.'],
    ['An invoice is a document that shows:', 'goods supplied and amount payable', 'money received only', 'staff salaries', 'company shares', 'A', 'Invoices detail purchases and charges.'],
    ['Export trade means selling goods:', 'to foreign countries', 'within the country', 'at the market', 'by auction', 'A', 'Exports are goods sold abroad.'],
    ['A warehouse is a place for:', 'storing goods', 'selling shares', 'repairing cars', 'printing money', 'A', 'Warehouses store goods.'],
    ['A partnership business is owned by:', 'two to twenty persons', 'one person only', 'shareholders', 'the government', 'A', 'Partnerships have two to twenty partners.'],
    ['The role of commerce in the economy is to:', 'facilitate the exchange of goods and services', 'produce raw materials', 'collect taxes', 'make laws', 'A', 'Commerce oils the wheel of trade.'],
]],

'Financial Accounting' => ['SS' => [
    ['Accounting is the process of:', 'recording and reporting financial transactions', 'selling goods', 'manufacturing products', 'paying workers', 'A', 'Accounting records and reports financial data.'],
    ['The double entry principle states that:', 'every debit has a corresponding credit', 'every entry is a debit', 'every entry is a credit', 'debits equal cash', 'A', 'Double entry requires a debit and a credit.'],
    ['Which of the following is an asset?', 'Cash', 'Loan', 'Creditors', 'Expenses', 'A', 'Cash is an asset owned by the business.'],
    ['A liability is:', 'an amount owed by the business', 'property of the business', 'money invested', 'profit earned', 'A', 'Liabilities are amounts the business owes.'],
    ['The accounting equation is:', 'Assets = Liabilities + Capital', 'Assets = Capital - Liabilities', 'Liabilities = Assets + Capital', 'Capital = Assets + Liabilities', 'A', 'Assets equal liabilities plus capital.'],
    ['The cash book records:', 'cash and bank transactions', 'only credit sales', 'only purchases', 'wages paid', 'A', 'The cash book covers cash and bank.'],
    ['A trial balance is prepared to test the:', 'arithmetical accuracy of the ledger', 'profit of the business', 'bank balance', 'value of stock', 'A', 'The trial balance checks debit/credit totals.'],
    ['Depreciation is the:', 'reduction in value of an asset over time', 'increase in value of an asset', 'cost of a new asset', 'price of goods', 'A', 'Depreciation spreads an asset\'s cost over its life.'],
    ['Which of the following is an expense?', 'Rent', 'Cash', 'Stock', 'Debtors', 'A', 'Rent is a business expense.'],
    ['Capital refers to the amount:', 'invested by the owner', 'owed to creditors', 'spent on rent', 'of bank loans', 'A', 'Capital is the owner\'s investment.'],
    ['A debit entry increases:', 'assets and expenses', 'liabilities', 'capital', 'income', 'A', 'Debits increase assets and expenses.'],
    ['The balance sheet shows the:', 'financial position of the business', 'daily sales', 'wages of staff', 'cost of production', 'A', 'The balance sheet reports assets, liabilities and capital.'],
    ['Petty cash is used for:', 'small day-to-day expenses', 'buying buildings', 'paying salaries', 'settling bank loans', 'A', 'Petty cash covers minor expenses.'],
    ['Credit sales are recorded in the:', 'Sales journal', 'Purchases journal', 'Cash book', 'Wages book', 'A', 'The sales journal records credit sales.'],
    ['A creditor is someone to whom the business:', 'owes money', 'lends money', 'sells goods', 'gives gifts', 'A', 'Creditors are owed money by the business.'],
    ['The profit and loss account shows:', 'income and expenses for the period', 'assets and liabilities', 'cash receipts', 'bank balance', 'A', 'It summarises revenue and expenses.'],
    ['Closing stock is reported in the:', 'Balance sheet', 'Wages book', 'Cash book', 'Sales journal', 'A', 'Closing stock is an asset on the balance sheet.'],
    ['A bank overdraft is classified as a:', 'Liability', 'Fixed asset', 'Current asset', 'Capital', 'A', 'An overdraft is money owed to the bank.'],
    ['The source document for money received is the:', 'Receipt', 'Cheque stub', 'Delivery note', 'Invoice', 'A', 'Receipts evidence cash received.'],
    ['Gross profit is calculated as:', 'sales - cost of goods sold', 'sales + purchases', 'net profit - expenses', 'capital + sales', 'A', 'Gross profit = sales less cost of goods sold.'],
]],

'Agricultural Science' => ['SS' => [
    ['Agriculture is defined as the:', 'cultivation of crops and rearing of animals', 'processing of food', 'buying of farm produce', 'transport of goods', 'A', 'Agriculture involves crop and animal production.'],
    ['Which of the following is a cereal crop?', 'Maize', 'Beans', 'Groundnut', 'Cassava', 'A', 'Maize is a cereal (grain) crop.'],
    ['Crop rotation helps to:', 'maintain soil fertility', 'reduce rainfall', 'increase weeds', 'destroy crops', 'A', 'Rotation conserves soil nutrients.'],
    ['Which of the following is a legume?', 'Cowpea', 'Maize', 'Rice', 'Sorghum', 'A', 'Cowpea is a leguminous crop.'],
    ['The rearing of fish is called:', 'Aquaculture', 'Horticulture', 'Apiculture', 'Sericulture', 'A', 'Fish farming is aquaculture.'],
    ['Which of the following is farm machinery?', 'Tractor', 'Hoe', 'Cutlass', 'Basket', 'A', 'A tractor is powered machinery.'],
    ['The process of planting seeds in the soil is called:', 'Sowing', 'Weeding', 'Harvesting', 'Threshing', 'A', 'Sowing places seeds in the soil.'],
    ['The nutrient that promotes leafy, green growth is:', 'Nitrogen', 'Phosphorus', 'Potassium', 'Calcium', 'A', 'Nitrogen supports vegetative growth.'],
    ['Irrigation means:', 'artificial application of water to crops', 'harvesting of crops', 'spraying of chemicals', 'removal of weeds', 'A', 'Irrigation supplies water to crops.'],
    ['Which of the following animals is kept mainly for wool?', 'Sheep', 'Goat', 'Pig', 'Chicken', 'A', 'Sheep provide wool.'],
    ['Fertilisers are applied to soil to:', 'add nutrients', 'remove weeds', 'kill pests', 'improve taste', 'A', 'Fertilisers supply plant nutrients.'],
    ['Weeds are best described as:', 'unwanted plants in the farm', 'useful crops', 'soil organisms', 'farm animals', 'A', 'Weeds compete with crops.'],
    ['Which of the following is a method of preserving crops?', 'Drying', 'Soaking in water', 'Planting', 'Weeding', 'A', 'Drying reduces moisture and preserves grains.'],
    ['The scientific study of soil is called:', 'Soil science', 'Botany', 'Zoology', 'Entomology', 'A', 'Soil science studies soil.'],
    ['A young plant raised in a nursery is called a:', 'Seedling', 'Tuber', 'Bulb', 'Rhizome', 'A', 'Seedlings are raised in nurseries.'],
    ['Poultry refers to:', 'domesticated birds', 'cattle', 'goats', 'fish', 'A', 'Poultry are domestic fowls.'],
    ['Which pest commonly damages stored grains?', 'Weevil', 'Grasshopper', 'Snail', 'Earthworm', 'A', 'Weevils infest stored grain.'],
    ['Harvesting is the:', 'gathering of mature crops', 'planting of seeds', 'weeding of farms', 'tilling of soil', 'A', 'Harvesting collects mature crops.'],
    ['Mixed farming involves:', 'rearing animals and growing crops together', 'growing only one crop', 'rearing only animals', 'fishing only', 'A', 'Mixed farming combines crops and livestock.'],
    ['Which of the following is a source of farm power?', 'Human', 'Weed', 'Pest', 'Seed', 'A', 'Human effort is a source of farm power.'],
]],
];

/* Merge ".SS"-namespaced banks back into the subject key so subjects that
   have both a JSS and an SS bank keep both. */
foreach (array_keys($bank) as $k) {
    if (str_contains($k, '.SS')) {
        $name = str_replace('.SS', '', $k);
        $bank[$name]['SS'] = $bank[$k]['SS'];
        unset($bank[$k]);
    }
}

/* ------------------------------------------------------------------ *
 *  1) Make sure every subject row has a valid teacher owner.
 *     Prefer the allocated teacher (subject_allocations) when it is a
 *     real teacher account; otherwise assign round-robin.
 * ------------------------------------------------------------------ */
$db = getDB();

$teachers = array_column($db->query("SELECT id FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY id")->fetchAll(), 'id');
if (!$teachers) { fwrite(STDERR, "No teacher accounts found. Aborting.\n"); exit(1); }

$alloc = [];
foreach ($db->query("SELECT subject_id, class_id, MIN(teacher_id) AS t FROM subject_allocations WHERE academic_session_id = 1 GROUP BY subject_id, class_id")->fetchAll() as $a) {
    if (in_array((int)$a['t'], $teachers, true)) {
        $alloc[$a['subject_id'] . '-' . $a['class_id']] = (int)$a['t'];
    }
}

$i = 0;
$ownershipCount = 0;
foreach ($db->query("SELECT id, class_id FROM subjects ORDER BY id")->fetchAll() as $s) {
    $owner = $alloc[$s['id'] . '-' . $s['class_id']] ?? $teachers[$i % count($teachers)];
    $i++;
    $stmt = $db->prepare("UPDATE subjects SET teacher_id = ? WHERE id = ?");
    $stmt->execute([$owner, $s['id']]);
    $ownershipCount++;
}
echo "Teacher ownership assigned for $ownershipCount subject rows (" . count($teachers) . " teachers).\n";

/* ------------------------------------------------------------------ *
 *  2) Remove any existing dummy rows so the script is re-runnable.
 * ------------------------------------------------------------------ */
$del = $db->prepare("DELETE FROM exam_questions WHERE explanation LIKE ?");
$del->execute(["%$MARK%"]);
echo "Cleaned up existing dummy question rows.\n";

/* ------------------------------------------------------------------ *
 *  3) Seed 20 MCQs per core subject per level, per subject row.
 * ------------------------------------------------------------------ */
$ins = $db->prepare(
    "INSERT INTO exam_questions (teacher_id, subject_id, class_id, term_id, question_type, question_text, option_a, option_b, option_c, option_d, correct_answer, marks, explanation)
     SELECT s.teacher_id, s.id, s.class_id, 1, 'mcq', ?, ?, ?, ?, ?, ?, 1, ?
     FROM subjects s WHERE s.name = ? AND s.level = ?"
);

$totalInserted = 0;
$missing = [];
foreach ($bank as $subjectName => $levels) {
    foreach ($levels as $level => $questions) {
        $matchedRows = $db->prepare("SELECT COUNT(*) FROM subjects WHERE name = ? AND level = ?");
        $matchedRows->execute([$subjectName, $level]);
        $rowCount = (int)$matchedRows->fetchColumn();

        if ($rowCount === 0) {
            $missing[] = "$subjectName [$level]";
            continue;
        }

        foreach ($questions as $q) {
            [$qText, $optA, $optB, $optC, $optD, $ans, $expl] = $q;
            $ins->execute([$qText, $optA, $optB, $optC, $optD, $ans, $MARK . ' ' . $expl, $subjectName, $level]);
            $totalInserted++;
        }
    }
}

/* ------------------------------------------------------------------ *
 *  3b) Fallback: every teacher should own at least one core bank, even
 *      if they are only allocated trade (non-core) subjects. For teachers
 *      with no dummy questions, reassign one core row (from a teacher who
 *      has spare banks) and migrate that row's dummy questions to them.
 * ------------------------------------------------------------------ */
$coreSet = [];
foreach ($bank as $name => $levels) {
    foreach ($levels as $level => $qs) $coreSet[$name . '|' . $level] = true;
}
$coreRows = [];
foreach ($db->query("SELECT id, teacher_id FROM subjects")->fetchAll() as $r) {
    $s = $db->prepare("SELECT name, level FROM subjects WHERE id = ?");
    $s->execute([$r['id']]);
    $meta = $s->fetch();
    if (isset($coreSet[$meta['name'] . '|' . $meta['level']])) {
        $coreRows[$r['id']] = (int)$r['teacher_id'];
    }
}

$dummyQCount = function (int $teacherId) use ($db, $MARK): int {
    $stmt = $db->prepare("SELECT COUNT(*) FROM exam_questions WHERE teacher_id = ? AND explanation LIKE ?");
    $stmt->execute([$teacherId, "%$MARK%"]);
    return (int)$stmt->fetchColumn();
};

$fallbackAssigned = 0;
$usedRows = [];
foreach ($teachers as $t) {
    if ($dummyQCount($t) > 0) continue;

    $candidate = null;
    foreach ($coreRows as $subId => $ownerId) {
        if ($ownerId === $t) continue;
        if (isset($usedRows[$subId])) continue;
        if ($dummyQCount($ownerId) >= 40) { $candidate = $subId; break; }
    }
    if ($candidate === null) {
        foreach ($coreRows as $subId => $ownerId) {
            if ($ownerId === $t) continue;
            if (isset($usedRows[$subId])) continue;
            if ($dummyQCount($ownerId) >= 20) { $candidate = $subId; break; }
        }
    }
    if ($candidate === null) {
        fwrite(STDERR, "WARNING: no spare core bank to give teacher #$t\n");
        continue;
    }

    $db->prepare("UPDATE subjects SET teacher_id = ? WHERE id = ?")->execute([$t, $candidate]);
    $db->prepare("UPDATE exam_questions SET teacher_id = ? WHERE subject_id = ? AND explanation LIKE ?")
       ->execute([$t, $candidate, "%$MARK%"]);
    $usedRows[$candidate] = true;
    $fallbackAssigned++;
}

echo "Fallback: assigned core banks to $fallbackAssigned additional teacher(s).\n";

/* ------------------------------------------------------------------ *
 *  4) Report
 * ------------------------------------------------------------------ */
echo "\nInserted $totalInserted dummy MCQ rows.\n";
if ($missing) {
    echo "NOTE: no subject rows matched for: " . implode(', ', $missing) . "\n";
}

$verify = $db->query("
    SELECT s.name, s.level, COUNT(eq.id) AS qn, COUNT(DISTINCT s.teacher_id) AS owners
    FROM exam_questions eq
    JOIN subjects s ON s.id = eq.subject_id
    WHERE eq.explanation LIKE '%$MARK%'
    GROUP BY s.name, s.level
    ORDER BY s.level, s.name
")->fetchAll();
echo "\nPer-subject bank sizes:\n";
foreach ($verify as $v) {
    printf("  %-28s %-4s %4d questions\n", $v['name'], $v['level'], $v['qn']);
}

$teachersWithQs = $db->query("
    SELECT COUNT(DISTINCT eq.teacher_id) FROM exam_questions eq
    JOIN users u ON u.id = eq.teacher_id
    WHERE eq.explanation LIKE '%$MARK%'
")->fetchColumn();
echo "\nTeachers with at least one dummy question: $teachersWithQs\n";
echo "Done.\n";
