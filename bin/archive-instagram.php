<?php

fwrite(STDERR, "Deprecated: use\n");
fwrite(STDERR, "  docker compose exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.RefreshInstagramAccounts --args='{\"full\":true}' -f\n");
fwrite(STDERR, "  docker compose exec -T php php bin/cli.php jobs:loop App.Classes.Jobs.Ingest.RefreshInstagramAccounts --interval=21600 -f\n");
exit(1);
