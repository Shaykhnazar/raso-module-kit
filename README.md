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
| MP-02 | `JwtGuard`, `JwksCache`, `ScopeMiddleware` | ⏳ |
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

### `Raso\ModuleKit\Testing`

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
4. **Test faylida `function` e'lon qilmang.** `pest --parallel` ikki faylni bitta jarayonga yuklasa `Cannot redeclare` bilan yiqiladi — va bu xato ketma-ket ishlatilganda **ko'rinmaydi**. Umumiy yordamchi → `src/Testing/helpers.php`.
5. **TDD.** Domen qoidasi avval testda tug'iladi.
