# Image for Prefect in the local stack (K-12): the same image runs the Prefect server and the
# worker. The worker runs flows as plain processes, so the signal service is installed here too,
# and score_all can call `python -m engine run --all` (services/flows/score_all.py).
# Built from the repo root, like signals.Dockerfile.

FROM python:3.12-slim

WORKDIR /srv/navuuna/services

COPY services/signals ./signals
COPY services/flows ./flows
RUN pip install --no-cache-dir ./signals ./flows

COPY infra/docker/prefect-worker-start.sh /usr/local/bin/prefect-worker-start.sh

WORKDIR /srv/navuuna/services/flows
