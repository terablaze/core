<?php

namespace Terablaze\Translation;

use Countable;
use InvalidArgumentException;
use Terablaze\Collection\ArrayCollection;
use Terablaze\Support\ArrayMethods;
use Terablaze\Support\NamespacedItemResolver;
use Terablaze\Support\StringMethods;
use Terablaze\Support\Traits\Macroable;

class Translator extends NamespacedItemResolver implements TranslatorInterface
{
    use Macroable;

    /**
     * The loader implementation.
     *
     * @var LoaderInterface
     */
    protected LoaderInterface $loader;

    /**
     * The default locale being used by the translator.
     *
     * @var string
     */
    protected string $locale;

    /**
     * The fallback locale used by the translator.
     *
     * @var string
     */
    protected string $fallback;

    /**
     * The array of loaded translation groups.
     *
     * @var array
     */
    protected array $loaded = [];

    /**
     * The message selector.
     *
     * @var MessageSelector
     */
    protected $selector;

    /**
     * Create a new translator instance.
     *
     * @param  LoaderInterface  $loader
     * @param string $locale
     * @return void
     */
    public function __construct(LoaderInterface $loader, string $locale)
    {
        $this->loader = $loader;

        $this->setLocale($locale);
    }

    /**
     * Determine if a translation exists for a given locale.
     *
     * @param string $key
     * @param string|null $locale
     * @return bool
     */
    public function hasForLocale(string $key, ?string $locale = null): bool
    {
        return $this->has($key, $locale, false);
    }

    /**
     * Determine if a translation exists.
     *
     * @param string $key
     * @param string|null $locale
     * @param bool $fallback
     * @return bool
     */
    public function has(string $key, ?string $locale = null, bool $fallback = true): bool
    {
        return $this->get($key, [], $locale, $fallback) !== $key;
    }

    /**
     * Get the translation for the given key.
     *
     * @param string $key
     * @param  array  $replace
     * @param string|null $locale
     * @param bool $fallback
     * @return string|array
     */
    public function get(string $key, array $replace = [], ?string $locale = null, bool $fallback = true)
    {
        $locale = $locale ?: $this->locale;

        // For JSON translations, there is only one file per locale, so we will simply load
        // that file and then we will be ready to check the array for the key. These are
        // only one level deep so we do not need to do any fancy searching through it.
        $this->load('*', '*', $locale);

        $line = $this->loaded['*']['*'][$locale][$key] ?? null;

        // If we can't find a translation for the JSON key, we will attempt to translate it
        // using the typical translation file. This way developers can always just use a
        // helper such as __ instead of having to pick between trans or __ with views.
        if (! isset($line)) {
            [$namespace, $group, $item] = $this->parseKey($key);

            // Here we will get the locale that should be used for the language line. If one
            // was not passed, we will use the default locales which was given to us when
            // the translator was instantiated. Then, we can load the lines and return.
            $locales = $fallback ? $this->localeArray($locale) : [$locale];

            foreach ($locales as $locale) {
                if (
                    ! is_null($line = $this->getLine(
                        $namespace,
                        $group,
                        $locale,
                        $item,
                        $replace
                    ))
                ) {
                    return $line;
                }
            }
        }

        // If the line doesn't exist, we will return back the key which was requested as
        // that will be quick to spot in the UI if language keys are wrong or missing
        // from the application's language files. Otherwise we can return the line.
        return $this->makeReplacements($line ?: $key, $replace);
    }

    /**
     * Get a translation according to an integer value.
     *
     * @param string $key
     * @param Countable|int|array  $number
     * @param  array  $replace
     * @param string|null $locale
     * @return string
     */
    public function choice(string $key, $number, array $replace = [], ?string $locale = null): string
    {
        $line = $this->get(
            $key,
            $replace,
            $locale = $this->localeForChoice($locale)
        );

        // If the given "number" is actually an array or countable we will simply count the
        // number of elements in an instance. This allows developers to pass an array of
        // items without having to count it on their end first which gives bad syntax.
        if (is_array($number) || $number instanceof Countable) {
            $number = count($number);
        }

        $replace['count'] = $number;

        return $this->makeReplacements(
            $this->getSelector()->choose($line, $number, $locale),
            $replace
        );
    }

    /**
     * Get the proper locale for a choice operation.
     *
     * @param string|null $locale
     * @return string
     */
    protected function localeForChoice(?string $locale = null): string
    {
        return $locale ?: $this->locale ?: $this->fallback;
    }

    /**
     * Retrieve a language line out the loaded array.
     *
     * @param string $namespace
     * @param string $group
     * @param string $locale
     * @param string $item
     * @param  array  $replace
     * @return string|array|null
     */
    protected function getLine(string $namespace, string $group, string $locale, $item, array $replace = [])
    {
        $this->load($namespace, $group, $locale);

        $line = ArrayMethods::get($this->loaded[$namespace][$group][$locale], $item);

        if (is_string($line)) {
            return $this->makeReplacements($line, $replace);
        } elseif (is_array($line) && count($line) > 0) {
            foreach ($line as $key => $value) {
                $line[$key] = $this->makeReplacements($value, $replace);
            }

            return $line;
        }
        return null;
    }

