import json
import os
import re
import threading
import time
from pathlib import Path
from datetime import datetime, timezone

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from rcon.source import Client

app = FastAPI(title="Minecraft Gateway", version="0.8.0")

RCON_HOST = os.getenv("RCON_HOST", "minecraft")
RCON_PORT = int(os.getenv("RCON_PORT", "25575"))
RCON_PASSWORD = os.getenv("RCON_PASSWORD", "")

DATA_DIR = Path(os.getenv("DATA_DIR", "/app/data"))
GAMEMODE_FILE = DATA_DIR / "gamemodes.json"
TELEMETRY_DIR = Path(os.getenv("TELEMETRY_DIR", "/app/telemetry"))
WORKER_HEARTBEAT_FILE = TELEMETRY_DIR / "worker-heartbeat.json"
WORKER_STALE_SECONDS = int(os.getenv("WORKER_STALE_SECONDS", "35"))

ALLOWED_GAMEMODES = {"survival", "creative", "adventure", "spectator"}
USERNAME_RE = re.compile(r"^[A-Za-z0-9_]{3,16}$")

_last_applied = {}


class PlayerRequest(BaseModel):
    username: str = Field(pattern=r"^[A-Za-z0-9_]{3,16}$")


class PlayerReasonRequest(BaseModel):
    username: str = Field(pattern=r"^[A-Za-z0-9_]{3,16}$")
    reason: str = Field(default="Administrative action", min_length=1, max_length=160)


class GamemodeRequest(BaseModel):
    username: str = Field(pattern=r"^[A-Za-z0-9_]{3,16}$")
    gamemode: str = Field(pattern=r"^(survival|creative|adventure|spectator)$")


def run_rcon(command: str) -> str:
    if not RCON_PASSWORD:
        raise HTTPException(status_code=500, detail="RCON_PASSWORD not configured")

    try:
        with Client(RCON_HOST, RCON_PORT, passwd=RCON_PASSWORD) as client:
            return client.run(command)
    except Exception as exc:
        raise HTTPException(status_code=500, detail=str(exc))


def load_gamemodes() -> dict:
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    if not GAMEMODE_FILE.exists():
        return {}
    try:
        return json.loads(GAMEMODE_FILE.read_text())
    except Exception:
        return {}


def save_gamemodes(data: dict) -> None:
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    GAMEMODE_FILE.write_text(json.dumps(data, indent=2, sort_keys=True))


def online_players() -> list[str]:
    result = run_rcon("list")
    if ":" not in result:
        return []
    raw = result.split(":", 1)[1].strip()
    if not raw:
        return []
    return [p.strip() for p in raw.split(",") if p.strip()]


def watchdog_loop():
    while True:
        try:
            desired = load_gamemodes()
            online = online_players()
            online_lc = {p.lower(): p for p in online}

            for stored_name, mode in desired.items():
                if mode not in ALLOWED_GAMEMODES:
                    continue

                key = stored_name.lower()
                if key not in online_lc:
                    _last_applied.pop(key, None)
                    continue

                real_name = online_lc[key]
                cache_key = f"{key}:{mode}"

                if _last_applied.get(key) == cache_key:
                    continue

                result = run_rcon(f"gamemode {mode} {real_name}")
                print(f"[watchdog] gamemode {mode} -> {real_name}: {result}", flush=True)
                _last_applied[key] = cache_key

        except Exception as exc:
            print(f"[watchdog] error: {exc}", flush=True)

        time.sleep(10)


@app.on_event("startup")
def startup():
    t = threading.Thread(target=watchdog_loop, daemon=True)
    t.start()


def utc_now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def read_worker_heartbeat() -> dict:
    try:
        data = json.loads(WORKER_HEARTBEAT_FILE.read_text())
        heartbeat_ts = float(data.get("timestamp", 0))
        age_seconds = max(0, int(time.time() - heartbeat_ts))
        data["age_seconds"] = age_seconds
        data["online"] = age_seconds <= WORKER_STALE_SECONDS
        if not data["online"]:
            data["status"] = "stale"
        return data
    except FileNotFoundError:
        return {
            "online": False,
            "status": "unknown",
            "message": "Heartbeat del worker todavía no disponible",
        }
    except Exception as exc:
        return {
            "online": False,
            "status": "error",
            "message": str(exc),
        }


