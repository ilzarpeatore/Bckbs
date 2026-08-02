# Flutter Integration Guide — MightyFitness Backend APIs

Documento de referencia para integrar las nuevas funciones del backend Laravel en las apps Flutter (cliente y coach). Incluye endpoints, métodos, payloads y ejemplos de uso.

---

## 1. Autenticación y base URL

**Base URL:** `http://localhost:8000/api` (ajustar por entorno)

### Login cliente
```http
POST /api/login
Content-Type: application/json

{
  "email": "demo@bestronger.app",
  "password": "12345678",
  "user_type": "user"
}
```

Respuesta:
```json
{
  "data": {
    "id": 3,
    "api_token": "33|aizJpk3XdIZSAlOSEYrmeG3mr13M1mO4qMxXBU3l739b6f36",
    ...
  }
}
```

Usar `api_token` como Bearer token en todas las llamadas cliente:
```http
Authorization: Bearer <api_token>
```

### Login admin (panel web)
```http
POST /api/admin/login
Content-Type: application/json

{
  "email": "admin@admin.com",
  "password": "12345678"
}
```

Respuesta:
```json
{
  "token": "17|Ih7ex2VfAcM3Y3opX2yurnB0PLQpxtVuI5ANT4CRf8124709",
  "data": { ... }
}
```

Usar `token` como Bearer token en todas las llamadas admin:
```http
Authorization: Bearer <token>
```

---

## 2. Client Notes (Notas del coach)

Solo admin. El cliente NO ve estas notas directamente (a menos que se decida mostrarlas).

### Listar notas de un cliente
```http
GET /api/admin/client-note-list?client_id=3
Authorization: Bearer <admin_token>
```

Respuesta:
```json
{
  "data": [
    {
      "id": 1,
      "content": "Test note from OpenCode",
      "author": { "id": 1, "first_name": "System", "last_name": "Admin" },
      "created_at": "2026-07-21T17:26:06.000000Z"
    }
  ]
}
```

### Crear nota
```http
POST /api/admin/client-note-store
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "client_id": 3,
  "content": "El cliente progresa bien en peso muerto."
}
```

### Actualizar nota
```http
POST /api/admin/client-note-update
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "id": 1,
  "content": "Texto actualizado"
}
```

### Eliminar nota
```http
POST /api/admin/client-note-delete
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "id": 1
}
```

---

## 3. Progress Photos (Fotos de progreso)

### Admin: listar fotos de un cliente
```http
GET /api/admin/progress-photo-list?client_id=3
Authorization: Bearer <admin_token>
```

Respuesta:
```json
{
  "data": [
    {
      "id": 1,
      "url": "http://127.0.0.1:8000/storage/1/test-progress-photo.png",
      "name": "Before photo",
      "created_at": "2026-07-21T17:26:58.000000Z"
    }
  ]
}
```

### Admin: subir foto
```http
POST /api/admin/progress-photo-store
Authorization: Bearer <admin_token>
Content-Type: multipart/form-data

Fields:
- client_id: 3
- name: "Front photo" (opcional)
- photo: <archivo imagen>
```

### Admin: eliminar foto
```http
POST /api/admin/progress-photo-delete
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "client_id": 3,
  "photo_id": 1
}
```

### Cliente: fotos de progreso
Las fotos subidas por el cliente a través de formularios tipo `progress_photos` se guardan en la misma colección `progress_photos` del usuario. El cliente puede listarlas mediante el endpoint existente de galería o dashboard.

---

## 4. Tasks (Tareas)

Solo admin. Futuro: se pueden mostrar notificaciones push al cliente cuando se le asigne una tarea.

### Listar tareas
```http
GET /api/admin/task-list?status=pending&client_id=3&search=cita
Authorization: Bearer <admin_token>
```

Respuesta:
```json
{
  "pagination": { ... },
  "data": {
    "data": [
      {
        "id": 1,
        "title": "Revisar check-in semanal",
        "description": "...",
        "due_date": "2026-07-28",
        "priority": "high",
        "status": "pending",
        "client": { "id": 3, "first_name": "Demo", "last_name": "User", "email": "..." },
        "author": { "id": 1, ... }
      }
    ]
  }
}
```

