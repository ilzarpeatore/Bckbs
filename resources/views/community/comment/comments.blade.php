@foreach( $data['comments'] as $comment )
    <x-comment-card :comment="$comment" />
@endforeach
