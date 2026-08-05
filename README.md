# raso/module-kit

Raso OS **modul-servislari** uchun umumiy yadro. Har modul (AI Chat, Calendar, …)
shu paketni o'rnatadi va infratuzilmani noldan yozmaydi.

> Arxitektura: `~/Documents/Hamkorlik/Tizim arxitekturasi/Modul platformasi/`
> Reja: `muvaffaqiyat-os/.claude/tasks/modul-platformasi.md`

---

## Holat

| Task | Nima | Holat |
|---|---|---|
| **MP-01** | Domen primitivlari + sifat gate shablonlari | ✅ **tugadi** |
| **MP-02** | Offline JWT tekshiruvi, JWKS kesh, scope middleware | ✅ **tugadi** |
| MP-03 | `OutboxRelay`, `EventConsumer`, `UserDeletedListener` | ⏳ |

---

## O'rnatish

Hozircha path repository sifatida (paket hali hech qayerga e'lon qilinmagan):

```json
{
  "repositories": [
    { "type": "path", "url": "../raso-module-kit" }
  ],
  "require": { "raso/module-kit": "@dev" }
}
```

---

## Nima bor

### `Raso\ModuleKit\Domain` — sof PHP, Laravel yo'q

| Klass | Nima |
|---|---|
| `PublicId` | UUIDv7 VO. **Ichki `bigint id` bu yerga hech qachon tushmaydi** — u sanaladigan va foydalanuvchilar sonini oshkor qiladi |
| `Locale` | `uz · uz_Cyrl · ru · en`. `uz_Cyrl` — alohida til emas, **yozuv**; `language()` ikkalasi uchun `uz` beradi |
| `Scope` | OAuth scope to'plami. **Wildcard va prefiks mos kelishi YO'Q** — `chat:read` `chat:*` ni ochmaydi |
| `VerificationLevel` | L0–L3. Noma'lum qiymat → **L0** (fail-closed) |
| `RasoUser` | Token claim'laridan quriladi. **PII maydoni yo'q** va test buni majburlaydi |
| `DomainEvent` | Servislararo yagona muloqot shartnomasi |
| `AggregateRoot` | Hodisa to'plash. `pendingEvents()` tozalamaydi, `releaseEvents()` tozalaydi |

### `Raso\ModuleKit\Auth` — offline token tekshiruvi

| Klass | Nima |
|---|---|
| `TokenVerifier` | RS256, `kid` bo'yicha kalit tanlash, `iss`/`aud`/`exp` tekshiruvi. **`alg` imzodan OLDIN tekshiriladi** — algorithm confusion hujumi shu yerda to'xtaydi |
| `CachedJwksProvider` | 24 soat kesh + `kid` topilmasa majburiy yangilash, **5 daqiqalik sovish davri** bilan (amplifikatsiya himoyasi) |
| `HttpJwksFetcher` | JWKS ni IdP'dan oladi, 3 sekund timeout |
| `RasoUserIdentity` | `RasoUser` (sof domen) ↔ Laravel `Authenticatable` ko'prigi |
| `Http\AuthenticateMiddleware` | `raso.auth` — 401 va `WWW-Authenticate: Bearer` |
| `Http\ScopeMiddleware` | `raso.scope:chat:write` — yetmasa 403 |

Route'da:

```php
Route::middleware(['raso.auth', 'raso.scope:chat:write'])
    ->post('/threads', StoreThreadController::class);
```

`.env` (boshqa sozlash kerak emas — provider avtomatik ulanadi):

```
RASO_ISSUER=https://api.raso.uz
RASO_MODULE_KEY=chat
RASO_JWKS_URI=https://api.raso.uz/oauth/jwks
```

### `Raso\ModuleKit\Testing`

- `AuthKit::make()` — soxta IdP + xotiradagi kesh + verifier, bitta chaqiruvda
- `FakeJwtIssuer` — RSA kalit yasaydi, token imzolaydi, `JwksFetcher` portini bajaradi (**testlar tarmoqqa chiqmaydi**)
- `RasoUserFactory::make([...])` — determinstik test foydalanuvchisi
- global `fakeRasoUser([...])` — composer `autoload.files` orqali hamma joyda

### `config/` — modul repolariga nusxa ko'chiriladigan shablonlar

- `deptrac.layers.yaml` — qatlam chegaralari (Domain/Application/Infrastructure/Presentation/Contracts)
- `deptrac.contexts.yaml.dist` — bounded context chegaralari (`__CONTEXT__` almashtiriladi)
- `phpstan.module.neon.dist` — larastan lvl 8 (modullar Laravel ilovasi)
- `pint.json`

---

## Buyruqlar

```bash
composer check    # pint --test → phpstan lvl8 → deptrac → pest --parallel
composer pint     # formatni tuzatadi
composer test
```

---

## Qat'iy qoidalar

1. **`Domain/` da Laravel YO'Q.** Eloquent, Facade, `request()`, `config()` — hech biri. Octane jarayonni tirik saqlaydi; domen obyektida konteyner havolasi bo'lsa — memory leak.
2. **`RasoUser` ga PII qo'shilmaydi.** `email`, `phone`, `telegram` nomli maydon qo'shilsa `RasoUserTest` refleksiya bilan yiqiladi. Bu qasddan: modul kontakt ma'lumotini ko'rmaydi, bildirishnomani core (Engagement) yuboradi.
3. **Fail-closed.** Noma'lum rol → `user`. Noma'lum verifikatsiya darajasi → `L0`. Noma'lum til → `uz`. Hech qachon ruxsat kengaymasin.
4. **Auth fail-closed.** JWKS olinmasa token **rad etiladi** (401), 500 emas. Rad etish sababi klientga aytilmaydi — «imzo noto'g'ri» bilan «kalit topilmadi» ni ajratish hujumchiga ma'lumot beradi.
5. **Faqat RS256.** `none` va HS256 aniq rad etiladi.
6. **Test faylida `function` e'lon qilmang.** `pest --parallel` ikki faylni bitta jarayonga yuklasa `Cannot redeclare` bilan yiqiladi — va bu xato ketma-ket ishlatilganda **ko'rinmaydi**. Umumiy yordamchi → `src/Testing/helpers.php`.
7. **TDD.** Domen qoidasi avval testda tug'iladi.
