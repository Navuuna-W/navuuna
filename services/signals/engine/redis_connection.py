# Opens the signal service's connection to Redis on Box A, which carries the
# signals.* streams (ADR-004a §3). The CLI opens it once per run.

from collections.abc import Mapping

from redis import Redis

REDIS_URL_VARIABLE = "NV_REDIS_URL"


class RedisSettingsError(Exception):
    """The environment does not say how to reach Redis."""


def connect_to_redis(environment: Mapping[str, str]) -> Redis:
    """Open a Redis connection from NV_REDIS_URL that returns strings, not bytes.

    Input: the process environment. Output: a Redis client.
    Raises RedisSettingsError when the variable is missing or empty.
    Implements ADR-004a §3 — streams live on the Box A Redis.
    """
    redis_url = environment.get(REDIS_URL_VARIABLE, "")
    if not redis_url:
        raise RedisSettingsError(f"set {REDIS_URL_VARIABLE}, e.g. redis://:password@host:6379/0")
    return Redis.from_url(redis_url, decode_responses=True)
