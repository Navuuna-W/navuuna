# Tests for the MODULES_ENABLED switch (ADR-012 §3, Bible §14.6).

from engine.module_flags import is_module_enabled


def test_every_module_is_enabled_when_the_variable_is_not_set() -> None:
    environment: dict[str, str] = {}

    is_enabled = is_module_enabled("water", environment)

    assert is_enabled


def test_a_listed_module_is_enabled() -> None:
    environment = {"MODULES_ENABLED": "water, roads"}

    is_enabled = is_module_enabled("roads", environment)

    assert is_enabled


def test_a_module_left_off_the_list_is_disabled() -> None:
    environment = {"MODULES_ENABLED": "water"}

    is_enabled = is_module_enabled("roads", environment)

    assert not is_enabled


def test_an_empty_value_disables_every_module() -> None:
    environment = {"MODULES_ENABLED": ""}

    is_enabled = is_module_enabled("water", environment)

    assert not is_enabled
