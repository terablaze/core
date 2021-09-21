<?php

namespace TeraBlaze\Core\Console\Command;

use Symfony\Component\Console\Input\InputOption;
use TeraBlaze\Console\Command;
use TeraBlaze\Console\ConfirmableTrait;
use TeraBlaze\Encryption\Encrypter;

class KeyGenerateCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected static $defaultName = 'key:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Set the application key';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $key = $this->generateRandomKey();

        if ($this->input->getOption('show')) {
            $this->io->comment($key);
            return self::SUCCESS;
        }

        // Next, we will replace the application key in the environment file so it is
        // automatically setup for this developer. This key gets generated using a
        // secure random byte generator and is later base64 encoded for storage.
        if (!$this->setKeyInEnvironmentFile($key)) {
            return self::FAILURE;
        }

        setConfig('app.key', $key);

        $this->io->info('Application key set successfully.');
        return self::SUCCESS;
    }

    /**
     * Generate a random key for the application.
     *
     * @return string
     */
    protected function generateRandomKey()
    {
        return 'base64:' . base64_encode(
                Encrypter::generateKey(getConfig('app.cipher'))
            );
    }

    /**
     * Set the application key in the environment file.
     *
     * @param string $key
     * @return bool
     */
    protected function setKeyInEnvironmentFile($key)
    {
        $currentKey = getConfig('app.key');

        if (strlen($currentKey) !== 0 && (!$this->confirmToProceed())) {
            return false;
        }

        $this->writeNewEnvironmentFileWith($key);

        return true;
    }

    /**
     * Write a new environment file with the given key.
     *
     * @param string $key
     * @return void
     */
    protected function writeNewEnvironmentFileWith($key)
    {
        file_put_contents(
            $envFile = baseDir('.env'),
            preg_replace(
                $this->keyReplacementPattern(),
                'APP_KEY=' . $key,
                file_get_contents($envFile)
            )
        );
    }

    /**
     * Get a regex pattern that will match env APP_KEY with any random key.
     *
     * @return string
     */
    protected function keyReplacementPattern()
    {
        $escaped = preg_quote('=' . getConfig('app.key'), '/');

        return "/^APP_KEY{$escaped}/m";
    }

    protected function getOptions()
    {
        return [
            ['show', null, InputOption::VALUE_NONE, 'Display the key instead of modifying files'],
            ['force', 'f', InputOption::VALUE_NONE, 'Force the operation to run when in production'],
        ];
    }
}
