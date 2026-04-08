<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\EventRepositoryInterface;
use App\Http\Requests\Event\EventRequest;
use App\Http\Requests\Event\AttendEventRequest;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(protected EventRepositoryInterface $eventRepository)
    {
    }

    public function index(Request $request)
    {
        return $this->eventRepository->index($request);
    }

    public function show($slug)
    {
        return $this->eventRepository->show($slug);
    }

    public function store(EventRequest $request)
    {
        return $this->eventRepository->store($request);
    }

    public function update(EventRequest $request, $id)
    {
        return $this->eventRepository->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->eventRepository->destroy($id);
    }

    public function attend($event_id)
    {
        return $this->eventRepository->attend($event_id);
    }

    public function attendExternal(AttendEventRequest $request, $event_id)
    {
        return $this->eventRepository->attendExternal($request, $event_id);
    }

    public function initializePaystack(AttendEventRequest $request, $event_id)
    {
        return $this->eventRepository->initializePaystack($request, $event_id);
    }

    public function verifyPaystack(Request $request)
    {
        $reference = $request->reference;
        if (!$reference) {
            return $this->handleErrorResponse("No reference provided", 400);
        }
        return $this->eventRepository->verifyPaystack($reference);
    }
}
