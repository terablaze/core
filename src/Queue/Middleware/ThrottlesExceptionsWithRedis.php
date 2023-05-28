<?php

namespace Terablaze\Queue\Middleware;

use Terablaze\Redis\RedisManager;
use Terablaze\Redis\Limiters\DurationLimiter;
use Terablaze\Support\Helpers;
use Terablaze\Support\Traits\TimeAware;
use Throwable;

class ThrottlesExceptionsWithRedis extends ThrottlesExceptions
{
    use TimeAware;

    /**
     * The Redis factory implementation.
     *
     * @var RedisManager
     */
    protected $redis;

    /**
     * The rate limiter instance.
     *
     * @var \Terablaze\Redis\Limiters\DurationLimiter
     */
    protected $limiter;

    /**
     * Process the job.
     *
     * @param  mixed  $job
     * @param  callable  $next
     * @return mixed
     */
    public function handle($job, $next)
    {
        $this->redis = Helpers::container()->get(RedisManager::class);

        $this->limiter = new DurationLimiter(
            $this->redis, $this->getKey($job), $this->maxAttempts, $this->decayMinutes * 60
        );

        if ($this->limiter->tooManyAttempts()) {
            return $job->release($this->limiter->decaysAt - $this->currentTime());
        }

        try {
            $next($job);

            $this->limiter->clear();
        } catch (Throwable $throwable) {
            if ($this->whenCallback && ! call_user_func($this->whenCallback, $throwable)) {
                throw $throwable;
            }

            $this->limiter->acquire();

            return $job->release($this->retryAfterMinutes * 60);
        }
    }
}
