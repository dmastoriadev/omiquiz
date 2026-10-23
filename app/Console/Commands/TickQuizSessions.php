<?php

namespace App\Console\Commands;

use App\Models\GameSession;
use App\Services\QuizSessionService;
use Illuminate\Console\Command;
use Throwable;

class TickQuizSessions extends Command
{
    protected $signature = 'omiquiz:tick';
    protected $description = 'Advance quiz and drawing deadlines and finalize results, including sessions with no connected browsers';

    public function handle(QuizSessionService $service): int
    {
        $failed = false;
        GameSession::query()->where('status', GameSession::STATUS_IN_PROGRESS)
            ->chunkById(100, function ($sessions) use ($service, &$failed): void {
                foreach ($sessions as $session) {
                    try {
                        $service->advance($session);
                    } catch (Throwable $exception) {
                        $failed = true;
                        report($exception);
                        $this->error('Could not advance session '.$session->slug.'. See the application log.');
                    }
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
