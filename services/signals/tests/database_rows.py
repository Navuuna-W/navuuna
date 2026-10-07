# Small helpers that insert the rows a database test needs, as the database owner.
# Call them before act_as_signal_service: nv_signals may not write these tables (ADR-004a).
# Each one fills only the NOT NULL columns, with values that pass the tables' CHECKs.

from datetime import datetime
from uuid import UUID, uuid4

from psycopg.types.json import Jsonb

from engine.database import DatabaseConnection

POINT_IN_NAIROBI = "SRID=4326;POINT(36.8219 -1.2921)"
WATER_POINT_RADIUS_M = 50


def insert_returning_id(
    connection: DatabaseConnection, query: str, values: dict[str, object]
) -> UUID:
    found = connection.execute(query, values).fetchone()
    assert found is not None
    new_id: UUID = found["id"]
    return new_id


def create_source(connection: DatabaseConnection) -> UUID:
    return insert_returning_id(
        connection,
        """INSERT INTO core.sources (name, kind, licence, attribution)
           VALUES (%(name)s, 'vector', 'ODbL', 'Test') RETURNING id""",
        # Source names are unique (core_sources_name_unique).
        {"name": f"Test source {uuid4()}"},
    )


def create_point_entity(
    connection: DatabaseConnection,
    external_ref: str,
    retired_at: datetime | None = None,
    module: str = "water",
) -> UUID:
    return insert_returning_id(
        connection,
        """INSERT INTO core.entities
               (entity_type, module, name, external_ref, geom, radius_m, retired_at)
           VALUES ('point', %(module)s, 'Test tap', %(external_ref)s, %(geom)s, %(radius_m)s,
                   %(retired_at)s) RETURNING id""",
        {
            "external_ref": external_ref,
            "geom": POINT_IN_NAIROBI,
            "radius_m": WATER_POINT_RADIUS_M,
            "retired_at": retired_at,
            "module": module,
        },
    )


def create_consent(connection: DatabaseConnection, withdrawn_at: datetime | None) -> UUID:
    return insert_returning_id(
        connection,
        """INSERT INTO core.consents (contributor_id, scope, text_version, granted_at, withdrawn_at)
           VALUES (gen_random_uuid(), 'observation', 'v1', '2026-01-01T00:00:00Z', %(withdrawn_at)s)
           RETURNING id""",
        {"withdrawn_at": withdrawn_at},
    )


def create_observation(
    connection: DatabaseConnection,
    entity_id: UUID,
    observed_at: datetime,
    consent_id: UUID | None = None,
) -> UUID:
    # A contributor needs a consent (observations_contributor_has_consent_check).
    return insert_returning_id(
        connection,
        """INSERT INTO core.observations
               (entity_id, source_id, kind, payload, observed_at, contributor_id, consent_id)
           VALUES (%(entity_id)s, %(source_id)s, 'community', %(payload)s, %(observed_at)s,
                   %(contributor_id)s, %(consent_id)s)
           RETURNING id""",
        {
            "entity_id": entity_id,
            "source_id": create_source(connection),
            "payload": Jsonb({"state": "operational"}),
            "observed_at": observed_at,
            "contributor_id": uuid4() if consent_id else None,
            "consent_id": consent_id,
        },
    )


def create_text_block(connection: DatabaseConnection) -> dict[str, UUID]:
    """Every record must point at the document and text block it was read from (Bible §7.2)."""
    document_id = insert_returning_id(
        connection,
        """INSERT INTO raw.documents (source_id, url, md5, storage_path, mime, http_status)
           VALUES (%(source_id)s, %(url)s, md5(%(url)s),
                   'documents/report.pdf', 'application/pdf', 200) RETURNING id""",
        # url + md5 is unique (raw_documents_url_md5_unique).
        {"source_id": create_source(connection), "url": f"https://example.org/{uuid4()}.pdf"},
    )
    block_id = insert_returning_id(
        connection,
        """INSERT INTO raw.text_blocks (document_id, page, block_index, text)
           VALUES (%(document_id)s, 1, 0, 'Scheme table') RETURNING id""",
        {"document_id": document_id},
    )
    return {"document_id": document_id, "block_id": block_id}


def create_water_scheme(connection: DatabaseConnection, entity_id: UUID) -> UUID:
    return insert_returning_id(
        connection,
        """INSERT INTO records.water_schemes
               (entity_id, name, document_id, block_id, aligner_version, alignment_confidence)
           VALUES (%(entity_id)s, 'Test scheme', %(document_id)s, %(block_id)s, '1.0.0', 0.9)
           RETURNING id""",
        {"entity_id": entity_id, **create_text_block(connection)},
    )


def create_road_contract(connection: DatabaseConnection, entity_id: UUID) -> UUID:
    return insert_returning_id(
        connection,
        """INSERT INTO records.road_contracts
               (entity_id, document_id, block_id, aligner_version, alignment_confidence)
           VALUES (%(entity_id)s, %(document_id)s, %(block_id)s, '1.0.0', 0.9)
           RETURNING id""",
        {"entity_id": entity_id, **create_text_block(connection)},
    )
