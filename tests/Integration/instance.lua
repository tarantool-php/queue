#!/usr/bin/tarantool

box.cfg {
    listen = 3301,
    log_level = 6,
    wal_mode = 'none',
    snap_dir = '/tmp',
    slab_alloc_arena = .1,
}

box.schema.user.grant('guest', 'read,write,execute', 'universe')

console = require('console')
console.listen('127.0.0.1:33333')

queue = require('queue')
queue.start()

function create_tube(tube_name, tube_type, opts)
    if nil ~= queue.tube[tube_name] then
        queue.tube[tube_name]:drop()
    end

    return queue.create_tube(tube_name, tube_type, opts)
end
