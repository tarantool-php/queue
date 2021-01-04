# Tarantool Queue

[![Quality Assurance](https://github.com/tarantool-php/queue/workflows/QA/badge.svg)](https://github.com/tarantool-php/queue/actions?query=workflow%3AQA)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tarantool-php/queue/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tarantool-php/queue/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/tarantool-php/queue/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/tarantool-php/queue/?branch=master)
[![Mentioned in Awesome PHP](https://awesome.re/mentioned-badge.svg)](https://github.com/ziadoz/awesome-php)
[![Telegram](https://img.shields.io/badge/Telegram-join%20chat-blue.svg)](https://t.me/tarantool_php)

[Tarantool](https://www.tarantool.io/en/developers/) is a NoSQL database running in a Lua application server. It integrates
Lua modules, called [LuaRocks](https://luarocks.org/). This package provides PHP bindings for
[Tarantool Queue LuaRock](https://github.com/tarantool/queue/).


## Table of contents

 * [Installation](#installation)
 * [Before start](#before-start)
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


## Before start

In order to use queue, you first need to make sure that your Tarantool instance
is configured, up and running. The minimal required configuration might look like this:

```lua
-- queues.lua

box.cfg {listen = 3301}

queue = require('queue')
queue.create_tube('foobar', 'fifottl', {if_not_exists = true})
```

> *You can read more about the box configuration in the official Tarantool [documentation](http://tarantool.org/doc/book/configuration/index.html#initialization-file).
> More information on queue configuration can be found [here](https://github.com/tarantool/queue/blob/master/README.md).*

To start the instance you need to copy (or symlink) `queues.lua` file into the `/etc/tarantool/instances.enabled`
directory and run the following command:

```bash
sudo tarantoolctl start queues
```


## Working with queue

Once you have your instance running, you can start by creating a queue object with the queue (tube) name you defined
in the Lua script:

```php
use Tarantool\Queue\Queue;

...

$queue = new Queue($client, 'foobar');
```

where `$client` is an instance of `Tarantool\Client\Client` from the [tarantool/client](https://github.com/tarantool-php/client) package.


### Data types

Under the hood Tarantool uses [MessagePack](http://msgpack.org/) binary format to serialize/deserialize
data being stored in a queue. It can handle most of the PHP data types (except resources and closures) without 
any manual pre- or post-processing:

```php
$queue->put('foo');
$queue->put(true);
$queue->put(42);
$queue->put(4.2);
$queue->put(['foo' => ['bar' => ['baz' => null]]]);
$queue->put(new MyObject());
```

> *To learn more about object serialization, please follow this [link](https://github.com/tarantool-php/client#user-defined-types).*


### Tasks

Most of the [Queue API](src/Queue.php) methods return a [Task](src/Task.php) object
containing the following getters:

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
queue supports. For example, `fifottl` queue (which we defined [earlier](#before-start) in our Lua config 
file), supports `delay`, `ttl`, `ttr` and `pri` options:

```php
use Tarantool\Queue\Options;

$queue->put('foo', [Options::DELAY => 30.0]);
$queue->put('bar', [Options::TTL => 5.0]);
$queue->put('baz', [Options::TTR => 10.0, Options::PRI => 42]);
```

> *See the full list of available options [here](https://github.com/tarantool/queue#queue-types).*


### Consumer API

To reserve a task for execution, call `take()` method. It accepts an optional `timeout` parameter.
If a timeout value is supplied the call will wait `timeout` seconds until a `READY` task appears in the queue.
The method returns either a [Task](#tasks) object or `null`:

```php
$taskOrNull = $queue->take();

// wait 2 seconds
$taskOrNull = $queue->take(2.0);

// wait 100 milliseconds
$taskOrNull = $queue->take(.1);
```

After successful execution, a task can be marked as acknowledged (that will also delete the task from a queue):

```php
$data = $task->getData();

// process $data

$task = $queue->ack($task->getId());
```

Or put back into the queue in case it cannot be executed:

```php
$task = $queue->release($task->getId());

// for *ttl queues you can specify a delay
$task = $queue->release($task->getId(), [Options::DELAY => 30.0]);
```

To look at a task without changing its state, use:

```php
$task = $queue->peek($task->getId());
```

To bury (disable) a task:

```php
$task = $queue->bury($task->getId());
```

To reset buried task(s) back to `READY` state:

```php
$count = $queue->kick(3); // kick 3 buried tasks
```

To increase TTR and/or TTL of a running task (only for *ttl queues):

```php
$taskOrNull = $queue->touch($takenTask->getId(), 5.0); // increase ttr/ttl to 5 seconds
```

A task (in any state) can be deleted permanently with `delete()`:

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

The `stats()` method provides access to the statistical information accumulated
since a queue was created:

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

Thanks to flexible nature of the [queue](https://github.com/tarantool/queue/) Lua module, you can easily create
your own queue drivers or extend existing ones with an additional functionality. For example, suppose you added
the `put_many` method to your `foobar` queue, which inserts multiple tasks atomically:

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

To invoke this method from php, use `Queue::call()`:

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

And then run both unit and integration tests:

```bash
docker run --rm --net=tarantool-php -v $(pwd):/queue -w /queue queue
```

The library uses [PHPUnit](https://phpunit.de/) under the hood, and if needed,
you can pass additional arguments and options to the `phpunit` command.
For example, to run only unit tests, execute:

```bash
docker run --rm --net=tarantool-php -v $(pwd):/queue -w /queue \
    vendor/bin/phpunit --testsuite=unit
```


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
