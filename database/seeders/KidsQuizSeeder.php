<?php

namespace Database\Seeders;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A globally inclusive 15-week quiz programme for ages 5-8.
 *
 * Every week contains exactly 30 unique questions: five each from Chess,
 * Science, Funny, Riddles, General Knowledge and World. At two minutes per
 * question, one complete weekly set fills a one-hour quiz class.
 */
class KidsQuizSeeder extends Seeder
{
    private const QUESTIONS_PER_CATEGORY = 75;

    public function run(): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        if (! $admin) {
            throw new RuntimeException('Create an admin before loading the kids quiz pack.');
        }

        $packs = [
            'Chess' => $this->chessQuestions(),
            'Science' => $this->scienceQuestions(),
            'Funny' => $this->funnyQuestions(),
            'Riddles' => $this->riddleQuestions(),
            'General Knowledge' => $this->generalKnowledgeQuestions(),
            'World' => $this->worldQuestions(),
        ];

        $seen = [];
        foreach ($packs as $category => $questions) {
            if (count($questions) !== self::QUESTIONS_PER_CATEGORY) {
                throw new RuntimeException($category.' must contain exactly 75 questions; found '.count($questions).'.');
            }

            foreach ($questions as [$question]) {
                $key = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $question)));
                if (isset($seen[$key])) {
                    throw new RuntimeException('Repeated quiz question detected: '.$question);
                }
                $seen[$key] = true;
            }
        }

        DB::transaction(function () use ($admin, $packs): void {
            $sequence = 0;
            foreach ($packs as $category => $questions) {
                foreach ($questions as $index => [$question, $answer, $distractors]) {
                    [$options, $correctIndex] = $this->buildOptions($answer, $distractors, $sequence++);

                    Quiz::query()->updateOrCreate(
                        ['question_text' => $question],
                        [
                            'week' => intdiv($index, 5) + 1,
                            'category' => $category,
                            'age_min' => 5,
                            'age_max' => 8,
                            'type' => 'text',
                            'image_path' => null,
                            'options' => array_map(
                                fn (string $option) => ['kind' => 'text', 'value' => $option],
                                $options
                            ),
                            'correct_index' => $correctIndex,
                            'time_limit_seconds' => 120,
                            'created_by' => $admin->id,
                        ]
                    );
                }
            }
        });

        $this->command?->info('450 unique global kids quizzes loaded: 15 weeks x 30 questions x 2 minutes.');
    }

    /** @return array{0: array<int, string>, 1: int} */
    private function buildOptions(string $answer, array $distractors, int $sequence): array
    {
        $distractors = array_values(array_filter(
            array_unique(array_map('strval', $distractors)),
            fn (string $value) => $value !== $answer
        ));

        if (count($distractors) < 3) {
            throw new RuntimeException('Every quiz question needs three distinct wrong options: '.$answer);
        }

        $correctIndex = $sequence % 4;
        $options = array_slice($distractors, 0, 3);
        array_splice($options, $correctIndex, 0, [$answer]);

        return [$options, $correctIndex];
    }

    /** Convert question/answer pairs into questions with plausible nearby distractors. */
    private function fromPairs(array $pairs): array
    {
        $questions = [];
        $answers = array_values($pairs);
        $total = count($answers);

        foreach (array_keys($pairs) as $index => $question) {
            $answer = (string) $pairs[$question];
            $distractors = [];
            for ($offset = 1; $offset < $total && count($distractors) < 3; $offset++) {
                $candidate = (string) $answers[($index + $offset) % $total];
                if ($candidate !== $answer && ! in_array($candidate, $distractors, true)) {
                    $distractors[] = $candidate;
                }
            }
            $questions[] = [(string) $question, $answer, $distractors];
        }

        return $questions;
    }

    private function chessQuestions(): array
    {
        $rules = $this->fromPairs([
            'On which rank do White pawns begin a chess game?' => 'Second rank',
            'On which rank do Black pawns begin a chess game?' => 'Seventh rank',
            'Which chess piece may jump over other pieces?' => 'Knight',
            'Which piece is never actually captured in a legal chess game?' => 'King',
            'What is the name of an attack on two pieces at the same time?' => 'Fork',
            'What is the name of a piece that cannot move without exposing a more valuable piece?' => 'Pinned piece',
            'What tactic attacks a valuable piece and then the piece behind it?' => 'Skewer',
            'What is it called when moving one piece reveals an attack by another?' => 'Discovered attack',
            'What result occurs when a player has no legal move but is not in check?' => 'Stalemate',
            'What must a player do immediately when their king is in check?' => 'Remove the check',
            'Which two pieces move during castling?' => 'King and rook',
            'What is the notation for kingside castling?' => 'O-O',
            'What is the notation for queenside castling?' => 'O-O-O',
            'Which letter represents a knight in chess notation?' => 'N',
            'Which symbol usually shows check in chess notation?' => '+',
            'Which symbol usually shows checkmate in chess notation?' => '#',
            'What does the letter x mean in chess notation?' => 'A capture',
            'How many squares may a king move on a normal move?' => 'One square',
            'A bishop that starts on a light square always stays on which colour?' => 'Light squares',
            'May two kings legally stand on neighbouring squares?' => 'No, never',
            'Which pieces can a pawn promote to?' => 'Queen, rook, bishop or knight',
            'What special pawn capture can happen just after a two-square pawn move?' => 'En passant',
            'Which side moves first in every standard chess game?' => 'White',
            'Where does the White queen begin?' => 'd1',
            'Where does the Black king begin?' => 'e8',
            'How many files are on a chessboard?' => 'Eight files',
            'How many ranks are on a chessboard?' => 'Eight ranks',
            'What is the name for the four central squares d4, e4, d5 and e5?' => 'The centre',
            'Which endgame idea means placing kings directly opposite each other?' => 'Opposition',
            'What is the final goal of a legal chess attack on the king?' => 'Checkmate',
        ]);

        $squareQuestions = [];
        foreach (['b1', 'c1', 'd2', 'e3', 'f4', 'g5', 'h6', 'a8', 'c7', 'd6', 'e5', 'f2', 'g1', 'h3', 'b8'] as $square) {
            $file = ord($square[0]) - 96;
            $rank = (int) $square[1];
            $answer = (($file + $rank) % 2 === 0) ? 'Dark' : 'Light';
            $squareQuestions[] = [
                'Without looking at a board, what colour is square '.$square.'?',
                $answer,
                [$answer === 'Dark' ? 'Light' : 'Dark', 'Both colours', 'No colour'],
            ];
        }

        $knightQuestions = [];
        $starts = ['a1', 'b1', 'c2', 'd2', 'e2', 'f2', 'g2', 'h1', 'a4', 'b4', 'c4', 'd4', 'e5', 'f6', 'g7'];
        $allSquares = [];
        foreach (range('a', 'h') as $file) {
            foreach (range(1, 8) as $rank) $allSquares[] = $file.$rank;
        }
        foreach ($starts as $index => $start) {
            $file = ord($start[0]) - 96;
            $rank = (int) $start[1];
            $legal = [];
            foreach ([[1, 2], [2, 1], [-1, 2], [-2, 1], [1, -2], [2, -1], [-1, -2], [-2, -1]] as [$df, $dr]) {
                $f = $file + $df;
                $r = $rank + $dr;
                if ($f >= 1 && $f <= 8 && $r >= 1 && $r <= 8) $legal[] = chr(96 + $f).$r;
            }
            sort($legal);
            $answer = $legal[$index % count($legal)];
            $wrong = array_values(array_filter($allSquares, fn (string $sq) => $sq !== $start && ! in_array($sq, $legal, true)));
            $knightQuestions[] = [
                'Which square can a knight on '.$start.' reach in exactly one move?',
                $answer,
                [$wrong[$index], $wrong[$index + 8], $wrong[$index + 16]],
            ];
        }

        $materialQuestions = [];
        $sets = [
            ['one queen and one pawn', 10], ['two rooks', 10], ['one rook and two pawns', 7],
            ['two bishops and one pawn', 7], ['one knight and three pawns', 6],
            ['one queen and one bishop', 12], ['one rook, one knight and one pawn', 9],
            ['two knights and two pawns', 8], ['one queen and two rooks', 19],
            ['three pawns and one bishop', 6], ['one rook and two bishops', 11],
            ['one queen, one knight and one pawn', 13], ['two rooks and three pawns', 13],
            ['one bishop, one knight and two pawns', 8], ['one queen and four pawns', 13],
        ];
        foreach ($sets as [$pieces, $value]) {
            $materialQuestions[] = [
                'Using Q=9, R=5, B=3, N=3 and pawn=1, what is '.$pieces.' worth?',
                (string) $value,
                [(string) ($value - 1), (string) ($value + 1), (string) ($value + 2)],
            ];
        }

        return array_merge($rules, $squareQuestions, $knightQuestions, $materialQuestions);
    }

    private function scienceQuestions(): array
    {
        return array_merge(
            $this->fromPairs([
                'Which is the largest planet in our solar system?' => 'Jupiter',
                'What is the closest star to Earth?' => 'The Sun',
                'Which planet has the most clearly visible rings?' => 'Saturn',
                'Which planet is known as the Red Planet?' => 'Mars',
                'What is Earth’s natural satellite called?' => 'The Moon',
                'Which planet is hottest even though it is not closest to the Sun?' => 'Venus',
                'Which planet is closest to the Sun?' => 'Mercury',
                'Which planet spins almost on its side?' => 'Uranus',
                'Which major planet is farthest from the Sun?' => 'Neptune',
                'Which galaxy contains our solar system?' => 'Milky Way',
                'What force keeps planets moving around the Sun?' => 'Gravity',
                'What is the curved path of a planet around a star called?' => 'Orbit',
                'Who was the first person to walk on the Moon?' => 'Neil Armstrong',
                'Which tool helps us see very distant objects in space?' => 'Telescope',
                'Which famous world beyond Neptune is classed as a dwarf planet?' => 'Pluto',
            ]),
            $this->fromPairs([
                'Which organ pumps blood around the body?' => 'Heart',
                'Which organs take oxygen from the air?' => 'Lungs',
                'Which hard structure protects the brain?' => 'Skull',
                'Which joint lets the middle of your arm bend?' => 'Elbow',
                'What is the largest organ of the human body?' => 'Skin',
                'Which organ mixes and begins digesting food after it is swallowed?' => 'Stomach',
                'Which organ controls thoughts, memory and movement?' => 'Brain',
                'Which blood cells mainly carry oxygen?' => 'Red blood cells',
                'What is the very hard outer covering of a tooth called?' => 'Enamel',
                'How many baby teeth does a child normally grow?' => '20',
                'How many bones are in most adult human skeletons?' => '206',
                'Which part of the ear helps the body balance?' => 'Inner ear',
                'Which large muscle below the lungs helps breathing?' => 'Diaphragm',
                'Which pair of organs filters waste from the blood?' => 'Kidneys',
                'What do we call the places where two bones meet?' => 'Joints',
            ]),
            $this->fromPairs([
                'At what temperature does pure water freeze on the Celsius scale?' => '0°C',
                'At what temperature does pure water boil at sea level in Celsius?' => '100°C',
                'What are the three common states of matter?' => 'Solid, liquid and gas',
                'Which material is transparent and commonly used for windows?' => 'Glass',
                'Which metal is a very good conductor and is often used in wires?' => 'Copper',
                'Which material is strongly attracted to an ordinary magnet?' => 'Iron',
                'What creates sound when an object moves rapidly back and forth?' => 'Vibrations',
                'Which travels faster through air: light or sound?' => 'Light',
                'What forms when an object blocks light?' => 'Shadow',
                'What process separates white light into rainbow colours?' => 'Refraction',
                'Which simple machine is a playground seesaw?' => 'Lever',
                'What kind of energy is stored inside food?' => 'Chemical energy',
                'What is liquid water changing into water vapour called?' => 'Evaporation',
                'What is water vapour changing back into liquid called?' => 'Condensation',
                'Which instrument measures temperature?' => 'Thermometer',
            ]),
            $this->fromPairs([
                'What is the largest mammal alive today?' => 'Blue whale',
                'What is the fastest land animal?' => 'Cheetah',
                'Which group contains the only mammals able to truly fly?' => 'Bats',
                'What is a group of lions called?' => 'Pride',
                'Which animal begins as a tadpole and becomes an amphibian?' => 'Frog',
                'Which lizard is famous for changing colour?' => 'Chameleon',
                'Which sea mammal uses clicks and echoes to find objects?' => 'Dolphin',
                'Which unusual mammal lays eggs and has a duck-like bill?' => 'Platypus',
                'Which animal has black-and-white stripes unique to each individual?' => 'Zebra',
                'What does a camel mainly store in its hump?' => 'Fat',
                'Which sea animal has eight arms and three hearts?' => 'Octopus',
                'Which living land animal has the longest neck?' => 'Giraffe',
                'What is the largest living bird?' => 'Ostrich',
                'Which stage comes after larva in a butterfly’s life cycle?' => 'Pupa',
                'What is a shark’s skeleton mostly made from?' => 'Cartilage',
            ]),
            $this->fromPairs([
                'Which green pigment helps leaves absorb light?' => 'Chlorophyll',
                'Which plant part usually absorbs water from soil?' => 'Roots',
                'What process lets green plants use light to make food?' => 'Photosynthesis',
                'What can be counted to estimate the age of a tree trunk?' => 'Growth rings',
                'What is the solid outer layer of Earth called?' => 'Crust',
                'What is melted rock below Earth’s surface called?' => 'Magma',
                'What is melted rock called after it reaches Earth’s surface?' => 'Lava',
                'What is water falling from clouds called?' => 'Precipitation',
                'Which instrument measures wind speed?' => 'Anemometer',
                'What is a cloud touching the ground called?' => 'Fog',
                'What name is given to moving air?' => 'Wind',
                'About how long does Earth take to rotate once?' => '24 hours',
                'About how long does Earth take to orbit the Sun?' => '365 days',
                'What imaginary line circles the middle of Earth?' => 'Equator',
                'What dark part of soil is made from decayed living things?' => 'Humus',
            ])
        );
    }

    private function funnyQuestions(): array
    {
        return array_merge(
            $this->fromPairs([
                'Why did the banana visit the doctor?' => 'It was not peeling well',
                'What do you call a noodle pretending to be something else?' => 'An impasta',
                'Why did the cookie need a rest?' => 'It felt crummy',
                'What did one dinner plate say to the other?' => 'Dinner is on me',
                'Why did the tomato turn red?' => 'It saw the salad dressing',
                'What do you call cheese that belongs to someone else?' => 'Nacho cheese',
                'Why was the mushroom invited to every party?' => 'It was a fun-gi',
                'Why do eggs make good comedians?' => 'They crack everyone up',
                'Why did the popcorn go to the cinema?' => 'It wanted a starring role',
                'What do you call a strawberry feeling sad?' => 'A blueberry',
                'Why did the cheese pose for the camera?' => 'Someone said cheese',
                'What kind of nut always sounds as if it is sneezing?' => 'A cashew',
                'Why did the ice cream carry an umbrella?' => 'There were lots of sprinkles',
                'Why was the loaf of bread late?' => 'It got toasted in traffic',
                'What did the hungry clock order at the café?' => 'Four seconds',
            ]),
            $this->fromPairs([
                'Why was the mathematics book unhappy?' => 'It had too many problems',
                'Why did the pencil take a holiday?' => 'It needed to get to the point',
                'Why did the ruler feel important?' => 'It was the measure of success',
                'Why did the computer wear a sweater?' => 'It left its Windows open',
                'Why did the police take the storybook away?' => 'It was booked',
                'Why did the teacher wear sunglasses in class?' => 'The pupils were very bright',
                'Why did the music teacher bring a ladder?' => 'To reach the high notes',
                'Why did the student eat the homework?' => 'The teacher called it a piece of cake',
                'Why was the calendar worried?' => 'Its days were numbered',
                'Why did the eraser win an award?' => 'It cleaned up every mistake',
                'What is a snake’s favourite school subject?' => 'Hiss-tory',
                'Why did the dictionary stay calm?' => 'It had all the right words',
                'What is a ghost’s favourite school subject?' => 'Spelling',
                'Why did the notebook sit near the window?' => 'It wanted fresh pages',
                'What did the zero say to the number eight?' => 'Nice belt',
            ]),
            $this->fromPairs([
                'What do you call an alligator that solves mysteries?' => 'An investigator',
                'What do you call a bull that is sleeping?' => 'A bulldozer',
                'What do you call a bear with no teeth?' => 'A gummy bear',
                'What do you call a cow that plays music?' => 'A moo-sician',
                'What do you call a dinosaur with a huge vocabulary?' => 'A thesaurus',
                'What move does a pig use in karate?' => 'A pork chop',
                'What do you call an owl that performs magic?' => 'Hoo-dini',
                'What do you call a rabbit flying through the sky?' => 'A hare-plane',
                'Which fish is good at lending money?' => 'A loan shark',
                'What do you call a duck that tells clever jokes?' => 'A wise quacker',
                'What is a sheep’s favourite karate move?' => 'A lamb chop',
                'What do you call a snail working on a ship?' => 'A snailor',
                'What does a bee use to brush its hair?' => 'A honeycomb',
                'Why did the frog leave its car at the wrong place?' => 'It was toad away',
                'What does a cat say before eating lunch?' => 'Let me paws first',
            ]),
            $this->fromPairs([
                'Why was the broom late for work?' => 'It overswept',
                'Why could the bicycle not stand by itself?' => 'It was two-tired',
                'Why are elevator jokes so clever?' => 'They work on many levels',
                'What do clouds wear under their shorts?' => 'Thunderwear',
                'What does a snowman eat for breakfast?' => 'Frosted flakes',
                'How does the ocean say hello?' => 'It waves',
                'Why did the tree use a computer?' => 'It wanted to log on',
                'Why was the vacuum cleaner proud?' => 'It cleaned up nicely',
                'Why did the battery end the friendship?' => 'There was no spark',
                'Why was the light bulb top of the class?' => 'It was very bright',
                'Why did the camera tell everyone to smile?' => 'It wanted a good shot',
                'What kind of room has no doors or windows?' => 'A mushroom',
                'Why did the mirror always answer honestly?' => 'It could not hide the reflection',
                'Why did the lamp go to school?' => 'To become brighter',
                'Why did the chair join the meeting?' => 'It wanted a seat at the table',
            ]),
            $this->fromPairs([
                'Why did the astronaut like the computer keyboard?' => 'It had a space bar',
                'How does the Moon get a haircut?' => 'Eclipse it',
                'What kind of music does Neptune enjoy?' => 'Nep-tunes',
                'Why did the star receive a gold medal?' => 'It gave a stellar performance',
                'Where does an alien grow vegetables?' => 'In a space garden',
                'What is an astronaut’s favourite chocolate?' => 'A Mars bar',
                'Why did the rocket bring a packed lunch?' => 'It was ready for launch',
                'What do planets use to read at night?' => 'Satellite lights',
                'Why did Earth invite the Moon to dinner?' => 'It needed a little space',
                'What do you call a comet that tidies its room?' => 'A clean streak',
                'Why did the Sun go to school?' => 'To get a little brighter',
                'How do astronauts organise a party?' => 'They planet',
                'What did one star say to another star?' => 'You light up my universe',
                'Why was the telescope a good listener?' => 'It always looked into things',
                'What kind of books do astronauts enjoy?' => 'Books with space stories',
            ])
        );
    }

    private function riddleQuestions(): array
    {
        return array_merge(
            $this->fromPairs([
                'I have hands and a face but no arms or legs. What am I?' => 'A clock',
                'I have many keys but cannot open a single lock. What am I?' => 'A piano',
                'I have one eye but cannot see. What am I?' => 'A needle',
                'I get wetter the more I dry. What am I?' => 'A towel',
                'I have teeth but cannot bite. What am I?' => 'A comb',
                'I have a neck but no head and wear a cap. What am I?' => 'A bottle',
                'I am full of holes but can still hold water. What am I?' => 'A sponge',
                'I show cities, rivers and roads but have no people or water. What am I?' => 'A map',
                'I become shorter as I grow older. What am I?' => 'A candle',
                'I travel far while staying stuck in one corner. What am I?' => 'A postage stamp',
                'I can be opened and closed and may carry a letter. What am I?' => 'An envelope',
                'I have a space and an enter key but no rooms or doors. What am I?' => 'A keyboard',
                'I have a spine and many pages but no bones. What am I?' => 'A book',
                'I have a tongue but cannot taste anything. What am I?' => 'A shoe',
                'I go up when rain comes down. What am I?' => 'An umbrella',
            ]),
            $this->fromPairs([
                'What always goes up but never comes back down?' => 'Your age',
                'What is always coming but never arrives today?' => 'Tomorrow',
                'What disappears as soon as you say its name?' => 'Silence',
                'What answers you even though it never asks a question?' => 'An echo',
                'What follows you in light but leaves you in darkness?' => 'Your shadow',
                'What runs but never walks and has a bed but never sleeps?' => 'A river',
                'I float without wings and may cry without eyes. What am I?' => 'A cloud',
                'I appear after rain and wear many colours. What am I?' => 'A rainbow',
                'You cannot see me, but you can feel me push. What am I?' => 'The wind',
                'The more of me there is, the less you can see. What am I?' => 'Darkness',
                'Feed me and I live; give me water and I die. What am I?' => 'Fire',
                'I fall from the sky, have six sides and melt in your hand. What am I?' => 'A snowflake',
                'I have a peak but never wear a hat. What am I?' => 'A mountain',
                'We sparkle at night but hide in daylight. What are we?' => 'Stars',
                'I pass every second but can never be held. What am I?' => 'Time',
            ]),
            $this->fromPairs([
                'What letter appears once in minute, twice in moment and never in hour?' => 'The letter M',
                'What letter begins eternity and ends both time and space?' => 'The letter E',
                'Which letter is in the exact middle of the word nowhere?' => 'The letter H',
                'Which letter is at the end of the word rainbow?' => 'The letter W',
                'Which word becomes longer in meaning when two letters are added to it?' => 'Short',
                'Which English word has three pairs of double letters in a row?' => 'Bookkeeper',
                'Which word is always spelled wrongly in this riddle?' => 'Wrongly',
                'What begins with T, ends with T and has tea inside?' => 'A teapot',
                'Which single word contains all twenty-six letters?' => 'Alphabet',
                'Which letter comes at the end of everything?' => 'The letter G',
                'What can you catch with no hands but cannot throw?' => 'A cold',
                'Which five-letter word sounds the same after four letters are removed?' => 'Queue',
                'What begins with P, ends with E and holds thousands of letters?' => 'A post office',
                'What has four letters, sometimes has nine, and never has five?' => 'The words count their own letters',
                'Which word starts and ends with E but contains only one letter?' => 'Envelope',
            ]),
            $this->fromPairs([
                'I am an odd number; remove my first letter and I become even. What number am I?' => 'Seven',
                'You have three apples and take away two. How many apples do you have?' => 'Two',
                'Two fathers and two sons share three apples equally. How many people are there?' => 'Three',
                'How many months have at least twenty-eight days?' => 'All twelve',
                'Ten fish are in a tank and three cannot swim. How many remain in the tank?' => 'Ten',
                'A rooster sits on a roof. Which way does its egg roll?' => 'Roosters do not lay eggs',
                'If one corner is cut from a square sheet, how many corners remain?' => 'Five',
                'Which is heavier: one kilogram of feathers or one kilogram of iron?' => 'They weigh the same',
                'An electric train travels north. Which way does its smoke blow?' => 'It makes no smoke',
                'What three positive numbers give the same result when added and multiplied?' => 'One, two and three',
                'If there are six birds and each bird has two wings, how many wings are there?' => 'Twelve',
                'Half of eight plus two equals what?' => 'Six',
                'A square has four corners. How many corners do three separate squares have?' => 'Twelve corners',
                'What number comes next: 2, 4, 8, 16, ...?' => '32',
                'What number is missing: 3, 6, 9, __, 15?' => '12',
            ]),
            $this->fromPairs([
                'The more you take from me, the more you leave behind. What am I?' => 'Footsteps',
                'What belongs to you but other people use it more than you do?' => 'Your name',
                'What grows larger whenever more is taken away from it?' => 'A hole',
                'What can be broken without being touched?' => 'A promise',
                'What can be kept only after it is given to someone?' => 'Your word',
                'What can fill a room but takes up no space?' => 'Light',
                'What can travel around the world without moving from its place?' => 'A thought',
                'What has thirteen hearts but no other organs?' => 'A deck of cards',
                'What has a thumb and four fingers but is not alive?' => 'A glove',
                'What has many branches but no leaves, trunk or fruit?' => 'A bank',
                'What can you hold in your right hand but never in your left hand?' => 'Your left elbow',
                'What goes through a door but never enters the room?' => 'A keyhole',
                'What has a head and a tail but no body?' => 'A coin',
                'What is easy to lift but hard to throw?' => 'A feather',
                'What has an endless ring but no finger?' => 'A telephone',
            ])
        );
    }

    private function generalKnowledgeQuestions(): array
    {
        return array_merge(
            $this->fromPairs([
                'How many minutes are there in a full day?' => '1,440',
                'How many hours are there in one full week?' => '168',
                'How many days are in a leap year?' => '366',
                'How many minutes make one quarter of an hour?' => '15',
                'How many years make one century?' => '100',
                'What is the sum of the angles inside a triangle?' => '180 degrees',
                'What number does the Roman numeral X represent?' => '10',
                'How many items are in a dozen?' => '12',
                'What is the perimeter of a square with sides of 3 cm?' => '12 cm',
                'How many flat faces does a cube have?' => '6',
                'What is the largest two-digit whole number?' => '99',
                'What is the smallest three-digit whole number?' => '100',
                'What is one quarter of twenty?' => '5',
                'What is seven multiplied by three?' => '21',
                'What is half of fifty?' => '25',
            ]),
            $this->fromPairs([
                'Who wrote the original story The Adventures of Pinocchio?' => 'Carlo Collodi',
                'Who wrote Alice’s Adventures in Wonderland?' => 'Lewis Carroll',
                'Who wrote The Little Prince?' => 'Antoine de Saint-Exupéry',
                'Who created the character Pippi Longstocking?' => 'Astrid Lindgren',
                'Who wrote the Winnie-the-Pooh stories?' => 'A. A. Milne',
                'Who wrote The Gruffalo?' => 'Julia Donaldson',
                'Who wrote Matilda?' => 'Roald Dahl',
                'Who created Peter Rabbit?' => 'Beatrix Potter',
                'Who created the Moomin characters?' => 'Tove Jansson',
                'Who wrote The Wonderful Wizard of Oz?' => 'L. Frank Baum',
                'Who wrote Where the Wild Things Are?' => 'Maurice Sendak',
                'Who wrote the children’s novel Heidi?' => 'Johanna Spyri',
                'Who wrote The Jungle Book?' => 'Rudyard Kipling',
                'Who wrote Charlotte’s Web?' => 'E. B. White',
                'Who is traditionally credited with the tale The Ugly Duckling?' => 'Hans Christian Andersen',
            ]),
            $this->fromPairs([
                'How many different notes are named in a basic musical scale?' => 'Seven',
                'Which family of instruments includes the violin?' => 'String family',
                'Which family of instruments includes the trumpet?' => 'Brass family',
                'Which family of instruments includes the flute?' => 'Woodwind family',
                'Which family of instruments includes the drum?' => 'Percussion family',
                'What does a conductor lead?' => 'An orchestra',
                'What word describes the speed of a piece of music?' => 'Tempo',
                'What do we call the repeating beat pattern in music?' => 'Rhythm',
                'Which symbol tells a musician not to play for a certain time?' => 'A rest',
                'What is a picture made from many tiny coloured pieces called?' => 'Mosaic',
                'What is the art of folding paper into shapes called?' => 'Origami',
                'What are the three traditional primary paint colours?' => 'Red, yellow and blue',
                'What colour is made by mixing blue and yellow paint?' => 'Green',
                'What is a person who writes music called?' => 'Composer',
                'What is a live play with songs, acting and dancing called?' => 'Musical theatre',
            ]),
            $this->fromPairs([
                'How many players from one team are normally on the field in football or soccer?' => 'Eleven',
                'How many rings are on the Olympic symbol?' => 'Five',
                'In which sport do players shoot a ball through a raised hoop?' => 'Basketball',
                'In tennis scoring, what word means zero points?' => 'Love',
                'Which sport uses a shuttlecock?' => 'Badminton',
                'Which sport has positions called pitcher and catcher?' => 'Baseball',
                'Which sport is played at Wimbledon?' => 'Tennis',
                'Which sport uses a puck on ice?' => 'Ice hockey',
                'Which sport includes a pommel horse and balance beam?' => 'Gymnastics',
                'In which sport might you score a try?' => 'Rugby',
                'Which cue sport is played on a table with coloured balls and pockets?' => 'Snooker',
                'Which sport includes the strokes freestyle, backstroke and butterfly?' => 'Swimming',
                'What colour card sends a football player off the field?' => 'Red card',
                'How many points is a free throw worth in basketball?' => 'One point',
                'Which board game uses letter tiles to build words?' => 'Scrabble',
            ]),
            $this->fromPairs([
                'Who developed the reading system made from raised dots?' => 'Louis Braille',
                'Which invention lets people speak to someone far away using their voice?' => 'Telephone',
                'Which tool points toward magnetic north?' => 'Compass',
                'What does a map legend explain?' => 'Map symbols',
                'What is a word that reads the same forwards and backwards called?' => 'Palindrome',
                'Which part of a book lists chapter names and page numbers?' => 'Table of contents',
                'Which reference book gives meanings of words?' => 'Dictionary',
                'Which reference book is organised as maps?' => 'Atlas',
                'What is the raised-dot reading and writing system called?' => 'Braille',
                'Which machine prints words and pictures onto paper from a computer?' => 'Printer',
                'What is a secret word used to protect an online account called?' => 'Password',
                'Which code uses dots and dashes to represent letters?' => 'Morse code',
                'What do the letters WWW stand for?' => 'World Wide Web',
                'What is a set of step-by-step instructions for a computer called?' => 'Program',
                'Which safety item protects a cyclist’s head?' => 'Helmet',
            ])
        );
    }

    private function worldQuestions(): array
    {
        return array_merge(
            $this->fromPairs([
                'What is the capital city of Canada?' => 'Ottawa',
                'What is the capital city of Brazil?' => 'Brasília',
                'What is the capital city of Egypt?' => 'Cairo',
                'What is the capital city of Kenya?' => 'Nairobi',
                'What is the capital city of India?' => 'New Delhi',
                'What is the capital city of Japan?' => 'Tokyo',
                'What is the capital city of Australia?' => 'Canberra',
                'What is the capital city of New Zealand?' => 'Wellington',
                'What is the capital city of France?' => 'Paris',
                'What is the capital city of Germany?' => 'Berlin',
                'What is the capital city of Mexico?' => 'Mexico City',
                'What is the capital city of Argentina?' => 'Buenos Aires',
                'What is the capital city of Thailand?' => 'Bangkok',
                'What is the capital city of South Korea?' => 'Seoul',
                'What is the capital city of Türkiye?' => 'Ankara',
            ]),
            $this->fromPairs([
                'What is the capital city of China?' => 'Beijing',
                'What is the capital city of the United Kingdom?' => 'London',
                'What is the capital city of Italy?' => 'Rome',
                'What is the capital city of Spain?' => 'Madrid',
                'What is the capital city of Norway?' => 'Oslo',
                'What is the capital city of Sweden?' => 'Stockholm',
                'What is the capital city of Finland?' => 'Helsinki',
                'What is the capital city of Nigeria?' => 'Abuja',
                'What is the capital city of Ghana?' => 'Accra',
                'What is the capital city of Morocco?' => 'Rabat',
                'What is the capital city of Peru?' => 'Lima',
                'What is the capital city of Chile?' => 'Santiago',
                'What is the capital city of Colombia?' => 'Bogotá',
                'What is the capital city of Vietnam?' => 'Hanoi',
                'What is the capital city of Indonesia?' => 'Jakarta',
            ]),
            $this->fromPairs([
                'In which country would you find the Eiffel Tower?' => 'France',
                'In which country would you find the Pyramids of Giza?' => 'Egypt',
                'In which country would you find Machu Picchu?' => 'Peru',
                'In which country would you find the Taj Mahal?' => 'India',
                'In which country would you find most of the Great Wall?' => 'China',
                'In which country would you find the ancient city of Petra?' => 'Jordan',
                'In which country would you find Christ the Redeemer statue?' => 'Brazil',
                'In which country would you find the Sydney Opera House?' => 'Australia',
                'In which country would you find the Colosseum?' => 'Italy',
                'In which country would you find Mount Kilimanjaro?' => 'Tanzania',
                'In which country would you find Mount Fuji?' => 'Japan',
                'In which country would you find the Grand Canyon?' => 'United States',
                'In which country would you find the Burj Khalifa?' => 'United Arab Emirates',
                'In which country would you find the Moai statues of Easter Island?' => 'Chile',
                'In which country would you find Angkor Wat?' => 'Cambodia',
            ]),
            $this->fromPairs([
                'On which continent is Brazil?' => 'South America',
                'On which continent is Egypt?' => 'Africa',
                'On which continent is Japan?' => 'Asia',
                'On which continent is France?' => 'Europe',
                'On which continent is Canada?' => 'North America',
                'Which world region or continent includes Australia?' => 'Oceania',
                'Which continent surrounds the South Pole?' => 'Antarctica',
                'On which continent is Kenya?' => 'Africa',
                'On which continent is Chile?' => 'South America',
                'On which continent is India?' => 'Asia',
                'On which continent is Iceland?' => 'Europe',
                'On which continent is Mexico?' => 'North America',
                'Which world region or continent includes New Zealand?' => 'Oceania',
                'On which continent is Madagascar?' => 'Africa',
                'Which world region or continent includes Papua New Guinea?' => 'Oceania',
            ]),
            $this->fromPairs([
                'What is the currency of Japan?' => 'Yen',
                'What is the currency of the United Kingdom?' => 'Pound sterling',
                'What is the currency of India?' => 'Indian rupee',
                'What is the currency of South Korea?' => 'Won',
                'Which currency is shared by many European Union countries?' => 'Euro',
                'What is the currency of Mexico?' => 'Mexican peso',
                'What is the currency of Switzerland?' => 'Swiss franc',
                'What is the currency of Thailand?' => 'Baht',
                'What is the main unit of currency in China?' => 'Renminbi (yuan)',
                'What is the currency of South Africa?' => 'Rand',
                'What is the currency of Brazil?' => 'Brazilian real',
                'What is the currency of Türkiye?' => 'Turkish lira',
                'What is the currency of Nigeria?' => 'Naira',
                'What is the currency of Indonesia?' => 'Rupiah',
                'What is the currency of Kenya?' => 'Kenyan shilling',
            ])
        );
    }
}
