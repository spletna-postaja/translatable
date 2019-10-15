<?php

namespace Laraplus\Data;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Query\Grammars\SqlServerGrammar;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class TranslatableScope implements Scope
{
    protected $table;

    protected $i18nTable;

    protected $locale;

    protected $fallback;

    protected $joinType = 'leftJoin';

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param EloquentBuilder $builder
     * @param Eloquent $model
     *
     * @return void
     */
    public function apply(EloquentBuilder $builder, Eloquent $model)
    {
        $this->table = $model->getTable();
        $this->locale = $model->getLocale();
        $this->i18nTable = $model->getI18nTable();
        $this->fallback = $model->getFallbackLocale();

        if (! Str::startsWith($this->table, 'laravel_reserved_')) {
            $this->createJoin($builder, $model);
            $this->createWhere($builder, $model);
            $this->createSelect($builder, $model);
        }
    }

    /**
     * Create the join clause.
     *
     * @param EloquentBuilder $builder
     * @param Eloquent $model
     */
    protected function createJoin(EloquentBuilder $builder, Eloquent $model)
    {
        $joinType = $this->getJoinType($model);
        $clause = $this->getJoinClause($model, $this->locale, $this->i18nTable);

        $builder->$joinType($this->i18nTable, $clause);

        if ($model->shouldFallback()) {
            $clause = $this->getJoinClause($model, $this->fallback, $this->i18nTable.'_fallback');

            $builder->$joinType("{$this->i18nTable} as {$this->i18nTable}_fallback", $clause);
        }
    }

    /**
     * Return the join type.
     *
     * @param Eloquent $model
     *
     * @return string
     */
    protected function getJoinType(Eloquent $model)
    {
        $innerJoin = ! $model->shouldFallback() && $model->getOnlyTranslated();

        return $innerJoin ? 'join' : 'leftJoin';
    }

    /**
     * Return the join clause.
     *
     * @param Eloquent $model
     * @param string $locale
     * @param string $alias
     *
     * @return callable
     */
    protected function getJoinClause(Eloquent $model, $locale, $alias)
    {
        return function (JoinClause $join) use ($model, $locale, $alias) {
            $primary = $model->getKeyName();
            $foreign = $model->getForeignKey();
            $langKey = $model->getLocaleKey();

            $join->on($alias.'.'.$foreign, '=', $this->table.'.'.$primary)
                ->where($alias.'.'.$langKey, '=', $locale);
        };
    }

    /**
     * Create the where clause.
     *
     * @param EloquentBuilder $builder
     * @param Eloquent $model
     */
    protected function createWhere(EloquentBuilder $builder, Eloquent $model)
    {
        if ($model->getOnlyTranslated() && $model->shouldFallback()) {
            $key = $model->getForeignKey();
            $primary = "{$this->i18nTable}.{$key}";
            $fallback = "{$this->i18nTable}_fallback.{$key}";
            $ifNull = $builder->getQuery()->compileIfNull($primary, $fallback);

            $builder->whereRaw("$ifNull is not null");
        }
    }

    /**
     * Create the select clause.
     *
     * @param EloquentBuilder $builder
     * @param Eloquent $model
     */
    protected function createSelect(EloquentBuilder $builder, Eloquent $model)
    {
        if ($builder->getQuery()->columns) {
            return;
        }

        $select = $this->formatColumns($builder, $model);

        $builder->select(array_merge([$this->table.'.*'], $select));
    }

    /**
     * Format the columns.
     *
     * @param EloquentBuilder $builder
     * @param Eloquent $model
     *
     * @return array
     */
    protected function formatColumns(EloquentBuilder $builder, Eloquent $model)
    {
        $map = function ($field) use ($builder, $model) {
            if (! $model->shouldFallback()) {
                return "{$this->i18nTable}.{$field}";
            }

            $primary = "{$this->i18nTable}.{$field}";
            $fallback = "{$this->i18nTable}_fallback.{$field}";
            $alias = $field;

            return new Expression($builder->getQuery()->compileIfNull($primary, $fallback, $alias));
        };

        return array_map($map, $model->translatableAttributes());
    }

    /**
     * Return string based on null type.
     *
     * @param Grammar $grammar
     *
     * @return string
     */
    protected function getIfNull(Grammar $grammar)
    {
        return $grammar instanceof SqlServerGrammar ? 'isnull' : 'ifnull';
    }

    /**
     * Extend the builder.
     *
     * @param EloquentBuilder $builder
     */
    public function extend(EloquentBuilder $builder)
    {
        $builder->macro('onlyTranslated', function (EloquentBuilder $builder, $locale = null) {
            $builder->getModel()->setOnlyTranslated(true);

            if ($locale) {
                $builder->getModel()->setLocale($locale);
            }

            return $builder;
        });

        $builder->macro('withUntranslated', function (EloquentBuilder $builder) {
            $builder->getModel()->setOnlyTranslated(false);

            return $builder;
        });

        $builder->macro('withFallback', function (EloquentBuilder $builder, $fallbackLocale = null) {
            $builder->getModel()->setWithFallback(true);

            if ($fallbackLocale) {
                $builder->getModel()->setFallbackLocale($fallbackLocale);
            }

            return $builder;
        });

        $builder->macro('withoutFallback', function (EloquentBuilder $builder) {
            $builder->getModel()->setWithFallback(false);

            return $builder;
        });

        $builder->macro('translateInto', function (EloquentBuilder $builder, $locale) {
            if ($locale) {
                $builder->getModel()->setLocale($locale);
            }

            return $builder;
        });

        $builder->macro('withoutTranslations', function (EloquentBuilder $builder) {
            $builder->withoutGlobalScope(static::class);

            return $builder;
        });

        $builder->macro('withAllTranslations', function (EloquentBuilder $builder) {
            $builder->withoutGlobalScope(static::class)->with('translations');

            return $builder;
        });
    }
}
