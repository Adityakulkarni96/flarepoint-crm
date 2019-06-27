<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Lead extends Model
{
    protected $fillable = [
        'title',
        'description',
        'status',
        'user_assigned_id',
        'user_created_id',
        'client_id',
        'contact_date',
    ];
    protected $dates = ['contact_date'];

    protected $hidden = ['remember_token'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_assigned_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_created_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'source');
    }

    public function activity()
    {
        return $this->morphMany(Activity::class, 'source');
    }

    public function getDaysUntilContactAttribute()
    {
        return Carbon::now()->startOfDay()->diffInDays($this->contact_date, false);
    }

    /**
     * Add a reply to the thread.
     *
     * @param array $reply
     *
     * @return Model
     */
    public function addComment($reply)
    {
        $reply = $this->comments()->create($reply);

        return $reply;
    }

    public function scopeMy($query)
    {
        return $query->where('user_assigned_id', '=', Auth::id());
    }
}
