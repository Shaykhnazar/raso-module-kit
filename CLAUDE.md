# CLAUDE.md — raso/module-kit

Bu paket — Raso OS **modul-servislari** uchun umumiy yadro. Har modul (Chat,
Calendar, …) shuni o'rnatadi. Ya'ni bu yerdagi xato **hamma modulga** tarqaladi.

**Arxitektura hujjati (kod repo'sidan tashqarida):**
`~/Documents/Hamkorlik/Tizim arxitekturasi/Modul platformasi/`
**Reja:** `~/Documents/muvaffaqiyat-os/.claude/tasks/modul-platformasi.md`

---

## Nima uchun bu paket bor

6 ta modul repo'si bo'ladi. Agar JWT tekshiruvi, event iste'moli va deptrac
konfigi har birida alohida yozilsa — ular **farqlanib ketadi** va xavfsizlik
teshigi bittasida yopilib, boshqasida ochiq qoladi. Shuning uchun: **bitta manba**.

---

## Qat'iy qoidalar

1. **`src/Domain/` — sof PHP.** Laravel, Eloquent, Facade, `request()`,
   `config()` — hech biri. Sabab: Octane/RoadRunner jarayonni tirik saqlaydi;
   domen obyektida konteyner havolasi bo'lsa memory leak. `deptrac` buni
   majburlaydi (`Domain: ~` — hech narsaga bog'lanmaydi).
2. **`RasoUser` ga PII QO'SHILMAYDI.** Email, telefon, `telegram_chat_id`,
   hujjat — hech qachon. `RasoUserTest` refleksiya bilan tekshiradi va
   qo'shilsa yiqiladi. Bu shunchaki uslub emas: `Modul platformasi/00` §4.4
   qarori va O'zR shaxsiy ma'lumotlar qonuni talabi.
3. **Fail-closed.** Noma'lum qiymat hech qachon ruxsatni kengaytirmasin:
   noma'lum rol → `user`, noma'lum daraja → `L0`, noma'lum til → `uz`.
   Yangi kod yozganda shu naqsh saqlansin.
4. **`Scope` da wildcard yo'q.** `chat:read` `chat`, `chat:*` yoki
   `chat:read_all` ni **ochmaydi**. Kimdir "qulaylik uchun" prefiks mos
   kelishini qo'shmoqchi bo'lsa — bu ruxsatni jimgina kengaytiradigan
   klassik teshik, rad eting.
5. **TDD.** Domen qoidasi avval testda tug'iladi (red → green → refactor).
6. **Test faylida `function` E'LON QILMANG.** `pest --parallel` ikki test
   faylini bitta jarayonga yuklasa `Cannot redeclare` bilan yiqiladi va bu
   xato ketma-ket ishlatilganda **KO'RINMAYDI**. Umumiy yordamchi →
   `src/Testing/helpers.php` (`function_exists` qo'riqchisi bilan),
   composer `autoload.files` orqali yuklanadi.
7. **Auth doim FAIL-CLOSED.** JWKS olinmasa, `kid` topilmasa, `alg` tanish
   bo'lmasa — token **rad etiladi** (401), 500 emas. 500 auth muammosini
   infratuzilma xatosi kabi ko'rsatadi va monitoringda noto'g'ri joyga
   qaraladi.
8. **Faqat RS256.** `none` va HS256 aniq rad etiladi va `alg` **imzodan
   OLDIN** tekshiriladi. HS256 ga ruxsat berilsa, JWKS'dagi OCHIQ kalit
   maxfiy kalit sifatida ishlatilib, har kim token qalbakilashtira olardi
   (algorithm confusion).
9. **Rad etish sababi klientga aytilmaydi.** «Imzo noto'g'ri» bilan «kalit
   topilmadi» ni ajratish hujumchiga tizim haqida ma'lumot beradi. Sabab
   `TokenRejected::$reason` da — faqat log va test uchun.
10. **Iste'mol va belgilash — BITTA tranzaksiyada.** `EventConsumer` shuning
    uchun `TransactionRunner` oladi. Avval belgilab keyin qayta ishlasak va
    jarayon yiqilsa, xabar «ko'rilgan» bo'lib qoladi va **hech qachon qayta
    ishlanmaydi** — `identity.user_deleted` uchun bu ma'lumot o'chirilmay
    qolishi demakdir. Bu qoidani «optimizatsiya» uchun buzmang.
11. **`identity.user_deleted` — muzokara qilinmaydi.** Har modul
    `UserDeletedListener` ni kengaytiradi va `UserDeletionContract` testini
    yozadi. `raso:module:doctor` CI'da ishlaydi.
12. **Har o'zgarishdan keyin `composer check`.** Yashil bo'lmasa ish tugamagan.

---

## Buyruqlar

```bash
composer check    # pint --test → phpstan lvl8 → deptrac → pest --parallel
composer pint     # formatni tuzatadi
composer test
```

---

## Tuzilish

```
src/Domain/     PublicId · Locale · Scope · VerificationLevel
                RasoUser · DomainEvent · AggregateRoot
src/Auth/       TokenVerifier · CachedJwksProvider · HttpJwksFetcher
                RasoUserIdentity · Http/{Authenticate,Scope}Middleware
src/Events/     OutboxRelay · EventConsumer · UserDeletedListener
                EventNames · portlar (OutboxStore, ConsumedEvents, …)
src/Laravel/    ModuleKitServiceProvider · RedisStreamPublisher
                IlluminateTransactionRunner · Console/{Doctor,PurgeUser}
src/Testing/    AuthKit · EventKit · FakeJwtIssuer · UserDeletionContract
                InMemory* · RasoUserFactory · helpers.php
config/         module-kit.php (runtime) + modul shablonlari (.dist)
tests/Unit/     sof PHP unit testlar (Laravel ilovasi yuklanmaydi)
```

**Qatlam chegarasi (`deptrac.yaml`):** `Domain` hech narsaga bog'lanmaydi ·
`Auth → Domain` · `Events → Domain` · `Laravel → Auth, Events, Domain` ·
`Testing → Domain, Auth, Events`.

Teskari yo'nalish yo'q: `RasoUser` Laravel'ni bilmaydi, ko'prik
`RasoUserIdentity` esa `Auth` qatlamida turadi. `Events` `Auth` ga
TEGMAYDI — hodisada foydalanuvchi `sub` (uuid), obyekt emas.

**`config/` shablonlari** — modul repo'si nusxa oladi, paketdan `import`
qilmaydi (deptrac/phpstan konfiglari yo'l bo'yicha ishlaydi). Shablon
o'zgarsa, modullarga qo'lda tarqatiladi — buni `raso:module:init` avtomatlashtiradi (MP-05).

---

## Keyingi tasklar

Paket B0 bosqichida **tugadi** (MP-01, MP-02, MP-03). Keyingi ish paketdan
tashqarida:

- **MP-04** — `raso/llm` paketi: `LlmClient` va drayverlarni core'dan ajratish.
  ⚠️ Faqat ko'chirish, mantiq o'zgarmaydi, core'da 846 test yashil qolsin.
- **MP-05** — `raso-platform` meta-repo (submodule, docker-compose, Makefile).
- **MP-06+** — core'ni OIDC provider qilish.

Paketga qaytiladigan joy: modul yozayotganda biror narsa **nusxa ko'chirilsa**
— demak u shu paketda bo'lishi kerak edi, task ochilsin.
