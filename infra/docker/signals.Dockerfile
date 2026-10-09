# Image for the Python signal service in the local stack (K-08). Runs `python -m engine`,
# which scores entities and listens for recompute requests (K-09b).
# Built from the repo root, like api.Dockerfile.

FROM python:3.12-slim

WORKDIR /srv/navuuna/services/signals

COPY services/signals ./
# Installs the dependencies from pyproject.toml. `python -m engine` still runs the code in this
# folder (the working directory comes first on the import path), so the engine finds
# modules/ next to it.
RUN pip install --no-cache-dir .

CMD ["python", "-m", "engine", "consume"]
