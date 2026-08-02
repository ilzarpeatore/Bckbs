@if( $data['comments']->count() > 0 )
    <ul class="list-inline mt-2 mb-0" id="append_comment_data">
        @include('community.comment.comments', $data)
    </ul>
    <div class="d-flex justify-content-center">
        <span class="post-loader d-none" data-loader="comments"></span>
    </div>
@else
<ul class="list-inline mt-2 mb-0" id="append_comment_data"></ul>
@endif

<script>
    var page = 1;
    var loading = false;
    var totalPages = "{{ $data['pagination']['totalPages'] ?? 1 }}";
    var posting_id = "{{ $data['posting_id'] ?? '' }}";

    $('.modal-body').on('scroll', function() {
        let modalBody = $(this);

        if (
            modalBody.scrollTop() + modalBody.innerHeight() >= modalBody[0].scrollHeight - 100 &&
            !loading && page < totalPages
        ) {
            loading = true; // lock before calling
            page++;
            infinteLoadMore(page);
        }
    });

    function infinteLoadMore(page) {
        $('[data-loader="comments"]').removeClass('d-none');

        $.ajax({
            url: "{{ route('post.comment') }}",
            type: 'GET',
            data: { posting_id: posting_id, page: page, type: 'comment' },
        })
        .done(function(response) {
            if (response.pagination.currentPage <= response.pagination.totalPages) {
                $('#append_comment_data').append(response.data);
            }

            loading = false; // unlock after load
            $('[data-loader="comments"]').addClass('d-none');
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            $('[data-loader="comments"]').addClass('d-none');
            loading = false;
        });
    }

    $(document).on('click', '.toggle-reply-btn', function() {
        let $this = $(this);
        let target = $this.attr('data-bs-target');
        let isExpanded = $this.attr('aria-expanded') === 'false';
        $(target).collapse('toggle');
        $this.text(isExpanded ? "{{ __('message.view_reply') }}" : "{{ __('message.hide_reply') }}").attr('aria-expanded', !isExpanded);
    });

    $(document).off('click', '.load-more-replies').on('click', '.load-more-replies', function (e) {
        e.preventDefault();
        let comment_id = $(this).data('comment-id');
        let rply_page = parseInt($(this).data('page'));
        rply_page++; // next page to load
        let btn = $(this);
        btn.prop('disabled', true).text("{{ __('message.loading') }}");

        $.ajax({
            url: "{{ route('comment.reply') }}",
            type: 'GET',
            data: { comment_id: comment_id, page: rply_page },
        })
        .done(function(response) {
            if (response.pagination.currentPage <= response.pagination.totalPages) {
                $('#reply-comment-list-'+comment_id).append(response.data);
                btn.data('page', rply_page); // update page number

                if (rply_page >= response.pagination.totalPages) {
                    btn.remove(); // remove button if no more replies
                } else {
                    btn.prop('disabled', false).text("{{ __('message.load_more_replies') }}");
                }
            } else {
                btn.remove();
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            // console.error('Server error:', errorThrown);
            btn.prop('disabled', false).text("{{ __('message.load_more_replies') }}");
        });
    });

    $(document).off('click', '.comment-reply').on('click', '.comment-reply', function (e) {
        e.preventDefault();
        let comment_id = $(this).data('comment-id');
        let name = $(this).data('comment-user-name');
        $('.comment-user-name').text("{{ __('message.reply_to') }} " + name);
        $('.comment-id').val(comment_id);
        $('.comment-type').val('comment_reply');
        $('.comment-user-name').removeClass('d-none');
        $('.comment-reply-id').val('');
        $('.comment').val('');
        $('.remove-comment-reply').removeClass('d-none');
    });

    $(document).off('click', '.remove-comment-reply').on('click', '.remove-comment-reply', function (e) {
        e.preventDefault();
        resetCommentForm('commentreply');
    });

    $(document).off('click', '.edit-comment').on('click', '.edit-comment', function (e) {
        e.preventDefault();

        let href = $(this).attr('href');
        let type = $(this).attr('data-type');
        $.ajax({
            url: href,
            type: 'GET',
            data : { type: type },
        })
        .done(function(response) {
            if (!response.status) {
                $('.'+response.type+'-'+response.id).remove();
                return;
            }
            $('.form_footer').html(response.data);
            if (typeof window.initCommunityCommentComposers === 'function') {
                window.initCommunityCommentComposers(document.querySelector('.form_footer'));
            }
        });
    });

    function resetCommentForm(type) {
        $('.comment').val('');
        $('.comment-type').val('comment');

        switch (type) {
            case 'comment':
                $('.comment-id').val('');
                $('.comment-user-name').addClass('d-none');
                $('.remove-comment-reply').addClass('d-none');
                break;

            case 'commentreply':
                $('.comment-id').val('');
                $('.comment-type').val('comment');
                $('.comment-reply-id').val('');
                $('.comment-user-name').text('');
                $('.comment-user-name').addClass('d-none');
                $('.remove-comment-reply').addClass('d-none');
                break;

            default:
                break;
        }
    }
</script>
