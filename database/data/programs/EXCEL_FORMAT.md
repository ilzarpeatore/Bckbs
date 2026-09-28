# Formato Excel para generar programas de entrenamiento (BeFit / Bckbs)

Este documento describe el formato exacto de archivo `.xlsx` que debes rellenar para generar una **programación de entrenamiento de N semanas**, lista para importarse automáticamente en la base de datos de BeFit.

Adjunto tienes `excel.example.xlsx` — un programa real de 4 semanas × 5 sesiones ya relleno con este mismo formato. Úsalo como referencia de estructura y de cómo se escriben los valores; no copies su contenido si el usuario te pide un programa distinto.

## Qué debes devolver

Un archivo `.xlsx` con **exactamente estas dos hojas** (los nombres deben ser idénticos, con tilde en "Programación"):

1. **`Programa`** — metadatos del programa completo.
2. **`Programación`** — una fila por cada ejercicio de cada sesión, de todas las semanas.

No cambies los nombres de columna de la fila de cabecera. No añadas hojas extra salvo que quieras incluir una hoja de notas — se ignorará, no rompe nada, pero no es necesaria.

---

## Hoja "Programa"

Fila 1 = cabecera, fila 2 = valores. Una única fila de datos.

| Columna | Tipo | Obligatoria | Ejemplo |
|---|---|---|---|
| `titulo` | texto | sí | `Hipertrofia Full Body 5 días — 4 semanas` |
| `descripcion` | texto | no | `Progresión lineal 3 semanas + descarga en semana 4. RIR objetivo 1-2, RIR 3 en descarga.` |
| `semanas` | entero | no (informativo) | `4` |

`semanas` es solo informativo para quien lea el archivo — el número real de semanas del programa lo determina cuántos valores distintos de `semana` aparecen en la hoja "Programación". Si pones `semanas=4` ahí pero solo escribes 3 semanas de filas, el programa se importará con 3 semanas, no 4. Asegúrate de que coincidan.

---

## Hoja "Programación"

Una fila = un ejercicio dentro de un bloque, dentro de un día, dentro de una semana. **No es una fila por serie** (no es el formato de exportación de Hevy/Strong) — si un ejercicio tiene 4 series de 6-8 reps, eso es **una sola fila** con `series=4` y `reps=6-8`.

### Columnas (19, en este orden)

| # | Columna | Tipo | Obligatoria | Formato / ejemplo | Notas |
|---|---|---|---|---|---|
| 1 | `semana` | entero | **sí** | `1`, `2`, `3`... | 1..N. Todas las semanas del programa deben estar explícitas — no dejes que se repita sola la última semana, escribe cada semana con su propia progresión de carga/RIR. |
| 2 | `dia` | entero 1-7 | **sí** | `1` | 1=Lunes, 2=Martes... 7=Domingo. |
| 3 | `nombre_dia` | texto | **sí** | `Empuje A` | Debe ser igual en todas las filas de ese (semana, día). |
| 4 | `es_descanso` | `TRUE`/`FALSE` | no | `FALSE` | Ver "Días de descanso" abajo — **normalmente no necesitas usar esta columna en absoluto**. |
| 5 | `notas_dia` | texto | no | `Calentar rodilla 5min antes de sentadilla` | Nota de la sesión completa. Basta con rellenarla en una sola fila de ese día (por ejemplo la primera). |
| 6 | `bloque` | texto | no | `Parte principal` | Filas consecutivas con el mismo texto en `bloque` (dentro del mismo día) se agrupan en el mismo bloque. Si lo dejas vacío, se usa `Parte principal` por defecto. Otros valores típicos: `Calentamiento`, `Accesorio`, `Core`. |
| 7 | `instrucciones_bloque` | texto | no | `Superserie con el siguiente, sin descanso` | Instrucción a nivel de bloque (superserie, circuito, nº de vueltas...). Es texto libre — la base de datos no tiene un campo estructurado para "estos ejercicios van alternados", así que esta es la única forma de comunicarlo. |
| 8 | `ejercicio` | texto | **sí*** | `Press banca barra` | Nombre del ejercicio en español, lo más estándar posible (ver "Nombres de ejercicios" abajo). |
| 9 | `equipo` | texto | no | `Barra`, `Mancuernas`, `Polea`, `Máquina` | Ayuda a identificar el ejercicio correcto si el nombre es ambiguo. |
| 10 | `series` | entero | **sí*** | `4` | |
| 11 | `reps` | texto | **sí*** | `6-8` o `10` | Valor único o rango `"min-max"`. |
| 12 | `rir` | texto | no | `2` o `1-2` | Reps in reserve. Valor único o rango. Admite decimales (`1.5`). |
| 13 | `rpe` | texto | no | `8` | Alternativa a `rir`. Si rellenas ambas en la misma fila, se usa `rpe`. Usa una **u** otra, no hace falta las dos. |
| 14 | `carga_kg` | número | no | `80` | Peso absoluto en kg. |
| 15 | `carga_pct` | número | no | `75` | % de 1RM — alternativa a `carga_kg`, no uses las dos a la vez para el mismo ejercicio. |
| 16 | `descanso_seg` | entero | no | `120` | Descanso entre series, en segundos. |
| 17 | `tempo` | texto | no | `30X1` | Notación estándar de tempo (excéntrica-pausa-concéntrica-pausa). |
| 18 | `duracion_seg` | entero | no | `45` | Para ejercicios por tiempo en vez de por reps (plancha, cargadas, etc.) — en ese caso deja `reps` vacío. |
| 19 | `notas` | texto | no | `al fallo la última serie` | Nota de ese ejercicio suelto (no de la sesión ni del bloque). |
| 20 | `tecnica` | texto | no | `Rest-pause` | Técnica especial de ese ejercicio esa semana. Usa el nombre del catálogo: Cluster sets, Bisets, Superseries, Rest-pause, Rest-pause ampliado, Drop sets, Drop sets mecánicos, Series mecánicas, BFR (oclusión), Myo-reps, Parciales, Excéntricas lentas, Isométricas. Cualquier otro texto se guarda como técnica «Otra» con ese texto. El cliente la ve en la app con su explicación. |
| 21 | `tecnica_series` | texto | no | `última` | `última` si la técnica va solo en la última serie; vacío o `todas` = en todas las series. |

