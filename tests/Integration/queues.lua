#!/usr/bin/env tarantool

local listen = os.getenv('TNT_LISTEN_URI')

box.cfg {
    listen = (listen == '' or listen == nil) and 3301 or listen,
    log_level = 6,
    wal_mode = 'none',
    snap_dir = '/tmp'
}

box.schema.user.grant('guest', 'read,write,execute,create,drop,alter', 'universe', nil, {if_not_exists = true})

queue = require('queue')

function create_tube(tube_name, tube_type, opts)
    if queue.tube[tube_name] then
        queue.tube[tube_name]:drop()
    end

    return queue.create_tube(tube_name, tube_type, opts)
end
