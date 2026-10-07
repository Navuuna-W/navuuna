# Lets the service run as `python -m engine ...`; everything happens in engine/cli.py.

import os
import sys

from engine.cli import main

if __name__ == "__main__":
    sys.exit(main(sys.argv[1:], os.environ))
