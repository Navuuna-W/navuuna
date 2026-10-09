#!/bin/sh
# Starts the Prefect worker in the local stack (K-12). First makes sure the `scoring` work pool
# exists and the deployments in services/flows/prefect.yaml are registered, then polls the pool.
# Safe to run on every container start: each step updates what is already there.
set -eu

prefect work-pool create scoring --type process --overwrite
prefect --no-prompt deploy --all
exec prefect worker start --pool scoring --type process
