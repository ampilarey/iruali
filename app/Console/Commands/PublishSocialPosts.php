<?php

namespace App\Console\Commands;

use App\Models\SocialPost;
use App\Services\SocialPublisher;
use Illuminate\Console\Command;

/**
 * Every five minutes: approved social posts whose time has come go out.
 */
class PublishSocialPosts extends Command
{
    protected $signature = 'social:publish';

    protected $description = 'Post approved social posts that are due';

    public function handle(SocialPublisher $publisher): int
    {
        SocialPost::where('status', 'approved')->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->limit(20)->get()
            ->each(function (SocialPost $post) use ($publisher) {
                $publisher->publish($post);
                $this->info('Post #'.$post->id.': '.$post->status.'.');
            });

        return self::SUCCESS;
    }
}
