<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Actions\RebuildPostMetadata;
use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Models\Category;
use App\Domain\Taxonomy\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Curated travel content for local development.
 *
 * Hand-written rather than faker-generated so the reading experience can be
 * judged against real prose: every published post carries at least three
 * headings (the table of contents only renders past that threshold), a pull
 * quote, and a list.
 *
 * Re-runnable: posts are matched on slug and rewritten in place. That means a
 * re-seed overwrites whatever these slugs hold, including edits made through
 * the admin — the fixtures are the source of truth, not the database.
 */
class PostSeeder extends Seeder
{
    /**
     * @var list<array{name: string, description: string}>
     */
    private const array CATEGORIES = [
        ['name' => 'Japan', 'description' => 'Slow trains, mountain towns, and the art of arriving late.'],
        ['name' => 'Iceland', 'description' => 'Long roads, longer daylight, and weather that makes the plans.'],
        ['name' => 'Coastal Portugal', 'description' => 'Cold Atlantic, warm tiles, and lunch that runs past four.'],
        ['name' => 'Hotel Reviews', 'description' => 'Rooms we paid for, and whether we would book them again.'],
        ['name' => 'Slow Travel', 'description' => 'Fewer places, longer stays, better questions.'],
    ];

    /**
     * @var list<string>
     */
    private const array TAGS = [
        'ryokan', 'onsen', 'road trip', 'night train', 'budget stays',
        'boutique hotels', 'coastline', 'hot springs', 'walking routes',
        'off season', 'family run', 'first timers',
    ];

    public function run(RebuildPostMetadata $rebuildPostMetadata): void
    {
        $author = User::query()->whereNotNull('author_slug')->first()
            ?? User::factory()->create(['author_slug' => 'test-user']);

        $categories = collect(self::CATEGORIES)->mapWithKeys(fn (array $category): array => [
            $category['name'] => Category::query()->firstOrCreate(
                ['slug' => Str::slug($category['name'])],
                ['name' => $category['name'], 'description' => $category['description']],
            ),
        ]);

        $tags = collect(self::TAGS)->mapWithKeys(fn (string $tag): array => [
            $tag => Tag::query()->firstOrCreate(
                ['slug' => Str::slug($tag)],
                ['name' => $tag],
            ),
        ]);

        $images = $this->availableImages();

        foreach ($this->posts() as $index => $definition) {
            $post = Post::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'author_id' => $author->id,
                    'updated_by_id' => null,
                    'category_id' => $categories[$definition['category']]->id,
                    'title' => $definition['title'],
                    'excerpt' => $definition['excerpt'],
                    'body' => $definition['body'],
                    'featured_image_path' => $images === [] ? null : $images[$index % count($images)],
                    'featured_image_alt' => $definition['image_alt'],
                    'featured_image_caption' => $definition['image_caption'] ?? null,
                    'status' => $definition['status'],
                    'published_at' => $definition['published_at'] ?? null,
                    'content_updated_at' => $definition['content_updated_at'] ?? $definition['published_at'] ?? null,
                    'slug_locked_at' => isset($definition['published_at']) ? now() : null,
                    'scheduled_at' => $definition['scheduled_at'] ?? null,
                    'featured_at' => $definition['featured_at'] ?? null,
                ],
            );

            $post->tags()->sync(
                collect($definition['tags'])->map(fn (string $tag): int => $tags[$tag]->id)->all(),
            );

