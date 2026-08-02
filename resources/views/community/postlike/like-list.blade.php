@foreach($data['post_like'] as $like)
    <li>
        <div class="d-flex align-items-center gap-3 px-3 py-2">
            <img src="{{ getSingleMedia($like->user, 'profile_image', null) }}" class="img-fluid rounded-circle p-1 border border-2 border-primary border-dotted avatar-50" alt="profile">
            <span class="fw-medium">
                {{ optional($like->user)->display_name ?? '-' }}
            </span>
        </div>
    </li>
@endforeach