def parse_player_list(raw: str) -> tuple[int, int | None, list[str]]:
    match = re.search(r"There are\s+(\d+)\s+of a max of\s+(\d+)\s+players online", raw, re.I)
    current = int(match.group(1)) if match else 0
    maximum = int(match.group(2)) if match else None

    players = []
    if ":" in raw:
        tail = raw.split(":", 1)[1].strip()
        if tail:
            players = [name.strip() for name in tail.split(",") if name.strip()]
            if not match:
                current = len(players)

    return current, maximum, players


@app.get("/health")
def health():
    return {"status": "ok", "service": "minecraft-gateway", "version": "0.8.0"}


@app.get("/dashboard")
def dashboard():
    checked_at = utc_now_iso()
    started = time.perf_counter()

    try:
        raw = run_rcon("list")
        latency_ms = round((time.perf_counter() - started) * 1000, 1)
        current, maximum, players = parse_player_list(raw)

        minecraft = {
            "online": True,
            "status": "online",
            "players_online": current,
            "max_players": maximum,
            "players": players,
            "raw": raw,
        }
        rcon = {
            "online": True,
            "status": "connected",
            "latency_ms": latency_ms,
        }
    except HTTPException as exc:
        minecraft = {
            "online": False,
            "status": "offline",
            "players_online": 0,
            "max_players": None,
            "players": [],
            "message": str(exc.detail),
        }
        rcon = {
            "online": False,
            "status": "disconnected",
            "latency_ms": None,
            "message": str(exc.detail),
        }

    return {
        "checked_at": checked_at,
        "minecraft": minecraft,
        "rcon": rcon,
        "gateway": {
            "online": True,
            "status": "ok",
            "service": "minecraft-gateway",
            "version": "0.8.0",
        },
        "worker": read_worker_heartbeat(),
    }


@app.get("/whitelist/list")
def whitelist_list():
    return {"result": run_rcon("whitelist list")}


@app.post("/whitelist/add")
def whitelist_add(req: PlayerRequest):
    return {"username": req.username, "result": run_rcon(f"whitelist add {req.username}")}


@app.post("/whitelist/remove")
def whitelist_remove(req: PlayerRequest):
    return {"username": req.username, "result": run_rcon(f"whitelist remove {req.username}")}


@app.get("/whitelist/exists/{username}")
def whitelist_exists(username: str):
    if not USERNAME_RE.match(username):
        raise HTTPException(status_code=400, detail="Invalid username")

    result = run_rcon("whitelist list")
    return {
        "username": username,
        "exists": username.lower() in result.lower(),
        "raw": result,
    }


@app.get("/player/list")
def player_list():
    return {"players": online_players()}


@app.get("/player/banlist")
def player_banlist():
    return {"result": run_rcon("banlist players")}


@app.get("/player/ban/exists/{username}")
def player_ban_exists(username: str):
    if not USERNAME_RE.match(username):
        raise HTTPException(status_code=400, detail="Invalid username")
    result = run_rcon("banlist players")
    return {"username": username, "exists": username.lower() in result.lower(), "raw": result}


@app.post("/player/ban")
def player_ban(req: PlayerReasonRequest):
    reason = " ".join(req.reason.split())
    return {
        "username": req.username,
        "action": "ban",
        "result": run_rcon(f"ban {req.username} {reason}"),
    }


@app.post("/player/pardon")
def player_pardon(req: PlayerRequest):
    return {
        "username": req.username,
        "action": "pardon",
        "result": run_rcon(f"pardon {req.username}"),
    }


@app.post("/player/kick")
def player_kick(req: PlayerReasonRequest):
    reason = " ".join(req.reason.split())
    return {
        "username": req.username,
        "action": "kick",
        "result": run_rcon(f"kick {req.username} {reason}"),
    }


@app.get("/player/gamemode")
def gamemode_list():
    return {"gamemodes": load_gamemodes()}


@app.get("/player/gamemode/{username}")
def gamemode_get(username: str):
    if not USERNAME_RE.match(username):
        raise HTTPException(status_code=400, detail="Invalid username")

    data = load_gamemodes()
    return {
        "username": username,
        "gamemode": data.get(username),
    }


@app.post("/player/gamemode")
def gamemode_set(req: GamemodeRequest):
    data = load_gamemodes()
    data[req.username] = req.gamemode
    save_gamemodes(data)

    online = [p.lower() for p in online_players()]
    applied_now = False
    result = "queued"

    if req.username.lower() in online:
        result = run_rcon(f"gamemode {req.gamemode} {req.username}")
        applied_now = True

    return {
        "username": req.username,
        "gamemode": req.gamemode,
        "applied_now": applied_now,
        "result": result,
    }


