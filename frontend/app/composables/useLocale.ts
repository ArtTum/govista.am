const messages = {
  hy: {
    nav: { tours: 'Տուրեր', domestic: 'Ներքին տուրեր', international: 'Արտաքին տուրեր', stays: 'Կացարաններ', cars: 'Ավտոմեքենաներ', private: 'Անհատական', packages: 'Փաթեթներ', destinations: 'Ուղղություններ', services: 'Ծառայություններ', blog: 'Բլոգ', about: 'Մեր մասին', contact: 'Կապ' },
    common: { explore: 'Բացահայտել', viewAll: 'Տեսնել բոլորը', from: 'սկսած', book: 'Ամրագրել', rent: 'Վարձակալել', details: 'Մանրամասներ', days: 'օր', reviews: 'կարծիք', readMore: 'Կարդալ', perPerson: 'մեկ անձի համար', featured: 'Ընտրված', menu: 'Մենյու' },
    tourTypes: { group: 'Խմբային', private: 'Անհատական', package: 'Փաթեթային' },
    tourScopes: { domestic: 'Ներքին տուր', international: 'Արտաքին տուր' },
    home: { toursEyebrow: 'Ձեզ համար ընտրված', toursTitle: 'Ճանապարհորդություններ, որոնք դառնում են պատմություն', destinationsEyebrow: 'Որտե՞ղ գնալ', destinationsTitle: 'Հայաստանի ամենագեղեցիկ անկյունները', servicesEyebrow: 'Ամեն ինչ մեկ տեղում', servicesTitle: 'Մենք հոգում ենք ամբողջ ճանապարհը', storiesEyebrow: 'Մեր հյուրերի խոսքերով', storiesTitle: 'Հիշողություններ, որ վերադառնում են մեզ հետ', journalEyebrow: 'GoVista Journal', journalTitle: 'Հայաստանը հասկանալու համար', faqEyebrow: 'Օգտակար պատասխաններ', faqTitle: 'Հաճախ տրվող հարցեր' },
      forms: { name: 'Անուն', email: 'Էլ․ փոստ', phone: 'Հեռախոս', date: 'Ամսաթիվ', checkIn: 'Մուտքի ամսաթիվ', checkOut: 'Ելքի ամսաթիվ', guests: 'Հյուրեր', people: 'Ուղևորներ', message: 'Ձեր ցանկությունները', send: 'Ուղարկել հայտը', success: 'Շնորհակալություն։ Մենք շուտով կկապվենք ձեզ հետ։' },
  },
  ru: {
    nav: { tours: 'Туры', domestic: 'Туры по Армении', international: 'Зарубежные туры', stays: 'Жильё', cars: 'Автомобили', private: 'Индивидуальные', packages: 'Пакеты', destinations: 'Направления', services: 'Услуги', blog: 'Блог', about: 'О нас', contact: 'Контакты' },
    common: { explore: 'Открыть', viewAll: 'Смотреть все', from: 'от', book: 'Забронировать', rent: 'Арендовать', details: 'Подробнее', days: 'дней', reviews: 'отзывов', readMore: 'Читать', perPerson: 'за человека', featured: 'Избранное', menu: 'Меню' },
    tourTypes: { group: 'Групповой', private: 'Индивидуальный', package: 'Пакетный' },
    tourScopes: { domestic: 'По Армении', international: 'За рубеж' },
    home: { toursEyebrow: 'Выбрано для вас', toursTitle: 'Путешествия, которые становятся историями', destinationsEyebrow: 'Куда поехать', destinationsTitle: 'Самые красивые уголки Армении', servicesEyebrow: 'Всё в одном месте', servicesTitle: 'Мы заботимся обо всём путешествии', storiesEyebrow: 'Словами наших гостей', storiesTitle: 'Воспоминания, которые возвращаются с нами', journalEyebrow: 'GoVista Journal', journalTitle: 'Чтобы понять Армению', faqEyebrow: 'Полезные ответы', faqTitle: 'Частые вопросы' },
      forms: { name: 'Имя', email: 'Эл. почта', phone: 'Телефон', date: 'Дата', checkIn: 'Дата заезда', checkOut: 'Дата выезда', guests: 'Гости', people: 'Пассажиры', message: 'Ваши пожелания', send: 'Отправить заявку', success: 'Спасибо! Мы скоро свяжемся с вами.' },
  },
  en: {
    nav: { tours: 'Tours', domestic: 'Armenia tours', international: 'Outbound tours', stays: 'Stays', cars: 'Car rental', private: 'Private', packages: 'Packages', destinations: 'Destinations', services: 'Services', blog: 'Journal', about: 'About', contact: 'Contact' },
    common: { explore: 'Explore', viewAll: 'View all', from: 'from', book: 'Book now', rent: 'Rent now', details: 'Details', days: 'days', reviews: 'reviews', readMore: 'Read story', perPerson: 'per person', featured: 'Featured', menu: 'Menu' },
    tourTypes: { group: 'Group tour', private: 'Private tour', package: 'Travel package' },
    tourScopes: { domestic: 'Armenia tour', international: 'Outbound tour' },
    home: { toursEyebrow: 'Chosen for you', toursTitle: 'Journeys that become stories', destinationsEyebrow: 'Where to go', destinationsTitle: 'Armenia’s most beautiful corners', servicesEyebrow: 'Everything in one place', servicesTitle: 'We take care of the whole journey', storiesEyebrow: 'In our guests’ words', storiesTitle: 'Memories that travel home with you', journalEyebrow: 'GoVista Journal', journalTitle: 'Stories to understand Armenia', faqEyebrow: 'Helpful answers', faqTitle: 'Frequently asked questions' },
      forms: { name: 'Name', email: 'Email', phone: 'Phone', date: 'Date', checkIn: 'Check-in date', checkOut: 'Check-out date', guests: 'Guests', people: 'Passengers', message: 'Your wishes', send: 'Send request', success: 'Thank you! We’ll be in touch shortly.' },
  },
}

export function useLocale() {
  const route = useRoute()
  const router = useRouter()
  const locale = computed(() => {
    const value = String(route.params.locale || 'hy')
    return ['hy', 'ru', 'en'].includes(value) ? value : 'hy'
  })

  const t = (path: string) => {
    return path.split('.').reduce((value: any, key) => value?.[key], messages[locale.value as keyof typeof messages]) || path
  }

  const localePath = (path = '') => `/${locale.value}${path.startsWith('/') ? path : `/${path}`}`.replace(/\/$/, '')

  const switchLocale = async (next: string) => {
    const segments = route.fullPath.split('/')
    segments[1] = next
    await router.push(segments.join('/') || `/${next}`)
  }

  return { locale, t, localePath, switchLocale, locales: ['hy', 'ru', 'en'] }
}
