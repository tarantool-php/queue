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

function try_drop_tube(name)
    if queue.tube[name] then
        queue.tube[name]:drop()
    end
end

function create_tube(name, type, opts)
    try_drop_tube(name)
    return queue.create_tube(name, type, opts)
end

