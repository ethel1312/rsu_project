#!/bin/sh
set -eu

# mod_php uses prefork. Normalize the enabled modules before Apache reads them.
a2dismod mpm_event mpm_worker
a2enmod mpm_prefork
exec "$@"
