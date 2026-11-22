@php
    /** @var \App\Models\OrganizationSetting|null $organization */
    $organization = $organization ?? \App\Models\OrganizationSetting::current();
@endphp
@if ($organization->report_footer)
    <p style="margin-top:18px; font-size:9px; color:#9ca3af;">
        {{ $organization->report_footer }}
    </p>
@endif