@app.delete("/player/gamemode/{username}")
def gamemode_delete(username: str):
    if not USERNAME_RE.match(username):
        raise HTTPException(status_code=400, detail="Invalid username")

    data = load_gamemodes()
    removed = data.pop(username, None)
    save_gamemodes(data)

    return {
        "username": username,
        "removed": removed is not None,
    }

# BEGIN OPTIGRID E5.2.2 SERVER STATE

ALLOWED_DIFFICULTIES = {"peaceful", "easy", "normal", "hard"}
ALLOWED_DEFAULT_GAMEMODES = {"survival", "creative", "adventure", "spectator"}


class ServerDifficultyRequest(BaseModel):
    value: str = Field(pattern=r"^(peaceful|easy|normal|hard)$")


class ServerDefaultGamemodeRequest(BaseModel):
    value: str = Field(pattern=r"^(survival|creative|adventure|spectator)$")


def _strip_mc_formatting(value: str) -> str:
    return re.sub(r"§.", "", value or "").strip()


def _extract_first(patterns: list[str], raw: str):
    clean = _strip_mc_formatting(raw)
    for pattern in patterns:
        match = re.search(pattern, clean, flags=re.IGNORECASE)
        if match:
            return match.group(1).strip().lower()
    return None


def _parse_list_status(raw: str) -> dict:
    clean = _strip_mc_formatting(raw)
    match = re.search(
        r"There are\s+(\d+)\s+of a max of\s+(\d+)\s+players online:\s*(.*)$",
        clean,
        flags=re.IGNORECASE,
    )
    if not match:
        return {
            "players_online": None,
            "max_players": None,
            "players": [],
            "raw": raw,
        }

    players = [p.strip() for p in match.group(3).split(",") if p.strip()]
    return {
        "players_online": int(match.group(1)),
        "max_players": int(match.group(2)),
        "players": players,
        "raw": raw,
    }


def _parse_difficulty(raw: str):
    return _extract_first(
        [
            r"difficulty(?:\s+is|\s*:)?\s*['\"]?([a-z_]+)",
            r"current difficulty(?:\s+is|\s*:)?\s*['\"]?([a-z_]+)",
        ],
        raw,
    )


def _command_failed(raw: str) -> bool:
    clean = _strip_mc_formatting(raw).lower()
    markers = (
        "unknown or incomplete command",
        "incorrect argument",
        "no such command",
        "<--[here]",
    )
    return any(marker in clean for marker in markers)


def _read_version_with_retry() -> dict:
    import time

    last_raw = None
    for attempt in range(4):
        try:
            raw = run_rcon("version")
            last_raw = raw
        except HTTPException as exc:
            return {
                "minecraft_version": None,
                "paper_version": None,
                "raw": None,
                "error": str(exc.detail),
            }

        clean = _strip_mc_formatting(raw)
        if "checking version" not in clean.lower():
            minecraft = None
            paper = None

            match = re.search(
                r"Minecraft(?:\s+server)?(?:\s+version)?\s+([0-9][0-9A-Za-z._+\-]*)",
                clean,
                flags=re.IGNORECASE,
            )
            if match:
                minecraft = match.group(1)

            match = re.search(
                r"(?:Paper|Purpur|Folia)[^\n]*?(?:version|git-)([0-9A-Za-z._+\-]+)",
                clean,
                flags=re.IGNORECASE,
            )
            if match:
                paper = match.group(1)

            return {
                "minecraft_version": minecraft,
                "paper_version": paper,
                "raw": raw,
                "error": None,
            }

        if attempt < 3:
            time.sleep(1)

    return {
        "minecraft_version": None,
        "paper_version": None,
        "raw": last_raw,
        "error": "Paper no completó la consulta de versión dentro del tiempo de espera",
    }