\* `ejercicio`, `series` y `reps` son obligatorias **salvo que la fila sea de descanso** (`es_descanso=TRUE`), en cuyo caso se dejan vacías.

### Días de descanso — normalmente no escribas nada

Si una semana no tiene ninguna fila para el día 6, ese día se convierte automáticamente en descanso al importar. **No necesitas escribir filas de descanso explícitas.** Por ejemplo, para un programa de Lunes a Viernes (5 sesiones), simplemente no escribas ninguna fila con `dia=6` o `dia=7` — sábado y domingo quedan como descanso solos.

Usa `es_descanso=TRUE` únicamente si quieres poner una etiqueta a ese día de descanso (por ejemplo `nombre_dia=Descanso activo` + `notas_dia=Caminar 30 min`). En ese caso, deja vacías todas las columnas de ejercicio de esa fila (8 a 19).

### Nombres de ejercicios

El nombre que escribas en `ejercicio` pasa por un sistema de reconocimiento (matcher) contra el catálogo ya existente en la base de datos — no hace falta que sea exacto letra por letra, pero cuanto más estándar y descriptivo, mejor matchea:

- Bien: `Press banca barra`, `Sentadilla barra`, `Remo con barra`, `Curl bíceps mancuerna`, `Jalón al pecho`.
- Evita abreviaturas raras o nombres de marca de gimnasio.
- Si el ejercicio no existe en el catálogo, se crea automáticamente — no es un error, pero prefiere nombres reconocibles para reutilizar ejercicios ya existentes en vez de crear duplicados con nombres ligeramente distintos.

### Progresión semana a semana

A diferencia de otros formatos de importación de este sistema (que solo traen una semana y la repiten automáticamente subiendo la carga un poco cada semana), aquí **tú decides la progresión real, semana por semana**. Escribe cada semana como una fila distinta con su propia carga/reps/RIR. Esto te permite:

- Progresión lineal (subir carga cada semana, como en el ejemplo adjunto).
- Ondulación (alternar semanas pesadas/ligeras).
- Semana de descarga (`deload`): baja series, sube RIR, baja carga ~20-30% — mira la semana 4 del ejemplo adjunto.
- Cambiar reps/ejercicios entre bloques o mesociclos dentro del mismo programa.

### Reglas de progresión (OBLIGATORIAS)

«Cada semana con su propia prescripción» **no basta**: un macrociclo que repite las mismas series y las mismas reps mes tras mes (solo con la
onda de RIR) no es una programación. Un programa que incumple estas reglas **no se entrega**. Versión completa, con el porqué y un validador:
`docs/AGENTE PROGRAMACION/REGLAS_DE_PROGRESION.md` y `validar_progresion.py` (repo `bsa`).