### Crear tarea
```http
POST /api/admin/task-store
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "title": "Llamar al cliente",
  "description": "Seguimiento mensual",
  "client_id": 3,
  "due_date": "2026-07-28",
  "priority": "high",
  "status": "pending"
}
```

### Actualizar tarea
```http
POST /api/admin/task-update
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "id": 1,
  "title": "Llamar al cliente",
  "status": "completed"
}
```

### Eliminar tarea
```http
POST /api/admin/task-delete
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "id": 1
}
```

---

## 5. Forms & Check-ins (Formularios y Check-ins)

Esta es la funcionalidad más extensa. Soporta Questionnaires (una sola vez) y Check-ins (recurrentes: daily, weekly, monthly).

### 5.1 Admin: gestión de formularios

#### Listar formularios
```http
GET /api/admin/admin-form-list?kind=questionnaire&per_page=100
Authorization: Bearer <admin_token>
```

`kind` puede ser: `questionnaire`, `checkin`, u omitirse.

#### Crear/editar formulario
```http
POST /api/admin/admin-form-store
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "title": "Initial Questionnaire",
  "description": "Cuestionario inicial",
  "recurrence": null
}
```

Para check-ins, usar `"recurrence": "daily"`, `"weekly"` o `"monthly"`.

Para editar, incluir `"id": <form_id>`.

#### Eliminar formulario
```http
POST /api/admin/admin-form-delete
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "id": 3
}
```

#### Ver formulario con preguntas
```http
GET /api/admin/admin-form-detail?id=3
Authorization: Bearer <admin_token>
```

Respuesta:
```json
{
  "data": {
    "id": 3,
    "title": "Initial Questionnaire",
    "recurrence": null,
    "questions": [
      {
        "id": 3,
        "question_text": "What is your goal?",
        "type": "multiple_choice",
        "options": ["Lose fat", "Build muscle", "Maintenance"],
        "allow_multiple": true,
        "is_required": true,
        "order": 0
      },
      {
        "id": 4,
        "question_text": "Current weight?",
        "type": "metric",
        "metric_id": 3,
        "metric": { "id": 3, "key": "carga", "label": "Carga", "unit": "kg" },
        "is_required": true,
        "order": 1
      },
      {
        "id": 5,
        "question_text": "Upload progress photos",
        "type": "progress_photos",
        "max_files": 3,
        "is_required": false,
        "order": 2
      }
    ]
  }
}
```

#### Crear/editar pregunta
```http
POST /api/admin/admin-form-question-store
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "form_id": 3,
  "question_text": "What is your goal?",
  "type": "multiple_choice",
  "options": ["Lose fat", "Build muscle", "Maintenance"],
  "allow_multiple": true,
  "order": 0,
  "is_required": true
}
```

Para editar, incluir `"id": <question_id>`.

#### Tipos de pregunta soportados

| Tipo | Campos extras | Uso en Flutter |
|------|---------------|----------------|
| `text` | `placeholder` | Input de texto |
| `textarea` | `placeholder` | Textarea multilinea |
| `number` | `placeholder` | Input numérico |
| `scale` | `scale_max` (default 10) | Slider 1-N |
| `yes_no` | — | Switch o botones Yes/No |
| `date` | — | DatePicker |
| `multiple_choice` | `options[]`, `allow_multiple` | Radio / Checkbox |
| `media` | `max_files` | Selector de imágenes/videos |
| `progress_photos` | `max_files` | Selector de fotos → galería |
| `star_rating` | `star_max` (default 5) | Estrellas 1-N |
| `metric` | `metric_id` | Input numérico → sync a métricas |
| `signature` | — | Canvas de firma (base64) |

#### Eliminar pregunta
```http
POST /api/admin/admin-form-question-delete
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "id": 3
}
```

#### Asignar formulario a cliente
```http
POST /api/admin/admin-form-assign
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "form_id": 3,
  "client_id": 3
}
```

#### Listar submissions (respuestas)
```http
GET /api/admin/admin-form-submission-list?form_id=3&client_id=3&per_page=50
Authorization: Bearer <admin_token>
```

#### Dejar feedback sobre submission
```http
POST /api/admin/admin-form-feedback
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "submission_id": 1,
  "coach_feedback": "Buen progreso, sigue así."
}
```

### 5.2 Cliente: responder formularios

