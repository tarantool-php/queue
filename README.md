# Tarantool Queue

[![Quality Assurance](https://github.com/tarantool-php/queue/workflows/QA/badge.svg)](https://github.com/tarantool-php/queue/actions?query=workflow%3AQA)
[![Mentioned in Awesome PHP](https://awesome.re/mentioned-badge.svg)](https://github.com/ziadoz/awesome-php)
[![Telegram](https://img.shields.io/badge/Telegram-join%20chat-blue.svg)](https://t.me/tarantool_php)

[Tarantool](https://www.tarantool.io/en/developers/) is a NoSQL database and Lua application server. It supports Lua
modules, which can be installed with [LuaRocks](https://luarocks.org/). This package provides PHP bindings for the
[Tarantool Queue LuaRock](https://github.com/tarantool/queue/).


## Table of contents

 * [Installation](#installation)
 * [Before you start](#before-you-start)
 * [Working with queue](#working-with-queue)
   * [Data types](#data-types)
   * [Tasks](#tasks)
   * [Producer API](#producer-api)
   * [Consumer API](#consumer-api)
   * [Statistics](#statistics)
   * [Custom methods](#custom-methods)
 * [Testing](#testing)
 * [License](#license)


## Installation

The recommended way to install the library is through [Composer](http://getcomposer.org):

```bash
composer require tarantool/queue
```


## Before you start

To use the queue, make sure your Tarantool instance is configured and running. A minimal configuration looks like this:

```lua
-- queues.lua

box.cfg {listen = 3301}

queue = require('queue')
queue.create_tube('foobar', 'fifottl', {if_not_exists = true})
```

> *You can read more about the box configuration in the official Tarantool [documentation](http://tarantool.org/doc/book/configuration/index.html#initialization-file).
> More information on queue configuration can be found [here](https://github.com/tarantool/queue/blob/master/README.md).*

To start the instance, copy (or symlink) the `queues.lua` file into the `/etc/tarantool/instances.enabled` directory
and run the following command:

```bash
sudo tt start queues
```


## Working with queue

Once you have your instance running, create a queue object using the queue (tube) name you defined
in the Lua script:

```php
use Tarantool\Queue\Queue;

...

$queue = new Queue($client, 'foobar');
```

where `$client` is an instance of `Tarantool\Client\Client` from the [tarantool/client](https://github.com/tarantool-php/client) package.

Use a typed queue factory when you want the method signature to show only the options supported by a built-in queue type:

```php
$queue = Queue::fifoTtl($client, 'foobar');
$task = $queue->put('foo', ttl: 60.0, delay: 5.0);

$limitedQueue = Queue::limFifoTtl($client, 'bounded');
$taskOrNull = $limitedQueue->put('bar', timeout: 0.0); // null when the queue is full
```

Typed queue classes describe the queue type expected by the caller; they do not create the tube or verify its server-side type.
The original `new Queue($client, $name)` API remains available for generic and custom queues.
With this generic API, PHP does not validate option arrays against the queue type; the server-side driver decides whether to accept them.

For queues with named subqueues, create a `Subqueue` object once and use it for subsequent operations:

```php
$subqueue = Queue::subqueueTtl($client, 'jobs')->subqueue('emails');
$task = $subqueue->put('send message', ttl: 60.0);
$task = $subqueue->take(timeout: 2.0);
$subqueue->ack($task->getId());
```

Subqueue names do not need to be created in advance. Calling `subqueue()` only creates a PHP object; the first `put()` adds a task under that name. Taking from an empty subqueue returns `null`. The driver takes from one named subqueue at a time and does not provide a take-from-any-subqueue operation.


### Data types

Under the hood, Tarantool uses the [MessagePack](http://msgpack.org/) binary format to serialize and deserialize
data stored in a queue. It can handle most PHP data types (except resources and closures) without
any manual pre- or post-processing:

```php
$queue->put('foo');
$queue->put(true);
$queue->put(42);
$queue->put(4.2);
$queue->put(['foo' => ['bar' => ['baz' => null]]]);
$queue->put(new MyObject());
```

> *To learn more about object serialization, see [User-defined types](https://github.com/tarantool-php/client#user-defined-types).*


### Tasks

Most [Queue API](src/Queue.php) methods return a [Task](src/Task.php) object
that provides the following getters:

```php
Task::getId()
Task::getState() // States::READY, States::TAKEN, States::DONE, States::BURY or States::DELAYED
Task::getData()
```

It also provides these convenience methods:

```php
Task::isReady()
Task::isTaken()
Task::isDone()
Task::isBuried()
Task::isDelayed()
```


### Producer API

For built-in queue types, use the typed queue class and named parameters. For example, the `fifottl` queue, which we
defined [earlier](#before-you-start) in our Lua configuration, supports `delay`, `ttl`, `ttr`, and `pri`:

```php
$queue = Queue::fifoTtl($client, 'foobar');
$queue->put('foo', delay: 30.0);
$queue->put('bar', ttl: 5.0);
$queue->put('baz', ttr: 10.0, pri: 42);
```

For custom drivers or the generic API, `put()` accepts the task data and an optional options array:

```php
use Tarantool\Queue\Options;

$genericQueue = new Queue($client, 'foobar');
$genericQueue->put('foo', [Options::DELAY => 30.0]);
$genericQueue->put('bar', [Options::TTL => 5.0]);
$genericQueue->put('baz', [Options::TTR => 10.0, Options::PRI => 42]);
```

> *See the [full list of available options](https://github.com/tarantool/queue#queue-types).*


### Consumer API

To reserve a task for execution, call the `take()` method. It accepts an optional `timeout` parameter.
If you supply a timeout, the call waits for up to that many seconds for a `READY` task to appear in the queue.
The method returns either a [Task](#tasks) object or `null`:

```php
$taskOrNull = $queue->take();

// wait 2 seconds
$taskOrNull = $queue->take(2.0);

// wait 100 milliseconds
$taskOrNull = $queue->take(.1);
```

After successful execution, acknowledge the task to delete it from the queue:

```php
$data = $task->getData();

// process $data

$task = $queue->ack($task->getId());
```

If the task cannot be executed, return it to the queue:

```php
$task = $queue->release($task->getId());

// For *ttl queues, you can specify a delay.
$task = $queue->release($task->getId(), [Options::DELAY => 30.0]);
```

To view a task without changing its state, use:

```php
$task = $queue->peek($task->getId());
```

To bury (disable) a task:

```php
$task = $queue->bury($task->getId());
```

To return buried tasks to the `READY` state:

```php
$count = $queue->kick(3); // kick 3 buried tasks
```

To increase the TTR or TTL of a running task (only for `*ttl` queues):

```php
$taskOrNull = $queue->touch($takenTask->getId(), 5.0); // increase ttr/ttl to 5 seconds
```

You can permanently delete a task in any state by calling `delete()`:

```php
$task = $queue->delete($task->getId());
```

To delete all tasks in a queue:

```php
$queue->truncate();
```

> *For a detailed API documentation, please read the section 
> "[Using the queue module](https://github.com/tarantool/queue#using-the-queue-module)" 
> of the queue README.*


### Statistics

The `stats()` method returns statistics accumulated since the queue was created:

```php
$stats = $queue->stats();
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

In addition, you can specify a key to return only a subset of the array:

```php
$calls = $queue->stats('calls');
$total = $queue->stats('tasks.total');
```


### Custom methods

Thanks to the flexibility of the [queue](https://github.com/tarantool/queue/) Lua module, you can create your own queue
drivers or extend existing ones with additional functionality. For example, suppose you add a `put_many` method to your
`foobar` queue to insert multiple tasks atomically:

```lua
-- queues.lua

...

queue.tube.foobar.put_many = function(self, items)
    local put = {}

    box.begin()
    for k, item in pairs(items) do
        put[k] = tube:put(unpack(item))
    end
    box.commit()

    return put
end
```

To invoke this method from PHP, use `Queue::call()`:

```php
$result = $queue->call('put_many', [
    'foo' => ['foo', [Options::DELAY => 30.0]],
    'bar' => ['bar'],
]);
```


## Testing

The easiest way to run tests is with Docker. First, build an image using the [dockerfile.sh](dockerfile.sh) generator:

```bash
./dockerfile.sh | docker build -t queue -
```

Then run a Tarantool instance (needed for integration tests):

```bash
docker network create tarantool-php
docker run -d --net=tarantool-php -p 3301:3301 --name=tarantool \
    -v $(pwd)/tests/Integration/queues.lua:/queues.lua \
    tarantool/tarantool:2 tarantool /queues.lua
```

Then run both unit and integration tests:

```bash
docker run --rm --net=tarantool-php -v $(pwd):/queue -w /queue queue
```

The tests use [PHPUnit](https://phpunit.de/). You can pass additional arguments and options to the `phpunit` command.
For example, run only the unit tests with:

```bash
docker run --rm --net=tarantool-php -v $(pwd):/queue -w /queue queue \
    vendor/bin/phpunit --testsuite=unit
```


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
