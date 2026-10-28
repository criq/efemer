<?php

fwrite(STDERR, "Deprecated: use\n");
fwrite(STDERR, "  docker compose exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.ImportInstagramDump -f\n");
exit(1);
