<!-- Campaign strips (live sales and events) -->
@if(($stripCampaigns ?? collect())->isNotEmpty())
    <section class="space-y-3">
        @foreach($stripCampaigns as $campaign)
            @include('campaigns._banner', ['campaign' => $campaign, 'size' => 'strip'])
        @endforeach
    </section>
@endif
