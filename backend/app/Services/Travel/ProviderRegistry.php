<?php

namespace App\Services\Travel;

use App\Models\TravelProvider;

class ProviderRegistry
{
    public const DEFINITIONS = [
        'viator' => ['name' => 'Viator', 'services' => ['activities'], 'fields' => ['api_key'], 'docs' => 'https://docs.viator.com/partner-api/', 'mode' => 'redirect'],
        'booking' => ['name' => 'Booking.com Demand', 'services' => ['hotels'], 'fields' => ['api_key', 'affiliate_id'], 'docs' => 'https://developers.booking.com/demand/docs', 'mode' => 'redirect'],
        'duffel' => ['name' => 'Duffel', 'services' => ['flights'], 'fields' => ['api_key'], 'docs' => 'https://duffel.com/docs/api/v2/offer-requests', 'mode' => 'request'],
        'hotelbeds' => ['name' => 'Hotelbeds Transfers', 'services' => ['transfers'], 'fields' => ['api_key', 'secret'], 'docs' => 'https://developer.hotelbeds.com/documentation/transfers/booking-api/search-availability/availability-simple/', 'mode' => 'request'],
        'geoapify' => ['name' => 'Geoapify', 'services' => ['places'], 'fields' => ['api_key'], 'docs' => 'https://apidocs.geoapify.com/docs/places/', 'mode' => 'information'],
        'maratuk' => ['name' => 'Մարաթուկ · Maratuk', 'services' => ['packages'], 'fields' => [], 'docs' => 'https://backend.maratuktours.com/swagger/index.html', 'portal' => 'https://maratuktours.com/', 'mode' => 'manual', 'description' => 'Փաթեթներ և գործակալային համագործակցություն։ API-ի տվյալները կարող եք պահել ստորև․ ադապտերը կիրականացվի գործընկերային փաստաթղթերը ստանալուց հետո։', 'next_step' => 'Ստանալ աշխատող OpenAPI/Swagger փաստաթուղթ, գործընկերային թույլտվություն, փորձնական հաշիվ և API սերվերի հասցե։'],
        'world_voyage' => ['name' => 'World Voyage', 'services' => ['packages'], 'fields' => [], 'docs' => 'https://world-voyage.com/', 'portal' => 'https://booking.world-voyage.com/', 'mode' => 'manual', 'description' => 'Գործակալային փաթեթներ և B2B հարթակ։ API կարգավորումները պատրաստ են լրացման․ արտաքին կապի համար անհրաժեշտ է մատակարարի տեխնիկական նկարագրությունը։', 'next_step' => 'Ճշտել API/XML ինտեգրման հասանելիությունը և ստանալ փաստաթղթերը, սերվերի հասցեն ու փորձնական մուտքը։'],
        'travelone' => ['name' => 'TravelOne Armenia', 'services' => ['packages'], 'fields' => [], 'docs' => 'https://docs.santsg.com/tourvisio/', 'portal' => 'https://b2b.travelone.am/', 'mode' => 'manual', 'description' => 'TourVisio ադապտեր՝ ադմինում մուտքի ստուգման, տեղեկատուների և փաթեթների որոնման համար։ Հասանելիությունը և API հասցեն տրամադրում է TravelOne-ը։', 'next_step' => 'Ստանալ TourVisio API base URL, Agency/User/Password, փորձնական միջավայր և փաթեթների որոնման թույլտվություն։'],
        'anriva' => ['name' => 'ANRIVA', 'services' => ['packages'], 'fields' => [], 'docs' => 'https://booking.anrivatour.com/index.php?m=pages&p=102', 'portal' => 'https://booking.anrivatour.com/', 'mode' => 'manual', 'description' => 'Գործակալային փաթեթներ և API կարգավորումների նախապատրաստում։ API-ի հանրային փաստաթուղթ չի հաստատվել․ առաջարկները կարող եք կառավարել ձեռքով կամ CSV ներմուծմամբ։', 'next_step' => 'Ճշտել EVN–SSH / EVN–HRG առաջարկները, պայմանագիրը, միջնորդավճարը և առաջարկների ֆայլի կամ API-ի հասանելիությունը։'],
        'tez_tour' => ['name' => 'TEZ TOUR', 'services' => ['packages'], 'fields' => [], 'docs' => 'https://www.tez-tour.com/article.html?id=7015568', 'portal' => 'https://www.tez-tour.com/en/rnd/forAgency.html', 'mode' => 'manual', 'description' => 'Օպերատորն ունի XML/JSON որոնման և ամրագրման համակարգ։ Այստեղ միացված է մենեջերի կողմից առաջարկների կառավարումը․ արտաքին API կապը դեռ չի իրականացվել։', 'next_step' => 'Ստանալ Հայաստանի գործակալության հաստատում, տարածաշրջանի API հասցեներ, IP թույլտվություն և փորձնական փաստաթղթեր։'],
        'tourvisor' => ['name' => 'Tourvisor', 'services' => ['packages'], 'fields' => ['api_key'], 'docs' => 'https://api.tourvisor.ru/search/docs', 'portal' => 'https://tourvisor.ru/', 'mode' => 'package_search', 'description' => 'JSON API-ով որոնում և արդյունքների թարմացում։ Արտաքին ամրագրում չի կատարվում։ Իրական որոնման համար անհրաժեշտ են ստուգված մեկնման և հանգստավայրերի կոդեր։', 'next_step' => 'Ստանալ JWT բանալի, հաստատել հայկական գործակալության հասանելիությունն ու ծախսերը, տեղեկատուներից ընտրել EVN-ի և ուղղությունների կոդերը։'],
        'sletat' => ['name' => 'Sletat', 'services' => ['packages'], 'fields' => [], 'docs' => 'https://sletat.ru/agency/xml/', 'portal' => 'https://sletat.ru/agency/', 'mode' => 'manual', 'description' => 'Բազմաթիվ օպերատորների փաթեթների հարթակ։ Գործող տարբերակում առաջարկները մուտքագրվում են ձեռքով կամ ֆայլից․ Sletat-ի API կապը դեռ չի իրականացվել։', 'next_step' => 'Ճշտել Հայաստանի պայմանները, EVN առաջարկները, ընթացիկ JSON API-ի փաստաթուղթը և ամրագրման առանձին ծառայությունը։'],
        'tbo' => ['name' => 'TBO Packages', 'services' => ['packages'], 'fields' => [], 'docs' => 'https://www.tbo.com/tbo-packages', 'portal' => 'https://www.tbo.com/', 'mode' => 'manual', 'description' => 'Գործակալային փաթեթների հարթակ։ Հյուրանոցների TBO API-ն ինքնաբերաբար ամբողջական փաթեթների API չէ։ Այստեղ առաջարկները կառավարվում են ձեռքով կամ CSV-ով։', 'next_step' => 'Հաստատել Հայաստանի հաշիվը, փաթեթների API հասանելիությունը, թռիչքի ներառումները և հաշվարկի պայմանները։'],
    ];

    public static function definition(string $code): array
    {
        abort_unless(isset(self::DEFINITIONS[$code]), 404);

        return self::DEFINITIONS[$code];
    }

    public static function ready(TravelProvider $provider): bool
    {
        if (self::definition($provider->code)['mode'] === 'manual') {
            return false;
        }
        foreach (self::definition($provider->code)['fields'] as $field) {
            if (empty($provider->credentials[$field])) {
                return false;
            }
        }

        return true;
    }

    public static function safe(TravelProvider $provider): array
    {
        return [...self::definition($provider->code), ...$provider->toArray(), 'configured' => self::ready($provider),
            'credential_fields_set' => array_keys(array_filter($provider->credentials ?? [])), 'booking_enabled' => false,
            'package_provider' => in_array($provider->code, PackageCatalog::PROVIDERS),
            'partnership_ready' => PackageCatalog::approved($provider),
            'integration' => in_array($provider->code, ProviderIntegration::CODES) ? ProviderIntegration::safe($provider) : null];
    }
}
