<?php declare(strict_types=1);

namespace Laraplus\Data;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class TranslationModel extends Eloquent
{
    /**
     * Translation model does not include timestamps by default.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Name of the table (will be set dynamically).
     *
     * @var string
     */
    protected $table = null;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Locale key name.
     *
     * @var string
     */
    protected $localeKey = 'locale';

    /**
     * Set the keys for a save update query.
     *
     * @param EloquentBuilder $query
     *
     * @return EloquentBuilder
     */
    protected function setKeysForSaveQuery($query)
    {
        $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());
        $query->where($this->localeKey, '=', $this->{$this->localeKey});

        return $query;
    }

    /**
     * Set the locale key.
     *
     * @param $localeKey
     *
     * @return $this
     */
    public function setLocaleKey($localeKey)
    {
        $this->localeKey = $localeKey;

        return $this;
    }
}
