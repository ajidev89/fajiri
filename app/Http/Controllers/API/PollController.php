<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\PollRepositoryInterface;
use App\Http\Requests\Poll\PollRequest;
use App\Models\Poll;
use Illuminate\Http\Request;

class PollController extends Controller
{
    public function __construct(protected PollRepositoryInterface $pollRepository) {}

    /**
     * List all polls (admin — paginated with search/filter).
     */
    public function index(Request $request)
    {
        return $this->pollRepository->index($request);
    }

    /**
     * Show a single poll detail.
     */
    public function show(Poll $poll)
    {
        return $this->pollRepository->show($poll);
    }

    /**
     * Create a new poll.
     */
    public function store(PollRequest $request)
    {
        return $this->pollRepository->store($request);
    }

    /**
     * Update an existing poll.
     */
    public function update(PollRequest $request, Poll $poll)
    {
        return $this->pollRepository->update($request, $poll);
    }

    /**
     * Delete a poll.
     */
    public function destroy(Poll $poll)
    {
        return $this->pollRepository->destroy($poll);
    }

    /**
     * Get the full summary of a poll including vote breakdown and stats.
     */
    public function summary(Poll $poll)
    {
        return $this->pollRepository->summary($poll);
    }

    /**
     * Get paginated responses for a poll.
     */
    public function responses(Poll $poll, Request $request)
    {
        return $this->pollRepository->responses($poll, $request);
    }

    /**
     * Submit a vote on a poll (authenticated users).
     */
    public function vote(Request $request, Poll $poll)
    {
        $request->validate([
            'option_ids'    => 'required_without:text_response|array',
            'option_ids.*'  => 'integer|exists:poll_options,id',
            'text_response' => 'required_without:option_ids|string|max:5000',
        ]);

        return $this->pollRepository->vote($request, $poll);
    }
}