    /**
     * Make the place-holder replacements on a line.
     *
     * @param string $line
     * @param  array  $replace
     * @return string
     */
    protected function makeReplacements(string $line, array $replace): string
    {
        if (empty($replace)) {
            return $line;
        }

        $replace = $this->sortReplacements($replace);

        foreach ($replace as $key => $value) {
            $line = str_replace(
                [':' . $key, ':' . StringMethods::upper($key), ':' . StringMethods::ucfirst($key)],
                [$value, StringMethods::upper($value), StringMethods::ucfirst($value)],
                $line
            );
        }

        return $line;
    }

    /**
     * Sort the replacements array.
     *
     * @param  array  $replace
     * @return array
     */
    protected function sortReplacements(array $replace): array
    {
        return (new ArrayCollection($replace))->sortBy(function ($value, $key) {
            return mb_strlen($key) * -1;
        })->all();
    }

    /**
     * Add translation lines to the given locale.
     *
     * @param  array  $lines
     * @param string $locale
     * @param string $namespace
     * @return void
     */
    public function addLines(array $lines, string $locale, string $namespace = '*')
    {
        foreach ($lines as $key => $value) {
            [$group, $item] = explode('.', $key, 2);

            ArrayMethods::set($this->loaded, "$namespace.$group.$locale.$item", $value);
        }
    }

    /**
     * Load the specified language group.
     *
     * @param string $namespace
     * @param string $group
     * @param string $locale
     * @return void
     */
    public function load(string $namespace, string $group, string $locale): void
    {
        if ($this->isLoaded($namespace, $group, $locale)) {
            return;
        }

        // The loader is responsible for returning the array of language lines for the
        // given namespace, group, and locale. We'll set the lines in this array of
        // lines that have already been loaded so that we can easily access them.
        $lines = $this->loader->load($locale, $group, $namespace);

        $this->loaded[$namespace][$group][$locale] = $lines;
    }

    /**
     * Determine if the given group has been loaded.
     *
     * @param string $namespace
     * @param string $group
     * @param string $locale
     * @return bool
     */
    protected function isLoaded(string $namespace, string $group, string $locale): bool
    {
        return isset($this->loaded[$namespace][$group][$locale]);
    }

    /**
     * Add a new namespace to the loader.
     *
     * @param string $namespace
     * @param string $hint
     * @return void
     */
    public function addNamespace(string $namespace, string $hint)
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    /**
     * Add a new JSON path to the loader.
     *
     * @param string $path
     * @return void
     */
    public function addJsonPath(string $path): void
    {
        $this->loader->addJsonPath($path);
    }

    /**
     * Parse a key into namespace, group, and item.
     *
     * @param  string  $key
     * @return array
     */
    public function parseKey($key): array
    {
        $segments = parent::parseKey($key);

        if (is_null($segments[0])) {
            $segments[0] = '*';
        }

        return $segments;
    }

    /**
     * Get the array of locales to be checked.
     *
     * @param string|null $locale
     * @return array
     */
    protected function localeArray(?string $locale): array
    {
        return array_filter([$locale ?: $this->locale, $this->fallback]);
    }

    /**
     * Get the message selector instance.
     *
     * @return MessageSelector
     */
    public function getSelector(): MessageSelector
    {
        if (! isset($this->selector)) {
            $this->selector = new MessageSelector();
        }

        return $this->selector;
    }

    /**
     * Set the message selector instance.
     *
     * @param MessageSelector $selector
     * @return void
     */
    public function setSelector(MessageSelector $selector)
    {
        $this->selector = $selector;
    }

    /**
     * Get the language line loader implementation.
     *
     * @return LoaderInterface
     */
    public function getLoader(): LoaderInterface
    {
        return $this->loader;
    }

    /**
     * Get the default locale being used.
     *
     * @return string
     */
    public function locale(): string
    {
        return $this->getLocale();
    }

    /**
     * Get the default locale being used.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Set the default locale.
     *
     * @param string $locale
     * @return void
     */
    public function setLocale(string $locale)
    {
        if (StringMethods::contains($locale, ['/', '\\'])) {
            throw new InvalidArgumentException('Invalid characters present in locale.');
        }

        $this->locale = $locale;
    }

    /**
     * Get the fallback locale being used.
     *
     * @return string
     */
    public function getFallback(): string
    {
        return $this->fallback;
    }

    /**
     * Set the fallback locale being used.
     *
     * @param string $fallback
     * @return void
     */
    public function setFallback(string $fallback)
    {
        $this->fallback = $fallback;
    }

    /**
     * Set the loaded translation groups.
     *
     * @param  array  $loaded
     * @return void
     */
    public function setLoaded(array $loaded)
    {
        $this->loaded = $loaded;
    }
}