@app.get("/server")
def server_state():
    checked_at = __import__("datetime").datetime.now(
        __import__("datetime").timezone.utc
    ).isoformat()

    errors = []

    try:
        capacity = _parse_list_status(run_rcon("list"))
        online = True
    except HTTPException as exc:
        capacity = {
            "players_online": None,
            "max_players": None,
            "players": [],
            "raw": None,
        }
        online = False
        errors.append({"component": "capacity", "message": str(exc.detail)})

    try:
        difficulty_raw = run_rcon("difficulty")
        difficulty_value = _parse_difficulty(difficulty_raw)
        difficulty = {
            "value": difficulty_value,
            "available": difficulty_value is not None,
            "source": "rcon",
            "raw": difficulty_raw,
            "error": None if difficulty_value is not None
            else "Respuesta RCON no reconocida",
        }
    except HTTPException as exc:
        difficulty = {
            "value": None,
            "available": False,
            "source": "rcon",
            "raw": None,
            "error": str(exc.detail),
        }

    version = _read_version_with_retry()

    if not difficulty["available"]:
        errors.append({"component": "difficulty", "message": difficulty["error"]})
    if version["error"]:
        errors.append({"component": "version", "message": version["error"]})

    return {
        "checked_at": checked_at,
        "status": "online" if online else "offline",
        "online": online,
        "runtime": {
            "difficulty": difficulty,
            "default_gamemode": {
                "value": None,
                "available": False,
                "source": "database_sync",
                "raw": None,
                "error": "RCON permite escribir defaultgamemode, pero no consultarlo",
            },
            "minecraft_version": {
                "value": version["minecraft_version"],
                "available": version["minecraft_version"] is not None,
                "source": "rcon",
                "raw": version["raw"],
                "error": version["error"],
            },
            "paper_version": {
                "value": version["paper_version"],
                "available": version["paper_version"] is not None,
                "source": "rcon",
                "raw": version["raw"],
                "error": version["error"],
            },
        },
        "capacity": capacity,
        "capabilities": {
            "difficulty": {
                "read": True,
                "write": True,
                "hot_apply": True,
                "restart_required": False,
                "verification": "rcon_readback",
            },
            "default_gamemode": {
                "read": False,
                "write": True,
                "hot_apply": True,
                "restart_required": False,
                "verification": "command_acknowledgement",
            },
            "pvp": {
                "read": False,
                "write": False,
                "hot_apply": False,
                "restart_required": None,
                "reason": "Sin operación RCON estándar dentro de la frontera acordada",
            },
        },
        "errors": errors,
    }


@app.post("/server/difficulty")
def server_set_difficulty(req: ServerDifficultyRequest):
    value = req.value.lower()
    result = run_rcon(f"difficulty {value}")

    if _command_failed(result):
        raise HTTPException(
            status_code=409,
            detail={
                "message": "Minecraft rechazó el cambio de dificultad",
                "requested": value,
                "result": result,
            },
        )

    verification_raw = run_rcon("difficulty")
    applied = _parse_difficulty(verification_raw)

    if applied != value:
        raise HTTPException(
            status_code=409,
            detail={
                "message": "La verificación RCON no coincide",
                "requested": value,
                "observed": applied,
                "result": result,
                "verification_raw": verification_raw,
            },
        )

    return {
        "setting_key": "difficulty",
        "requested_value": value,
        "applied_value": applied,
        "accepted": True,
        "verified": True,
        "verification": "rcon_readback",
        "source": "rcon",
        "result": result,
        "verification_raw": verification_raw,
    }


@app.post("/server/default-gamemode")
def server_set_default_gamemode(req: ServerDefaultGamemodeRequest):
    value = req.value.lower()
    result = run_rcon(f"defaultgamemode {value}")

    if _command_failed(result):
        raise HTTPException(
            status_code=409,
            detail={
                "message": "Minecraft rechazó el gamemode predeterminado",
                "requested": value,
                "result": result,
            },
        )

    return {
        "setting_key": "default_gamemode",
        "requested_value": value,
        "applied_value": value,
        "accepted": True,
        "verified": False,
        "verification": "command_acknowledgement",
        "source": "rcon",
        "result": result,
        "message": "Comando aceptado; la BBDD conservará el último estado aplicado conocido.",
    }

# END OPTIGRID E5.2.2 SERVER STATE

# BEGIN OPTIGRID E5.3.2 WORLD STATE

class WorldTimeState(BaseModel):
    value: int | None = None
    available: bool
    source: str = "rcon"
    raw: str | None = None
    error: str | None = None


class WeatherState(BaseModel):
    value: str | None = None
    available: bool
    source: str = "rcon"
    raw: str | None = None
    error: str | None = None


class WorldBorderState(BaseModel):
    size: int | None = None
    available: bool
    source: str = "rcon"
    raw: str | None = None
    error: str | None = None


class SpawnState(BaseModel):
    x: int | None = None
    y: int | None = None
    z: int | None = None
    available: bool
    source: str = "rcon"
    raw: str | None = None
    error: str | None = None


