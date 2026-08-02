<script>
    (function () {
        window.communityComposer = window.communityComposer || {};

        let emojiPickerModulePromise = null;
        const emojiPickerModulePath = @json(asset('js/emoji-picker-element/index.js'));
        const emojiPickerDataPath = @json(asset('js/emoji-picker-element-data/en/emojibase/data.json'));
        const communityLang = {
            emojiPickerFailedToLoad: @json(__('message.emoji_picker_failed_to_load')),
        };
        const emojiPickerUi = {
            width: 340,
            height: 250,
            gap: 8,
            padding: 12,
        };
        let activeEmojiContext = null;
        let emojiEventsBound = false;
        let emojiThemeObserverBound = false;

        function loadEmojiPickerModule() {
            if (!emojiPickerModulePromise) {
                emojiPickerModulePromise = import(emojiPickerModulePath)
                    .then(function () {
                        return true;
                    })
                    .catch(function () {
                        return false;
                    });
            }

            return emojiPickerModulePromise;
        }

        function bindEmojiEvents() {
            if (emojiEventsBound) {
                return;
            }

            emojiEventsBound = true;

            document.addEventListener('click', function (event) {
                if (!activeEmojiContext) {
                    return;
                }

                const picker = activeEmojiContext.picker;
                const toggle = activeEmojiContext.toggle;

                if (picker.contains(event.target) || toggle.contains(event.target)) {
                    return;
                }

                picker.classList.add('d-none');
                activeEmojiContext = null;
            });
        }

        function isDarkThemeActive() {
            const html = document.documentElement;
            const body = document.body;

            return Boolean(
                html?.classList.contains('dark') ||
                html?.classList.contains('dark-mode') ||
                body?.classList.contains('dark') ||
                body?.classList.contains('dark-mode') ||
                html?.getAttribute('data-bs-theme') === 'dark' ||
                body?.getAttribute('data-bs-theme') === 'dark'
            );
        }

        function syncEmojiPickerTheme(target) {
            if (!target) {
                return;
            }

            const pickerElement = target.matches?.('emoji-picker')
                ? target
                : target.querySelector?.('emoji-picker');

            if (!pickerElement) {
                return;
            }

            const isDark = isDarkThemeActive();

            pickerElement.classList.toggle('dark', isDark);
            pickerElement.classList.toggle('light', !isDark);
        }

        function bindEmojiThemeObserver() {
            if (emojiThemeObserverBound || typeof MutationObserver === 'undefined') {
                return;
            }

            emojiThemeObserverBound = true;

            const observer = new MutationObserver(function () {
                document.querySelectorAll('emoji-picker').forEach(function (pickerElement) {
                    syncEmojiPickerTheme(pickerElement);
                });
            });

            [document.documentElement, document.body].forEach(function (node) {
                if (!node) {
                    return;
                }

                observer.observe(node, {
                    attributes: true,
                    attributeFilter: ['class', 'data-bs-theme'],
                });
            });
        }

        function ensureEmojiPickerLoaded(picker, options) {
            if (!picker || picker.dataset.loaded === '1' || picker.dataset.loading === '1') {
                return;
            }

            picker.dataset.loading = '1';

            loadEmojiPickerModule().then(function (loaded) {
                if (!loaded) {
                    picker.innerHTML = '<div class="small text-danger p-2">' + communityLang.emojiPickerFailedToLoad + '</div>';
                    picker.dataset.loaded = '1';
                    delete picker.dataset.loading;
                    return;
                }

                if (picker.dataset.loaded === '1') {
                    delete picker.dataset.loading;
                    return;
                }

                const pickerElement = document.createElement('emoji-picker');
                pickerElement.setAttribute('locale', 'en');
                pickerElement.style.width = '100%';
                pickerElement.style.height = options?.height || '100%';
                pickerElement.setAttribute('data-source', emojiPickerDataPath);
                syncEmojiPickerTheme(pickerElement);

                pickerElement.addEventListener('emoji-click', function (event) {
                    if (typeof options?.onSelect === 'function') {
                        options.onSelect(event.detail.unicode);
                    }
                });

                picker.appendChild(pickerElement);
                picker.dataset.loaded = '1';
                delete picker.dataset.loading;
            });
        }

        function setupTextareaAutoResize(textarea) {
            if (!textarea) {
                return function () {};
            }

            let rafId = null;

            function resize() {
                rafId = null;
                textarea.style.height = 'auto';
                textarea.style.height = `${textarea.scrollHeight}px`;
            }

            function scheduleResize() {
                if (rafId) {
                    return;
                }

                rafId = requestAnimationFrame(resize);
            }

            textarea.addEventListener('input', scheduleResize);
            scheduleResize();

            return scheduleResize;
        }

        function setEmojiPickerPosition(toggle, picker) {
            if (!toggle || !picker) {
                return;
            }

            if (picker.parentNode !== document.body) {
                document.body.appendChild(picker);
            }

            const anchor = toggle.closest?.('.community-comment-compose-box') || toggle;
            const rect = anchor.getBoundingClientRect();
            const pickerWidth = picker.offsetWidth || emojiPickerUi.width;
            const pickerHeight = picker.offsetHeight || emojiPickerUi.height;
            const gap = emojiPickerUi.gap;
            const padding = emojiPickerUi.padding;
            const dirAttr = (
                toggle.closest?.('[dir]')?.getAttribute('dir') ||
                document.documentElement?.getAttribute('dir') ||
                document.body?.getAttribute('dir') ||
                'ltr'
            ).toLowerCase();
            const isRtl = dirAttr === 'rtl';
            
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;

            const minLeft = padding;
            const maxLeft = viewportWidth - pickerWidth - padding;
            let leftViewport = isRtl ? rect.right - pickerWidth : rect.left;
            leftViewport = Math.min(Math.max(minLeft, leftViewport), maxLeft);

            const topMin = padding;
            const topMax = viewportHeight - padding;
            const belowSpace = topMax - rect.bottom;
            const aboveSpace = rect.top - topMin;
            const needsSpace = pickerHeight + gap;
            const openBelow = belowSpace >= needsSpace || belowSpace > aboveSpace;

            let topViewport;
            if (openBelow) {
                topViewport = rect.bottom + gap;
                if (topViewport + pickerHeight > topMax) {
                    topViewport = Math.max(topMin, topMax - pickerHeight);
                }
            } else {
                topViewport = rect.top - pickerHeight - gap;
                if (topViewport < topMin) {
                    topViewport = topMin;
                }
            }

            // Since we reparented it to the body, fixed positioning relative to viewport is the most robust solution.
            picker.style.position = 'fixed';
            picker.style.left = `${leftViewport}px`;
            picker.style.top = `${topViewport}px`;
            picker.style.setProperty('--emoji-left', `${leftViewport}px`);
            picker.style.setProperty('--emoji-top', `${topViewport}px`);
        }

        function setupEmojiPicker(options) {
            const toggle = options?.toggle;
            const picker = options?.picker;
            const textarea = options?.textarea;
            const resizeTextarea = options?.resizeTextarea || function () {};
            const pickerHeight = options?.pickerHeight || '100%';

            if (!toggle || !picker || !textarea) {
                return;
            }

            bindEmojiEvents();
            bindEmojiThemeObserver();

            function insertEmoji(emoji) {
                const start = textarea.selectionStart ?? textarea.value.length;
                const end = textarea.selectionEnd ?? textarea.value.length;
                const value = textarea.value;

                textarea.value = value.slice(0, start) + emoji + value.slice(end);
                textarea.focus();

                const nextPos = start + emoji.length;
                textarea.setSelectionRange(nextPos, nextPos);
                resizeTextarea();
            }

            // Prevent Bootstrap modal from stealing focus back when user clicks the search input
            picker.addEventListener('focusin', function (event) {
                event.stopPropagation();
            });

            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                ensureEmojiPickerLoaded(picker, {
                    height: pickerHeight,
                    onSelect: insertEmoji,
                });

                if (activeEmojiContext && activeEmojiContext.picker !== picker) {
                    activeEmojiContext.picker.classList.add('d-none');
                }

                if (picker.classList.contains('d-none')) {
                    syncEmojiPickerTheme(picker);
                    setEmojiPickerPosition(toggle, picker);
                    picker.classList.remove('d-none');
                    activeEmojiContext = {
                        toggle: toggle,
                        picker: picker,
                    };
                } else {
                    picker.classList.add('d-none');
                    activeEmojiContext = null;
                }

                textarea.focus();
            });
        }

        window.communityComposer.setupTextareaAutoResize = setupTextareaAutoResize;
        window.communityComposer.setupEmojiPicker = setupEmojiPicker;

        function initCommunityCommentComposer(root) {
            const scope = root instanceof Element ? root : document;

            scope.querySelectorAll('.community-comment-compose-form').forEach(function (form) {
                if (form.dataset.communityCommentInit === '1') {
                    return;
                }

                const textarea = form.querySelector('.community-comment-compose-input');
                const emojiToggle = form.querySelector('#community-comment-emoji-toggle');
                const emojiPicker = form.querySelector('#community-comment-emoji-picker');

                if (!textarea || !emojiToggle || !emojiPicker) {
                    return;
                }

                form.dataset.communityCommentInit = '1';

                const resizeCommentTextarea = setupTextareaAutoResize(textarea);

                form.communityCommentRefresh = function () {
                    resizeCommentTextarea();
                };

                setupEmojiPicker({
                    toggle: emojiToggle,
                    picker: emojiPicker,
                    textarea: textarea,
                    resizeTextarea: resizeCommentTextarea,
                });
            });
        }

        window.communityComposer.initCommentComposers = initCommunityCommentComposer;
        window.initCommunityCommentComposers = initCommunityCommentComposer;

        window.communityComposer.refreshCommentComposers = function (root) {
            const scope = root instanceof Element ? root : document;

            scope.querySelectorAll('.community-comment-compose-form').forEach(function (form) {
                if (typeof form.communityCommentRefresh === 'function') {
                    form.communityCommentRefresh();
                }
            });
        };
        window.refreshCommunityCommentComposers = window.communityComposer.refreshCommentComposers;

        document.addEventListener('DOMContentLoaded', function () {
            window.initCommunityCommentComposers(document);
        });
    })();

    $(document).on('click','[data--confirmation--comment="true"]',function(e){
        e.preventDefault();
        var form = $(this).attr('data--submit');

        var title = $(this).attr('data-title');

        var message = $(this).attr('data-message');

        var ajaxtype = $(this).attr('data--ajax');
        if(form == 'confirm_form') {
            $('#confirm_form').attr('action', $(this).attr('href'));
        }
        let __this = this

        confirmationModal(form,title,message,ajaxtype,__this);
    });
    function submitAjaxCommentForm(form, currentButton) {
        $('form').validator('update');
        var current = currentButton || form.find('[data-comment-form="ajax"]').first();
        current.addClass('disabled');

        var url  = form.attr('action');
        var fd   = new FormData(form[0]);

        $.ajax({
            type: "POST",
            url: url,
            data: fd, // serializes form's elements.
            success: function (e) {
                if (e.redirect) {
                    window.location.href = e.redirect;
                    return;
                }

                if (e.status === true) {
                    // Pre-clear textareas and file inputs before handling events so that any programmatic .modal('hide') 
                    // inside the event handlers bypasses the 'unsaved changes' check.
                    form.find('.community-comment-compose-input, .community-post-textarea').val('');
                    form.find('input[type="file"]').val('');

                    if (
                        (e.event === 'postcreated' || e.event === 'postupdated') &&
                        typeof window.handleFrontendCommunityPostEvent === 'function'
                    ) {
                        if (window.handleFrontendCommunityPostEvent(e) === true) {
                            return;
                        }
                    }

                    switch (e.event) {
                        case "submited":
                            showMessage(e.message);
                            $(".modal").modal('hide');
                            $('.dataTable').DataTable().ajax.reload( null, false );
                        break;

                        case "refresh":
                            window.location.reload();
                        break;

                        case "callback":
                            showMessage(e.message);
                            $(".modal").modal('hide');
                            location.reload();
                        break;

                        case "norefresh":
                            showMessage(e.message);
                            $(".modal").modal('hide');
                            getAssignList(e.type);
                        break;

                        case "report":
                            showMessage(e.message);
                            @if (Route::has('community'))
                            if($('.posting-card').length < 0){
                                window.location.href = "{{ route('community') }}";
                            }
                            @endif
                            $('#posting-'+e.posting_id).remove();
                            $(".modal").modal('hide');
                        break;

                        case "comment":
                            if (e.is_updated) {
                                $('.comment-'+e.id).html(e.data);
                            } else {
                                $('#append_comment_data').prepend(e.data);
                            }
                            $('.comment-id').val('');
                            $('.comment').val('');
                            if (typeof window.refreshCommunityCommentComposers === 'function') {
                                window.refreshCommunityCommentComposers(document);
                            }
                            scrollToComment('comment-' + e.id);
                            resetCommentForm('comment');
                        break;

                        case "commentreply":
                            $('.comment').val('');
                            if (typeof window.refreshCommunityCommentComposers === 'function') {
                                window.refreshCommunityCommentComposers(document);
                            }
                            const commentId = e.comment_id;
                            
                            const newReplyHtml = e.data;
                            
                            const replySection = $('#replyComment-' + commentId);
                            const replyList = $('#reply-comment-list-' + commentId);
                            const replyButton = $('[data-comment-id="' + commentId + '"].comment-reply');
                            const loadMoreButton = replySection.find('.load-more-replies');
                            const isVisible = replySection.hasClass('show');

                            $('.commentreply-'+e.id).replaceWith(e.data);
                            resetCommentForm('commentreply');
                            if ( e.is_updated ) {
                                return;
                            }
                            if( isVisible && loadMoreButton.length == 0) {
                                replyList.append(newReplyHtml);
                            } else {
                                
                                // replyButton.closest('#lreply-'+commentId).after(newReplyHtml);
                                let replyList = $('#lreply-' + commentId);

                                // Find all existing replies
                                let existingReplies = replyList.nextAll('[id^="commentreply-"]').last();
                                
                                if (existingReplies.length == 0) {
                                    replyList.after(newReplyHtml);
                                } else {
                                    existingReplies.after(newReplyHtml);
                                }
                            }
                        break;

                        default:
                            console.warn("Unhandled event type:", e.event);
                            break;
                    }
                }
                if (e.status == false) {
                    if (e.event == 'validation') {
                        errorMessage(e.message);
                        if( e.posting_id != undefined ) {
                            if( e.type != undefined && e.type == 'report' ) {
                                return;   
                            }
                            $('#posting-'+e.posting_id).remove();
                            $(".modal").modal('hide');
                        }
                    }
                }
            },
            error: function(error) {
                const message = error?.responseJSON?.message;
                if (message) {
                    errorMessage(message);
                }
            },
            complete: function() {
                current.removeClass('disabled');
            },
            cache: false,
            contentType: false,
            processData: false,
        });
    }

    // remove previous bindings (prevents double firing)
    $(document).off('click', '[data-comment-form="ajax"]');
    $(document).on('click', '[data-comment-form="ajax"]', function(f) {
        f.preventDefault();
        submitAjaxCommentForm($(this).closest('form'), $(this));
    });

    $(document).off('submit', '.community-comment-form');
    $(document).on('submit', '.community-comment-form', function(f) {
        f.preventDefault();
        submitAjaxCommentForm($(this));
    });

    function confirmationModal(form,title = "{{ __('message.confirmation') }}",message = "{{ __('message.delete_msg') }}",ajaxtype=false,_this)
    {
        const storageDark = localStorage.getItem('theme');
        const theme = (storageDark == "light") ? 'material' : 'dark';
        $.confirm({
            content: message,
            type: '',
            title: title,
            scrollToPreviousElement: false,
            scrollToPreviousElementAnimate: false,
            buttons: {
                yes: {
                    action: function () {

                        if(ajaxtype == 'true') {
                            let url = _this;

                            let data = $('[data--submit="'+form+'"]').serializeArray();
                            $.post(url, data).then(response => {
                                if(response.status) {
                                    if (jQuery.inArray(response.event, [ 'comment', 'commentreply' ]) !== -1) {
                                        $('.'+response.event+"-"+response.id).remove();

                                        resetCommentForm(response.event);
                                        if( response.event == 'commentreply' && response.comment_reply_count == 0 ) {
                                            $('#view-reply-line-'+response.comment_id).remove();
                                        }
                                    }
                                    if( response.event == 'posting' ) {
                                        $('#posting-'+response.id).remove();
                                    }
                                    showMessage(response.message)
                                }
                                if(response.status == false){
                                    errorMessage(response.message)
                                }
                            })
                        } else {
                            if (form !== undefined && form){
                                $(document).find('[data--submit="'+form+'"]').submit();
                            }else{
                                return true;
                            }
                        }
                    }
                },
                no: {
                    action: function () {}
                },
            },
            theme: theme
        });
        return false;
    }

    function scrollToComment(commentId) {
        const modalBody = $('.modal-body');
        const comment = $('#' + commentId);

        if (modalBody.length && comment.length) {
            modalBody.animate({
                scrollTop: modalBody.scrollTop() + comment.position().top - 10
            }, 600);
        }
    }

    function errorMessage(message) {
        Swal.fire({
            icon: 'error',
            title: "{{ __('message.opps') }}",
            text: message,
            confirmButtonColor: "var(--bs-primary)",
            confirmButtonText: "{{ __('message.ok') }}"
        });
    }

    function showMessage(message) {
        Swal.fire({
            icon: 'success',
            title: "{{ __('message.done') }}",
            text: message,
            confirmButtonColor: "var(--bs-primary)",
            confirmButtonText: "{{ __('message.ok') }}"
        });
    }

    // Modal Close Protection for unsaved text
    $(document).on('hide.bs.modal', '.modal', function (e) {
        const modal = $(this);
        
        // If we previously bypassed it in the dialog
        if (modal.data('bypass-close-warning')) {
            modal.removeData('bypass-close-warning');
            return;
        }

        const inputs = modal.find('.community-comment-compose-input, .community-post-textarea');
        const fileInputs = modal.find('input[type="file"]');
        let hasContent = false;
        let isPostContent = false;
        let isCommentContent = false;

        inputs.each(function () {
            if ($(this).val().trim() !== '') {
                hasContent = true;
                if ($(this).hasClass('community-post-textarea')) {
                    isPostContent = true;
                } else if ($(this).hasClass('community-comment-compose-input')) {
                    isCommentContent = true;
                }
            }
        });

        fileInputs.each(function () {
            if (this.files && this.files.length > 0) {
                hasContent = true;
                isPostContent = true; // Assuming only posts have file attachments
            }
        });

        if (hasContent) {
            e.preventDefault();

            const storageDark = localStorage.getItem('theme');
            const theme = (storageDark == "light") ? 'material' : 'dark';
            
            let popupContent = '{{ __("message.leave_page_content") }}';
            if (isPostContent) {
                popupContent = '{{ __("message.leave_post_content") }}';
            } else if (isCommentContent) {
                popupContent = '{{ __("message.leave_comment_content") }}';
            }

            $.confirm({
                title: '{{ __("message.leave_page") }}',
                content: popupContent,
                theme: theme,
                scrollToPreviousElement: false,
                scrollToPreviousElementAnimate: false,
                buttons: {
                    leave: {
                        text: '{{ __("message.leave_page") }}',
                        btnClass: 'btn-danger',
                        action: function () {
                            inputs.val(''); // Clear it so it won't fire again
                            fileInputs.val(''); 
                            modal.data('bypass-close-warning', true);
                            setTimeout(function() {
                                modal.modal('hide');
                            }, 10);
                        }
                    },
                    stay: {
                        text: '{{ __("message.stay_on_page") }}',
                        btnClass: 'btn-primary',
                        action: function () {}
                    }
                }
            });
        }
    });

</script>
<script>
    $(document).on('click', '.comment-read-toggle', function (event) {
        event.preventDefault();

        const $toggle = $(this);
        const $wrapper = $toggle.closest('.comment-read-more-text');
        const $dots = $wrapper.find('.dots');
        const $moreText = $wrapper.find('.more-text');

        $dots.toggleClass('d-none');
        $moreText.toggleClass('d-none');
        $toggle.text(
            $moreText.hasClass('d-none')
                ? '{{ __("message.read_more") }}'
                : '{{ __("message.read_less") }}'
        );
    });
</script>
