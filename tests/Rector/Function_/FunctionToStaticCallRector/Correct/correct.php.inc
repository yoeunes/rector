<?php

namespace Rector\Tests\FunctionToStaticCallRector;

class SomeClass
{
    public function someMethod()
    {
        \SomeStaticClass::render('template', []);
    }
}
