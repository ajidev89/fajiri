<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\EventRepositoryInterface;
use App\Http\Resources\Event\EventResource;
use App\Http\Services\CloudinaryService;
use App\Http\Traits\ResponseTrait;
use App\Http\Traits\AuthUserTrait;
use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Support\Str;
use App\Enums\Attendee\Status;
use App\Services\PaystackService;

class EventRepository implements EventRepositoryInterface
{
    use ResponseTrait, AuthUserTrait;

    public function __construct(
        protected Event $event, 
        protected EventAttendee $eventAttendee,
        protected CloudinaryService $cloudinaryService,
        protected PaystackService $paystackService
    ) {
    }

    public function index($request)
    {
        $events = $this->event->with(['category', 'addedBy'])
            ->when($request->category_id, function ($query) use ($request) {
                return $query->where('category_id', $request->category_id);
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->is_featured, function ($query) use ($request) {
                return $query->where('is_featured', $request->is_featured);
            })
            ->when($request->added_by, function ($query) use ($request) {
                return $query->where('added_by', $request->added_by);
            })
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->handleSuccessCollectionResponse("Events fetched successfully", EventResource::collection($events));
    }

    public function show($slug)
    {
        try {
            $event = $this->event->with(['category', 'addedBy', 'attendees'])->where('slug', $slug)->firstOrFail();
            return $this->handleSuccessResponse("Event fetched successfully", new EventResource($event));
        } catch (\Exception $e) {
            return $this->handleErrorResponse("Event not found", 404);
        }
    }

    public function store($request)
    {
        try {
            $imageUrl = null;
            if ($request->hasFile('image')) {
                $upload = $this->cloudinaryService->uploadImage($request->file('image'), 'events');
                $imageUrl = $upload['url'];
            }

            $event = $this->event->create([
                'added_by' => $this->user()->id,
                'category_id' => $request->category_id,
                'title' => $request->title,
                'slug' => Str::slug($request->title) . '-' . Str::random(5),
                'description' => $request->description,
                'amount' => $request->amount ?? 0,
                'slots' => $request->slots ?? 0,
                'location' => $request->location,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'image' => $imageUrl,
                'status' => $request->status ?? 'upcoming',
                'is_featured' => $request->is_featured ?? false,
            ]);

            return $this->handleSuccessResponse("Event created successfully", new EventResource($event));
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function update($request, $id)
    {
        try {
            $event = $this->event->findOrFail($id);
            
            $data = $request->only([
                'category_id', 'title', 'description', 'location', 
                'start_date', 'end_date', 'status', 'is_featured',
                'amount', 'slots'
            ]);

            if ($request->title) {
                $data['slug'] = Str::slug($request->title) . '-' . Str::random(5);
            }

            if ($request->hasFile('image')) {
                $upload = $this->cloudinaryService->uploadImage($request->file('image'), 'events');
                $data['image'] = $upload['url'];
            }

            $event->update($data);

            return $this->handleSuccessResponse("Event updated successfully", new EventResource($event));
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $event = $this->event->findOrFail($id);
            $event->delete();

            return $this->handleSuccessResponse("Event deleted successfully");
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function attend($event_id)
    {
        try {
            $event = $this->event->findOrFail($event_id);
            $user = $this->user();

            // Check if already attending
            $existing = $this->eventAttendee->where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                return $this->handleErrorResponse("You are already registered for this event", 400);
            }

            if ($event->amount > 0) {
                return $this->handleErrorResponse("Payment required for this event. Please initialize payment.", 402);
            }

            $this->eventAttendee->create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'code' => Str::random(10),
                'name' => $user->profile?->first_name . ' ' . $user->profile?->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => Status::ACTIVE->value,
            ]);

            return $this->handleSuccessResponse("Attendance registered successfully");
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function attendExternal($request, $event_id)
    {
        try {
            $event = $this->event->findOrFail($event_id);

            // Check if already attending via email
            $existing = $this->eventAttendee->where('event_id', $event->id)
                ->where('email', $request->email)
                ->first();

            if ($existing) {
                return $this->handleErrorResponse("This email is already registered for this event", 400);
            }

            if ($event->amount > 0) {
                return $this->handleErrorResponse("Payment required for this event. Please initialize payment.", 402);
            }

            $this->eventAttendee->create([
                'event_id' => $event->id,
                'name' => $request->name,
                'code' => Str::random(10),
                'email' => $request->email,
                'phone' => $request->phone,
                'status' => Status::ACTIVE->value,
            ]);

            return $this->handleSuccessResponse("Attendance registered successfully");
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function initializePaystack($request, $event_id)
    {
        try {
            $event = $this->event->findOrFail($event_id);
            if ($event->amount <= 0) {
                return $this->handleErrorResponse("This is a free event. Use the regular registration endpoint.", 400);
            }

            $user = auth()->user();
            $email = $user ? $user->email : $request->email;
            $name = $user ? ($user->profile?->first_name . ' ' . $user->profile?->last_name) : $request->name;
            $phone = $user ? $user->phone : $request->phone;

            if (!$email || !$name) {
                return $this->handleErrorResponse("Name and email are required for registration.", 400);
            }

            // Check if already registered
            $existing = $this->eventAttendee->where('event_id', $event->id)
                ->where('email', $email)
                ->first();

            if ($existing) {
                if ($existing->status === Status::ACTIVE->value) {
                    return $this->handleErrorResponse("You are already registered for this event.", 400);
                }
                // If exists but inactive, reuse the code/reference
                $reference = $existing->code;
            } else {
                $reference = Str::random(12);
                $this->eventAttendee->create([
                    'event_id' => $event->id,
                    'user_id' => $user->id ?? null,
                    'code' => $reference,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'status' => Status::INACTIVE->value,
                ]);
            }

            $payload = [
                'amount' => $event->amount * 100, // kobo
                'email' => $email,
                'reference' => $reference,
                'callback_url' => config('app.url') . '/api/events/paystack/verify',
                'metadata' => [
                    'event_id' => $event->id,
                    'type' => 'event_attendance'
                ]
            ];

            $result = $this->paystackService->initializeTransaction($payload);

            return $this->handleSuccessResponse("Transaction initialized", $result);
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function verifyPaystack($reference)
    {
        try {
            $data = $this->paystackService->verifyTransaction($reference);

            if ($data['status'] === 'success') {
                $attendee = $this->eventAttendee->where('code', $reference)->first();
                
                if ($attendee && $attendee->status === Status::INACTIVE->value) {
                    $attendee->update(['status' => Status::ACTIVE->value]);
                    return $this->handleSuccessResponse("Payment verified and registration successful", $attendee);
                }
            }

            return $this->handleErrorResponse("Payment verification failed", 400);
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }
}
