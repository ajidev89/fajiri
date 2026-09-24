<?php

namespace App\Console\Commands;

use App\Enums\User\Status;
use App\Jobs\SendGlobalAnnouncementJob;
use App\Models\Announcement;
use App\Models\Profile;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AnnounceBirthdays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'birthdays:announce';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Announce today\'s birthdays to every user in one grouped message.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = now();

        if ($this->alreadyAnnounced($today)) {
            $this->info('Birthday announcements were already sent today.');

            return self::SUCCESS;
        }

        $names = $this->celebrantNames($today);

        if ($names === []) {
            $this->info('No birthdays to announce today.');

            return self::SUCCESS;
        }

        $announcement = Announcement::create([
            'title' => 'Happy Birthday',
            'content' => $this->message($names),
            'target_audience' => ['all'],
        ]);

        SendGlobalAnnouncementJob::dispatch($announcement);

        $this->info('Announced birthdays for '.$this->joinNames($names).'.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function celebrantNames(Carbon $today): array
    {
        $profiles = Profile::query()
            ->whereNotNull('dob')
            ->where(function ($query) use ($today) {
                $query->where(function ($query) use ($today) {
                    $query->whereMonth('dob', $today->month)
                        ->whereDay('dob', $today->day);
                });

                if ($today->month === 2 && $today->day === 28 && ! $today->isLeapYear()) {
                    $query->orWhere(function ($query) {
                        $query->whereMonth('dob', 2)
                            ->whereDay('dob', 29);
                    });
                }
            })
            ->whereHas('user', function ($query) {
                $query->where('status', Status::ACTIVE->value);
            })
            ->get();

        return $this->displayNames($profiles);
    }

    private function alreadyAnnounced(Carbon $today): bool
    {
        return Announcement::query()
            ->whereDate('created_at', $today->toDateString())
            ->where('title', 'Happy Birthday')
            ->where('content', 'like', '%birthday%')
            ->exists();
    }

    /**
     * @param  Collection<int, Profile>  $profiles
     * @return list<string>
     */
    private function displayNames(Collection $profiles): array
    {
        $named = $profiles->map(function (Profile $profile) {
            $first = trim((string) $profile->first_name);
            $last = trim((string) $profile->last_name);

            return [
                'first' => $first !== '' ? $first : $last,
                'last' => $last,
            ];
        })->filter(fn (array $name) => $name['first'] !== '');

        $firstCounts = $named->countBy(fn (array $name) => mb_strtolower($name['first']));

        return $named
            ->map(function (array $name) use ($firstCounts) {
                $sharedFirstName = $firstCounts[mb_strtolower($name['first'])] > 1 && $name['last'] !== '';

                return $sharedFirstName ? $name['first'].' '.$name['last'] : $name['first'];
            })
            ->sort(fn (string $left, string $right) => strcasecmp($left, $right))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $names
     */
    private function message(array $names): string
    {
        $list = $this->joinNames($names);
        $count = count($names);

        $opening = match ($count) {
            1 => "{$list} has a birthday today.",
            2 => "{$list} have their birthdays today.",
            default => "{$list} all have their birthdays today.",
        };

        $closing = $count === 1
            ? "Wishing {$list} a joyful birthday."
            : 'Wishing them a joyful birthday.';

        return $opening."\n\n"
            .'Please take a moment to gift them and appreciate the joy they bring to our community. A kind word, a gift, or a simple wish can make their day brighter.'
            ."\n\n"
            .$closing;
    }

    /**
     * @param  list<string>  $names
     */
    private function joinNames(array $names): string
    {
        $names = array_values($names);
        $count = count($names);

        if ($count <= 1) {
            return $names[0] ?? '';
        }

        if ($count === 2) {
            return $names[0].' and '.$names[1];
        }

        $last = array_pop($names);

        return implode(', ', $names).' and '.$last;
    }
}
