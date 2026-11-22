@php
    /** @var \App\Models\OrganizationSetting|null $organization */
    $organization = $organization ?? \App\Models\OrganizationSetting::current();
@endphp
<table class="org-header" style="width:100%; border-collapse:collapse; margin-bottom:16px;">
    <tr>
        @if ($organization->logoDataUri())
            <td style="width:64px; vertical-align:middle; padding:0 12px 0 0;">
                <img src="{{ $organization->logoDataUri() }}" alt="Logo" style="max-width:56px; max-height:56px;">
            </td>
        @endif
        <td style="vertical-align:middle; padding:0;">
            <div style="font-size:16px; font-weight:bold; color:#0f2744;">
                {{ $organization->name }}
            </div>
            @if ($organization->short_name || $organization->tagline)
                <div style="font-size:11px; color:#6b7280; margin-top:2px;">
                    {{ collect([$organization->short_name, $organization->tagline])->filter()->implode(' · ') }}
                </div>
            @endif
            <div style="font-size:10px; color:#6b7280; margin-top:4px;">
                {{ collect([$organization->phone, $organization->email, $organization->address])->filter()->implode(' · ') }}
            </div>
        </td>
    </tr>
</table>
