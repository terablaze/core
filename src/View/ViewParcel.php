<?php

namespace TeraBlaze\View;

use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\View\Engine\EngineInterface;

class ViewParcel extends Parcel implements ParcelInterface
{
    public function boot(): void
    {
        $config = loadConfigArray('views');

        /** @var View $manager */
        $manager = $this->container->make('view', ['class' => View::class]);
        $manager->setCachePath($config['cache_path'] ?? '');

        $this->bindPaths($manager, $config['paths'] ?? []);
        $this->bindEngines($manager, $config['engines'] ?? []);
        $this->bindMacros($manager);
    }

    /**
     * @param View $manager
     * @param string[] $paths
     */
    private function bindPaths(View $manager, array $paths): void
    {
        foreach ($paths as $path) {
            $manager->addPath($path);
        }
    }

    private function bindMacros(View $manager): void
    {
        $manager->addMacro('escape', fn($value) => htmlspecialchars($value, ENT_QUOTES));
        $manager->addMacro('includes', fn(...$params) => print $manager->render(...$params));
    }

    /**
     * @param View $manager
     * @param string[] $engines
     */
    private function bindEngines(View $manager, array $engines): void
    {
        foreach ($engines as $extension => $engine) {
            /** @var EngineInterface $engineInstance */
            $engineInstance = $this->container->make($engine);
            $manager->addEngine($extension, $engineInstance);
        }
    }
}
