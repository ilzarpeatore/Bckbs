<ul class="list-unstyled" id="append_like_data">
    @include('community.postlike.like-list', $data)
</ul>
<div class="d-flex justify-content-center">
    <span class="post-loader d-none" data-loader="postlike"></span>
</div>

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
        $('[data-loader="postlike"]').removeClass('d-none');

        $.ajax({
            url: "{{ route('postlikes') }}",
            type: 'GET',
            data: { posting_id: posting_id, page: page, type: 'postlike' },
        })
        .done(function(response) {

            if (response.pagination.currentPage <= response.pagination.totalPages) {
                $('#append_like_data').append(response.data);
            } else {
                console.log('No more pages');
            }

            loading = false; // unlock after load
            $('[data-loader="postlike"]').addClass('d-none');
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            $('[data-loader="postlike"]').addClass('d-none');
            loading = false;
        });
    }