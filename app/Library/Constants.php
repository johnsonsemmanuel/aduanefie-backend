<?php

// POINT_SRID is defined in App\Providers\AppServiceProvider::register().
// It cannot live here because composer autoloads this file before .env is
// loaded, and the value depends on the configured database driver
// (MySQL/MariaDB use SRID 0, PostGIS uses 4326).
