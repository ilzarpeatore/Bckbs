<div class="modal fade" id="reportPostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('frontend::message.report_this_spam') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">{{ __('frontend::message.reason') }}</label>
                <textarea class="form-control"
                          name="reason"
                          id="report_reason"
                          placeholder="{{ __('frontend::message.reason') }}"
                          rows="4"
                          required>
                </textarea>
            </div>
            <div class="modal-footer d-flex justify-content-center">
                @if(auth()->check())
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        {{ __('frontend::message.cancel') }}
                    </button>
                    <button type="button" id="submitReport" class="btn btn-primary">
                        {{ __('frontend::message.report') }}
                    </button>
                @else
                    <a href="{{ route('frontend.signin') }}" class="btn btn-primary">
                        {{ __('auth.sign_in') }}
                    </a>
                @endif
            </div>
            @if(auth()->check())
                <form action="{{ route('posting.report') }}" method="POST" id="reportPostForm">
                    @csrf
                    <input type="hidden" name="posting_id" id="report_posting_id">
                    <input type="hidden" name="reason" id="hidden_reason">
                </form>
            @endif
        </div>
    </div>
</div>
