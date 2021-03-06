<?php

namespace App;

use App\Presenters\EventPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;
use Robbo\Presenter\PresentableInterface;


class Event extends Model implements Feedable, PresentableInterface
{
    public $fillable = ['title', 'organizer', 'description', 'url', 'image_url', 'date', 'approved'];

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query) {
        return $query->where('approved', 1);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param  string $searchText
     * @param  string $searchDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchApproved($query, $searchText='', $searchDate='', $timeOffset=null) {
        $now = Carbon::now();
        if ($timeOffset) {
            $ci = CarbonInterval::fromString($timeOffset);
            $now->sub($ci);
        }
        $query = $query->where('date', '>=', $now->toDateTimeString())
                    ->where('approved', 1)
                    ->orderBy('date', 'ASC');

        if ($searchText) {
            $query->where(function($query) use ($searchText) {
                $query->where('title', 'like', $searchText)
                    ->orWhere('description', 'like', $searchText)
                    ->orWhere('organizer', 'like', $searchText);
            });
        }

        if ($searchDate) {
            $query->where(DB::raw('date(date)'), '=', $searchDate);
        }

        return $query;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param  Carbon $now
     * @param  integer $minutesBeforeStart
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOnLiveSoon($query, Carbon $now, $minutesBeforeStart) {
        $endTime = $now->copy();
        $ci = CarbonInterval::fromString($minutesBeforeStart.'m');
        $endTime->add($ci);

        $query = $query->where('date', '>=', $now->toDateTimeString())
                    ->where('date', '<=', $endTime->toDateTimeString())
                    ->where('approved', 1)
                    ->orderBy('date', 'ASC');

        return $query;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param  string $searchDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDaily($query, $searchDate='') {
        $query = $query->where(DB::raw('date(date)'), '=', $searchDate)
                    ->where('approved', 1)
                    ->orderBy('date', 'ASC');

        return $query;
    }

    /**
     * @return Spatie\Feed\FeedItem
     */
    public function toFeedItem()
    {
        $presenter = $this->getPresenter();
        return FeedItem::create([
            'id' => sha1($this->id),
            'title' => $this->title,
            'summary' => $presenter->descriptionHtml,
            'updated' => Carbon::create($this->date),
            'link' => $this->url,
            'author' => $this->organizer ? : __('foto-diretta.no-organizer'),
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getAllFeedItems()
    {
       return self::searchApproved()->get();
    }

    /**
     * @return \App\Presenters\EventPresenter
     */
    public function getPresenter()
    {
        return new EventPresenter($this);
    }
}
