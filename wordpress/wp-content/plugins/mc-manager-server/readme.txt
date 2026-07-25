=== MC Manager Server ===
Contributors: optigrid
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 1.5.0
License: Proprietary

Dashboard Host extensible para administrar el servidor Minecraft de OptiGrid.

== Changelog ==

= 1.5.0 =
* Añade GatewayClient basado en la HTTP API de WordPress.
* Incorpora respuestas tipadas y excepciones normalizadas.
* Añade métodos para salud, servidor, mundos, gamerules y telemetría.
* Añade operaciones validadas para dificultad y modo de juego por defecto.
* Expone filtros públicos para URL base, argumentos HTTP y sustitución del cliente.

= 1.3.0 =
* Añade el módulo core.server.

= 1.6.0 =
* Conecta core.server en modo lectura con /server y /world/state.
* Añade ServerSnapshot y muestra dificultad, modo de juego, jugadores, mundos y chunks reales.
* Mantiene deshabilitadas todas las operaciones de escritura.

= 1.6.2 =
* core.server combina /server, /world/state y /telemetry/minecraft.
* Mundos y chunks se obtienen de paper chunkinfo a través de la telemetría existente.
* default_gamemode conserva su estado no disponible cuando RCON no permite read-back.

= 1.6.3 =
* E5.8.3.2: edición incremental de configuración activa.
* Dificultad con verificación RCON posterior.
* Modo de juego predeterminado mediante reconocimiento del comando.
* Payload contractual del Gateway: {"value":"..."}.
* Sin cambios en server.properties, sync_worker o ciclo de vida de PaperMC.

= 1.6.4 =
* E5.8.3.4: integración final de la escritura en la sección original.
* Eliminada la sección visual duplicada.
* Un único formulario aplica dificultad y modo de juego predeterminado.
* Conservados mundos cargados, chunks cargados y recarga de valores.

