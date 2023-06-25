<?php

namespace Terablaze\Mail;

use Aws\Ses\SesClient;
use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Terablaze\Log\LogManager;
use Terablaze\Mail\Transport\ArrayTransport;
use Terablaze\Mail\Transport\LogTransport;
use Terablaze\Mail\Transport\SesTransport;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\FailoverTransport;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Terablaze\Queue\QueueManagerInterface;
use Terablaze\Support\ArrayMethods;
use Terablaze\Support\Helpers;
use Terablaze\Support\StringMethods;
use Terablaze\View\View;

/**
 * @mixin \Terablaze\Mail\Mailer
 */
class MailManager implements MailManagerInterface
{
    /**
     * The application instance.
     *
     * @var \Terablaze\Container\ContainerInterface
     */
    protected $container;

    /**
     * The array of resolved mailers.
     *
     * @var array
     */
    protected $mailers = [];

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * Create a new Mail manager instance.
     *
     * @param \Terablaze\Container\ContainerInterface $container
     * @return void
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * Get a mailer instance by name.
     *
     * @param string|null $name
     * @return \Terablaze\Mail\MailerInterface
     */
    public function mailer($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->mailers[$name] = $this->get($name);
    }

    /**
     * Get a mailer driver instance.
     *
     * @param string|null $driver
     * @return \Terablaze\Mail\Mailer
     */
    public function driver($driver = null)
    {
        return $this->mailer($driver);
    }

    /**
     * Attempt to get the mailer from the local cache.
     *
     * @param string $name
     * @return \Terablaze\Mail\Mailer
     */
    protected function get($name)
    {
        return $this->mailers[$name] ?? $this->resolve($name);
    }

    /**
     * Resolve the given mailer.
     *
     * @param string $name
     * @return \Terablaze\Mail\Mailer
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Mailer [{$name}] is not defined.");
        }

        // Once we have created the mailer instance we will set a container instance
        // on the mailer. This allows us to resolve mailer classes via containers
        // for maximum testability on said classes instead of passing Closures.
        $mailer = new Mailer(
            $name,
            $this->container->get(View::class),
            $this->createSymfonyTransport($config),
            $this->container->get(EventDispatcherInterface::class)
        );

        if ($this->container->has(QueueManagerInterface::class)) {
            $mailer->setQueue($this->container->get(QueueManagerInterface::class));
        }

        // Next we will set all of the global addresses on this mailer, which allows
        // for easy unification of all "from" addresses as well as easy debugging
        // of sent messages since these will be sent to a single email address.
        foreach (['from', 'reply_to', 'to', 'return_path'] as $type) {
            $this->setGlobalAddress($mailer, $config, $type);
        }

        return $mailer;
    }

    /**
     * Create a new transport instance.
     *
     * @param array $config
     * @return \Symfony\Component\Mailer\Transport\TransportInterface
     *
     * @throws \InvalidArgumentException
     */
    public function createSymfonyTransport(array $config)
    {
        $transport = $config['transport'];

        if (isset($this->customCreators[$transport])) {
            return call_user_func($this->customCreators[$transport], $config);
        }

        if (trim($transport ?? '') === '' || !method_exists($this, $method = 'create' . ucfirst($transport) . 'Transport')) {
            throw new InvalidArgumentException("Unsupported mail transport [{$transport}].");
        }

        return $this->{$method}($config);
    }

    /**
     * Create an instance of the Symfony SMTP Transport driver.
     *
     * @param array $config
     * @return \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport
     */
    protected function createSmtpTransport(array $config)
    {
        $factory = new EsmtpTransportFactory;

        $transport = $factory->create(new Dsn(
            !empty($config['encryption']) && $config['encryption'] === 'tls' ? (($config['port'] == 465) ? 'smtps' : 'smtp') : '',
            $config['host'],
            $config['username'] ?? null,
            $config['password'] ?? null,
            $config['port'] ?? null,
            $config
        ));

        return $this->configureSmtpTransport($transport, $config);
    }

    /**
     * Configure the additional SMTP driver options.
     *
     * @param \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport $transport
     * @param array $config
     * @return \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport
     */
    protected function configureSmtpTransport(EsmtpTransport $transport, array $config)
    {
        $stream = $transport->getStream();

        if ($stream instanceof SocketStream) {
            if (isset($config['source_ip'])) {
                $stream->setSourceIp($config['source_ip']);
            }

            if (isset($config['timeout'])) {
                $stream->setTimeout($config['timeout']);
            }
        }

        return $transport;
    }

    /**
     * Create an instance of the Symfony Sendmail Transport driver.
     *
     * @param array $config
     * @return \Symfony\Component\Mailer\Transport\SendmailTransport
     */
    protected function createSendmailTransport(array $config)
    {
        return new SendmailTransport($config['path']);
    }

    /**
     * Create an instance of the Symfony Amazon SES Transport driver.
     *
     * @param array $config
     * @return \Terablaze\Mail\Transport\SesTransport
     */
    protected function createSesTransport(array $config)
    {
        $config = array_merge(
            Helpers::getConfig('services.ses', []),
            ['version' => 'latest', 'service' => 'email'],
            $config
        );

        $config = ArrayMethods::except($config, ['transport']);

        return new SesTransport(
            new SesClient($this->addSesCredentials($config)),
            $config['options'] ?? []
        );
    }