#### Listar formularios asignados
```http
GET /api/form-assigned-list?kind=questionnaire
Authorization: Bearer <client_token>
```

`kind` puede ser `questionnaire` o `checkin`.

#### Ver detalle de formulario
```http
GET /api/form-detail?id=3
Authorization: Bearer <client_token>
```

#### Enviar respuestas

**Importante:** para preguntas con archivos, usar `multipart/form-data`. Para formularios solo texto, puede usarse `application/json`.

Ejemplo con archivos (progress photos):
```http
POST /api/form-submit
Authorization: Bearer <client_token>
Content-Type: multipart/form-data

Fields:
- form_assignment_id: 2
- answers[0][form_question_id]: 3
- answers[0][answer_value]: ["Build muscle"]
- answers[1][form_question_id]: 4
- answers[1][answer_value]: 75.5
- answers[2][form_question_id]: 5
- media_5: <archivo imagen>
```

Reglas para enviar respuestas:

1. **Todas las preguntas deben tener un objeto en `answers[]`** con `form_question_id`.
2. **`answer_value`**:
   - `text`, `textarea`, `number`, `scale`, `yes_no`, `date`, `star_rating`, `signature`: valor como string.
   - `multiple_choice` con `allow_multiple=true`: array JSON, ej: `["Lose fat","Build muscle"]`.
   - `multiple_choice` con `allow_multiple=false`: string con el valor seleccionado.
   - `metric`: string numérico.
   - `media` / `progress_photos`: `answer_value` puede ir vacío; el servidor lo rellena con las URLs de los archivos.
3. **Archivos**: adjuntar bajo la clave `media_<question_id>`. Puede ser un solo archivo o múltiples si `max_files` > 1.
4. **Sincronización automática**:
   - `metric`: el valor se guarda también en `user_graphs`.
   - `progress_photos`: los archivos se guardan en la colección `progress_photos` del usuario.

Ejemplo JSON sin archivos:
```http
POST /api/form-submit
Authorization: Bearer <client_token>
Content-Type: application/json

{
  "form_assignment_id": 2,
  "answers": [
    { "form_question_id": 3, "answer_value": ["Build muscle"] },
    { "form_question_id": 4, "answer_value": "75.5" }
  ]
}
```

Respuesta:
```json
{
  "message": "Check-In has been save successfully"
}
```

---

## 6. Metrics (Métricas)

### Listar catálogo de métricas
```http
GET /api/admin/metric-list
Authorization: Bearer <admin_token>
```

Respuesta:
```json
{
  "data": [
    { "id": 3, "key": "carga", "label": "Carga", "unit": "kg", "input_type": "number", "higher_is_better": true, "order": 3 }
  ]
}
```

### Cliente: historial de métricas
```http
GET /api/usergraph-list?type=carga
Authorization: Bearer <client_token>
```

Respuesta:
```json
{
  "pagination": { ... },
  "data": {
    "data": [
      { "id": 3, "value": "75.5", "type": "carga", "date": "2026-07-21", "unit": "kg" }
    ]
  }
}
```

### Guardar métrica manualmente (ya existente)
```http
POST /api/usergraph-save
Authorization: Bearer <client_token>
Content-Type: application/json

{
  "value": 75.5,
  "type": "carga",
  "unit": "kg",
  "date": "2026-07-21"
}
```

---

## 7. Banner Sliders

### Listar banners (admin)
```http
GET /api/admin/banner-sliders?per_page=100
Authorization: Bearer <admin_token>
```

Respuesta:
```json
{
  "pagination": { ... },
  "data": {
    "data": [
      {
        "id": 1,
        "title": "New Banner",
        "slug": "new-banner",
        "type": "url",
        "url": "https://example.com",
        "workout_id": null,
        "status": "active",
        "bannerslider_image": "http://127.0.0.1:8000/storage/2/banner.png"
      }
    ]
  }
}
```

### Cliente: banners en dashboard
Los banners se devuelven automáticamente en:
```http
GET /api/dashboard-detail
```

Dentro de la respuesta, buscar `banner_slider`:
```json
{
  "data": {
    "banner_slider": [
      {
        "id": 1,
        "title": "New Banner",
        "type": "url",
        "url": "https://example.com",
        "bannerslider_image": "http://127.0.0.1:8000/storage/2/banner.png"
      }
    ]
  }
}
```

