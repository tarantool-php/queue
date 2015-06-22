# Tarantool Queue

[![Build Status](https://travis-ci.org/tarantool-php/queue.svg?branch=master)](https://travis-ci.org/tarantool-php/queue)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tarantool-php/queue/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tarantool-php/queue/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/tarantool-php/queue/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/tarantool-php/queue/?branch=master)


## Installation

The recommended way to install the library is through [Composer](http://getcomposer.org):

```sh
$ composer require tarantool/queue:~1.0@dev
```


## Usage example

```php
use Tarantool\Queue\Queue;

$tarantool = new Tarantool();
$queue = new Queue($tarantool, 'my_queue');

$task = $queue->put('foo');
$task = $queue->take(.1);

$data = $task->getData();

$task = $queue->ack($task->getId());
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

To run all tests:

```sh
$ phpunit

```

## License

The library is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
