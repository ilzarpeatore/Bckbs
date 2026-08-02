<div class="col-md-12">
    @if( auth()->check() )
        <span class="{{ isset($data) && $data['comment_user_name'] ? '' : 'd-none' }} comment-user-name">{{ $data['comment_user_name'] ?? '' }}</span>
        <a href="javascript:void(0);" class="remove-comment-reply {{ isset($data) && $data['comment_user_name'] ? '' : 'd-none' }}">
            <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#EA3323">
                <path d="m336-280 144-144 144 144 56-56-144-144 144-144-56-56-144 144-144-144-56 56 144 144-144 144 56 56ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/>
            </svg>
        </a>
        {{ html()->form('POST', route('save.comment.reply'))->attribute('class', 'community-comment-compose-form mt-1')->attribute('data-toggle', 'validator')->open() }}
            
            <input type="hidden" name="comment_id" value="{{ $data['comment_id'] ?? '' }}" class="comment-id">
            <input type="hidden" name="posting_id" value="{{ $posting_id }}" class="posting-id">
            <input type="hidden" name="comment_type" value="{{ $data['type'] ?? 'comment' }}" class="comment-type">
            <input type="hidden" name="comment_reply_id" value="{{ $data['comment_reply_id'] ?? '' }}" class="comment-reply-id">

            <div class="community-comment-compose-box">
                <textarea
                    name="comment"
                    class="form-control comment community-comment-compose-input"
                    required
                    rows="2"
                    placeholder="{{ __('message.write_a_comment') }}"
                >{{ $data['comment'] ?? '' }}</textarea>

                <div class="community-comment-compose-actions">
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="p-0 border-0 bg-transparent text-primary" id="community-comment-emoji-toggle" aria-label="Emoji">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                                <circle cx="9" cy="10" r="1" fill="currentColor"/>
                                <circle cx="15" cy="10" r="1" fill="currentColor"/>
                                <path d="M8 14.5C9 16 10.28 16.75 12 16.75C13.72 16.75 15 16 16 14.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </button>
                        <div id="community-comment-emoji-picker" class="community-comment-emoji-picker d-none"></div>
                    </div>

                    <button type="submit" id="btn_submit" data-comment-form="ajax" class="btn btn-sm btn-primary font-size-12">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                            <path d="M120-160v-640l760 320-760 320Zm80-120 474-200-474-200v140l240 60-240 60v140Zm0 0v-400 400Z"/>
                        </svg>
                    </button>
                </div>
            </div>
        {{ html()->form()->close() }}
    @else
        <div class="d-flex justify-content-center">
            <a href="{{ route('frontend.signin') }}" class="btn btn-primary">
                {{ __('auth.sign_in') }}
            </a>
        </div>
    @endif
</div>


