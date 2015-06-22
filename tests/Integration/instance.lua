#!/usr/bin/tarantool

box.cfg {
    listen = 3301,
    log_level = 6,
    wal_mode = 'none',
    snap_dir = '/tmp',
    slab_alloc_arena = .1,
}

box.schema.user.grant('guest', 'read,write,execute', 'universe')

queue = require('queue')
queue.start()

for _, tube_type in pairs({ 'fifo', 'fifottl', 'utube', 'utubettl' }) do
    local tube_name = 'queue_' .. tube_type
    if null == queue.tube[tube_name] then
        queue.create_tube(tube_name, tube_type, { temporary = true })
    end
end

-- https://github.com/tarantool/tarantool-php/issues/26

function queue._create_tube(tube_name, tube_type, opts)
    queue.create_tube(tube_name, tube_type, opts)
end
