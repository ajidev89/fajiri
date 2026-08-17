<?php

namespace App\Http\Repository;

use App\Enums\Poll\PollStatus;
use App\Enums\Poll\PollType;
use App\Http\Repository\Contracts\PollRepositoryInterface;
use App\Http\Resources\Poll\PollResource;
use App\Http\Resources\Poll\PollResponseResource;
use App\Http\Traits\ResponseTrait;
use App\Http\Traits\AuthUserTrait;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollResponse;
use Illuminate\Support\Facades\DB;

class PollRepository implements PollRepositoryInterface
{
    use ResponseTrait, AuthUserTrait;

    public function __construct(
        protected Poll $poll,
        protected PollOption $pollOption,
        protected PollResponse $pollResponse,
    ) {}

    /**
     * List all polls with optional search, filter, and sort.
     */
    public function index($request)
    {
        $user = auth('sanctum')->user() ?? auth()->user();
        $isAdmin = $user && (
            $user->hasPermission('poll_management') ||
            $user->hasRole('super-admin') ||
            ($user->role && $user->role->slug === 'super-admin')
        );

        $query = $this->poll->with(['options', 'addedBy.profile']);

        if ($request->filled('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        if ($request->filled('status')) {
            $status = strtolower(trim($request->status));
            $status = match ($status) {
                'open', 'ongoing', 'published', 'active' => PollStatus::ACTIVE->value,
                'closed', 'ended', 'inactive', 'expired' => PollStatus::INACTIVE->value,
                'draft' => PollStatus::DRAFT->value,
                default => $status,
            };
            $query->where('status', $status);
        } elseif (!$isAdmin) {
            // Regular app users default to active polls
            $query->where('status', PollStatus::ACTIVE->value);
        }

        if ($request->filled('type')) {
            $type = strtolower(trim($request->type));
            $query->where('type', $type);
        }

        $sortBy    = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $polls = $query->paginate($request->per_page ?? 15);

        return $this->handleSuccessCollectionResponse('Polls fetched successfully', PollResource::collection($polls));
    }

    /**
     * Show a single poll — increments view counter.
     */
    public function show($poll)
    {
        $poll->increment('views');
        $poll->load(['options', 'addedBy.profile']);
        return $this->handleSuccessResponse('Poll fetched successfully', new PollResource($poll));
    }

    /**
     * Create a new poll with its options.
     */
    public function store($request)
    {
        DB::beginTransaction();
        try {
            $poll = $this->poll->create([
                'title'          => $request->title,
                'type'           => $request->type,
                'status'         => $request->status ?? PollStatus::DRAFT->value,
                'start_date'     => $request->start_date,
                'duration_hours' => $request->duration_hours,
                'added_by'       => $this->user()->id,
            ]);

            $this->syncOptions($poll, $request->options ?? []);

            DB::commit();
            $poll->load(['options', 'addedBy.profile']);
            return $this->handleSuccessResponse('Poll created successfully', new PollResource($poll), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleErrorResponse('Failed to create poll: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing poll and re-sync its options.
     */
    public function update($request, $poll)
    {
        DB::beginTransaction();
        try {
            $poll->update([
                'title'          => $request->title,
                'type'           => $request->type,
                'status'         => $request->status ?? $poll->status->value,
                'start_date'     => $request->start_date,
                'duration_hours' => $request->duration_hours,
            ]);

            $this->syncOptions($poll, $request->options ?? []);

            DB::commit();
            $poll->load(['options', 'addedBy.profile']);
            return $this->handleSuccessResponse('Poll updated successfully', new PollResource($poll));
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleErrorResponse('Failed to update poll: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Soft-delete a poll.
     */
    public function destroy($poll)
    {
        $poll->delete();
        return $this->handleSuccessResponse('Poll deleted successfully');
    }

    /**
     * Return full poll summary: vote breakdown, stats, and recent responses.
     */
    public function summary($poll)
    {
        $poll->load(['options.responses', 'responses.user.profile']);

        $totalVotes   = $poll->responses()->count();
        $totalViews   = $poll->views;
        $participants = $poll->responses()->distinct('user_id')->count('user_id');

        $options = $poll->options->map(function ($option) use ($totalVotes) {
            $votes = $option->responses->count();
            return [
                'id'              => $option->id,
                'label'           => $option->label,
                'votes_count'     => $votes,
                'vote_percentage' => $totalVotes > 0 ? round(($votes / $totalVotes) * 100, 1) : 0,
            ];
        });

        $recentResponses = $poll->responses()
            ->with(['user.profile', 'option'])
            ->latest()
            ->take(5)
            ->get();

        return $this->handleSuccessResponse('Poll summary fetched successfully', [
            'poll'             => new PollResource($poll),
            'options'          => $options,
            'stats'            => [
                'total_votes'        => $totalVotes,
                'total_views'        => $totalViews,
                'participants_count' => $participants,
            ],
            'recent_responses' => PollResponseResource::collection($recentResponses),
        ]);
    }

    /**
     * Return paginated list of all responses for a poll.
     */
    public function responses($poll, $request)
    {
        $responses = $poll->responses()
            ->with(['user.profile', 'option'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->handleSuccessCollectionResponse(
            'Responses fetched successfully',
            PollResponseResource::collection($responses)
        );
    }

    /**
     * Submit a vote on a poll (authenticated users only).
     * For radio type: one response per user (replaces previous).
     * For checkbox type: replaces all previous selections.
     * For text types: replaces previous text answer.
     */
    public function vote($request, $poll)
    {
        $user = $this->user();
        $type = $poll->type;

        // Validate poll is active
        if ($poll->status !== PollStatus::ACTIVE) {
            return $this->handleErrorResponse('This poll is not currently active.', 403);
        }

        // Check if poll has ended
        if (now()->greaterThan($poll->ends_at)) {
            return $this->handleErrorResponse('This poll has ended.', 403);
        }

        DB::beginTransaction();
        try {
            // Remove any existing responses from this user on this poll
            $this->pollResponse->where('poll_id', $poll->id)->where('user_id', $user->id)->delete();

            if (in_array($type->value, [PollType::RADIO->value, PollType::CHECKBOX->value])) {
                $optionIds = (array) $request->option_ids;

                // RADIO polls must have exactly one selected option
                if ($type->value === PollType::RADIO->value && count($optionIds) > 1) {
                    return $this->handleErrorResponse('Radio polls can only have one selected option.', 422);
                }

                foreach ($optionIds as $optionId) {
                    $this->pollResponse->create([
                        'poll_id'        => $poll->id,
                        'user_id'        => $user->id,
                        'poll_option_id' => $optionId,
                    ]);
                }
            } else {
                $this->pollResponse->create([
                    'poll_id'       => $poll->id,
                    'user_id'       => $user->id,
                    'text_response' => $request->text_response,
                ]);
            }

            DB::commit();
            return $this->handleSuccessResponse('Vote submitted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleErrorResponse('Failed to submit vote: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Sync options for a poll — deletes old ones and creates new ones.
     */
    private function syncOptions(Poll $poll, array $options): void
    {
        $poll->options()->delete();

        foreach ($options as $index => $option) {
            $poll->options()->create([
                'label' => $option['label'],
                'order' => $option['order'] ?? $index,
            ]);
        }
    }
}
