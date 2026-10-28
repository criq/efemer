<?php

fwrite(STDERR, "Deprecated: use\n");
fwrite(STDERR, "  docker compose exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Pages.ImportEfemerCz -f\n");
fwrite(STDERR, "For content-model ingest diary posts, prefer:\n");
fwrite(STDERR, "  docker compose exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.RefreshEfemerCz -f\n");
exit(1);
