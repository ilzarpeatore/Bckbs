@php
    $heart = $heart ?? [
        'filled_heart' => getfilledHeartSvg(24),
        'blank_heart' => getblankHeartSvg(24),
    ];

    $bookmark = $bookmark ?? [
        'filled_bookmark' => getFilledBookmarkSvg(24),
        'blank_bookmark' => getBlankBookmarkSvg(24),
    ];
@endphp

<div class="card posting-card rounded-3">
    <div class="card-header d-flex justify-content-between">
        <div class="header-title">
            <div class="d-flex justify-content-center flex-wrap gap-3">
                <img class="img-fluid rounded-circle p-1 border border-2 border-primary border-dotted avatar-50"
                     alt="profile-img"
                     loading="lazy"
                     src="{{ getSingleMedia(optional($post->user), 'profile_image', null) }}">
                <div class="media-support-info">
                    <div class="d-flex align-items-center mb-2 gap-2">
                        <h6 class="mb-0">{{ optional($post->user)->display_name ?? '-' }}</h6>
                    </div>
                    <p class="mb-0 text-muted">{{ timeAgoFormate($post->created_at) }}</p>
                </div>
            </div>
        </div>
        @if( $is_frontend )
            <div class="dropdown">
                <span class="d-flex align-items-center h5 mb-0 cursor-pointer" id="dropdownMenuButtonDeletePost" data-bs-toggle="dropdown" aria-expanded="false">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                        <path d="M240-400q-33 0-56.5-23.5T160-480q0-33 23.5-56.5T240-560q33 0 56.5 23.5T320-480q0 33-23.5 56.5T240-400Zm240 0q-33 0-56.5-23.5T400-480q0-33 23.5-56.5T480-560q33 0 56.5 23.5T560-480q0 33-23.5 56.5T480-400Zm240 0q-33 0-56.5-23.5T640-480q0-33 23.5-56.5T720-560q33 0 56.5 23.5T800-480q0 33-23.5 56.5T720-400Z"/>
                    </svg>
                </span>
                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButtonDeletePost">
                    @if( !$post->can_edit && $is_frontend )
                        <a class="dropdown-item" href="javascript:void(0);" data-modal-form="form" data-size="small" class="cursor-pointer" data--href="{{ route('postreport', ['posting_id' => $post->id ]) }}" data-app-title="{{ __('frontend::message.report') }}" id="report-{{ $post->id }}-section" data-placement="bottom">
                            {{ __('frontend::message.report') }}
                        </a>
                    @endif

                    @if( $post->can_edit )
                        <a class="dropdown-item" href="javascript:void(0);" data-modal-form="form" data-size="lg" data--href="{{ route('community.post.create', ['posting_id' => $post->id, 'mode' => 'text']) }}" data-app-title="">
                            <svg class="icon-20 me-2" width="20" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.83H5v-.92l9.06-9.06.92.92L5.92 20.08zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34a.9959.9959 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                            </svg>
                            {{ __('message.edit') }}
                        </a>

                        <a class="dropdown-item" href="{{ route('postdelete', $post->id) }}" data--submit='confirm_form' data--confirmation--comment='true' data--ajax='true' data-toggle='tooltip' data-title='{{ __("message.delete_form_title", ["form" =>  __("message.posting") ]) }}' data-message='{{ __("message.delete_alert", ["form" =>  __("message.posting") ]) }}'>
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

    <div class="card-body">
        @if( $is_frontend )
            <p class="read-more-text post-description-text">{!! truncate_multiline_with_read_more($post->description, 4, 50, 'read-more-text', 'read-toggle') ?? '...' !!}</p>
        @else
            <p class="read-more-text post-description-text">{!! nl2br(e(html_entity_decode($post->description ?? '-', ENT_QUOTES, 'UTF-8'))) !!}</p>
        @endif
        {{-- Media Section --}}
        @if(count($post->posting_media) > 0)
            <div class="user-post mt-2">
                <div class="swiper posting-media">
                    <div class="swiper-wrapper">
                        @foreach ($post->posting_media as $media)
                            <div class="swiper-slide">
                                @if(Str::startsWith($media['mime_type'], 'image/'))
                                    <a data-fslightbox="gallery-{{ $post->id }}" href="{{ $media['url'] }}">
                                        <img src="{{ $media['url'] }}" alt="post-image" class="img-fluid media-preview">
                                    </a>
                                @elseif(Str::startsWith($media['mime_type'], 'video/'))
                                    <a data-fslightbox="gallery-{{ $post->id }}" href="{{ $media['url'] }}">
                                        <video class="media-preview" controls controlsList="nodownload noremoteplayback"
                                               disablePictureInPicture oncontextmenu="return false;" playsinline>
                                            <source src="{{ $media['url'] }}" type="{{ $media['mime_type'] }}">
                                        </video>
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="swiper-pagination position-static"></div>
                </div>
            </div>
        @endif

        {{-- Likes & Comments --}}
        <div class="comment-area pt-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="total-like-block d-flex align-items-center">
                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2 toggle-favorite" data-id="{{ $post->id }}" data-type="posting">
                            @if( $is_frontend )
                                @if( $post->is_liked )
                                    {!! $heart['filled_heart'] !!}
                                @else
                                   {!! $heart['blank_heart'] !!}
                                @endif
                            @else
                                <svg class="icon-20" width="24" viewBox="0 0 24 24">
                                    <path fill="currentColor" d="M12.1,18.55L12,18.65L11.89,18.55C7.14,14.24 4,11.39 4,8.5C4,6.5 5.5,5 7.5,5C9.04,5 10.54,6 11.07,7.36H12.93C13.46,6 14.96,5 16.5,5C18.5,5 20,6.5 20,8.5C20,11.39 16.86,14.24 12.1,18.55M16.5,3C14.76,3 13.09,3.81 12,5.08C10.91,3.81 9.24,3 7.5,3C4.42,3 2,5.41 2,8.5C2,12.27 5.4,15.36 10.55,20.03L12,21.35L13.45,20.03C18.6,15.36 22,12.27 22,8.5C22,5.41 19.58,3 16.5,3Z"></path>
                                </svg>
                            @endif
                        </a>
                        <span class="ms-1 like-count" data-id="{{ $post->id }}">
                            <span class="like_count">
                                {{ $post->posting_like_count }}
                            </span>
                            
                            @if( $post->posting_like_count > 0 )
                                <a data-modal-form="form" data-size="small" class="cursor-pointer" data--href="{{ route('postlikes', ['posting_id' => $post->id ]) }}" data-app-title="{{ __('message.title_likes') }}" id="postlike-{{ $post->id }}-section" data-placement="bottom">
                                    <span class="like_label_text">
                                        {{ $post->posting_like_count > 1 ? __('message.likes') : __('message.like')}}
                                    </span>
                                </a>
                            @else
                                <span class="like_label_text">
                                    {{ __('message.like') }}
                                </span>
                            @endif
                        </span>
                    </div>

                    <div class="total-comment-block d-flex align-items-center">
                        <a data-modal-form="form" data-size="lg" class="cursor-pointer"
                            data--href="{{ route('post.comment', ['posting_id' => $post->id ]) }}"
                            data-app-title="{{ __('message.comments') }}"
                            id="comment-{{ $post->id }}-section"
                            data-placement="bottom">
                            <svg class="icon-20" width="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M9,22A1,1 0 0,1 8,21V18H4A2,2 0 0,1 2,16V4C2,2.89 2.9,2 4,2H20A2,2 0 0,1 22,4V16A2,2 0 0,1 20,18H13.9L10.2,21.71C10,21.9 9.75,22 9.5,22V22H9M10,16V19.08L13.08,16H20V4H4V16H10Z"></path>
                            </svg>
                        </a>
                        <span class="ms-1">{{ $post->comment_count }} {{ $post->comment_count > 1 ? __('message.count_comments') : __('message.count_comment') }}</span>
                    </div>
                </div>
                {{-- BOOKMARK --}}
                <div class="total-bookmark-block d-flex align-items-end">
                    <a href="javascript:void(0);" class="toggle-favorite" data-id="{{ $post->id }}" data-type="bookmark">
                        @if($is_frontend)
                            @if( $post->is_bookmark )
                                {!! $bookmark['filled_bookmark'] !!}
                            @else
                                {!! $bookmark['blank_bookmark'] !!}
                            @endif
                        @else
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M6 2H18V20L12 16L6 20V2Z" stroke="var(--site-color)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" fill="none"></path>
                            </svg>
                        @endif
                    </a>
                </div>   
            </div>
        </div>
    </div>
</div>
