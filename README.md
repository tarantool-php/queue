# Tarantool Queue

[![Build Status](https://travis-ci.org/tarantool-php/queue.svg?branch=master)](https://travis-ci.org/tarantool-php/queue)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tarantool-php/queue/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tarantool-php/queue/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/tarantool-php/queue/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/tarantool-php/queue/?branch=master)

[Tarantool](http://tarantool.org/) is a NoSQL database running in a Lua application server. It integrates
Lua modules, called [LuaRocks](https://luarocks.org/). This package provides PHP bindings for
[Tarantool Queue LuaRock](https://github.com/tarantool/queue/).


## Installation

The recommended way to install the library is through [Composer](http://getcomposer.org):

```sh
$ composer require tarantool/queue
```


## Before start

In order to use queue, you first need to make sure that your Tarantool instance
is configured, up and running. The minimal required configuration might look like this:

```lua
-- queues.lua

box.cfg {}

queue = require('queue')
queue.start()

local tube_name = 'foobar'
if null == queue.tube[tube_name] then
    queue.create_tube(tube_name, 'fifottl')
end
```

> You can read more about the box configuration in the official [Tarantool documentation](http://tarantool.org/doc/book/configuration/index.html#initialization-file).
> For more information about the queue configuration check out [queue's README](https://github.com/tarantool/queue/blob/master/README.md).

To start the instance you need to copy (or symlink) `queues.lua` file into the `/etc/tarantool/instances.enabled`
directory and run the following command:

```sh
$ sudo tarantoolctl start queues
```


## Working with queue

Once you have your instance running, you can start by creating a queue object with the queue (tube) name you defined
in the Lua script:

```php
use Tarantool\Queue\Queue;

$tarantool = new Tarantool();
$queue = new Queue($tarantool, 'foobar');
```


### Data types

Under the hood Tarantool uses [MessagePack](http://msgpack.org/) binary format to serialize/deserialize
data being stored in a queue. This means that it's safe to use such data types as `null`, `bool`, `int`,
`float`, `string`, `binary string` and `array` without any manual pre- or post-processing:

```php
$queue->put('foo');
$queue->put(true);
$queue->put(42);
$queue->put(4.2);
$queue->put(['foo' => ['bar' => ['baz' => null]]]);
```


### Tasks

Almost every method of the [Queue API](src/Queue.php) (except for `kick()` and `statistics()`)
returns back a [Task](src/Task.php) object containing the following getters:

```php
Task::getId()
Task::getState() // States::READY, States::TAKEN, States::DONE, States::BURY or States::DELAYED
Task::getData()
```

And some sugar methods:

```php
Task::isReady()
Task::isTaken()
Task::isDone()
Task::isBuried()
Task::isDelayed()
```


### Producer API

As you've already seen, to insert a task into a queue you need to call `put()` method, which accepts
two arguments: the data you want to process and optional array of task options, which this particular
queue supports. For example, `fifottl` queue (which we defined earlier in our Lua config file),
supports `delay`, `ttl`, `ttr`, `pri` and `temporary` options:

```php
$queue->put('foo', ['delay' => 30]);
$queue->put('bar', ['ttl' => 5]);
$queue->put('baz', ['ttr' => 10, 'pri' => 42]);
```

> See the full list of available options [here](https://github.com/tarantool/queue#producer-api).


### Consumer API

To reserve a task for execution, call `take()` method. It accepts an optional `timeout` parameter.
If a timeout value is supplied the call will wait `timeout` seconds until a `READY` task appears in the queue.
The method returns either a [Task](#tasks) object or `null`:

```php
$task = $queue->take();

// wait 2 seconds
$task = $queue->take(2);

// wait 100 milliseconds
$task = $queue->take(.1);
```

After successful execution, a task can be marked as acknowledged (that will also delete the task from a queue):

```php
$data = $task->getData();

// process $data

$this->queue->ack($task->getId());
```

Or put back into the queue in case it cannot be executed:

```php
$this->queue->release($task->getId());

// for ttl-like queues you can specify a delay
$this->queue->release($task->getId(), ['delay' => 30]);
```

To look at a task without changing its state, use:

```php
$task = $this->queue->peek($task->getId());
```

To bury (disable) a task:

```php
$queue->bury($task->getId());
```

To reset buried task(s) back to `READY` state:

```php
$count = $queue->kick(3); // kick 3 buried tasks
```

A task (in any state) can be deleted permanently with `delete()`:

```php
$this->queue->delete($task->getId());
```

> For a detailed API documentation, please read [API](https://github.com/tarantool/queue#api)
section of the [queue's README](https://github.com/tarantool/queue/blob/master/README.md).


### Statistics

The `statistics()` method provides access to the statistical information accumulated
since a queue was created:

```php
$stat = $queue->statistics();
```

The result of this call might look like this:

```php
[
    'tasks' => [
        'taken'   => 1,
        'buried'  => 1,
        'ready'   => 1,
        'done'    => 0,
        'delayed' => 0,
        'total'   => 3,
    ],
    'calls' => [
        'bury' => 1,
        'put'  => 3,
        'take' => 1,
        ...
    ],
]
```

In addition, you can specify a key (or keys) to return only a subset of the array:

```php
$calls = $queue->statistics('calls');
$total = $queue->statistics('tasks', 'total');
```


## Tests

To run unit tests:

```sh
$ phpunit --testsuite Unit
```

To run integration tests:

```sh
$ phpunit --testsuite Integration
```

> Make sure that the [instance.lua](tests/Integration/instance.lua) instance is up and running.

To run all tests:

```sh
$ phpunit

```

## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
