# Links the entities of a layer to the area they sit in, by filling parent_area_id. Runs after
# load_base_layer.py has written both the layer and its parent areas. The signal runner uses
# the link to find the other water points of the same ward (water adapter 4.4).

from database.connection import DatabaseConnection

# An entity belongs to the area that covers a point inside the entity. Using one inside
# point, not the whole shape, gives a road that crosses two wards exactly one parent.
# `IS DISTINCT FROM` skips rows that already have the right parent, so a second run
# changes nothing.
ASSIGN_PARENT_AREAS_SQL = """
    UPDATE core.entities AS entity
    SET parent_area_id = parent_area.id
    FROM core.entities AS parent_area
    WHERE entity.metadata ->> 'base_layer' = %(layer_name)s
      AND parent_area.metadata ->> 'base_layer' = %(parent_layer_name)s
      AND parent_area.retired_at IS NULL
      AND public.ST_Covers(parent_area.geom, public.ST_PointOnSurface(entity.geom))
      AND entity.parent_area_id IS DISTINCT FROM parent_area.id
"""


def assign_parent_areas(
    connection: DatabaseConnection, layer_name: str, parent_layer_name: str
) -> int:
    """Set parent_area_id on a layer's entities and return how many rows changed.

    Inputs: an open connection, the layer whose entities need a parent (e.g. the water
    points) and the layer holding the parent areas (e.g. the wards). Output: the number of
    entities whose parent was set or changed.
    An entity outside every parent area keeps no parent; it is never given the nearest one.
    Implements Bible §10 (core.entities.parent_area_id).
    """
    with connection.transaction():
        updated = connection.execute(
            ASSIGN_PARENT_AREAS_SQL,
            {"layer_name": layer_name, "parent_layer_name": parent_layer_name},
        )
    return updated.rowcount
