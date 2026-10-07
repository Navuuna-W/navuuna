# Helpers for tests that use the database_connection fixture (tests/conftest.py).

from engine.database import DatabaseConnection

# Points at a PostgreSQL with the Laravel migrations applied; unset means skip database tests.
TEST_DATABASE_URL_VARIABLE = "NV_TEST_DATABASE_URL"
SIGNAL_SERVICE_ROLE = "nv_signals"


def act_as_signal_service(connection: DatabaseConnection) -> None:
    """Switch the rest of the test's transaction to nv_signals, so its GRANTs are what count.

    Create prerequisite rows as the owner first: after this, only nv_signals writes work.
    """
    connection.execute(f"SET LOCAL ROLE {SIGNAL_SERVICE_ROLE}")
