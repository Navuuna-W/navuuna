# The frozen list of sub-variable IDs every adapter, score and finding refers to (Bible §6.8).
# The adapter contract checks against this list, so no adapter can invent a new sub-variable.
# Adding or removing an ID needs an ADR approved by all three leads (Bible §6.8, §15).

# Bible §6.8 heads the table "The 28 sub-variables" but the table itself lists 29 IDs.
# We encode the table exactly as written; the count in the heading is raised with the team.
SUB_VARIABLE_NAMES: dict[str, str] = {
    # V1 — Activity & utilisation
    "1.1": "Presence",
    "1.2": "Operational state",
    "1.3": "Utilisation ratio",
    "1.4": "Continuity",
    "1.5": "Intensity",
    # V2 — Declared-vs-observed discrepancy
    "2.1": "Existence gap",
    "2.2": "Magnitude gap",
    "2.3": "Attribute gap",
    "2.4": "Status gap",
    "2.5": "Record staleness",
    "2.6": "Explanation state",
    # V3 — Growth momentum
    "3.1": "Direction",
    "3.2": "Rate",
    "3.3": "Consistency",
    "3.4": "Acceleration",
    "3.5": "Neighbourhood momentum",
    "3.6": "Headroom",
    # V4 — Resource security
    "4.1": "Availability",
    "4.2": "Reliability",
    "4.3": "Trend",
    "4.4": "Competition",
    "4.5": "Hazard exposure",
    "4.6": "Buffer",
    # V5 — Accessibility
    "5.1": "Proximity",
    "5.2": "Connection quality",
    "5.3": "Access reliability",
    "5.4": "Cost of access",
    "5.5": "Service availability",
    "5.6": "Redundancy",
}

SUB_VARIABLE_IDS: frozenset[str] = frozenset(SUB_VARIABLE_NAMES)


def is_known_sub_variable(sub_id: str) -> bool:
    """Return True when `sub_id` is one of the frozen IDs in Bible §6.8.

    Input: a sub-variable ID such as "1.2". Output: whether the engine knows it.
    Implements Bible §6.8 — IDs are canonical and frozen; no new sub-variables.
    """
    return sub_id in SUB_VARIABLE_IDS
