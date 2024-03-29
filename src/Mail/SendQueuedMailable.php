<?php

namespace Terablaze\Mail;

use Terablaze\Bus\Traits\QueueableTrait;
use Terablaze\Queue\ShouldBeEncrypted;
use Terablaze\Queue\InteractsWithQueue;

class SendQueuedMailable
{
    use QueueableTrait, InteractsWithQueue;

    /**
     * The mailable message instance.
     *
     * @var \Terablaze\Mail\MailableInterface
     */
    public $mailable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @return int|null
     */
    public $maxExceptions;

    /**
     * Indicates if the job should be encrypted.
     *
     * @var bool
     */
    public $shouldBeEncrypted = false;

    /**
     * Create a new job instance.
     *
     * @param  \Terablaze\Mail\MailableInterface  $mailable
     * @return void
     */
    public function __construct(MailableInterface $mailable)
    {
        $this->mailable = $mailable;
        $this->tries = property_exists($mailable, 'tries') ? $mailable->tries : null;
        $this->timeout = property_exists($mailable, 'timeout') ? $mailable->timeout : null;
        $this->maxExceptions = property_exists($mailable, 'maxExceptions') ? $mailable->maxExceptions : null;
        $this->afterCommit = property_exists($mailable, 'afterCommit') ? $mailable->afterCommit : null;
        $this->shouldBeEncrypted = $mailable instanceof ShouldBeEncrypted;
    }

    /**
     * Handle the queued job.
     *
     * @param  \Terablaze\Mail\MailManagerInterface  $factory
     * @return void
     */
    public function handle(MailManagerInterface $factory)
    {
        $this->mailable->send($factory);
    }

    /**
     * Get the number of seconds before a released mailable will be available.
     *
     * @return mixed
     */
    public function backoff()
    {
        if (! method_exists($this->mailable, 'backoff') && ! isset($this->mailable->backoff)) {
            return;
        }

        return $this->mailable->backoff ?? $this->mailable->backoff();
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime|null
     */
    public function retryUntil()
    {
        if (! method_exists($this->mailable, 'retryUntil') && ! isset($this->mailable->retryUntil)) {
            return;
        }

        return $this->mailable->retryUntil ?? $this->mailable->retryUntil();
    }

    /**
     * Call the failed method on the mailable instance.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function failed($e)
    {
        if (method_exists($this->mailable, 'failed')) {
            $this->mailable->failed($e);
        }
    }

    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function displayName()
    {
        return get_class($this->mailable);
    }

    /**
     * Prepare the instance for cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->mailable = clone $this->mailable;
    }
}
