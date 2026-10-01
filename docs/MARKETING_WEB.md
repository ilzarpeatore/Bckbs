# Marketing de la web: newsletter, contacto, analítica y cestas abandonadas

(2026-10-01) Todo lo que la web pública (`webbs`) recoge de los visitantes y
cómo se ve en el panel (`bstronger-admin` → Marketing).

## Conexión web → backend

La web llama al backend desde su servidor, así que todas sus peticiones salen
con la misma IP. Para distinguir visitantes, la web manda:

- `X-Web-Key: <WEB_SERVER_KEY>`: clave compartida, **la misma en el `.env` del
  backend y en el `.env.production` de la web**.
- `X-Client-IP: <ip del visitante>`: solo se tiene en cuenta si la clave es correcta.

Con eso los límites de peticiones (`throttle:web-forms`, `throttle:api`) son
por visitante, no globales para toda la web (`App\Support\WebClient`). Sin la
clave, `POST api/track` responde 403 y no se registran visitas.

## Newsletter (doble opt-in)

- `POST api/newsletter-subscribe` `{email, source, attribution, website}`:
  - `website` es una trampa para bots y tiene que llegar vacío.
  - El alta queda en `pending` y se envía `NewsletterConfirmMail`.
- `POST api/newsletter-confirm` `{token}`: pasa a `confirmed`.
- `POST api/newsletter-unsubscribe` `{token}`: pasa a `unsubscribed`.
- La web tiene `/newsletter/confirmar` y `/newsletter/baja`.
- El origen (`source`) puede ser `footer`, `waitlist` o `pack:<slug>`.
- Panel → Newsletter:
  - Listado, filtros y totales.
  - Exportar a CSV (por defecto, solo confirmados), para importarlo en la herramienta de email.
  - Borrado definitivo (derecho de supresión).
- El envío de newsletters no se hace desde aquí: se exporta a Brevo, Mailchimp o similar, que gestionan las bajas de sus envíos.

## Contacto

- `POST api/contact-message` `{name, email, subject, message, attribution, website}`.
- Se guarda y se avisa por email a `CONTACT_NOTIFY_EMAIL` (o a `MAIL_FROM_ADDRESS`), con `Reply-To` del remitente.
- Panel → Mensajes de contacto.

## Analítica propia sin cookies

- La web registra cada página vista (`POST api/track`):
  - Ruta, dispositivo, navegador, web de origen.
  - UTM (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`).
  - Tipo de clic de anuncio: `gclid` (Google Ads), `fbclid` (Meta), `ttclid` (TikTok), `msclkid` (Bing).
- **Visitante** = hash diario de IP + navegador + una sal secreta que rota cada día, calculado en la web. No se guardan IP ni cookies y no se puede seguir a nadie de un día a otro. Por eso no hace falta el banner de cookies.
- **Atribución**: las conversiones (newsletter, contacto, intento de compra) guardan la campaña. Si llegan sin UTM, heredan la de la primera visita del mismo visitante en las últimas 24 h (`App\Support\Attribution`).
- Las visitas se borran a los 25 meses (`analytics:prune`, el día 1 de cada mes).
- Panel → Analítica:
  - Visitas por día, páginas, fuentes, dispositivos y clics de anuncios.
  - **Campañas** con embudo: visitantes → compras iniciadas → compras → ingresos, abandonos y altas de newsletter.
  - **Packs**: landing → comprar → pago → registrado en la app → onboarding terminado.

## Cestas abandonadas y recuperación

- Cada "Comprar" crea un `pack_checkout_attempts` (`started`) con su campaña.
- Webhook `checkout.session.completed` → `completed`.
- Webhook **`checkout.session.expired`** → `expired` (cesta abandonada). **Hay que añadir este evento al webhook de Stripe.**
- La sesión caduca a las `STRIPE_CHECKOUT_EXPIRES_HOURS` horas (por defecto 3, entre 1 y 24).
- Stripe Checkout pide consentimiento de comunicaciones (`consent_collection.promotions=auto`) y genera un enlace de recuperación (`after_expiration.recovery`).
- **Email de recuperación** (`PackRecoveryMail`): uno solo, y únicamente si:
  - aceptó comunicaciones;
  - hay email y enlace;
  - el pack sigue a la venta;
  - no lo ha comprado ya;
  - no se le ha escrito por ese pack en 7 días.
- Si paga con ese enlace, el intento pasa a `recovered`.
- Panel → Cestas abandonadas.

## Variables de entorno

Backend (`.env`):

```
WEB_SERVER_KEY=<cadena larga aleatoria, igual que en la web>
CONTACT_NOTIFY_EMAIL=<email donde recibir los mensajes de contacto>
STRIPE_CHECKOUT_EXPIRES_HOURS=3
```

Web (`.env.production`):

```
WEB_SERVER_KEY=<la misma>
ANALYTICS_SALT=<otra cadena larga aleatoria>
```
