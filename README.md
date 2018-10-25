# Tarantool Queue

[![Build Status](https://travis-ci.org/tarantool-php/queue.svg?branch=master)](https://travis-ci.org/tarantool-php/queue)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tarantool-php/queue/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tarantool-php/queue/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/tarantool-php/queue/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/tarantool-php/queue/?branch=master)
[![Mentioned in Awesome PHP](https://awesome.re/mentioned-badge.svg)](https://github.com/ziadoz/awesome-php)

[Tarantool](http://tarantool.org/) is a NoSQL database running in a Lua application server. It integrates
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
 * [Tests](#tests)
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

box.cfg {listen=3301}

queue = require('queue')
queue.create_tube('foobar', 'fifottl', {if_not_exists=true})
```

> *You can read more about the box configuration in the official [Tarantool documentation](http://tarantool.org/doc/book/configuration/index.html#initialization-file).
> For more information about the queue configuration check out [queue's README](https://github.com/tarantool/queue/blob/master/README.md).*

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

where `$client` is either an instance of the Tarantool class from the [pecl extension](https://github.com/tarantool/tarantool-php) 
or an instance of `Tarantool\Client\Client` from the [pure PHP package](https://github.com/tarantool-php/client).


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

> *Object serialization is only supported when [tarantool/client](https://github.com/tarantool-php/client) is used.*


### Tasks

Most of the [Queue API](src/Queue.php) methods return back
a [Task](src/Task.php) object containing the following getters:

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
supports `delay`, `ttl`, `ttr` and `pri` options:

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
> of the [queue's README](https://github.com/tarantool/queue/blob/master/README.md).*


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

Thanks to flexible nature of the [tarantool/queue](https://github.com/tarantool/queue/) module, 
you can easily create your own queue drivers or extend existing ones with an additional functionality. 
For example, you added the `put_many` method to your `foobar` queue, which inserts multiple tasks in a transaction:  

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

To call this method on a `$queue` object, use `Queue::call()`:

```php
$result = $queue->call('put_many', [
    'foo' => ['foo', [Options::DELAY => 30.0]],
    'bar' => ['bar'],
]);
```


## Tests

The easiest way to run tests is with Docker. First, build an image using the [dockerfile.sh](dockerfile.sh) generator:

```bash
./dockerfile.sh | docker build -t queue -
```

Then run Tarantool instance (needed for integration tests):

```bash
docker network create tarantool-php
docker run -d --net=tarantool-php --name=tarantool -v `pwd`:/queue \
    tarantool/tarantool:1 tarantool /queue/tests/Integration/queues.lua
```

And then run both unit and integration tests:

```bash
docker run --rm --net=tarantool-php --name=queue -v `pwd`:/queue -w /queue queue
```

To run only integration or unit tests, set the `PHPUNIT_OPTS` environment variable
to either `--testsuite integration` or `--testsuite unit` respectively, e.g.:

```bash
docker run --rm --net=tarantool-php --name=queue -v `pwd`:/queue -w /queue \
    -e PHPUNIT_OPTS='--testsuite unit' queue
```


## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
