{{-- A refund over the approval amount (App\Support\RefundApproval): approve it, or who approved it --}}
@if(\App\Support\RefundApproval::needed($refundable))
    <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 space-y-1" data-refund-approval>
        @if(\App\Support\RefundApproval::approved($refundable))
            <p>Approved by {{ \App\Models\User::find($refundable->refund_approved_by)?->name ?? 'a staff member' }} on {{ $refundable->refund_approved_at->format('d M Y H:i') }}.@if((int) $refundable->refund_approved_by === (int) auth()->id()) Someone else records it.@endif</p>
        @else
            <p>Over {{ \App\Support\Money::format(\App\Support\RefundApproval::threshold()) }}: a second staff member approves it before it is sent.</p>
            @if(\App\Support\StaffAccess::can($approveRoute))
                <form method="POST" action="{{ route($approveRoute, $refundable) }}">@csrf<button class="rounded border border-amber-400 bg-white px-2 py-1 font-semibold hover:bg-amber-100">Approve this refund</button></form>
            @endif
        @endif
    </div>
@endif
