<?php declare (strict_types=1);

use Rector\Tests\Rector\Property\PropertyToMethodRector\Source\Translator;

class CustomTranslator extends Translator
{
    public function fooMethod()
    {
        // get locale
        $locale = $this->getLocale();

        // update locale
        $this->setLocale($locale);
    }
}
