<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class PostingCard extends Component
{
    public $post, $is_frontend, $heart, $bookmark;

    /**
     * Create a new component instance.
     */
    public function __construct($post, $isFrontend = false, $heart = null, $bookmark=null)
    {
        $this->post = $post;
        $this->is_frontend =  filter_var($isFrontend, FILTER_VALIDATE_BOOLEAN);
        $this->heart = $heart;
        $this->bookmark = $bookmark;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.posting-card');
    }
}
