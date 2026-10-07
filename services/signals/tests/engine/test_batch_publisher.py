# Tests for publish_batch_written: the message matches the ADR-004a §3 contract exactly,
# because the Laravel rollup worker parses these fields by name.

import json
from datetime import datetime, timedelta, timezone
from uuid import UUID

import pytest
from redis import Redis

from engine.batch_publisher import BatchTooLargeError, publish_batch_written

BATCH_ID = UUID("0192a000-0000-7000-8000-000000000001")
ENTITY_ID = UUID("0192a000-0000-7000-8000-000000000002")
# 13:15 in Nairobi (UTC+3) is 10:15 UTC.
WRITTEN_AT_IN_NAIROBI = datetime(2026, 9, 29, 13, 15, tzinfo=timezone(timedelta(hours=3)))


def test_the_message_has_exactly_the_contract_fields(redis_client: Redis, test_stream: str) -> None:
    publish_batch_written(
        redis_client,
        BATCH_ID,
        [ENTITY_ID],
        "water",
        {"1.2": "1.0.0"},
        WRITTEN_AT_IN_NAIROBI,
        test_stream,
    )

    [(_, message)] = redis_client.xrange(test_stream)
    assert message == {
        "batch_id": str(BATCH_ID),
        "entity_ids": json.dumps([str(ENTITY_ID)]),
        "module": "water",
        "adapter_versions": json.dumps({"1.2": "1.0.0"}),
        "written_at": "2026-09-29T10:15:00Z",
    }


def test_more_than_500_entity_ids_are_refused(redis_client: Redis, test_stream: str) -> None:
    too_many_ids = [ENTITY_ID] * 501

    with pytest.raises(BatchTooLargeError, match="at most 500"):
        publish_batch_written(
            redis_client, BATCH_ID, too_many_ids, "water", {}, WRITTEN_AT_IN_NAIROBI, test_stream
        )

    assert redis_client.xlen(test_stream) == 0
