<?php

fwrite(STDERR, "Deprecated: use\n");
fwrite(STDERR, "  docker compose exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.RefreshInstagram --args='{\"account\":\"efemer_flowers\",\"full\":true}'\n");
fwrite(STDERR, "  docker compose exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.RefreshInstagram --args='{\"account\":\"himmlhaus\",\"full\":true}'\n");
exit(1);
