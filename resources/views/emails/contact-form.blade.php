<x-mail::message>
# New Web Form Submission

You have received a new message from the "Get in Touch" form.

**From:** {{ $data['first_name'] }} {{ $data['last_name'] }}  
**Email:** {{ $data['email'] }}  
**Subject:** {{ $data['subject'] }}

<x-mail::panel>
**Message:**

{{ $data['message'] }}
</x-mail::panel>

You can reply directly to this email to respond to {{ $data['first_name'] }}.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