### Comportamiento según tipo
- `type: url`: abrir URL externa al tocar.
- `type: workout`: navegar al workout con `workout_id`.

### Admin: subir/actualizar banner
```http
POST /api/admin/banner-sliders
Authorization: Bearer <admin_token>
Content-Type: multipart/form-data

Fields:
- title: "New Banner"
- type: "url" | "workout"
- url: "https://example.com" (si type=url)
- workout_id: 5 (si type=workout)
- status: "active" | "inactive"
- bannerslider_image: <archivo imagen>
```

Para actualizar:
```http
PUT /api/admin/banner-sliders/1
Authorization: Bearer <admin_token>
Content-Type: multipart/form-data
```

---

## 8. Patrones comunes

### Respuesta con paginación
Muchos endpoints devuelven:
```json
{
  "pagination": {
    "total_items": 100,
    "per_page": 50,
    "currentPage": 1,
    "totalPages": 2
  },
  "data": {
    "data": [ ... ],
    "current_page": 1,
    ...
  }
}
```

La lista real está en `response.data.data`.

### Subida de archivos en Flutter
Usar `MultipartRequest` de `http` o `Dio`:

```dart
var request = http.MultipartRequest('POST', Uri.parse('$baseUrl/api/admin/progress-photo-store'));
request.headers['Authorization'] = 'Bearer $token';
request.fields['client_id'] = '3';
request.files.add(await http.MultipartFile.fromPath('photo', filePath));
var response = await request.send();
```

Para `form-submit` con múltiples archivos:
```dart
var request = http.MultipartRequest('POST', Uri.parse('$baseUrl/api/form-submit'));
request.headers['Authorization'] = 'Bearer $clientToken';
request.fields['form_assignment_id'] = '2';
request.fields['answers[0][form_question_id]'] = '3';
request.fields['answers[0][answer_value]'] = jsonEncode(['Build muscle']);
request.files.add(await http.MultipartFile.fromPath('media_5', photoPath));
```

### Manejo de errores
- `401 Unauthorized`: token inválido o expirado → redirigir a login.
- `403 Forbidden`: usuario no tiene permiso.
- `422 Validation Error`: respuesta incluye `message` con el primer error.
- `500 Internal Server Error`: revisar logs de Laravel.

---

## 9. Checklist de integración por feature

### Client Notes
- [ ] Pantalla de notas en perfil del cliente (coach)
- [ ] CRUD de notas

### Progress Photos
- [ ] Galería de fotos en perfil del cliente
- [ ] Subida de foto desde galería
- [ ] Subida automática desde formularios `progress_photos`

### Tasks
- [ ] Pantalla de tareas del coach
- [ ] Crear/editar/eliminar tareas
- [ ] Asignar tareas a clientes
- [ ] (Opcional) Notificación push al cliente

### Forms & Check-ins
- [ ] Listado de formularios asignados en app cliente
- [ ] Pantalla de respuesta con renderizado dinámico por tipo
- [ ] Soporte multipart para media/progress_photos
- [ ] Listado de submissions en app coach
- [ ] Feedback del coach sobre submissions

### Metrics
- [ ] Pantalla de métricas del cliente
- [ ] Gráficas con `usergraph-list`
- [ ] Sincronización automática desde formularios `metric`

### Banner Sliders
- [ ] Mostrar banners desde `dashboard-detail`
- [ ] Navegación según `type`: url externa o workout interno

---

## 10. Archivos relevantes del backend

- `app/Http/Controllers/API/Admin/ClientNoteController.php`
- `app/Http/Controllers/API/Admin/ProgressPhotoController.php`
- `app/Http/Controllers/API/Admin/TaskController.php`
- `app/Http/Controllers/API/Admin/FormController.php`
- `app/Http/Controllers/API/FormController.php` (cliente)
- `app/Http/Controllers/API/MetricController.php`
- `app/Http/Controllers/API/Admin/BannerSliderController.php`
- `routes/api.php`
- `app/Models/Form.php`
- `app/Models/FormQuestion.php`
- `app/Models/FormSubmission.php`
- `app/Models/FormAnswer.php`
- `app/Models/UserGraph.php`

---

*Última actualización: 21/07/2026*
