<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Mail\ContactFormMail;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * Handle the incoming contact form submission.
     */
    public function submit(ContactRequest $request)
    {
        $data = $request->validated();
        
        // Determine recipient based on 'type' or keywords in subject/message
        $recipient = 'unite@fajiri.org'; // default General
        
        $isDonation = false;
        if (isset($data['type']) && $data['type'] === 'donation') {
            $isDonation = true;
        } else {
            // Fallback keyword check
            $contentToSearch = strtolower($data['subject'] . ' ' . $data['message']);
            $keywords = ['give', 'giving', 'donate', 'donation', 'donations'];
            
            foreach ($keywords as $keyword) {
                if (str_contains($contentToSearch, $keyword)) {
                    $isDonation = true;
                    break;
                }
            }
        }
        
        if ($isDonation) {
            $recipient = 'give@fajiri.org';
        }

        try {
            Mail::to($recipient)->send(new ContactFormMail($data));
            
            return response()->json([
                'status' => 'success',
                'message' => 'Your message has been successfully sent. We will get back to you shortly.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Contact form email failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while sending your message. Please try again later.',
            ], 500);
        }
    }
}
