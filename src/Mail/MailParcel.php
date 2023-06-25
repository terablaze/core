<?php

namespace Terablaze\Mail;

use Terablaze\Core\Parcel\Parcel;
use Terablaze\Support\Helpers;
use Terablaze\View\View;

class MailParcel extends Parcel
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
        $parsed = $this->loadConfig('mail')->get('mail');

        $this->registerTerablazeMailer();
        $this->registerMarkdownRenderer();
    }

    /**
     * Register the Illuminate mailer instance.
     *
     * @return void
     */
    protected function registerTerablazeMailer()
    {
        $this->container->registerServiceInstance(
            'mail.manager',
            new MailManager($this->container)
        );
        $this->container->setAlias(MailManagerInterface::class, 'mail.manager');

        $this->container->registerServiceInstance(
            'mailer',
            $this->container->get('mail.manager')->mailer()
        );
    }

    /**
     * Register the Markdown renderer instance.
     *
     * @return void
     */
    protected function registerMarkdownRenderer()
    {
        if ($this->getKernel()->inConsole()) {
            $this->publishes([
                __DIR__ . '/resources/views' => $this->getKernel()->resourceDir('views/vendor/mail'),
            ], 'terablaze-mail');
        }

        $this->container->registerServiceInstance(
            Markdown::class,
            new Markdown($this->container->get(View::class), [
                'theme' => Helpers::getConfig('mail.markdown.theme', 'default'),
                'paths' => Helpers::getConfig('mail.markdown.paths', []),
            ])
        );
    }
}