**1. Series — el volumen de cada grupo muscular SUBE mes a mes.** Las series semanales (semana 1) de cada grupo son mayores en cada mesociclo
que en el anterior: `M1 < M2 < M3 < M4 < M5` y `M6 >= M5`. Incremento típico: +1 serie semanal por grupo y mesociclo, repartido entre ejercicios
y sesiones. Ejemplo (espalda): 8 → 9 → 11 → 12 → 13 → 14. Mantener las mismas series todos los meses, o bajarlas en el último, es un error.
Excepción: el prehab de manguito/escápula sube despacio.

**2. Onda dentro del mesociclo y descarga real.** S1 = base; la semana más exigente = base **+1 serie** en los ejercicios de progresión; la última
semana es **descarga**: mitad de series, carga -25 %, RIR 4 y el rango de reps de la S1 (por debajo del 80 % de la semana pico).

**3. Repeticiones — unos BAJAN, otros SUBEN, y cambian cada semana.** Dentro de cada mesociclo el rango de reps **cambia semana a semana** en
todos los ejercicios de progresión (solo pueden ir fijos el prehab de hombro, el Face Pull, los gemelos y el core):

| Sentido | Ejemplo | Carga |
|---|---|---|
| **Baja** (compuesto de pierna) | 12-15 → 10-12 → 8-10 → 6-8 → 4-6 | sube ~5 % cada semana |
| **Baja** (tracción / empuje en máquina) | 12-15 → 10-12 → 8-10 (→ 6-8 tracción) | sube |
| **Sube** (compuesto) | 4-6 o 6-8 → 8-10 / 10-12 → 12-15 | se mantiene (o +2,5 %) |
| **Sube** (aislamiento) | 10-12 → 12-15 → 15-20 | se mantiene |

- **En cada sesión hay al menos un ejercicio que baja y otro que sube.**
- Un mismo ejercicio **alterna el sentido de un mesociclo al siguiente** (el que bajaba en M1 sube en M2).
- Con el paso de los mesociclos los compuestos se mueven a rangos más pesados (arranca en 12-15; hacia M5-M6 llega a 6-8 en tracción y 4-6 en pierna).
- Las restricciones del cliente mandan (p. ej. hombro inestable: empujes solo en máquina, **mínimo 8 reps y RIR ≥ 2**).
- Escribe en `notas` qué hace la carga: «Bajas a 10-12 reps: SUBE la carga ~5 %» o «Subes a 12-15 con la misma carga».

---

## Ejemplo mínimo (2 filas, ilustrativo)

| semana | dia | nombre_dia | es_descanso | notas_dia | bloque | instrucciones_bloque | ejercicio | equipo | series | reps | rir | rpe | carga_kg | carga_pct | descanso_seg | tempo | duracion_seg | notas | tecnica | tecnica_series |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | 1 | Empuje A | FALSE | | Parte principal | | Press banca barra | Barra | 4 | 6-8 | 2 | | 80 | | 150 | | | | | |
| 1 | 1 | Empuje A | FALSE | | Accesorio | | Elevaciones laterales | Mancuernas | 3 | 12-15 | 1 | | 10 | | 60 | | | | Rest-pause | última |

Para un ejemplo completo y real de 4 semanas × 5 sesiones, abre `excel.example.xlsx`.

---

## Checklist antes de devolver el archivo

- [ ] Hojas llamadas exactamente `Programa` y `Programación`.
- [ ] Cabeceras de la fila 1 sin modificar (mismos nombres, mismo orden si es posible).
- [ ] `semanas` en la hoja Programa coincide con el número de semanas distintas escritas en Programación.
- [ ] Todas las semanas pedidas están explícitas, cada una con su propia prescripción — no dejar que "se sobreentienda" la progresión.
- [ ] **Progresión de series:** cada grupo muscular hace más series semanales en cada mesociclo que en el anterior (M6 >= M5), y la última semana de cada mesociclo es una descarga real.
- [ ] **Progresión de reps:** ningún ejercicio de progresión repite el mismo rango de reps las semanas de carga; en cada sesión hay ejercicios que suben y otros que bajan.
- [ ] Si se entrega un macrociclo de varios mesociclos, se entrega **también el Excel de revisión del macrociclo completo** (dashboard, visión general, leyenda y una pestaña por mesociclo).
- [ ] Cada fila de ejercicio tiene `series` y `reps` rellenos.
- [ ] No se han escrito filas para los días de descanso (salvo que se quiera añadir una nota a ese día).
- [ ] `rir` o `rpe`, no ambos a la vez en el mismo ejercicio (si se rellenan los dos, no es un error, pero solo se usa `rpe`).