class AutosaveState(BaseModel):
    value: bool | None = None
    available: bool
    source: str = "rcon"
    raw: str | None = None
    error: str | None = None


class WorldStatePayload(BaseModel):
    time: WorldTimeState
    weather: WeatherState
    world_border: WorldBorderState
    spawn: SpawnState
    autosave: AutosaveState


class WorldStateError(BaseModel):
    component: str
    message: str


class WorldStateResponse(BaseModel):
    status: str
    checked_at: str
    world: WorldStatePayload
    errors: list[WorldStateError]


def _read_world_time() -> dict:
    try:
        raw = run_rcon("time query daytime")
    except HTTPException as exc:
        return {"value": None, "available": False, "source": "rcon", "raw": None, "error": str(exc.detail)}

    clean = _strip_mc_formatting(raw)
    match = re.search(r"\btime\s+is\s+(\d+)\b", clean, flags=re.IGNORECASE)
    if not match:
        return {"value": None, "available": False, "source": "rcon", "raw": raw, "error": "Respuesta RCON de hora no reconocida"}

    return {"value": int(match.group(1)), "available": True, "source": "rcon", "raw": raw, "error": None}


def _read_world_border() -> dict:
    try:
        raw = run_rcon("worldborder get")
    except HTTPException as exc:
        return {"size": None, "available": False, "source": "rcon", "raw": None, "error": str(exc.detail)}

    clean = _strip_mc_formatting(raw)
    match = re.search(
        r"\bworld\s+border\s+is\s+currently\s+([0-9]+(?:\.[0-9]+)?)\s+block",
        clean,
        flags=re.IGNORECASE,
    )
    if not match:
        return {"size": None, "available": False, "source": "rcon", "raw": raw, "error": "Respuesta RCON de world border no reconocida"}

    return {"size": int(float(match.group(1))), "available": True, "source": "rcon", "raw": raw, "error": None}


def _unsupported_weather_state() -> dict:
    return {
        "value": None,
        "available": False,
        "source": "rcon",
        "raw": None,
        "error": "Weather state is not readable through the standard RCON command set",
    }


def _unsupported_spawn_state() -> dict:
    return {
        "x": None, "y": None, "z": None,
        "available": False,
        "source": "rcon",
        "raw": None,
        "error": "Global spawn is not readable through the standard RCON command set",
    }


def _unsupported_autosave_state() -> dict:
    return {
        "value": None,
        "available": False,
        "source": "rcon",
        "raw": None,
        "error": "Autosave state cannot be queried without issuing a state-changing command",
    }


@app.get("/world/state", response_model=WorldStateResponse)
def world_state():
    checked_at = __import__("datetime").datetime.now(
        __import__("datetime").timezone.utc
    ).isoformat()

    world_time = _read_world_time()
    world_border = _read_world_border()
    weather = _unsupported_weather_state()
    spawn = _unsupported_spawn_state()
    autosave = _unsupported_autosave_state()

    errors = []
    if not world_time["available"]:
        errors.append({"component": "time", "message": world_time["error"] or "Hora no disponible"})
    if not world_border["available"]:
        errors.append({"component": "world_border", "message": world_border["error"] or "World border no disponible"})

    online = world_time["available"] or world_border["available"]

    return {
        "status": "online" if online else "offline",
        "checked_at": checked_at,
        "world": {
            "time": world_time,
            "weather": weather,
            "world_border": world_border,
            "spawn": spawn,
            "autosave": autosave,
        },
        "errors": errors,
    }

# END OPTIGRID E5.3.2 WORLD STATE

# BEGIN OPTIGRID E5.4.2 GAMERULES

GAMERULE_SPECS: dict[str, str] = {
    "keepInventory": "boolean",
    "doDaylightCycle": "boolean",
    "doWeatherCycle": "boolean",
    "doMobSpawning": "boolean",
    "mobGriefing": "boolean",
    "doFireTick": "boolean",
    "playersSleepingPercentage": "integer",
    "randomTickSpeed": "integer",
    "spawnRadius": "integer",
    "announceAdvancements": "boolean",
    "doImmediateRespawn": "boolean",
    "showDeathMessages": "boolean",
}


class GameruleState(BaseModel):
    value: bool | int | None = None
    type: str
    available: bool
    source: str = "rcon"
    raw: str | None = None
    error: str | None = None


class GamerulesError(BaseModel):
    gamerule: str
    message: str


