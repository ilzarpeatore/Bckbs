<li class="mb-1 comment-{{ $comment->id }}" id="comment-{{ $comment->id }}">
    <div class="d-flex gap-3">
        <img alt="userimg" class="avatar-50 rounded-circle img-fluid" loading="lazy" src="{{ getSingleMedia(optional($comment->user), 'profile_image', null) }}">
        <div class="w-100">
            <div class="d-flex align-items-center mb-2 gap-2 gap-lg-4">
                <h6 class="mb-0 comment-title">{{ optional($comment->user)->display_name ?? '-' }}</h6>
                <small class="text-muted">{{ shortTime($comment->created_at) }}</small>
                @if( $comment->can_edit || $comment->can_delete )
                    <div class="dropdown">
                        <span class="d-flex align-items-center h5 mb-0 cursor-pointer" id="dropdownMenuButton04" data-bs-toggle="dropdown" aria-expanded="false">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                <path d="M240-400q-33 0-56.5-23.5T160-480q0-33 23.5-56.5T240-560q33 0 56.5 23.5T320-480q0 33-23.5 56.5T240-400Zm240 0q-33 0-56.5-23.5T400-480q0-33 23.5-56.5T480-560q33 0 56.5 23.5T560-480q0 33-23.5 56.5T480-400Zm240 0q-33 0-56.5-23.5T640-480q0-33 23.5-56.5T720-560q33 0 56.5 23.5T800-480q0 33-23.5 56.5T720-400Z"/>
                            </svg>
                        </span>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton04">
                            @if( $comment->can_edit )
                            <a class="dropdown-item edit-comment" href="{{ route('edit.comment.reply', $comment->id) }}" data-type="comment">
                                <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="me-2">
                                    <path d="M13.7476 20.4428H21.0002" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12.78 3.79479C13.5557 2.86779 14.95 2.73186 15.8962 3.49173C15.9485 3.53296 17.6295 4.83879 17.6295 4.83879C18.669 5.46719 18.992 6.80311 18.3494 7.82259C18.3153 7.87718 8.81195 19.7645 8.81195 19.7645C8.49578 20.1589 8.01583 20.3918 7.50291 20.3973L3.86353 20.443L3.04353 16.9723C2.92866 16.4843 3.04353 15.9718 3.3597 15.5773L12.78 3.79479Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    <path d="M11.021 6.00098L16.4732 10.1881" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                                {{ __('message.edit') }}
                            </a>
                            @endif
                            @if( $comment->can_delete )
                            <a class="dropdown-item" href="{{ route('delete.comment', $comment->id) }}"
                                data--submit='confirm_form'
                                data--confirmation--comment='true' data--ajax='true' data-toggle='tooltip'
                                title='{{ __("message.delete_form_title", ["form" =>  __("message.comment") ]) }}'
                                data-title='{{ __("message.delete_form_title", ["form" =>  __("message.comment") ]) }}'
                                data-message='{{ __("message.delete_alert", ["form" =>  __("message.comment") ]) }}'
                            >
                                <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="me-2">
                                    <path d="M19.3248 9.46826C19.3248 9.46826 18.7818 16.2033 18.4668 19.0403C18.3168 20.3953 17.4798 21.1893 16.1088 21.2143C13.4998 21.2613 10.8878 21.2643 8.27979 21.2093C6.96079 21.1823 6.13779 20.3783 5.99079 19.0473C5.67379 16.1853 5.13379 9.46826 5.13379 9.46826" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    <path d="M20.708 6.23975H3.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    <path d="M17.4406 6.23973C16.6556 6.23973 15.9796 5.68473 15.8256 4.91573L15.5826 3.69973C15.4326 3.13873 14.9246 2.75073 14.3456 2.75073H10.1126C9.53358 2.75073 9.02558 3.13873 8.87558 3.69973L8.63258 4.91573C8.47858 5.68473 7.80258 6.23973 7.01758 6.23973" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                                {{ __('message.delete') }}
                            </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
            <p class="mb-1 comment-description">{!! truncate_multiline_with_read_more($comment->comment, 4, 40, 'comment-read-more-text', 'comment-read-toggle') !!}</p>
            <div class="d-flex flex-wrap align-items-center mb-2" id="lreply-{{ $comment->id }}">
                @if( auth()->check() )
                <a href="javascript:void(0);" class="me-3 comment-reply" data-comment-id="{{ $comment->id }}" data-comment-user-name="{{ optional($comment->user)->display_name ?? '-' }}">
                    <svg width="24" height="24" class="me-1" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M8,9.8V10.7L9.7,11C12.3,11.4 14.2,12.4 15.6,13.7C13.9,13.2 12.1,12.9 10,12.9H8V14.2L5.8,12L8,9.8M10,5L3,12L10,19V14.9C15,14.9 18.5,16.5 21,20C20,15 17,10 10,9"></path>
                    </svg>
                    {{ __('message.lreply') }}
                </a>
                @endif
            </div>
            
            @if( $comment->comment_reply_count > 0 )
                <div class="d-flex align-items-center view-reply-line" id="view-reply-line-{{ $comment->id }}">
                    <a class="p-0 toggle-reply-btn cursor-pointer" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#replyComment-{{ $comment->id }}" 
                        aria-expanded="false" 
                        aria-controls="replyComment-{{ $comment->id }}">
                        {{ __('message.view_reply') }}
                    </a>
                </div>

                <div class="collapse mt-1" id="replyComment-{{ $comment->id }}">
                    <div id="reply-comment-list-{{ $comment->id }}">
                        <x-comment-reply-card :commentReply="$comment->commentReply" />
                    </div>
                    @if( $comment->comment_reply_count > 5 )
                        <div class="text-center">
                            <button class="btn btn-link load-more-replies p-0" 
                                    data-comment-id="{{ $comment->id }}" 
                                    data-page="1">
                                {{ __('message.load_more_replies') }}
                            </button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</li>
