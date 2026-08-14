@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel' || empty(trim($slot)))
<img src="{{ asset('logo.png') }}" class="logo" alt="{{ config('app.name', 'Fajiri') }}" style="max-height: 45px; width: auto;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