class GamerulesResponse(BaseModel):
    status: str
    checked_at: str
    gamerules: dict[str, GameruleState]
    errors: list[GamerulesError]


def _build_unavailable_state(value_type: str, error: str, raw: str | None = None) -> dict:
    return {
        "value": None,
        "type": value_type,
        "available": False,
        "source": "rcon",
        "raw": raw,
        "error": error,
    }


def _run_rcon_read(command: str) -> tuple[str | None, str | None]:
    try:
        raw = run_rcon(command)
    except HTTPException as exc:
        return None, str(exc.detail)
    except Exception as exc:
        return None, f"RCON read failed: {exc}"

    if raw is None:
        return None, "RCON returned no response"

    return str(raw), None


def _parse_boolean_rule(raw: str) -> bool | None:
    clean = _strip_mc_formatting(raw)
    match = re.search(
        r"\bis\s+currently\s+set\s+to:\s*(true|false)\b",
        clean,
        flags=re.IGNORECASE,
    )
    return None if not match else match.group(1).lower() == "true"


def _parse_integer_rule(raw: str) -> int | None:
    clean = _strip_mc_formatting(raw)
    match = re.search(
        r"\bis\s+currently\s+set\s+to:\s*(-?\d+)\b",
        clean,
        flags=re.IGNORECASE,
    )
    return None if not match else int(match.group(1))


def _read_gamerule(name: str, value_type: str) -> dict:
    raw, error = _run_rcon_read(f"gamerule {name}")

    if error is not None:
        return _build_unavailable_state(value_type, error)

    assert raw is not None
    clean = _strip_mc_formatting(raw)

    if re.search(
        r"incorrect argument|unknown command|unknown gamerule|no such gamerule|error",
        clean,
        flags=re.IGNORECASE,
    ):
        return _build_unavailable_state(
            value_type,
            "Minecraft rejected the gamerule query",
            raw,
        )

    if value_type == "boolean":
        value = _parse_boolean_rule(raw)
    elif value_type == "integer":
        value = _parse_integer_rule(raw)
    else:
        return _build_unavailable_state(
            value_type,
            f"Unsupported gamerule type: {value_type}",
            raw,
        )

    if value is None:
        return _build_unavailable_state(
            value_type,
            "Unrecognized gamerule response format",
            raw,
        )

    return {
        "value": value,
        "type": value_type,
        "available": True,
        "source": "rcon",
        "raw": raw,
        "error": None,
    }


@app.get("/gamerules", response_model=GamerulesResponse)
def gamerules():
    checked_at = __import__("datetime").datetime.now(
        __import__("datetime").timezone.utc
    ).isoformat()

    states: dict[str, dict] = {}
    errors: list[dict] = []

    for name, value_type in GAMERULE_SPECS.items():
        state = _read_gamerule(name, value_type)
        states[name] = state

        if not state["available"]:
            errors.append({
                "gamerule": name,
                "message": state["error"] or "Gamerule unavailable",
            })

    available_count = sum(1 for state in states.values() if state["available"])

    if available_count == len(states):
        status = "online"
    elif available_count > 0:
        status = "degraded"
    else:
        status = "offline"

    return {
        "status": status,
        "checked_at": checked_at,
        "gamerules": states,
        "errors": errors,
    }

# END OPTIGRID E5.4.2 GAMERULES

# BEGIN OPTIGRID E5.5.3 TELEMETRY

class TelemetryError(BaseModel):
    component: str
    command: str | None = None
    message: str


class TpsTelemetry(BaseModel):
    one_minute: float | None = None
    five_minutes: float | None = None
    fifteen_minutes: float | None = None
    available: bool
    source: str = "rcon"
    raw: str | None = None
    error: str | None = None


class MsptWindow(BaseModel):
    average: float
    minimum: float
    maximum: float


class MsptTelemetry(BaseModel):
    five_seconds: MsptWindow | None = None
    ten_seconds: MsptWindow | None = None
    one_minute: MsptWindow | None = None
    available: bool
    source: str = "rcon"
    raw: str | None = None
    error: str | None = None


class PerformanceTelemetry(BaseModel):
    tps: TpsTelemetry
    mspt: MsptTelemetry


class PlayersTelemetry(BaseModel):
    online: int | None = None
    maximum: int | None = None
    names: list[str]
    available: bool
    source: str = "rcon"
    raw: str | None = None
    error: str | None = None


