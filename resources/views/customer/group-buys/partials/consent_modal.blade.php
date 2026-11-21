<div class="modal fade" id="consentModal" tabindex="-1" role="dialog" aria-labelledby="consentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="consentModalLabel">Group Consent Terms</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><strong>Group:</strong> {{ $group->item?->name }}</p>
                <p><strong>Store:</strong> {{ $group->store?->name }}</p>
                <p><strong>Your share:</strong> GH₵ {{ number_format($memberShare ?? 0, 2) }}</p>
                <hr>
                <p>{{ $group->consent_text ?? 'No additional consent terms for this group.' }}</p>
                <div class="alert alert-info small mt-3">
                    By joining, you agree to the consent terms above. No payment is taken until the group is full and you checkout your share.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Not now</button>
                <a href="{{ route('customer.group-buys.join', $group->id) }}" class="btn btn-primary">I accept &amp; join</a>
            </div>
        </div>
    </div>
</div>
