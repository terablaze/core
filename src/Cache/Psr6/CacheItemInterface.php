<?php

namespace Terablaze\Cache\Psr6;

use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;

interface CacheItemInterface extends \Psr\Cache\CacheItemInterface
{
    /**
     * References the Unix timestamp stating when the item will expire.
     */
    public const METADATA_EXPIRY = 'expiry';

    /**
     * References the time the item took to be created, in milliseconds.
     */
    public const METADATA_CTIME = 'ctime';

    /**
     * References the list of tags that were assigned to the item, as string[].
     */
    public const METADATA_TAGS = 'tags';

    /**
     * Reserved characters that cannot be used in a key or tag.
     */
    public const RESERVED_CHARACTERS = '{}()/\@:';

    /**
     * Adds a tag to a cache item.
     *
     * Tags are strings that follow the same validation rules as keys.
     *
     * @param string|string[] $tags A tag or array of tags
     *
     * @return $this
     *
     * @throws InvalidArgumentException When $tag is not valid
     * @throws CacheException           When the item comes from a pool that is not tag-aware
     */
    public function tag($tags): self;

    /**
     * Returns a list of metadata info that were saved alongside with the cached value.
     *
     * See ItemInterface::METADATA_* consts for keys potentially found in the returned array.
     */
    public function getMetadata(): array;
}