class ChunkMetrics(BaseModel):
    total: int
    inactive: int
    full: int
    block_ticking: int
    entity_ticking: int


class ChunksTelemetry(BaseModel):
    available: bool
    source: str = "rcon"
    raw: str | None = None
    error: str | None = None
    worlds: dict[str, ChunkMetrics]
    total: ChunkMetrics | None = None


class RconTelemetry(BaseModel):
    latency_ms: float | None = None
    available: bool
    source: str = "gateway"
    error: str | None = None


class MinecraftTelemetryResponse(BaseModel):
    status: str
    checked_at: str
    performance: PerformanceTelemetry
    players: PlayersTelemetry
    chunks: ChunksTelemetry
    rcon: RconTelemetry
    errors: list[TelemetryError]


def _telemetry_error(component: str, command: str | None, message: str) -> dict:
    return {"component": component, "command": command, "message": message}


def _unavailable_tps(error: str, raw: str | None = None) -> dict:
    return {
        "one_minute": None,
        "five_minutes": None,
        "fifteen_minutes": None,
        "available": False,
        "source": "rcon",
        "raw": raw,
        "error": error,
    }


def _unavailable_mspt(error: str, raw: str | None = None) -> dict:
    return {
        "five_seconds": None,
        "ten_seconds": None,
        "one_minute": None,
        "available": False,
        "source": "rcon",
        "raw": raw,
        "error": error,
    }


def _unavailable_players(error: str, raw: str | None = None) -> dict:
    return {
        "online": None,
        "maximum": None,
        "names": [],
        "available": False,
        "source": "rcon",
        "raw": raw,
        "error": error,
    }


def _unavailable_chunks(error: str, raw: str | None = None) -> dict:
    return {
        "available": False,
        "source": "rcon",
        "raw": raw,
        "error": error,
        "worlds": {},
        "total": None,
    }


def _parse_tps_response(raw: str) -> dict | None:
    clean = _strip_mc_formatting(raw)
    match = re.search(
        r"TPS from last\s+1m,\s*5m,\s*15m:\s*"
        r"([0-9]+(?:\.[0-9]+)?),\s*"
        r"([0-9]+(?:\.[0-9]+)?),\s*"
        r"([0-9]+(?:\.[0-9]+)?)",
        clean,
        flags=re.IGNORECASE,
    )
    if not match:
        return None

    one, five, fifteen = (float(value) for value in match.groups())
    return {
        "one_minute": one,
        "five_minutes": five,
        "fifteen_minutes": fifteen,
        "available": True,
        "source": "rcon",
        "raw": raw,
        "error": None,
    }


def _parse_mspt_response(raw: str) -> dict | None:
    clean = _strip_mc_formatting(raw)
    triples = re.findall(
        r"([0-9]+(?:\.[0-9]+)?)/"
        r"([0-9]+(?:\.[0-9]+)?)/"
        r"([0-9]+(?:\.[0-9]+)?)",
        clean,
    )
    if len(triples) != 3:
        return None

    windows = [
        {
            "average": float(avg),
            "minimum": float(minimum),
            "maximum": float(maximum),
        }
        for avg, minimum, maximum in triples
    ]

    return {
        "five_seconds": windows[0],
        "ten_seconds": windows[1],
        "one_minute": windows[2],
        "available": True,
        "source": "rcon",
        "raw": raw,
        "error": None,
    }


def _parse_players_response(raw: str) -> dict | None:
    clean = _strip_mc_formatting(raw)
    match = re.search(
        r"There are\s+(\d+)\s+of a max of\s+(\d+)\s+"
        r"players online:\s*(.*)$",
        clean,
        flags=re.IGNORECASE | re.DOTALL,
    )
    if not match:
        return None

    names_raw = match.group(3).strip()
    names = [name.strip() for name in names_raw.split(",") if name.strip()]

    return {
        "online": int(match.group(1)),
        "maximum": int(match.group(2)),
        "names": names,
        "available": True,
        "source": "rcon",
        "raw": raw,
        "error": None,
    }


