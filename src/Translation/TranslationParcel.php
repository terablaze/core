<?php

namespace TeraBlaze\Translation;

use ReflectionException;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;

class TranslationParcel extends Parcel implements ParcelInterface
{
    /**
     * Register the service provider.
     *
     * @return void
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $this->registerLoader();

        /** @var Translator $translator */
        $translator = $this->container->make('translator', [
            'class' => Translator::class,
            'alias' => TranslatorInterface::class,
            'arguments' => [
                'locale' => getConfig('app.locale')
            ],
        ]);

        $translator->setFallback(getConfig('app.fallback_locale'));
    }

    /**
     * Register the translation line loader.
     *
     * @return void
     */
    protected function registerLoader()
    {
        $this->container->make('translation.loader', [
            'class' => FileLoader::class,
            'alias' => LoaderInterface::class,
            'arguments' => [
                'path' => getConfig('app.translation_path'),
            ],
        ]);
    }
}
