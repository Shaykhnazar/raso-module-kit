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
7. **Har o'zgarishdan keyin `composer check`.** Yashil bo'lmasa ish tugamagan.

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
src/Testing/    RasoUserFactory · helpers.php (fakeRasoUser)
config/         modul repolariga nusxa ko'chiriladigan shablonlar
tests/Unit/     sof PHP unit testlar (Laravel yuklanmaydi)
```

**`config/` shablonlari** — modul repo'si nusxa oladi, paketdan `import`
qilmaydi (deptrac/phpstan konfiglari yo'l bo'yicha ishlaydi). Shablon
o'zgarsa, modullarga qo'lda tarqatiladi — buni `raso:module:init` avtomatlashtiradi (MP-05).

---

## Keyingi tasklar

- **MP-02** — `Auth/`: `JwtGuard` (RS256, JWKS kesh, fail-closed), `ScopeMiddleware`.
  Bu yerda Laravel paydo bo'ladi → `illuminate/support` va larastan qo'shiladi,
  `deptrac.yaml` ga `Auth` layer'i (`Auth → Domain`, teskarisi yo'q).
- **MP-03** — `Events/`: `OutboxRelay`, `EventConsumer` (idempotent),
  `UserDeletedListener` (abstrakt) + `Testing/Contract/UserDeletionContractTest`.
