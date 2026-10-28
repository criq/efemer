<?php

fwrite(STDERR, "Deprecated: use\n");
fwrite(STDERR, "  docker compose exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.ResetImportedContent --args='{\"yes\":true}' -f\n");
exit(1);
