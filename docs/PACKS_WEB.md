# Packs vendidos en la web (Stripe) → contenido en la app

Implementado el 2026-09-30. Sustituye al checkout retirado el 2026-09-16 (PR #16), que estaba
pensado para comprar con cuenta ya creada; este flujo **no pide cuenta para pagar**.

## Qué es un pack

Un `Plan` con `sold_on_web = true`. Al concederse (PlanFulfillmentService) asigna todo lo que
tenga configurado:

| Campo del plan | Qué asigna |
| --- | --- |
| `training_program_id` | Programa de entrenamiento (copia propia del cliente) |
| `meal_plan_template_id` | Plan de nutrición en el calendario |
| `habit_template_ids` | Hábitos (copias de plantillas `habits` con `client_id` NULL) |
| `resource_ids` | Recursos asignados al cliente |
| `invoice_period` + `invoice_interval` | Duración (p. ej. 3 + `month`) |

Para la web: `short_description`, `description`, `image_url`, `price` (+ `signup_fee`),
`currency`, `sort_order`. El `slug` forma la URL: `bestronger.es/packs/<slug>`.

## Flujo

1. **Web** (`webbs`, `/packs/<slug>`) → `POST api/pack-checkout {slug, email?}` → URL de Stripe
   Checkout (pago único; el precio sale del Plan, no hay que crear productos en Stripe).
2. **Stripe** cobra y llama a `POST api/webhooks/stripe` (`checkout.session.completed`). Se crea
   una `pack_purchases` con email, importe y un **código de canje** de 10 caracteres, y se envía el
   email de confirmación (`PackPurchaseMail`).
   - Si ya existe una cuenta con ese email → se vincula en el acto.
3. **Página de gracias** (`/packs/gracias?session_id=…` → `GET api/pack-checkout-status`): dice
   con qué email registrarse y muestra el código. Si el webhook aún no ha llegado, consulta la
   sesión a Stripe y registra la compra (idempotente).
4. **App**: al registrarse con el mismo email, la compra se vincula sola
   (`UserController::register` → `PackPurchaseService::claimPendingByEmail`). Si usó otro email,
   Perfil → *Tengo un código* (`POST api/v1/pack-redeem`).
5. El contenido se asigna **al terminar el onboarding** (`OnboardingController::complete` →
   `startPendingFor`), para que el coach tenga los datos antes del día 1. Si ya lo había terminado,
   en el acto.
6. **Devolución** hecha en Stripe (`charge.refunded`) → compra `refunded` y se retira el contenido
   futuro (`PlanFulfillmentService::revokeAccess`).

Admin: `GET admin/pack-purchases` (listado, búsqueda por email o código),
`POST admin/pack-purchases-resend`, `POST admin/pack-purchases-link` (vincular a mano,
con `user_id` o con `user_email` del cliente). En el panel (`bstronger-admin`):
**Planes** (marcar "En venta en la web", descripción corta, imagen, hábitos y recursos
incluidos) y **Compras de packs** (`/pack-purchases`: reenviar email, vincular).

### Página Packs del panel (2026-10-01)
- Un pack es un `Plan` con `is_pack = true`. La página **Packs** del panel solo lista
  esos (`GET admin/plans?is_pack=1`) y tiene un formulario propio sin campos de
  suscripción (prueba, gracia, facturación): duración, precio, contenido y la web.
- **Enlace**: `plans.slug` es la URL pública (`{PACKS_WEB_URL}/packs/{slug}`), único
  (sufijo `-2`, `-3`… si se repite el nombre) y editable. `PlanResource.pack_url` lo
  da hecho cuando el pack se vende en la web.
- **Imagen**: `POST admin/pack-image` (jpg/png/webp, máx. 5 MB) la guarda en el disco
  `public` (`storage/app/public/packs`) y devuelve la URL para `image_url`.
- **Estadísticas**: `GET admin/pack-stats` → por pack: compras (sin devoluciones),
  ingresos, registrados en la app, sin registrar, devoluciones.
- **Recordatorio**: `php artisan packs:remind-unclaimed` (cron diario 10:00 Madrid)
  envía `PackReminderMail` a quien pagó y sigue sin cuenta vinculada: a los 3 días y
  a los 10 (máximo 2; `pack_purchases.reminders_sent`). `--dry-run` para ver a quién.

## Configuración (una vez)

En el `.env` del servidor (nunca en el repo):

```
STRIPE_SECRET_KEY=sk_live_...        # Dashboard → Developers → API keys
STRIPE_WEBHOOK_SECRET=whsec_...      # ver abajo
PACKS_WEB_URL=https://bestronger.es  # vuelta desde Stripe
APP_STORE_URL=https://apps.apple.com/app/id...   # aparece en el email
PLAY_STORE_URL=https://play.google.com/store/apps/details?id=...
```

Tras cambiar el `.env`: `php artisan config:cache`.

Webhook en Stripe: Dashboard → Developers → Webhooks → *Add endpoint*
- URL: `https://testapp.bestronger.es/api/webhooks/stripe`
- Eventos: `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
  `charge.refunded`
- Copiar el *Signing secret* a `STRIPE_WEBHOOK_SECRET`.

Métodos de pago (tarjeta, Bizum, Apple Pay/Google Pay): se activan en Dashboard → Settings →
Payment methods; el checkout los ofrece solos.

Probar antes en **modo test** (claves `sk_test_…` y un webhook de test) con la tarjeta
`4242 4242 4242 4242`.

## Normas de las tiendas (Apple 3.1.1 / 3.1.3)

- La app **no** muestra precios, ni botones ni enlaces de compra. Solo *Tengo un código*.
- Vender programas pregrabados fuera de la app es la zona gris de la guideline 3.1.3(b)
  (contenido multiplataforma). El riesgo baja si los packs se presentan como parte del servicio
  de coaching con seguimiento del entrenador (3.1.3(d), servicios persona a persona).
- Hasta 2026-09 se había explicado a Apple que la app solo complementa un servicio presencial
  sin pagos digitales (`bsa/docs/PLAN_VENTAS_PROGRAMAS_Y_BLOG.md`). Si App Review pregunta,
  la respuesta tiene que reflejar este flujo.

## Tests

`tests/Feature/PackPurchaseTest.php`: catálogo, checkout, webhook con firma real (y firma
inválida), idempotencia, pagos no completados, vinculación por email existente o al registrarse,
inicio al terminar el onboarding, código de canje (y reutilización bloqueada), página de gracias
con webhook tardío, devolución.
