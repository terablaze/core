<?php

namespace Terablaze\Repl;

use Exception;
use Symfony\Component\VarDumper\Caster\Caster;
use Terablaze\Collection\CollectionInterface;
use Terablaze\Database\ORM\Model;
use Terablaze\Support\HtmlString;
use Terablaze\Support\Stringable;

class ReplCaster
{
    /**
     * Application methods to include in the presenter.
     *
     * @var array
     */
    private static $kernelProperties = [
//        'configurationIsCached',
//        'environment',
//        'environmentFile',
//        'isLocal',
//        'routesAreCached',
//        'runningUnitTests',
//        'version',
//        'path',
//        'basePath',
//        'configPath',
//        'databasePath',
//        'langPath',
//        'publicPath',
//        'storagePath',
//        'bootstrapPath',
    ];

    /**
     * Get an array representing the properties of an application.
     *
     * @param  $kernel
     * @return array
     */
    public static function castKernel($kernel)
    {
        $results = [];

        foreach (self::$kernelProperties as $property) {
            try {
                $val = $kernel->$property();

                if (! is_null($val)) {
                    $results[Caster::PREFIX_VIRTUAL.$property] = $val;
                }
            } catch (Exception $e) {
                //
            }
        }

        return $results;
    }

    /**
     * Get an array representing the properties of a collection.
     *
     * @param  CollectionInterface  $collection
     * @return array
     */
    public static function castCollection($collection)
    {
        return [
            Caster::PREFIX_VIRTUAL.'all' => $collection->all(),
        ];
    }

    /**
     * Get an array representing the properties of an html string.
     *
     * @param  HtmlString  $htmlString
     * @return array
     */
    public static function castHtmlString($htmlString)
    {
        return [
            Caster::PREFIX_VIRTUAL.'html' => $htmlString->toHtml(),
        ];
    }

    /**
     * Get an array representing the properties of a fluent string.
     *
     * @param  Stringable  $stringable
     * @return array
     */
    public static function castStringable($stringable)
    {
        return [
            Caster::PREFIX_VIRTUAL.'value' => (string) $stringable,
        ];
    }

    /**
     * Get an array representing the properties of a model.
     *
     * @param  Model  $model
     * @return array
     */
    public static function castModel($model)
    {
        $attributes = array_merge(
            $model->getAttributes(), $model->getRelations()
        );

        $visible = array_flip(
            $model->getVisible() ?: array_diff(array_keys($attributes), $model->getHidden())
        );

        $hidden = array_flip($model->getHidden());

        $appends = (function () {
            return array_combine($this->appends, $this->appends);
        })->bindTo($model, $model)();

        foreach ($appends as $appended) {
            $attributes[$appended] = $model->{$appended};
        }

        $results = [];

        foreach ($attributes as $key => $value) {
            $prefix = '';

            if (isset($visible[$key])) {
                $prefix = Caster::PREFIX_VIRTUAL;
            }

            if (isset($hidden[$key])) {
                $prefix = Caster::PREFIX_PROTECTED;
            }

            $results[$prefix.$key] = $value;
        }

        return $results;
    }
}
