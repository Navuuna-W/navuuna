-- STUB for Devyan's 500-entity Nairobi fixture (D-14), used by infra/local/setup.sh until
-- data/fixtures/*.sql exists. One ward and three water points in central Nairobi, enough to
-- see the stack run. Listed in the stub register of Khillon's progress file (K-08).
-- Safe to load twice: rows whose external_ref already exists are skipped.

INSERT INTO core.entities (entity_type, name, external_ref, geom)
VALUES ('area', 'Stub ward', 'stub:ward-1',
        'SRID=4326;POLYGON((36.81 -1.30, 36.83 -1.30, 36.83 -1.28, 36.81 -1.28, 36.81 -1.30))')
ON CONFLICT (external_ref) DO NOTHING;

-- Water points sit inside the ward, 50 m radius like the K-09b test entities.
INSERT INTO core.entities (entity_type, module, name, external_ref, geom, radius_m, parent_area_id)
SELECT 'point', 'water', water_point.name, water_point.external_ref, water_point.geom, 50, ward.id
FROM (VALUES
        ('Stub water point 1', 'stub:water-1', 'SRID=4326;POINT(36.8219 -1.2921)'),
        ('Stub water point 2', 'stub:water-2', 'SRID=4326;POINT(36.8150 -1.2950)'),
        ('Stub water point 3', 'stub:water-3', 'SRID=4326;POINT(36.8250 -1.2850)')
     ) AS water_point (name, external_ref, geom)
CROSS JOIN core.entities AS ward
WHERE ward.external_ref = 'stub:ward-1'
ON CONFLICT (external_ref) DO NOTHING;
