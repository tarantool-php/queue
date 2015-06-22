<?php

namespace Tarantool\Queue;

abstract class States
{
    const READY = 'r';
    const TAKEN = 't';
    const DONE = '-';
    const BURIED = '!';
    const DELAYED = '~';
}
