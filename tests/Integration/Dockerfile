ARG TNT_IMAGE
FROM ${TNT_IMAGE}

RUN tarantool -e "require('queue')" >/dev/null 2>&1 || tt rocks install queue