            $rebuildPostMetadata->handle($post->unsetRelations());
        }
    }

    /**
     * Featured images actually present on disk.
     *
     * The factory's hardcoded `posts/featured-image.webp` does not exist, which
     * renders a broken image. Prefer real uploads when the developer has any,
     * and otherwise leave the column null so cards fall back to their designed
     * placeholder instead of a broken tile.
     *
     * @return list<string>
     */
    private function availableImages(): array
    {
        return collect(Storage::disk('public')->allFiles('posts'))
            ->filter(fn (string $path): bool => in_array(
                strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                ['jpg', 'jpeg', 'png', 'webp', 'avif'],
                true,
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function posts(): array
    {
        return [
            [
                'title' => 'Three days in a ryokan with no wifi',
                'slug' => 'three-days-in-a-ryokan-with-no-wifi',
                'category' => 'Japan',
                'tags' => ['ryokan', 'onsen', 'off season'],
                'excerpt' => 'We booked two nights in the mountains above Hakone and stayed three. Here is what the extra day bought us.',
                'image_alt' => 'A wooden ryokan corridor opening onto a snow-dusted garden',
                'image_caption' => 'The corridor to the outer bath, six in the morning.',
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(6),
                'content_updated_at' => now()->subDays(2),
                'featured_at' => now()->subDays(6),
                'body' => $this->ryokanBody(),
            ],
            [
                'title' => 'The Ring Road is not a week-long drive',
                'slug' => 'the-ring-road-is-not-a-week-long-drive',
                'category' => 'Iceland',
                'tags' => ['road trip', 'walking routes', 'off season'],
                'excerpt' => 'Everyone budgets seven days for Route 1. We took eleven and still cut two fjords. A honest look at the timings.',
                'image_alt' => 'A single-lane road curving between two moss-covered lava fields',
                'image_caption' => 'Somewhere past Vík, with nowhere to pull over for an hour.',
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(19),
                'featured_at' => now()->subDays(19),
                'body' => $this->ringRoadBody(),
            ],
            [
                'title' => 'A guesthouse in Costa Nova worth changing trains for',
                'slug' => 'a-guesthouse-in-costa-nova-worth-changing-trains-for',
                'category' => 'Hotel Reviews',
                'tags' => ['boutique hotels', 'family run', 'coastline'],
                'excerpt' => 'Nine rooms, striped houses, and a breakfast that runs until whenever you appear. We paid €94 a night in April.',
                'image_alt' => 'Striped wooden beach houses in white and red along a canal',
                'image_caption' => 'The palheiros, repainted every spring.',
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(33),
                'featured_at' => now()->subDays(33),
                'body' => $this->guesthouseBody(),
            ],
            [
                'title' => 'How to take a night train without hating yourself',
                'slug' => 'how-to-take-a-night-train-without-hating-yourself',
                'category' => 'Slow Travel',
                'tags' => ['night train', 'budget stays', 'first timers'],
                'excerpt' => 'Six overnight trains in a year taught us which carriage to book, when to eat, and why the top bunk is a trap.',
                'image_alt' => 'A narrow sleeper compartment with a made bunk and a window into darkness',
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(48),
                'body' => $this->nightTrainBody(),
            ],
            [
                'title' => 'Hot springs you can reach without a car',
                'slug' => 'hot-springs-you-can-reach-without-a-car',
                'category' => 'Iceland',
                'tags' => ['hot springs', 'walking routes', 'budget stays'],
                'excerpt' => 'Four pools within reach of a bus stop and a reasonable walk, and one that is absolutely not worth the hike.',
                'image_alt' => 'Steam rising off a natural pool ringed by dark rock',
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(66),
                'body' => $this->hotSpringsBody(),
            ],
            [
                'title' => 'Lunch is the plan: eating late along the Alentejo coast',
                'slug' => 'lunch-is-the-plan-eating-late-along-the-alentejo-coast',
                'category' => 'Coastal Portugal',
                'tags' => ['coastline', 'family run', 'off season'],
                'excerpt' => 'If you build the day around a two o\'clock table, everything else falls into place. Six stops between Comporta and Zambujeira.',
                'image_alt' => 'A tiled restaurant terrace overlooking an empty Atlantic beach',
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(81),
                'body' => $this->alentejoBody(),
            ],
            [
                'title' => 'Booking the shoulder season on purpose',
                'slug' => 'booking-the-shoulder-season-on-purpose',
                'category' => 'Slow Travel',
                'tags' => ['off season', 'budget stays', 'first timers'],
                'excerpt' => 'Draft — the arithmetic of travelling in the two weeks either side of everyone else.',
                'image_alt' => 'An empty hotel terrace with stacked chairs and low sun',
                'status' => PostStatus::Draft,
                'scheduled_at' => now()->addDays(4),
                'body' => $this->shoulderSeasonBody(),
            ],
            [
                'title' => 'What we pack for eleven days in one bag',
                'slug' => 'what-we-pack-for-eleven-days-in-one-bag',
                'category' => 'Slow Travel',
                'tags' => ['first timers', 'budget stays'],
                'excerpt' => 'Draft — the list, the mistakes, and the three things we now refuse to leave behind.',
                'image_alt' => 'A packed carry-on bag open on a hotel bed',
                'status' => PostStatus::Draft,
                'body' => $this->packingBody(),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $content
     * @return array<string, mixed>
     */
    private function doc(array $content): array
    {
        return ['type' => 'doc', 'content' => $content];
    }

    /**
     * @return array<string, mixed>
     */
    private function paragraph(string $text): array
    {
        return [
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function heading(string $text, int $level = 2): array
    {
        return [
            'type' => 'heading',
            'attrs' => ['level' => $level],
            'content' => [['type' => 'text', 'text' => $text]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function quote(string $text): array
    {
        return ['type' => 'blockquote', 'content' => [$this->paragraph($text)]];
    }

    /**
     * @param  list<string>  $items
     * @return array<string, mixed>
     */
    private function bulletList(array $items): array
    {
        return [
            'type' => 'bulletList',
            'content' => array_map(fn (string $item): array => [
                'type' => 'listItem',
                'content' => [$this->paragraph($item)],
            ], $items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ryokanBody(): array
    {
        return $this->doc([
            $this->paragraph('We took the slow train south out of Shinjuku on a Tuesday afternoon, and by the time the suburbs gave up and the valleys started, the carriage had emptied to four of us. That is the whole appeal of going in February: nobody else wants to.'),
            $this->paragraph('The ryokan sits about twenty minutes uphill from the station, and there is no shuttle. You call from a payphone that still works, or you walk. We walked, badly, with the wrong shoes.'),
            $this->heading('Arriving, and being fed immediately'),
            $this->paragraph('Check-in is at three, and dinner is at six, and there is no negotiating either. This felt oppressive for about an hour and then became the best thing about the stay. Somebody else was deciding when we ate, so we stopped thinking about it.'),
            $this->quote('By the second evening we had stopped reaching for our phones at the table. Not out of discipline — there was simply nothing on them.'),
            $this->paragraph('The meal is kaiseki, which means eleven small things arriving in an order somebody thought hard about. We understood maybe half of it. The half we understood was excellent.'),
            $this->heading('The bath, and the etiquette nobody explains'),
            $this->paragraph('The outdoor bath faces a garden that had six inches of snow on it. Here is what we wished we had known before the first attempt:'),
            $this->bulletList([
                'Wash completely at the low stools before you get in. This is not a rinse — it is the actual wash.',
                'The small towel never goes in the water. Fold it and put it on your head like everyone else.',
                'Six in the morning is empty. Nine at night is not.',
                'If you have tattoos, ask when you book rather than at the door.',
            ]),
            $this->paragraph('We went in three times a day for three days and it never got old, which is not a sentence we expected to write about sitting in hot water.'),
            $this->heading('Was the third night worth it?'),
            $this->paragraph('Yes, and it was not close. Two nights is an interruption; three is a change of pace. The first day you are still travelling. The second you sleep properly. The third you finally stop checking what time it is.'),
            ['type' => 'horizontalRule'],
            $this->paragraph('We paid ¥28,000 per person per night including both meals, in low season. It is not cheap and it is not trying to be. If you are choosing between two nights here and four somewhere cheaper, take the two nights.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ringRoadBody(): array
    {
        return $this->doc([
            $this->paragraph('Every itinerary you will find online budgets seven days for Route 1. Those itineraries are written by people who did it in seven days and would not do it that way again.'),
            $this->heading('What seven days actually buys you'),
            $this->paragraph('Roughly 1,330 kilometres of road, before you add a single detour. That is a comfortable four hours of driving a day with no stops — and the stops are the entire point.'),
            $this->quote('You are not driving to see Iceland. You are driving between the places where you get out of the car, and those places are where the trip happens.'),
            $this->heading('Where the time actually goes'),
            $this->bulletList([
                'The south coast eats two full days on its own, and deserves them.',
                'The east fjords are slow by design — single lanes, blind rises, and no reason to hurry.',
                'Weather closures are not rare. Budget a spare day or you will be driving in conditions you should not be.',
                'Every waterfall is thirty minutes longer than you planned.',
            ]),
            $this->heading('The eleven-day version'),
            $this->paragraph('We gave it eleven days in late September and still skipped two fjords we had meant to see. Nobody has ever come back from this drive wishing they had scheduled it tighter.'),
            $this->paragraph('If you only have a week, do half the ring and turn around. A relaxed south coast beats a rushed loop, every time.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function guesthouseBody(): array
    {
        return $this->doc([
            $this->paragraph('Costa Nova is twenty minutes by bus from Aveiro, and most people come for an afternoon, photograph the striped houses, and leave. We stayed four nights in a nine-room guesthouse a street back from the water.'),
            $this->heading('The room'),
            $this->paragraph('Small, white, and quiet, with a window that actually opens and a wardrobe older than the building it sits in. No air conditioning, which matters in August and did not matter at all in April.'),
            $this->bulletList([
                '€94 a night in April, breakfast included. High season roughly doubles it.',
                'Rooms at the back are darker and considerably quieter.',
                'No lift, and the stairs are steep and narrow.',
                'Parking is on the street and genuinely fine out of season.',
            ]),
            $this->heading('Breakfast, which is the point'),
            $this->paragraph('Served until whenever you appear. Bread from the bakery on the corner, cheese, a bowl of oranges, and coffee that keeps arriving without being asked for. We ate outside three mornings out of four.'),
            $this->quote('The owner asked where we were walking, then rewrote our route on the back of a receipt. Her version was better.'),
            $this->heading('Would we book it again?'),
            $this->paragraph('Yes — in April, and for at least three nights. It is a place that rewards staying put, and it is wasted on a single night between other destinations.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function nightTrainBody(): array
    {
        return $this->doc([
            $this->paragraph('Six overnight trains in a year, across four countries, in every class from a reclining seat to a two-berth sleeper with its own sink. Some of it was lovely. Some of it was eleven hours of not sleeping.'),
            $this->heading('Book the right carriage'),
            $this->paragraph('Couchettes sleep four or six strangers; sleepers sleep one or two and cost roughly double. The gap in price is real, and so is the gap in whether you arrive functional.'),
            $this->bulletList([
                'Ask for a carriage away from the restaurant car and the doors.',
                'Lower bunks are worth paying for. The top bunk has no window and nowhere to put anything.',
                'Middle bunks are the worst seat on the train. Avoid them at any price.',
            ]),
            $this->heading('Eat before you board'),
            $this->paragraph('The on-board food is expensive and finishes early. Buy dinner at the station, eat it in the first hour while the light is still going, and you will sleep considerably better.'),
            $this->quote('The trick is to treat the night train as a hotel that happens to move, not as a journey you have to endure.'),
            $this->heading('Arrival is the hard part'),
            $this->paragraph('You will be turned out at six in the morning into a city where nothing opens until nine. Book somewhere with luggage storage, or a room you can drop bags at early — this single decision does more for the day than anything else.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function hotSpringsBody(): array
    {
        return $this->doc([
            $this->paragraph('Not everyone hires a car, and a surprising number of Iceland\'s best pools are reachable on a bus and a walk. These four are, and one popular one very much is not.'),
            $this->heading('Reykjadalur, above Hveragerði'),
            $this->paragraph('A bus from Reykjavík and then an hour uphill along a river that gets warmer as you climb. Go far enough that the crowd thins, then get in where the temperature suits you.'),
            $this->heading('The town pools, which nobody writes about'),
            $this->paragraph('Every settlement has one, they cost about a thousand krónur, and they are where people actually are. Warmer than the tourist lagoons, a tenth of the price, and you will be the only visitor in the hot pot.'),
            $this->bulletList([
                'Bring a towel — hire is possible but rarely cheap.',
                'Shower properly before entering. This is enforced and non-negotiable.',
                'Weekday mornings are almost empty.',
            ]),
            $this->heading('The one to skip'),
            $this->paragraph('We walked ninety minutes each way for a pool that turned out to be lukewarm, crowded, and beside a car park we could have driven to. Check whether the hike is the point, or just the price of admission.'),
            $this->quote('A hot spring you have to earn is better. A hot spring you have to earn and then share with a coach party is just a long walk.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function alentejoBody(): array
    {
        return $this->doc([
            $this->paragraph('Portuguese lunch runs from one until roughly four, and if you plan the day around it instead of squeezing it in, the coast opens up considerably.'),
            $this->heading('Build the day backwards'),
            $this->paragraph('Pick where you are eating, then work out what you can walk before and after. This sounds like a joke. It produced our best three days on the coast.'),
            $this->heading('Six stops worth the detour'),
            $this->bulletList([
                'Comporta — rice fields, a long empty beach, and one restaurant that takes bookings a week out.',
                'Carvalhal — quieter, cheaper, and better for a late lunch than an early one.',
                'Melides — go for the lagoon, stay for the grilled fish.',
                'Porto Covo — the prettiest square on this stretch, and busy by two.',
                'Vila Nova de Milfontes — the only place here that feels like a town rather than a stop.',
                'Zambujeira do Mar — the end of the road, in the best sense.',
            ]),
            $this->quote('Nobody is rushing you out. If the table is yours at two, it is yours until they need it, and they will not need it.'),
            $this->heading('What to order when you cannot read the board'),
            $this->paragraph('Whatever is chalked up and priced by the kilo. It is what came in that morning, it is what the kitchen wants to cook, and it will be better than anything printed on a laminated menu.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shoulderSeasonBody(): array
    {
        return $this->doc([
            $this->paragraph('Draft. The two weeks either side of peak season are consistently the best value in travel, and the arithmetic is not subtle.'),
            $this->heading('The price cliff'),
            $this->paragraph('Rates on either side of a school holiday routinely differ by forty percent for the same room in the same week.'),
            $this->heading('What you give up'),
            $this->paragraph('Some restaurants shut. Some ferries stop. Work out which ones matter before you book.'),
            $this->heading('What you get back'),
            $this->paragraph('Tables without booking, staff who have time to talk, and photographs without strangers in them.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function packingBody(): array
    {
        return $this->doc([
            $this->paragraph('Draft. Eleven days, one 40-litre bag, no checked luggage, and two mistakes we keep making.'),
            $this->heading('The list'),
            $this->bulletList([
                'Four days of clothes and a plan for washing them.',
                'One pair of shoes you can walk twenty kilometres in.',
                'A dry bag, which has saved this list twice.',
            ]),
            $this->heading('What we always over-pack'),
            $this->paragraph('Layers for weather that never arrives.'),
            $this->heading('What we now refuse to leave behind'),
            $this->paragraph('A travel plug with three sockets, earplugs, and a paper map of wherever we land.'),
        ]);
    }
}
