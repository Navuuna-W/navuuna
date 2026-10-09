# Log lines as JSON, one object per line, for the signal service (work pack K-15, NFR-10).
# `python -m engine` installs this at start-up (cli.py). The field names match Laravel's
# Monolog JsonFormatter (datetime, level_name, channel, message, context), so the same `jq`
# filter reads the logs of both boxes.

import json
import logging
from datetime import UTC, datetime
from typing import Any

# Attributes every LogRecord has. Anything else on a record came from `extra=` and goes into
# "context", like the context array of a Laravel log call.
STANDARD_RECORD_ATTRIBUTES = frozenset(vars(logging.makeLogRecord({}))) | {"message", "asctime"}


class JsonFormatter(logging.Formatter):
    """Turns one log record into one line of JSON. Implements NFR-10 — structured logs."""

    def format(self, record: logging.LogRecord) -> str:
        """Return the record as JSON: time in UTC, level, logger name, message and context."""
        fields: dict[str, Any] = {
            "datetime": datetime.fromtimestamp(record.created, tz=UTC).isoformat(),
            "level_name": record.levelname,
            "channel": record.name,
            "message": record.getMessage(),
            "context": extra_fields_of(record),
        }
        if record.exc_info:
            fields["exception"] = self.formatException(record.exc_info)
        # default=str: a UUID or datetime passed in `extra=` is written as text, never dropped.
        return json.dumps(fields, default=str)


def extra_fields_of(record: logging.LogRecord) -> dict[str, Any]:
    """The fields a log call added with `extra=`, e.g. {"entity_id": ...}."""
    return {
        name: value
        for name, value in vars(record).items()
        if name not in STANDARD_RECORD_ATTRIBUTES
    }


def configure_logging() -> None:
    """Send every log record at INFO and above to stderr as JSON.

    Like logging.basicConfig, it does nothing when logging is already set up (e.g. by pytest).
    On Box B, Docker keeps stderr; its compose log options rotate it (K-07 / K-15b).
    """
    handler = logging.StreamHandler()
    handler.setFormatter(JsonFormatter())
    logging.basicConfig(level=logging.INFO, handlers=[handler])