    /**
     * Add the SES credentials to the configuration array.
     *
     * @param array $config
     * @return array
     */
    protected function addSesCredentials(array $config)
    {
        if (!empty($config['key']) && !empty($config['secret'])) {
            $config['credentials'] = ArrayMethods::only($config, ['key', 'secret', 'token']);
        }

        return ArrayMethods::except($config, ['token']);
    }

    /**
     * Create an instance of the Symfony Mail Transport driver.
     *
     * @return \Symfony\Component\Mailer\Transport\SendmailTransport
     */
    protected function createMailTransport()
    {
        return new SendmailTransport;
    }

    /**
     * Create an instance of the Symfony Mailgun Transport driver.
     *
     * @param array $config
     * @return \Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunApiTransport
     */
    protected function createMailgunTransport(array $config)
    {
        $factory = new MailgunTransportFactory(null, $this->getHttpClient($config));

        if (!isset($config['secret'])) {
            $config = Helpers::getConfig('services.mailgun', []);
        }

        return $factory->create(new Dsn(
            'mailgun+' . ($config['scheme'] ?? 'https'),
            $config['endpoint'] ?? 'default',
            $config['secret'],
            $config['domain']
        ));
    }

    /**
     * Create an instance of the Symfony Postmark Transport driver.
     *
     * @param array $config
     * @return \Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport
     */
    protected function createPostmarkTransport(array $config)
    {
        $factory = new PostmarkTransportFactory(null, $this->getHttpClient($config));

        $options = isset($config['message_stream_id'])
            ? ['message_stream' => $config['message_stream_id']]
            : [];

        return $factory->create(new Dsn(
            'postmark+api',
            'default',
            $config['token'] ?? Helpers::getConfig('services.postmark.token'),
            null,
            null,
            $options
        ));
    }

    /**
     * Create an instance of the Symfony Failover Transport driver.
     *
     * @param array $config
     * @return \Symfony\Component\Mailer\Transport\FailoverTransport
     */
    protected function createFailoverTransport(array $config)
    {
        $transports = [];

        foreach ($config['mailers'] as $name) {
            $config = $this->getConfig($name);

            if (is_null($config)) {
                throw new InvalidArgumentException("Mailer [{$name}] is not defined.");
            }

            $transports[] = $this->createSymfonyTransport($config);
        }

        return new FailoverTransport($transports);
    }

    /**
     * Create an instance of the Log Transport driver.
     *
     * @param array $config
     * @return \Terablaze\Mail\Transport\LogTransport
     */
    protected function createLogTransport(array $config)
    {
        $logger = $this->container->make(LoggerInterface::class);

        if ($logger instanceof LogManager) {
            $logger = $logger->channel(
                $config['channel']
            );
        }

        return new LogTransport($logger);
    }

    /**
     * Create an instance of the Array Transport Driver.
     *
     * @return \Terablaze\Mail\Transport\ArrayTransport
     */
    protected function createArrayTransport()
    {
        return new ArrayTransport;
    }

    /**
     * Get a configured Symfony HTTP client instance.
     *
     * @return \Symfony\Contracts\HttpClient\HttpClientInterface|null
     */
    protected function getHttpClient(array $config)
    {
        if ($options = ($config['client'] ?? false)) {
            $maxHostConnections = ArrayMethods::pull($options, 'max_host_connections', 6);
            $maxPendingPushes = ArrayMethods::pull($options, 'max_pending_pushes', 50);

            return HttpClient::create($options, $maxHostConnections, $maxPendingPushes);
        }
    }

    /**
     * Set a global address on the mailer by type.
     *
     * @param \Terablaze\Mail\Mailer $mailer
     * @param array $config
     * @param string $type
     * @return void
     */
    protected function setGlobalAddress($mailer, array $config, string $type)
    {
        $address = ArrayMethods::get($config, $type, Helpers::getConfig('mail.' . $type));

        if (is_array($address) && isset($address['address'])) {
            $mailer->{'always' . StringMethods::studly($type)}($address['address'], $address['name']);
        }
    }

    /**
     * Get the mail connection configuration.
     *
     * @param string $name
     * @return array
     */
    protected function getConfig(string $name)
    {
        return Helpers::getConfig("mail.mailers.{$name}");
    }

    /**
     * Get the default mail driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return Helpers::getConfig('mail.default');
    }

    /**
     * Set the default mail driver name.
     *
     * @param string $name
     * @return void
     */
    public function setDefaultDriver(string $name)
    {
        Helpers::setConfig('mail.default', $name);
    }

    /**
     * Disconnect the given mailer and remove from local cache.
     *
     * @param string|null $name
     * @return void
     */
    public function purge($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        unset($this->mailers[$name]);
    }

    /**
     * Register a custom transport creator Closure.
     *
     * @param string $driver
     * @param \Closure $callback
     * @return $this
     */
    public function extend($driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get the application instance used by the manager.
     *
     * @return \Terablaze\Container\ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set the application instance used by the manager.
     *
     * @param \Terablaze\Container\ContainerInterface $container
     * @return $this
     */
    public function setContainer($container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Forget all of the resolved mailer instances.
     *
     * @return $this
     */
    public function forgetMailers()
    {
        $this->mailers = [];

        return $this;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->mailer()->$method(...$parameters);
    }
}