def _parse_chunks_response(raw: str) -> dict | None:
    clean = _strip_mc_formatting(raw)
    pattern = re.compile(
        r"Chunks in\s+(.+?):\s*"
        r"Total:\s*(\d+)\s+"
        r"Inactive:\s*(\d+)\s+"
        r"Full:\s*(\d+)\s+"
        r"Block Ticking:\s*(\d+)\s+"
        r"Entity Ticking:\s*(\d+)",
        flags=re.IGNORECASE,
    )
    matches = pattern.findall(clean)
    if not matches:
        return None

    worlds: dict[str, dict] = {}
    aggregate: dict | None = None

    for name, total, inactive, full, block_ticking, entity_ticking in matches:
        metrics = {
            "total": int(total),
            "inactive": int(inactive),
            "full": int(full),
            "block_ticking": int(block_ticking),
            "entity_ticking": int(entity_ticking),
        }
        normalized_name = name.strip()
        if normalized_name.lower() == "all listed worlds":
            aggregate = metrics
        else:
            worlds[normalized_name] = metrics

    if not worlds:
        return None

    return {
        "available": True,
        "source": "rcon",
        "raw": raw,
        "error": None,
        "worlds": worlds,
        "total": aggregate,
    }


def _read_tps_telemetry() -> tuple[dict, dict | None]:
    raw, error = _run_rcon_read("tps")
    if error is not None:
        return _unavailable_tps(error), _telemetry_error("tps", "tps", error)

    assert raw is not None
    parsed = _parse_tps_response(raw)
    if parsed is None:
        message = "Unrecognized TPS response format"
        return _unavailable_tps(message, raw), _telemetry_error("tps", "tps", message)

    return parsed, None


def _read_mspt_telemetry() -> tuple[dict, dict | None]:
    raw, error = _run_rcon_read("mspt")
    if error is not None:
        return _unavailable_mspt(error), _telemetry_error("mspt", "mspt", error)

    assert raw is not None
    parsed = _parse_mspt_response(raw)
    if parsed is None:
        message = "Unrecognized MSPT response format"
        return _unavailable_mspt(message, raw), _telemetry_error("mspt", "mspt", message)

    return parsed, None


def _read_players_telemetry() -> tuple[dict, dict | None, dict]:
    perf_counter = __import__("time").perf_counter
    started = perf_counter()
    raw, error = _run_rcon_read("list")
    elapsed_ms = round((perf_counter() - started) * 1000.0, 2)

    if error is not None:
        return (
            _unavailable_players(error),
            _telemetry_error("players", "list", error),
            {
                "latency_ms": None,
                "available": False,
                "source": "gateway",
                "error": error,
            },
        )

    assert raw is not None
    parsed = _parse_players_response(raw)
    rcon = {
        "latency_ms": elapsed_ms,
        "available": True,
        "source": "gateway",
        "error": None,
    }

    if parsed is None:
        message = "Unrecognized players response format"
        return (
            _unavailable_players(message, raw),
            _telemetry_error("players", "list", message),
            rcon,
        )

    return parsed, None, rcon


def _read_chunks_telemetry() -> tuple[dict, dict | None]:
    raw, error = _run_rcon_read("paper chunkinfo")
    if error is not None:
        return (
            _unavailable_chunks(error),
            _telemetry_error("chunks", "paper chunkinfo", error),
        )

    assert raw is not None
    parsed = _parse_chunks_response(raw)
    if parsed is None:
        message = "Unrecognized chunks response format"
        return (
            _unavailable_chunks(message, raw),
            _telemetry_error("chunks", "paper chunkinfo", message),
        )

    return parsed, None


@app.get("/telemetry/minecraft", response_model=MinecraftTelemetryResponse)
def minecraft_telemetry():
    checked_at = __import__("datetime").datetime.now(
        __import__("datetime").timezone.utc
    ).isoformat()

    errors: list[dict] = []

    tps, tps_error = _read_tps_telemetry()
    mspt, mspt_error = _read_mspt_telemetry()
    players, players_error, rcon = _read_players_telemetry()
    chunks, chunks_error = _read_chunks_telemetry()

    for error in (tps_error, mspt_error, players_error, chunks_error):
        if error is not None:
            errors.append(error)

    if not rcon["available"]:
        errors.append(
            _telemetry_error(
                "rcon",
                "list",
                rcon["error"] or "RCON latency unavailable",
            )
        )

    availability = [
        tps["available"],
        mspt["available"],
        players["available"],
        chunks["available"],
        rcon["available"],
    ]

    if all(availability):
        status = "online"
    elif any(availability):
        status = "degraded"
    else:
        status = "offline"

    return {
        "status": status,
        "checked_at": checked_at,
        "performance": {"tps": tps, "mspt": mspt},
        "players": players,
        "chunks": chunks,
        "rcon": rcon,
        "errors": errors,
    }

# END OPTIGRID E5.5.3 TELEMETRY

