<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@govista.am');
        $adminPassword = env('ADMIN_PASSWORD');

        if (! $adminPassword && app()->environment('production')) {
            throw new \RuntimeException('ADMIN_PASSWORD must be set before running the production seeder.');
        }

        User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'GoVista Administrator',
                'password' => Hash::make($adminPassword ?: 'GoVista2026!'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $images = [
            'ararat' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1800&q=88',
            'sevan' => 'https://images.unsplash.com/photo-1470770841072-f978cf4d019e?auto=format&fit=crop&w=1400&q=86',
            'garni' => 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1400&q=86',
            'tatev' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1400&q=86',
            'dilijan' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=1400&q=86',
            'yerevan' => 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?auto=format&fit=crop&w=1400&q=86',
            'traveler' => 'https://images.unsplash.com/photo-1526772662000-3f88f10405ff?auto=format&fit=crop&w=1400&q=86',
            'hotel' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1400&q=86',
            'car' => 'https://images.unsplash.com/photo-1503736334956-4c8f8e92946d?auto=format&fit=crop&w=1400&q=86',
            'event' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=1400&q=86',
            'dubai' => 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=1400&q=86',
            'istanbul' => 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?auto=format&fit=crop&w=1400&q=86',
            'tbilisi' => 'https://images.unsplash.com/photo-1565008576549-57569a49371d?auto=format&fit=crop&w=1400&q=86',
            'paris' => 'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?auto=format&fit=crop&w=1400&q=86',
            'rome' => 'https://images.unsplash.com/photo-1552832230-c0197dd311b5?auto=format&fit=crop&w=1400&q=86',
            'santorini' => 'https://images.unsplash.com/photo-1570077188670-e3a8d69ac5ff?auto=format&fit=crop&w=1400&q=86',
            'egypt' => 'https://images.unsplash.com/photo-1503177119275-0aa32b3a9368?auto=format&fit=crop&w=1400&q=86',
            'maldives' => 'https://images.unsplash.com/photo-1514282401047-d79a71a590e8?auto=format&fit=crop&w=1400&q=86',
            'prague' => 'https://images.unsplash.com/photo-1541849546-216549ae216d?auto=format&fit=crop&w=1400&q=86',
            'barcelona' => 'https://images.unsplash.com/photo-1539037116277-4db20889f2d4?auto=format&fit=crop&w=1400&q=86',
            'cyprus' => 'https://images.unsplash.com/photo-1530841377377-3ff06c0ca713?auto=format&fit=crop&w=1400&q=86',
            'montenegro' => 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?auto=format&fit=crop&w=1400&q=86',
        ];

        $tours = [
            [
                'slug' => 'garni-geghard-symphony',
                'travel_scope' => 'domestic',
                'type' => 'group',
                'title' => $this->tr('Գառնի, Գեղարդ և Քարերի սիմֆոնիա', 'Гарни, Гегард и Симфония камней', 'Garni, Geghard & Symphony of Stones'),
                'subtitle' => $this->tr('Հին Հայաստանի ամենատպավորիչ պատմությունները մեկ օրում', 'Самые яркие истории древней Армении за один день', 'The best stories of ancient Armenia in one day'),
                'description' => $this->tr(
                    'Բացահայտեք Գառնիի հեթանոսական տաճարը, ժայռափոր Գեղարդը և Ազատի կիրճի բազալտե հրաշքը՝ հոգատար գիդի ուղեկցությամբ։',
                    'Откройте языческий храм Гарни, высеченный в скале Гегард и базальтовое чудо ущелья Азат с заботливым гидом.',
                    'Discover the pagan temple of Garni, rock-carved Geghard and the basalt wonder of Azat Gorge with an expert local guide.'
                ),
                'location' => $this->tr('Կոտայքի մարզ', 'Котайкская область', 'Kotayk Region'),
                'duration' => $this->tr('7 ժամ', '7 часов', '7 hours'),
                'price' => 14000,
                'old_price' => 16000,
                'currency' => 'AMD',
                'rating' => 4.95,
                'review_count' => 184,
                'image' => $images['garni'],
                'gallery' => [$images['garni'], $images['dilijan'], $images['traveler']],
                'highlights' => $this->trList(
                    ['Գառնիի տաճար', 'Գեղարդի վանք', 'Քարերի սիմֆոնիա', 'Լավաշի թխման փորձ'],
                    ['Храм Гарни', 'Монастырь Гегард', 'Симфония камней', 'Мастер-класс по лавашу'],
                    ['Garni Temple', 'Geghard Monastery', 'Symphony of Stones', 'Lavash baking experience']
                ),
                'itinerary' => $this->trList(
                    ['Մեկնում Երևանից', 'Գառնի և տեղական փորձառություն', 'Գեղարդ', 'Քարերի սիմֆոնիա', 'Վերադարձ'],
                    ['Выезд из Еревана', 'Гарни и местный опыт', 'Гегард', 'Симфония камней', 'Возвращение'],
                    ['Departure from Yerevan', 'Garni and local experience', 'Geghard', 'Symphony of Stones', 'Return']
                ),
                'included' => $this->trList(['Տրանսպորտ', 'Գիդ', 'Ջուր', 'Wi‑Fi'], ['Транспорт', 'Гид', 'Вода', 'Wi‑Fi'], ['Transport', 'Guide', 'Water', 'Wi‑Fi']),
                'excluded' => $this->trList(['Ճաշ', 'Մուտքի տոմսեր'], ['Обед', 'Входные билеты'], ['Lunch', 'Entrance tickets']),
                'featured' => true,
                'active' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'khor-virap-noravank-areni',
                'travel_scope' => 'domestic',
                'type' => 'group',
                'title' => $this->tr('Խոր Վիրապ, Արենի և Նորավանք', 'Хор Вирап, Арени и Нораванк', 'Khor Virap, Areni & Noravank'),
                'subtitle' => $this->tr('Արարատի տեսարան, գինի և կարմիր ժայռեր', 'Вид на Арарат, вино и красные скалы', 'Ararat views, wine and red cliffs'),
                'description' => $this->tr(
                    'Օր, որտեղ միավորվում են Արարատի խորհրդանշական տեսարանը, հայկական գինու համը և Նորավանքի կիրճի կախարդական լռությունը։',
                    'День, объединяющий культовый вид на Арарат, вкус армянского вина и волшебную тишину ущелья Нораванк.',
                    'A day uniting the iconic Ararat view, Armenian wine and the magical quiet of Noravank Canyon.'
                ),
                'location' => $this->tr('Արարատ և Վայոց Ձոր', 'Арарат и Вайоц Дзор', 'Ararat & Vayots Dzor'),
                'duration' => $this->tr('10 ժամ', '10 часов', '10 hours'),
                'price' => 18000,
                'currency' => 'AMD',
                'rating' => 4.98,
                'review_count' => 231,
                'image' => $images['ararat'],
                'gallery' => [$images['ararat'], $images['garni']],
                'highlights' => $this->trList(
                    ['Խոր Վիրապ', 'Արենիի գինու համտես', 'Թռչունների քարանձավ', 'Նորավանք'],
                    ['Хор Вирап', 'Дегустация вина в Арени', 'Пещера птиц', 'Нораванк'],
                    ['Khor Virap', 'Areni wine tasting', 'Birds Cave', 'Noravank']
                ),
                'included' => $this->trList(['Տրանսպորտ', 'Գիդ', 'Գինու համտես'], ['Транспорт', 'Гид', 'Дегустация вина'], ['Transport', 'Guide', 'Wine tasting']),
                'excluded' => $this->trList(['Ճաշ'], ['Обед'], ['Lunch']),
                'featured' => true,
                'active' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'sevan-dilijan-forest',
                'travel_scope' => 'domestic',
                'type' => 'group',
                'title' => $this->tr('Սևան և Դիլիջանի անտառներ', 'Севан и леса Дилижана', 'Sevan & Dilijan Forests'),
                'subtitle' => $this->tr('Կապույտ լիճ և հայկական փոքր Շվեյցարիա', 'Голубое озеро и армянская маленькая Швейцария', 'The blue lake and Armenia’s little Switzerland'),
                'description' => $this->tr(
                    'Թեթև ու գունեղ ճանապարհորդություն դեպի Սևանավանք, Դիլիջանի հին փողոց և Հաղարծնի կանաչապատ վանք։',
                    'Лёгкое и красочное путешествие к Севанаванку, старой улице Дилижана и зелёному монастырю Агарцин.',
                    'An easy, colorful journey to Sevanavank, Dilijan old town and the forest-wrapped Haghartsin Monastery.'
                ),
                'location' => $this->tr('Գեղարքունիք և Տավուշ', 'Гегаркуник и Тавуш', 'Gegharkunik & Tavush'),
                'duration' => $this->tr('9 ժամ', '9 часов', '9 hours'),
                'price' => 16500,
                'currency' => 'AMD',
                'rating' => 4.92,
                'review_count' => 147,
                'image' => $images['sevan'],
                'gallery' => [$images['sevan'], $images['dilijan']],
                'highlights' => $this->trList(
                    ['Սևանավանք', 'Սևանա լիճ', 'Հին Դիլիջան', 'Հաղարծին'],
                    ['Севанаванк', 'Озеро Севан', 'Старый Дилижан', 'Агарцин'],
                    ['Sevanavank', 'Lake Sevan', 'Old Dilijan', 'Haghartsin']
                ),
                'included' => $this->trList(['Տրանսպորտ', 'Գիդ', 'Ջուր'], ['Транспорт', 'Гид', 'Вода'], ['Transport', 'Guide', 'Water']),
                'excluded' => $this->trList(['Ճաշ'], ['Обед'], ['Lunch']),
                'featured' => true,
                'active' => true,
                'sort_order' => 3,
            ],
            [
                'slug' => 'tatev-wings-private',
                'travel_scope' => 'domestic',
                'type' => 'private',
                'title' => $this->tr('Տաթևի թևեր. անհատական օր', 'Крылья Татева: индивидуальный день', 'Wings of Tatev: Private Day'),
                'subtitle' => $this->tr('Հայաստանի հարավը՝ ձեր ռիթմով', 'Юг Армении в вашем ритме', 'Southern Armenia at your own pace'),
                'description' => $this->tr(
                    'Անհատական երկար օր՝ ճկուն կանգառներով, Տաթևի վանքով և աշխարհի ամենատպավորիչ ճոպանուղիներից մեկով։',
                    'Индивидуальный насыщенный день с гибкими остановками, монастырём Татев и одной из самых впечатляющих канатных дорог мира.',
                    'A private full day with flexible stops, Tatev Monastery and one of the world’s most spectacular cableways.'
                ),
                'location' => $this->tr('Սյունիքի մարզ', 'Сюникская область', 'Syunik Region'),
                'duration' => $this->tr('13 ժամ', '13 часов', '13 hours'),
                'price' => 68000,
                'currency' => 'AMD',
                'rating' => 5,
                'review_count' => 89,
                'image' => $images['tatev'],
                'gallery' => [$images['tatev'], $images['ararat']],
                'highlights' => $this->trList(
                    ['Շաքիի ջրվեժ', 'Տաթևի ճոպանուղի', 'Տաթևի վանք', 'Ճկուն երթուղի'],
                    ['Водопад Шаки', 'Канатная дорога Татева', 'Монастырь Татев', 'Гибкий маршрут'],
                    ['Shaki Waterfall', 'Wings of Tatev', 'Tatev Monastery', 'Flexible itinerary']
                ),
                'included' => $this->trList(['Անհատական մեքենա', 'Գիդ', 'Հյուրանոցից վերցնելը'], ['Частный автомобиль', 'Гид', 'Трансфер из отеля'], ['Private vehicle', 'Guide', 'Hotel pickup']),
                'excluded' => $this->trList(['Ճոպանուղու տոմս', 'Ճաշ'], ['Билет на канатную дорогу', 'Обед'], ['Cableway ticket', 'Lunch']),
                'featured' => true,
                'active' => true,
                'sort_order' => 4,
            ],
            [
                'slug' => 'armenia-seven-day-signature',
                'travel_scope' => 'domestic',
                'type' => 'package',
                'title' => $this->tr('Հայաստանի լավագույնը՝ 7 օրում', 'Лучшее Армении за 7 дней', 'Best of Armenia in 7 Days'),
                'subtitle' => $this->tr('Մշակույթ, բնություն, համեր և ընտրված հյուրանոցներ', 'Культура, природа, вкусы и отобранные отели', 'Culture, nature, flavors and handpicked hotels'),
                'description' => $this->tr(
                    'Մշակված ամբողջական փաթեթ՝ օդանավակայանի դիմավորումից մինչև վերջին հայկական ընթրիքը։',
                    'Продуманный полный пакет — от встречи в аэропорту до заключительного армянского ужина.',
                    'A thoughtfully curated package from airport welcome to your final Armenian dinner.'
                ),
                'location' => $this->tr('Հայաստան', 'Армения', 'Armenia'),
                'duration' => $this->tr('7 օր / 6 գիշեր', '7 дней / 6 ночей', '7 days / 6 nights'),
                'price' => 395000,
                'currency' => 'AMD',
                'rating' => 4.99,
                'review_count' => 64,
                'image' => $images['traveler'],
                'gallery' => [$images['traveler'], $images['sevan'], $images['ararat'], $images['tatev']],
                'highlights' => $this->trList(
                    ['Երևան', 'Գառնի և Գեղարդ', 'Սևան և Դիլիջան', 'Խոր Վիրապ և Նորավանք', 'Տաթև'],
                    ['Ереван', 'Гарни и Гегард', 'Севан и Дилижан', 'Хор Вирап и Нораванк', 'Татев'],
                    ['Yerevan', 'Garni & Geghard', 'Sevan & Dilijan', 'Khor Virap & Noravank', 'Tatev']
                ),
                'included' => $this->trList(['Հյուրանոց', 'Նախաճաշ', 'Տրանսպորտ', 'Գիդ', 'Դիմավորում'], ['Отель', 'Завтрак', 'Транспорт', 'Гид', 'Встреча'], ['Hotel', 'Breakfast', 'Transport', 'Guide', 'Airport welcome']),
                'excluded' => $this->trList(['Ավիատոմս', 'Ապահովագրություն'], ['Авиабилет', 'Страховка'], ['Flights', 'Insurance']),
                'featured' => true,
                'active' => true,
                'sort_order' => 5,
            ],
        ];

        $tours = [
            ...$tours,
            $this->catalogTour(
                'gyumri-cultural-day', 'domestic', 'group',
                $this->tr('Գյումրի․ մշակույթ և հումոր', 'Гюмри: культура и юмор', 'Gyumri: Culture & Character'),
                $this->tr('Քաղաքային պատմություններ, արհեստներ և իսկական գյումրվա համեր', 'Городские истории, ремёсла и настоящие вкусы Гюмри', 'City stories, crafts and authentic Gyumri flavors'),
                $this->tr('Քայլեք Գյումրիի սև տուֆով փողոցներով, այցելեք արհեստանոց և վայելեք տեղական խոհանոցը՝ քաղաքի բնավորությունը ճանաչող գիդի հետ։', 'Пройдите по улицам чёрного туфа, загляните в мастерскую и попробуйте местную кухню с гидом, который знает характер города.', 'Walk Gyumri’s black-tuff streets, visit an artisan studio and taste local cuisine with a guide who knows the city’s character.'),
                $this->tr('Շիրակի մարզ', 'Ширакская область', 'Shirak Region'),
                $this->tr('10 ժամ', '10 часов', '10 hours'), 19500, 'AMD', $images['yerevan'],
                $this->trList(['Կումայրի արգելոց', 'Սև բերդ', 'Արհեստանոց', 'Տեղական ճաշ'], ['Заповедник Кумайри', 'Чёрная крепость', 'Мастерская', 'Местный обед'], ['Kumayri district', 'Black Fortress', 'Artisan studio', 'Local lunch']), 6
            ),
            $this->catalogTour(
                'lori-monasteries-canyon', 'domestic', 'group',
                $this->tr('Լոռու վանքեր և կիրճեր', 'Монастыри и каньоны Лори', 'Lori Monasteries & Canyons'),
                $this->tr('ՅՈՒՆԵՍԿՕ-ի ժառանգություն և կանաչ հյուսիս', 'Наследие ЮНЕСКО и зелёный север', 'UNESCO heritage and Armenia’s green north'),
                $this->tr('Բացահայտեք Հաղպատն ու Սանահինը, Դեբեդի կիրճը և Լոռու խաղաղ լեռնային համայնքները մեկ հագեցած օրում։', 'Откройте Ахпат и Санаин, каньон Дебед и тихие горные сёла Лори за один насыщенный день.', 'Discover Haghpat and Sanahin, Debed Canyon and Lori’s quiet mountain communities in one immersive day.'),
                $this->tr('Լոռու մարզ', 'Лорийская область', 'Lori Region'),
                $this->tr('12 ժամ', '12 часов', '12 hours'), 22000, 'AMD', $images['dilijan'],
                $this->trList(['Հաղպատ', 'Սանահին', 'Դեբեդի կիրճ', 'Տեղական թեյ'], ['Ахпат', 'Санаин', 'Каньон Дебед', 'Местный чай'], ['Haghpat', 'Sanahin', 'Debed Canyon', 'Local tea']), 7
            ),
            $this->catalogTour(
                'jermuk-waterfall-wellness', 'domestic', 'private',
                $this->tr('Ջերմուկ․ ջրվեժ և հանգիստ', 'Джермук: водопад и отдых', 'Jermuk Waterfall & Wellness'),
                $this->tr('Հանքային ջուր, լեռներ և դանդաղ օր', 'Минеральная вода, горы и неспешный день', 'Mineral water, mountains and a slower pace'),
                $this->tr('Անհատական ուղևորություն դեպի Ջերմուկ՝ ջրվեժով, հանքային ջրերի սրահով և ազատ ժամանակով առողջարանային քաղաքում։', 'Индивидуальная поездка в Джермук с водопадом, галереей минеральных вод и свободным временем в курортном городе.', 'A private trip to Jermuk with its waterfall, mineral-water gallery and unhurried time in the spa town.'),
                $this->tr('Վայոց Ձոր', 'Вайоц Дзор', 'Vayots Dzor'),
                $this->tr('11 ժամ', '11 часов', '11 hours'), 72000, 'AMD', $images['tatev'],
                $this->trList(['Ջերմուկի ջրվեժ', 'Հանքային ջրերի սրահ', 'Քաղաքային ճոպանուղի', 'Ճկուն կանգառներ'], ['Водопад Джермук', 'Галерея минеральных вод', 'Городская канатная дорога', 'Гибкие остановки'], ['Jermuk Waterfall', 'Mineral water gallery', 'Town cable car', 'Flexible stops']), 8
            ),
            $this->catalogTour(
                'aragats-amberd-adventure', 'domestic', 'group',
                $this->tr('Արագած և Ամբերդ', 'Арагац и Амберд', 'Aragats & Amberd Adventure'),
                $this->tr('Լեռնային օդ, միջնադարյան ամրոց և Քարի լիճ', 'Горный воздух, средневековая крепость и озеро Кари', 'Mountain air, a medieval fortress and Lake Kari'),
                $this->tr('Ակտիվ օր Արագածի լանջերին՝ Ամբերդի ամրոցով, Քարի լճով և եղանակին համապատասխան թեթև քայլարշավով։', 'Активный день на склонах Арагаца с крепостью Амберд, озером Кари и лёгким походом по погоде.', 'An active day on Mount Aragats with Amberd Fortress, Lake Kari and a weather-friendly short hike.'),
                $this->tr('Արագածոտնի մարզ', 'Арагацотнская область', 'Aragatsotn Region'),
                $this->tr('8 ժամ', '8 часов', '8 hours'), 17500, 'AMD', $images['ararat'],
                $this->trList(['Ամբերդ', 'Քարի լիճ', 'Լեռնային քայլարշավ', 'Բյուրական'], ['Амберд', 'Озеро Кари', 'Горная прогулка', 'Бюракан'], ['Amberd', 'Lake Kari', 'Mountain walk', 'Byurakan']), 9
            ),
            $this->catalogTour(
                'echmiadzin-zvartnots', 'domestic', 'group',
                $this->tr('Էջմիածին և Զվարթնոց', 'Эчмиадзин и Звартноц', 'Echmiadzin & Zvartnots'),
                $this->tr('Հայաստանի հոգևոր սիրտը կես օրում', 'Духовное сердце Армении за полдня', 'Armenia’s spiritual heart in half a day'),
                $this->tr('Այցելեք Մայր Տաճարի համալիրը, Սուրբ Գայանեն և Զվարթնոցի տպավորիչ ավերակները՝ մասնագետ գիդի բացատրությամբ։', 'Посетите комплекс Кафедрального собора, Святую Гаянэ и впечатляющие руины Звартноца с экспертным гидом.', 'Visit the Mother Cathedral complex, Saint Gayane and the striking ruins of Zvartnots with an expert guide.'),
                $this->tr('Արմավիրի մարզ', 'Армавирская область', 'Armavir Region'),
                $this->tr('5 ժամ', '5 часов', '5 hours'), 11000, 'AMD', $images['garni'],
                $this->trList(['Մայր Տաճար', 'Սուրբ Գայանե', 'Զվարթնոց', 'Թանգարան'], ['Кафедральный собор', 'Святая Гаянэ', 'Звартноц', 'Музей'], ['Mother Cathedral', 'Saint Gayane', 'Zvartnots', 'Museum']), 10
            ),
            $this->catalogTour(
                'khndzoresk-cave-city', 'domestic', 'private',
                $this->tr('Խնձորեսկի քարանձավային քաղաք', 'Пещерный город Хндзореск', 'Khndzoresk Cave City'),
                $this->tr('Կախովի կամուրջ և Սյունիքի վայրի բնություն', 'Подвесной мост и дикая природа Сюника', 'A suspension bridge and wild Syunik landscapes'),
                $this->tr('Երկար, բայց անմոռանալի անհատական օր դեպի Խնձորեսկի քարանձավները, կախովի կամուրջը և Գորիսի շրջանի տեսարանները։', 'Долгий, но незабываемый индивидуальный день к пещерам Хндзореска, подвесному мосту и пейзажам Гориса.', 'A long yet unforgettable private day to Khndzoresk’s caves, suspension bridge and the landscapes around Goris.'),
                $this->tr('Սյունիքի մարզ', 'Сюникская область', 'Syunik Region'),
                $this->tr('14 ժամ', '14 часов', '14 hours'), 82000, 'AMD', $images['tatev'],
                $this->trList(['Հին Խնձորեսկ', 'Կախովի կամուրջ', 'Գորիս', 'Ֆոտո կանգառներ'], ['Старый Хндзореск', 'Подвесной мост', 'Горис', 'Фото-остановки'], ['Old Khndzoresk', 'Suspension bridge', 'Goris', 'Photo stops']), 11
            ),
            $this->catalogTour(
                'lake-arpi-north', 'domestic', 'package',
                $this->tr('Արփի լիճ և հյուսիսային գյուղեր', 'Озеро Арпи и северные деревни', 'Lake Arpi & Northern Villages'),
                $this->tr('Երկօրյա բնության փոքր արշավ', 'Двухдневное путешествие на природу', 'A two-day nature escape'),
                $this->tr('Հանգիստ երկօրյա փաթեթ Շիրակի բարձրավանդակում՝ թռչնադիտարկմամբ, գյուղական հյուրատնով և Արփի լճի լայն հորիզոնով։', 'Спокойный двухдневный пакет на Ширакском плато с наблюдением за птицами, сельским гостевым домом и просторами озера Арпи.', 'A quiet two-day package on the Shirak plateau with birdwatching, a village guesthouse and Lake Arpi’s wide horizons.'),
                $this->tr('Շիրակի մարզ', 'Ширакская область', 'Shirak Region'),
                $this->tr('2 օր / 1 գիշեր', '2 дня / 1 ночь', '2 days / 1 night'), 96000, 'AMD', $images['sevan'],
                $this->trList(['Արփի լիճ', 'Թռչնադիտարկում', 'Գյուղական հյուրատուն', 'Տեղական ընթրիք'], ['Озеро Арпи', 'Наблюдение за птицами', 'Сельский гостевой дом', 'Местный ужин'], ['Lake Arpi', 'Birdwatching', 'Village guesthouse', 'Local dinner']), 12
            ),
            $this->catalogTour(
                'dubai-city-desert', 'international', 'package',
                $this->tr('Դուբայ․ քաղաք և անապատ', 'Дубай: город и пустыня', 'Dubai City & Desert'),
                $this->tr('Փայլուն քաղաք, սաֆարի և ծովափ', 'Сияющий город, сафари и пляж', 'Skyline, desert safari and beach time'),
                $this->tr('Հինգօրյա պատրաստ փաթեթ Երևանից՝ ընտրված հյուրանոցով, օդանավակայանի տրանսֆերով, քաղաքի տուրով և անապատային սաֆարիով։', 'Готовый пятидневный пакет из Еревана с выбранным отелем, трансфером, обзорной экскурсией и сафари по пустыне.', 'A ready five-day package from Yerevan with a handpicked hotel, airport transfers, city tour and desert safari.'),
                $this->tr('Դուբայ, ԱՄԷ', 'Дубай, ОАЭ', 'Dubai, UAE'),
                $this->tr('5 օր / 4 գիշեր', '5 дней / 4 ночи', '5 days / 4 nights'), 690, 'USD', $images['dubai'],
                $this->trList(['Քաղաքային տուր', 'Անապատային սաֆարի', 'Մարինա', 'Ազատ օր ծովափին'], ['Обзорная экскурсия', 'Сафари по пустыне', 'Марина', 'Свободный день у моря'], ['City tour', 'Desert safari', 'Marina', 'Free beach day']), 13, true
            ),
            $this->catalogTour(
                'istanbul-bosphorus-weekend', 'international', 'package',
                $this->tr('Ստամբուլ և Բոսֆոր', 'Стамбул и Босфор', 'Istanbul & the Bosphorus'),
                $this->tr('Երկու մայրցամաք մեկ երկար հանգստյան օրում', 'Два континента за один длинный уикенд', 'Two continents in one long weekend'),
                $this->tr('Չորսօրյա քաղաքային փաթեթ՝ պատմական կենտրոնով, Բոսֆորի նավարկությամբ և ազատ ժամանակով շուկաների ու խոհանոցի համար։', 'Четырёхдневный городской пакет с историческим центром, круизом по Босфору и свободным временем для рынков и кухни.', 'A four-day city break with the historic center, a Bosphorus cruise and time for markets and food.'),
                $this->tr('Ստամբուլ, Թուրքիա', 'Стамбул, Турция', 'Istanbul, Türkiye'),
                $this->tr('4 օր / 3 գիշեր', '4 дня / 3 ночи', '4 days / 3 nights'), 520, 'USD', $images['istanbul'],
                $this->trList(['Սուլթանահմեթ', 'Բոսֆորի նավարկություն', 'Գալաթա', 'Մեծ շուկա'], ['Султанахмет', 'Круиз по Босфору', 'Галата', 'Гранд-базар'], ['Sultanahmet', 'Bosphorus cruise', 'Galata', 'Grand Bazaar']), 14
            ),
            $this->catalogTour(
                'tbilisi-kakheti-wine', 'international', 'group',
                $this->tr('Թբիլիսի և Կախեթիի գինին', 'Тбилиси и вина Кахетии', 'Tbilisi & Kakheti Wine'),
                $this->tr('Հին քաղաք, գինու ճանապարհ և վրացական հյուրընկալություն', 'Старый город, винная дорога и грузинское гостеприимство', 'Old town, wine country and Georgian hospitality'),
                $this->tr('Հարմարավետ եռօրյա ուղևորություն ավտոբուսով Երևանից՝ Թբիլիսիի շրջայցով և Կախեթիի գինու համտեսով։', 'Комфортная трёхдневная поездка на автобусе из Еревана с обзорной экскурсией по Тбилиси и дегустацией в Кахетии.', 'A comfortable three-day coach trip from Yerevan with a Tbilisi tour and Kakheti wine tasting.'),
                $this->tr('Թբիլիսի և Կախեթի, Վրաստան', 'Тбилиси и Кахетия, Грузия', 'Tbilisi & Kakheti, Georgia'),
                $this->tr('3 օր / 2 գիշեր', '3 дня / 2 ночи', '3 days / 2 nights'), 195, 'USD', $images['tbilisi'],
                $this->trList(['Հին Թբիլիսի', 'Նարիկալա', 'Սիղնաղ', 'Գինու համտես'], ['Старый Тбилиси', 'Нарикала', 'Сигнахи', 'Дегустация вина'], ['Old Tbilisi', 'Narikala', 'Sighnaghi', 'Wine tasting']), 15
            ),
            $this->catalogTour(
                'paris-romantic-escape', 'international', 'package',
                $this->tr('Փարիզյան հանգստյան օրեր', 'Романтический Париж', 'Paris Romantic Escape'),
                $this->tr('Դասական Փարիզ՝ ձեր ռիթմով', 'Классический Париж в вашем ритме', 'Classic Paris at your own pace'),
                $this->tr('Հինգօրյա փաթեթ՝ կենտրոնական հյուրանոցով, օդանավակայանի տրանսֆերով, քաղաքային շրջայցով և ազատ օրերով։', 'Пятидневный пакет с центральным отелем, трансфером, обзорной экскурсией и свободными днями.', 'A five-day package with a central hotel, airport transfers, a city tour and unhurried free days.'),
                $this->tr('Փարիզ, Ֆրանսիա', 'Париж, Франция', 'Paris, France'),
                $this->tr('5 օր / 4 գիշեր', '5 дней / 4 ночи', '5 days / 4 nights'), 980, 'EUR', $images['paris'],
                $this->trList(['Էյֆելյան աշտարակ', 'Սենա', 'Մոնմարտր', 'Ազատ օր'], ['Эйфелева башня', 'Сена', 'Монмартр', 'Свободный день'], ['Eiffel Tower', 'Seine', 'Montmartre', 'Free day']), 16
            ),
            $this->catalogTour(
                'rome-eternal-city', 'international', 'package',
                $this->tr('Հռոմ․ հավերժական քաղաք', 'Рим: вечный город', 'Rome: The Eternal City'),
                $this->tr('Պատմություն, արվեստ և իտալական համեր', 'История, искусство и итальянские вкусы', 'History, art and Italian flavors'),
                $this->tr('Չորս գիշեր Հռոմում՝ Կոլիզեումի և Վատիկանի ընտրովի տուրերով, հարմար տեղակայմամբ և անհատական խորհրդատվությամբ։', 'Четыре ночи в Риме с дополнительными экскурсиями в Колизей и Ватикан, удобным размещением и личными рекомендациями.', 'Four nights in Rome with optional Colosseum and Vatican tours, well-located accommodation and personal recommendations.'),
                $this->tr('Հռոմ, Իտալիա', 'Рим, Италия', 'Rome, Italy'),
                $this->tr('5 օր / 4 գիշեր', '5 дней / 4 ночи', '5 days / 4 nights'), 940, 'EUR', $images['rome'],
                $this->trList(['Կոլիզեում', 'Վատիկան', 'Տրևի շատրվան', 'Տրաստևերե'], ['Колизей', 'Ватикан', 'Фонтан Треви', 'Трастевере'], ['Colosseum', 'Vatican City', 'Trevi Fountain', 'Trastevere']), 17
            ),
            $this->catalogTour(
                'athens-santorini', 'international', 'package',
                $this->tr('Աթենք և Սանտորինի', 'Афины и Санторини', 'Athens & Santorini'),
                $this->tr('Անտիկ քաղաքից դեպի Էգեյան ծով', 'От античного города к Эгейскому морю', 'From ancient city to the Aegean Sea'),
                $this->tr('Յոթօրյա համակցված փաթեթ՝ երկու գիշեր Աթենքում, չորս գիշեր Սանտորինիում և ներքին լաստանավային անցումով։', 'Семидневный комбинированный пакет: две ночи в Афинах, четыре на Санторини и паром между островами.', 'A seven-day combination with two nights in Athens, four in Santorini and an included ferry connection.'),
                $this->tr('Աթենք և Սանտորինի, Հունաստան', 'Афины и Санторини, Греция', 'Athens & Santorini, Greece'),
                $this->tr('7 օր / 6 գիշեր', '7 дней / 6 ночей', '7 days / 6 nights'), 1280, 'EUR', $images['santorini'],
                $this->trList(['Ակրոպոլիս', 'Պլակա', 'Օյա', 'Կալդերայի մայրամուտ'], ['Акрополь', 'Плака', 'Ия', 'Закат над кальдерой'], ['Acropolis', 'Plaka', 'Oia', 'Caldera sunset']), 18
            ),
            $this->catalogTour(
                'cairo-hurghada', 'international', 'package',
                $this->tr('Կահիրե և Հուրգադա', 'Каир и Хургада', 'Cairo & Hurghada'),
                $this->tr('Բուրգեր, Կարմիր ծով և արև', 'Пирамиды, Красное море и солнце', 'Pyramids, Red Sea and sunshine'),
                $this->tr('Ութօրյա ընտանեկան փաթեթ՝ Կահիրեի տեսարժան վայրերով և Հուրգադայի all-inclusive ծովափնյա հանգստով։', 'Восьмидневный семейный пакет с достопримечательностями Каира и пляжным отдыхом all-inclusive в Хургаде.', 'An eight-day family package combining Cairo’s landmarks with an all-inclusive Red Sea stay in Hurghada.'),
                $this->tr('Կահիրե և Հուրգադա, Եգիպտոս', 'Каир и Хургада, Египет', 'Cairo & Hurghada, Egypt'),
                $this->tr('8 օր / 7 գիշեր', '8 дней / 7 ночей', '8 days / 7 nights'), 890, 'USD', $images['egypt'],
                $this->trList(['Գիզայի բուրգեր', 'Եգիպտական թանգարան', 'Կարմիր ծով', 'All-inclusive հյուրանոց'], ['Пирамиды Гизы', 'Египетский музей', 'Красное море', 'Отель all-inclusive'], ['Giza Pyramids', 'Egyptian Museum', 'Red Sea', 'All-inclusive resort']), 19
            ),
            $this->catalogTour(
                'maldives-island-escape', 'international', 'package',
                $this->tr('Մալդիվյան կղզի', 'Мальдивский остров', 'Maldives Island Escape'),
                $this->tr('Փիրուզագույն ջուր և ամբողջական հանգիստ', 'Бирюзовая вода и полный отдых', 'Turquoise water and complete escape'),
                $this->tr('Յոթ գիշեր ընտրված կղզում՝ սննդի տարբերակով, օդանավակայան-կղզի տրանսֆերով և անձնական ուղևորության աջակցությամբ։', 'Семь ночей на выбранном острове с питанием, трансфером аэропорт–остров и персональной поддержкой поездки.', 'Seven nights on a selected island with meals, airport-island transfers and personal trip support.'),
                $this->tr('Մալդիվներ', 'Мальдивы', 'Maldives'),
                $this->tr('8 օր / 7 գիշեր', '8 дней / 7 ночей', '8 days / 7 nights'), 1690, 'USD', $images['maldives'],
                $this->trList(['Ծովափնյա վիլլա', 'Կղզու տրանսֆեր', 'Սնորքլինգ', 'Անհատական աջակցություն'], ['Пляжная вилла', 'Трансфер на остров', 'Снорклинг', 'Личная поддержка'], ['Beach villa', 'Island transfer', 'Snorkeling', 'Personal support']), 20
            ),
            $this->catalogTour(
                'prague-vienna-budapest', 'international', 'group',
                $this->tr('Պրահա, Վիեննա և Բուդապեշտ', 'Прага, Вена и Будапешт', 'Prague, Vienna & Budapest'),
                $this->tr('Եվրոպական երեք մայրաքաղաք մեկ երթուղով', 'Три европейские столицы по одному маршруту', 'Three European capitals in one route'),
                $this->tr('Կազմակերպված խմբային շրջագայություն՝ երեք պատմական մայրաքաղաքներով, միջքաղաքային տրանսպորտով և ուղեկցող ղեկավարով։', 'Организованный групповой тур по трём историческим столицам с междугородним транспортом и сопровождающим.', 'An escorted group journey through three historic capitals with intercity transport and a tour leader.'),
                $this->tr('Չեխիա, Ավստրիա, Հունգարիա', 'Чехия, Австрия, Венгрия', 'Czechia, Austria & Hungary'),
                $this->tr('8 օր / 7 գիշեր', '8 дней / 7 ночей', '8 days / 7 nights'), 1190, 'EUR', $images['prague'],
                $this->trList(['Հին Պրահա', 'Շյոնբրուն', 'Դանուբ', 'Բուդայի ամրոց'], ['Старая Прага', 'Шёнбрунн', 'Дунай', 'Будайская крепость'], ['Old Prague', 'Schönbrunn', 'Danube', 'Buda Castle']), 21
            ),
            $this->catalogTour(
                'barcelona-costa-brava', 'international', 'package',
                $this->tr('Բարսելոնա և Կոստա Բրավա', 'Барселона и Коста-Брава', 'Barcelona & Costa Brava'),
                $this->tr('Գաուդիի քաղաքը և Միջերկրական ծով', 'Город Гауди и Средиземное море', 'Gaudí’s city and the Mediterranean'),
                $this->tr('Վեցօրյա փաթեթ Բարսելոնայում՝ քաղաքային շրջայցով և մեկ ամբողջ օր Կոստա Բրավայի ծովափնյա քաղաքներում։', 'Шестидневный пакет в Барселоне с обзорной экскурсией и целым днём в прибрежных городках Коста-Бравы.', 'A six-day Barcelona package with a city tour and a full day in Costa Brava’s seaside towns.'),
                $this->tr('Բարսելոնա, Իսպանիա', 'Барселона, Испания', 'Barcelona, Spain'),
                $this->tr('6 օր / 5 գիշեր', '6 дней / 5 ночей', '6 days / 5 nights'), 1090, 'EUR', $images['barcelona'],
                $this->trList(['Սագրադա Ֆամիլիա', 'Գոթական թաղամաս', 'Պարկ Գյուել', 'Կոստա Բրավա'], ['Саграда Фамилия', 'Готический квартал', 'Парк Гуэль', 'Коста-Брава'], ['Sagrada Família', 'Gothic Quarter', 'Park Güell', 'Costa Brava']), 22
            ),
            $this->catalogTour(
                'cyprus-sun-sea', 'international', 'package',
                $this->tr('Կիպրոս․ արև և ծով', 'Кипр: солнце и море', 'Cyprus Sun & Sea'),
                $this->tr('Ընտանեկան ծովափնյա հանգիստ', 'Семейный отдых у моря', 'A relaxed family beach holiday'),
                $this->tr('Յոթօրյա ծովափնյա փաթեթ՝ ընտրված հյուրանոցով, նախաճաշով, խմբային տրանսֆերով և ազատ օրերի առաջարկներով։', 'Семидневный пляжный пакет с выбранным отелем, завтраком, групповым трансфером и идеями для свободных дней.', 'A seven-day beach package with a selected hotel, breakfast, shared transfers and ideas for free days.'),
                $this->tr('Լառնակա, Կիպրոս', 'Ларнака, Кипр', 'Larnaca, Cyprus'),
                $this->tr('7 օր / 6 գիշեր', '7 дней / 6 ночей', '7 days / 6 nights'), 760, 'EUR', $images['cyprus'],
                $this->trList(['Ծովափնյա հյուրանոց', 'Լառնակայի կենտրոն', 'Այա Նապա', 'Ազատ օրեր'], ['Отель у моря', 'Центр Ларнаки', 'Айя-Напа', 'Свободные дни'], ['Beach hotel', 'Larnaca center', 'Ayia Napa', 'Free days']), 23
            ),
            $this->catalogTour(
                'montenegro-adriatic', 'international', 'package',
                $this->tr('Չեռնոգորիայի Ադրիատիկ', 'Адриатика Черногории', 'Montenegro Adriatic'),
                $this->tr('Ծով, լեռներ և հին քարե քաղաքներ', 'Море, горы и старинные каменные города', 'Sea, mountains and old stone towns'),
                $this->tr('Յոթօրյա ամառային փաթեթ Բուդվայում՝ Կոտորի ծոցի ընտրովի շրջայցով և հանգստի համար ազատ ժամանակով։', 'Семидневный летний пакет в Будве с дополнительной экскурсией по Которскому заливу и свободным временем для отдыха.', 'A seven-day summer package in Budva with an optional Bay of Kotor tour and generous time to relax.'),
                $this->tr('Բուդվա և Կոտոր, Չեռնոգորիա', 'Будва и Котор, Черногория', 'Budva & Kotor, Montenegro'),
                $this->tr('7 օր / 6 գիշեր', '7 дней / 6 ночей', '7 days / 6 nights'), 840, 'EUR', $images['montenegro'],
                $this->trList(['Բուդվայի հին քաղաք', 'Կոտորի ծոց', 'Պերաստ', 'Ադրիատիկի ծովափ'], ['Старый город Будвы', 'Которский залив', 'Пераст', 'Пляжи Адриатики'], ['Budva Old Town', 'Bay of Kotor', 'Perast', 'Adriatic beaches']), 24
            ),
        ];

        foreach ($tours as $tour) {
            Tour::updateOrCreate(['slug' => $tour['slug']], $tour);
        }

        $destinations = [
            ['slug' => 'yerevan', 'title' => $this->tr('Երևան', 'Ереван', 'Yerevan'), 'region' => $this->tr('Երևան', 'Ереван', 'Yerevan'), 'description' => $this->tr('Վարդագույն քաղաք՝ կենդանի մշակույթով, սրճարաններով և պատմությամբ։', 'Розовый город с живой культурой, кафе и историей.', 'The pink city of living culture, cafés and history.'), 'image' => $images['yerevan'], 'featured' => true, 'active' => true, 'sort_order' => 1],
            ['slug' => 'lake-sevan', 'title' => $this->tr('Սևանա լիճ', 'Озеро Севан', 'Lake Sevan'), 'region' => $this->tr('Գեղարքունիք', 'Гегаркуник', 'Gegharkunik'), 'description' => $this->tr('Հայաստանի կապույտ մարգարիտը՝ լեռնային հորիզոնով։', 'Голубая жемчужина Армении с горным горизонтом.', 'Armenia’s blue pearl framed by mountain horizons.'), 'image' => $images['sevan'], 'featured' => true, 'active' => true, 'sort_order' => 2],
            ['slug' => 'dilijan', 'title' => $this->tr('Դիլիջան', 'Дилижан', 'Dilijan'), 'region' => $this->tr('Տավուշ', 'Тавуш', 'Tavush'), 'description' => $this->tr('Անտառներ, արհեստանոցներ և հանգիստ լեռնային տրամադրություն։', 'Леса, мастерские и спокойная горная атмосфера.', 'Forests, craft studios and an easy mountain rhythm.'), 'image' => $images['dilijan'], 'featured' => true, 'active' => true, 'sort_order' => 3],
            ['slug' => 'tatev', 'title' => $this->tr('Տաթև', 'Татев', 'Tatev'), 'region' => $this->tr('Սյունիք', 'Сюник', 'Syunik'), 'description' => $this->tr('Վանք ամպերի եզրին և Սյունիքի վիթխարի բնությունը։', 'Монастырь на краю облаков и величие природы Сюника.', 'A monastery at the edge of the clouds and epic Syunik scenery.'), 'image' => $images['tatev'], 'featured' => true, 'active' => true, 'sort_order' => 4],
            ['slug' => 'areni', 'title' => $this->tr('Արենի', 'Арени', 'Areni'), 'region' => $this->tr('Վայոց Ձոր', 'Вайоц Дзор', 'Vayots Dzor'), 'description' => $this->tr('Գինու ճանապարհ, կարմիր ժայռեր և հնագույն քարանձավներ։', 'Винная дорога, красные скалы и древние пещеры.', 'Wine roads, red cliffs and ancient caves.'), 'image' => $images['garni'], 'featured' => true, 'active' => true, 'sort_order' => 5],
            ['slug' => 'garni', 'title' => $this->tr('Գառնի', 'Гарни', 'Garni'), 'region' => $this->tr('Կոտայք', 'Котайк', 'Kotayk'), 'description' => $this->tr('Հեթանոսական տաճար և քարե կիրճ՝ Երևանից մեկ քայլ հեռու։', 'Языческий храм и каменное ущелье недалеко от Еревана.', 'A pagan temple and stone gorge just outside Yerevan.'), 'image' => $images['garni'], 'featured' => true, 'active' => true, 'sort_order' => 6],
        ];

        foreach ($destinations as $destination) {
            Destination::updateOrCreate(['slug' => $destination['slug']], $destination);
        }

        $services = [
            ['slug' => 'handpicked-hotels', 'type' => 'accommodation', 'title' => $this->tr('Ընտրված հյուրանոցներ', 'Отобранные отели', 'Handpicked stays'), 'description' => $this->tr('Քաղաքային boutique հյուրանոցներից մինչև լեռնային eco-lodge-ներ՝ ստուգված մեր թիմի կողմից։', 'От городских бутик-отелей до горных эко-лоджей, проверенных нашей командой.', 'From city boutiques to mountain eco-lodges, personally checked by our team.'), 'icon' => 'BedDouble', 'image' => $images['hotel'], 'price_from' => 24000, 'currency' => 'AMD', 'features' => $this->trList(['Արագ ամրագրում', 'Լավագույն արժեք', '24/7 աջակցություն'], ['Быстрое бронирование', 'Лучшая цена', 'Поддержка 24/7'], ['Fast booking', 'Best value', '24/7 support']), 'featured' => true, 'active' => true, 'sort_order' => 1],
            ['slug' => 'airport-transfers', 'type' => 'transport', 'title' => $this->tr('Տրանսֆեր և մեքենա վարորդով', 'Трансфер и авто с водителем', 'Transfers & chauffeur'), 'description' => $this->tr('Հարմարավետ մեքենաներ, օդանավակայանի դիմավորում և վստահելի վարորդներ ամբողջ Հայաստանում։', 'Комфортные автомобили, встреча в аэропорту и надёжные водители по всей Армении.', 'Comfortable vehicles, airport welcome and trusted drivers across Armenia.'), 'icon' => 'CarFront', 'image' => $images['car'], 'price_from' => 8000, 'currency' => 'AMD', 'features' => $this->trList(['Թռիչքի մոնիթորինգ', 'Անվճար սպասում', 'Մանկական նստատեղ'], ['Мониторинг рейса', 'Бесплатное ожидание', 'Детское кресло'], ['Flight tracking', 'Free waiting', 'Child seat']), 'featured' => true, 'active' => true, 'sort_order' => 2],
            ['slug' => 'business-events', 'type' => 'events', 'title' => $this->tr('MICE և միջոցառումներ', 'MICE и мероприятия', 'MICE & events'), 'description' => $this->tr('Կոնֆերանսներ, թիմային ուղևորություններ և հատուկ միջոցառումներ՝ գաղափարից մինչև իրականացում։', 'Конференции, командные поездки и специальные мероприятия — от идеи до реализации.', 'Conferences, team journeys and special events managed from concept to delivery.'), 'icon' => 'Presentation', 'image' => $images['event'], 'price_from' => null, 'currency' => 'AMD', 'features' => $this->trList(['Վայրի ընտրություն', 'Տեխնիկական աջակցություն', 'Լոգիստիկա'], ['Подбор площадки', 'Техническая поддержка', 'Логистика'], ['Venue sourcing', 'Technical support', 'Logistics']), 'featured' => true, 'active' => true, 'sort_order' => 3],
            ['slug' => 'custom-journey', 'type' => 'custom', 'title' => $this->tr('Անհատական ուղևորություն', 'Индивидуальное путешествие', 'Tailor-made journey'), 'description' => $this->tr('Պատմեք՝ ինչ եք սիրում, և մենք կստեղծենք ձեր ռիթմով հայկական ճանապարհորդություն։', 'Расскажите, что вы любите, и мы создадим армянское путешествие в вашем ритме.', 'Tell us what you love and we’ll create an Armenian journey at your pace.'), 'icon' => 'Sparkles', 'image' => $images['traveler'], 'price_from' => null, 'currency' => 'AMD', 'features' => $this->trList(['Անձնական travel designer', 'Ճկուն ծրագիր', 'Տեղական գաղտնիքներ'], ['Личный travel-дизайнер', 'Гибкая программа', 'Локальные секреты'], ['Personal travel designer', 'Flexible itinerary', 'Local secrets']), 'featured' => true, 'active' => true, 'sort_order' => 4],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(['slug' => $service['slug']], $service);
        }

        $posts = [
            ['slug' => 'first-time-armenia-guide', 'category' => 'guide', 'title' => $this->tr('Առաջին այցը Հայաստան. 10 խորհուրդ', 'Первая поездка в Армению: 10 советов', 'First Time in Armenia: 10 Essential Tips'), 'excerpt' => $this->tr('Երբ գալ, ինչ վերցնել և ինչպես զգալ Հայաստանը՝ ոչ թե պարզապես տեսնել։', 'Когда приезжать, что взять и как почувствовать Армению, а не просто увидеть.', 'When to come, what to pack and how to feel Armenia rather than just see it.'), 'content' => $this->tr('Հայաստանը կոմպակտ է, բայց ամեն շրջադարձից հետո փոխվում է բնությունը, խոհանոցն ու տրամադրությունը։ Այս ուղեցույցը կօգնի ճիշտ սկսել ձեր առաջին ճանապարհորդությունը։', 'Армения компактна, но за каждым поворотом меняются природа, кухня и настроение. Этот гид поможет правильно начать первое путешествие.', 'Armenia is compact, yet every turn changes the landscape, food and mood. This guide helps you start your first journey well.'), 'image' => $images['traveler'], 'reading_time' => 7, 'published_at' => now()->subDays(5), 'featured' => true, 'active' => true],
            ['slug' => 'best-season-armenia', 'category' => 'inspiration', 'title' => $this->tr('Ե՞րբ այցելել Հայաստան', 'Когда лучше ехать в Армению', 'When Is the Best Time to Visit Armenia?'), 'excerpt' => $this->tr('Ծաղկող գարուն, արևոտ ամառ, գինու աշուն կամ ձյունոտ ձմեռ։', 'Цветущая весна, солнечное лето, винная осень или снежная зима.', 'Blooming spring, sunny summer, wine-rich autumn or snowy winter.'), 'content' => $this->tr('Յուրաքանչյուր եղանակ ունի իր ճանապարհորդությունը։ Ապրիլից հունիսը կանաչ է, սեպտեմբերն ու հոկտեմբերը՝ համեղ, իսկ ձմեռը՝ խաղաղ և սպիտակ։', 'У каждого сезона своё путешествие. С апреля по июнь зелено, сентябрь и октябрь особенно вкусны, а зима спокойная и белая.', 'Every season offers a different journey. April to June is green, September and October are delicious, and winter is quiet and white.'), 'image' => $images['dilijan'], 'reading_time' => 5, 'published_at' => now()->subDays(12), 'featured' => true, 'active' => true],
            ['slug' => 'armenian-flavors', 'category' => 'food', 'title' => $this->tr('Հայկական համերի փոքր ուղեցույց', 'Небольшой гид по армянским вкусам', 'A Short Guide to Armenian Flavors'), 'excerpt' => $this->tr('Լավաշից և ժենգյալով հացից մինչև Արենիի գինի։', 'От лаваша и женгялов хаца до вина Арени.', 'From lavash and jingalov hats to Areni wine.'), 'content' => $this->tr('Հայկական սեղանը ճանապարհորդության շարունակությունն է։ Յուրաքանչյուր մարզ ունի իր բաղադրատոմսը, պատմությունը և մարդկանց հետ ծանոթանալու ձևը։', 'Армянский стол — продолжение путешествия. У каждого региона свой рецепт, история и способ познакомиться с людьми.', 'The Armenian table is part of the journey. Every region has its recipe, story and way of meeting people.'), 'image' => $images['yerevan'], 'reading_time' => 6, 'published_at' => now()->subDays(21), 'featured' => false, 'active' => true],
        ];

        foreach ($posts as $post) {
            Post::updateOrCreate(['slug' => $post['slug']], $post);
        }

        $pages = [
            ['slug' => 'about', 'title' => $this->tr('GoVista-ի մասին', 'О GoVista', 'About GoVista'), 'content' => $this->tr('Մենք Հայաստանում ապրող ճանապարհորդների թիմ ենք։ Ստեղծում ենք անկեղծ, հարմարավետ և գեղեցիկ ուղևորություններ՝ տեղական գիտելիքով ու միջազգային սպասարկմամբ։', 'Мы команда путешественников, живущих в Армении. Создаём искренние, комфортные и красивые поездки с местным знанием и международным сервисом.', 'We are a team of travelers based in Armenia, creating honest, comfortable and beautiful journeys with local knowledge and international service.'), 'seo_title' => $this->tr('GoVista-ի մասին', 'О компании GoVista', 'About GoVista Armenia'), 'seo_description' => $this->tr('Բացահայտեք GoVista-ի թիմը և մեր մոտեցումը։', 'Познакомьтесь с командой и подходом GoVista.', 'Meet the GoVista team and our approach.'), 'image' => $images['traveler'], 'active' => true],
            ['slug' => 'privacy', 'title' => $this->tr('Գաղտնիության քաղաքականություն', 'Политика конфиденциальности', 'Privacy Policy'), 'content' => $this->tr('GoVista-ն օգտագործում է ձեր տվյալները միայն ամրագրման և սպասարկման նպատակով։', 'GoVista использует ваши данные только для бронирования и обслуживания.', 'GoVista uses your data only to arrange and service your booking.'), 'active' => true],
            ['slug' => 'terms', 'title' => $this->tr('Պայմաններ', 'Условия', 'Terms & Conditions'), 'content' => $this->tr('Ամրագրումների, վճարումների և չեղարկումների ամբողջական պայմանները հաստատվում են յուրաքանչյուր առաջարկի հետ։', 'Полные условия бронирования, оплаты и отмены подтверждаются с каждым предложением.', 'Full booking, payment and cancellation terms are confirmed with each proposal.'), 'active' => true],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(['slug' => $page['slug']], $page);
        }

        $testimonials = [
            ['name' => 'Anna Müller', 'country' => $this->tr('Գերմանիա', 'Германия', 'Germany'), 'message' => $this->tr('GoVista-ն մեզ ցույց տվեց այն Հայաստանը, որը ինքնուրույն երբեք չէինք գտնի։ Ամեն ինչ ջերմ էր, ճկուն և անթերի կազմակերպված։', 'GoVista показала нам Армению, которую мы никогда не нашли бы сами. Всё было тепло, гибко и безупречно организовано.', 'GoVista showed us an Armenia we would never have found alone. Everything felt warm, flexible and flawlessly organized.'), 'rating' => 5, 'source' => 'Google', 'active' => true, 'sort_order' => 1],
            ['name' => 'Алексей Смирнов', 'country' => $this->tr('Ռուսաստան', 'Россия', 'Russia'), 'message' => $this->tr('Վարորդը ճշտապահ էր, գիդը՝ փայլուն, իսկ ընտրված ռեստորանը դարձավ ուղևորության ամենագեղեցիկ անակնկալը։', 'Водитель был пунктуален, гид — блестящий, а выбранный ресторан стал лучшим сюрпризом поездки.', 'The driver was punctual, the guide brilliant, and the chosen restaurant became the loveliest surprise of our trip.'), 'rating' => 5, 'source' => 'Yandex', 'active' => true, 'sort_order' => 2],
            ['name' => 'Emily Carter', 'country' => $this->tr('Մեծ Բրիտանիա', 'Великобритания', 'United Kingdom'), 'message' => $this->tr('Մեր յոթօրյա ծրագիրը կատարյալ հավասարակշռված էր՝ պատմություն, բնություն, սնունդ և ազատ ժամանակ։', 'Наша семидневная программа была идеально сбалансирована: история, природа, еда и свободное время.', 'Our seven-day itinerary was perfectly balanced: history, nature, food and time to breathe.'), 'rating' => 5, 'source' => 'Tripadvisor', 'active' => true, 'sort_order' => 3],
        ];

        foreach ($testimonials as $testimonial) {
            Testimonial::updateOrCreate(['name' => $testimonial['name']], $testimonial);
        }

        $faqs = [
            [$this->tr('Հայաստան այցելելու համար վիզա պե՞տք է։', 'Нужна ли виза для поездки в Армению?', 'Do I need a visa to visit Armenia?'), $this->tr('Շատ երկրների քաղաքացիներ կարող են Հայաստան մուտք գործել առանց վիզայի։ Ամրագրելուց առաջ կստուգենք ձեր քաղաքացիության գործող պայմանները։', 'Граждане многих стран могут въезжать в Армению без визы. Перед бронированием мы проверим актуальные условия для вашего гражданства.', 'Citizens of many countries can enter Armenia visa-free. We will verify the current rules for your nationality before booking.')],
            [$this->tr('Տուրերը ո՞ր լեզուներով են անցկացվում։', 'На каких языках проводятся туры?', 'Which languages are tours available in?'), $this->tr('Հայերեն, ռուսերեն և անգլերեն՝ ըստ ընտրված ծրագրի։ Անհատական տուրերի համար կարող ենք առաջարկել նաև այլ լեզուներ։', 'На армянском, русском и английском — в зависимости от программы. Для частных туров доступны и другие языки.', 'Armenian, Russian and English depending on the program. Other languages can be arranged for private tours.')],
            [$this->tr('Կարո՞ղ եմ փոխել պատրաստի երթուղին։', 'Можно ли изменить готовый маршрут?', 'Can I customize an existing itinerary?'), $this->tr('Այո։ Անհատական և փաթեթային ծրագրերը հարմարեցնում ենք ձեր հետաքրքրություններին, ռիթմին և բյուջեին։', 'Да. Индивидуальные и пакетные программы адаптируем под ваши интересы, темп и бюджет.', 'Yes. We tailor private and package itineraries to your interests, pace and budget.')],
            [$this->tr('Ինչպե՞ս է կատարվում վճարումը։', 'Как производится оплата?', 'How do payments work?'), $this->tr('Հաստատումից հետո կստանաք անվտանգ վճարման հղում կամ բանկային տվյալներ։ Որոշ ծառայությունների համար ընդունվում է նաև տեղում վճարում։', 'После подтверждения вы получите безопасную ссылку на оплату или банковские реквизиты. Для некоторых услуг возможна оплата на месте.', 'After confirmation you receive a secure payment link or bank details. Some services can also be paid locally.')],
            [$this->tr('Երեխաների հետ տուրերը հարմա՞ր են։', 'Подходят ли туры для детей?', 'Are the tours family-friendly?'), $this->tr('Այո։ Կառաջարկենք մանկական նստատեղ, ավելի կարճ կանգառներ և ընտանիքի տարիքին համապատասխան ծրագիր։', 'Да. Предоставим детское кресло, более короткие остановки и программу по возрасту семьи.', 'Yes. We can provide child seats, shorter stops and an itinerary suited to your family’s ages.')],
        ];

        foreach ($faqs as $index => [$question, $answer]) {
            Faq::updateOrCreate([
                'category' => 'general',
                'sort_order' => $index + 1,
            ], [
                'question' => $question,
                'answer' => $answer,
                'category' => 'general',
                'active' => true,
                'sort_order' => $index + 1,
            ]);
        }

        $settings = [
            ['group' => 'general', 'key' => 'site_name', 'value' => 'GoVista', 'type' => 'text'],
            ['group' => 'general', 'key' => 'phone', 'value' => '+374 44 60 60 60', 'type' => 'text'],
            ['group' => 'general', 'key' => 'email', 'value' => 'hello@govista.am', 'type' => 'text'],
            ['group' => 'general', 'key' => 'address', 'value' => $this->tr('Աբովյան 12, Երևան', 'Абовяна 12, Ереван', '12 Abovyan St, Yerevan'), 'type' => 'translated'],
            ['group' => 'hero', 'key' => 'hero_eyebrow', 'value' => $this->tr('Բացահայտեք Հայաստանը ներսից', 'Откройте Армению изнутри', 'Discover Armenia from within'), 'type' => 'translated'],
            ['group' => 'hero', 'key' => 'hero_title', 'value' => $this->tr('Ձեր պատմությունը սկսվում է Հայաստանից', 'Ваша история начинается в Армении', 'Your story begins in Armenia'), 'type' => 'translated'],
            ['group' => 'hero', 'key' => 'hero_subtitle', 'value' => $this->tr('Ընտրված տուրեր, տեղական մարդիկ և ճանապարհորդություններ, որոնք երկար կմնան ձեզ հետ։', 'Авторские туры, местные люди и путешествия, которые останутся с вами надолго.', 'Handpicked tours, local people and journeys that stay with you long after you leave.'), 'type' => 'translated'],
            ['group' => 'hero', 'key' => 'hero_image', 'value' => $images['ararat'], 'type' => 'url'],
            ['group' => 'stats', 'key' => 'stats', 'value' => [
                ['value' => '8+', 'hy' => 'տարվա փորձ', 'ru' => 'лет опыта', 'en' => 'years of experience'],
                ['value' => '12K+', 'hy' => 'երջանիկ հյուր', 'ru' => 'счастливых гостей', 'en' => 'happy guests'],
                ['value' => '4.9', 'hy' => 'միջին գնահատական', 'ru' => 'средний рейтинг', 'en' => 'average rating'],
                ['value' => '24/7', 'hy' => 'աջակցություն', 'ru' => 'поддержка', 'en' => 'support'],
            ], 'type' => 'json'],
            ['group' => 'social', 'key' => 'instagram', 'value' => 'https://instagram.com/govista.am', 'type' => 'url'],
            ['group' => 'social', 'key' => 'facebook', 'value' => 'https://facebook.com/govista.am', 'type' => 'url'],
            ['group' => 'social', 'key' => 'whatsapp', 'value' => 'https://wa.me/37444606060', 'type' => 'url'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }

    private function tr(string $hy, string $ru, string $en): array
    {
        return compact('hy', 'ru', 'en');
    }

    private function catalogTour(
        string $slug,
        string $travelScope,
        string $type,
        array $title,
        array $subtitle,
        array $description,
        array $location,
        array $duration,
        int $price,
        string $currency,
        string $image,
        array $highlights,
        int $sortOrder,
        bool $featured = false,
    ): array {
        $isDomestic = $travelScope === 'domestic';

        return [
            'slug' => $slug,
            'travel_scope' => $travelScope,
            'type' => $type,
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'location' => $location,
            'duration' => $duration,
            'price' => $price,
            'currency' => $currency,
            'rating' => min(4.98, 4.78 + (($sortOrder % 16) / 100)),
            'review_count' => 28 + ($sortOrder * 7),
            'image' => $image,
            'gallery' => [$image],
            'highlights' => $highlights,
            'itinerary' => $highlights,
            'included' => $isDomestic
                ? $this->trList(['Տրանսպորտ', 'Գիդ', 'Ջուր'], ['Транспорт', 'Гид', 'Вода'], ['Transport', 'Guide', 'Water'])
                : $this->trList(['Հյուրանոց', 'Նախաճաշ', 'Տրանսֆեր', 'GoVista աջակցություն'], ['Отель', 'Завтрак', 'Трансфер', 'Поддержка GoVista'], ['Hotel', 'Breakfast', 'Transfers', 'GoVista support']),
            'excluded' => $isDomestic
                ? $this->trList(['Ճաշ', 'Անձնական ծախսեր'], ['Обед', 'Личные расходы'], ['Lunch', 'Personal expenses'])
                : $this->trList(['Ավիատոմս՝ եթե նշված չէ', 'Ապահովագրություն', 'Անձնական ծախսեր'], ['Авиабилет, если не указан', 'Страховка', 'Личные расходы'], ['Flights unless stated', 'Insurance', 'Personal expenses']),
            'featured' => $featured,
            'active' => true,
            'sort_order' => $sortOrder,
        ];
    }

    private function trList(array $hy, array $ru, array $en): array
    {
        return compact('hy', 'ru', 'en');
    }
}
