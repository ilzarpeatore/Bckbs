<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\Posting;
use App\Models\ReportComment;

class CommentController extends Controller
{

    public function getCommentList(Request $request)
    {
        $posting = Posting::published()->where('id', request('posting_id'))->first();
        
        $message = __('message.not_found_entry', ['name' => __('message.posting') ]);
        $status_code = 400;

        if( $posting != null ) {

            $comment = Comment::where('posting_id', $posting->id)->excludeReportedComment()->excludeBlockedUsers()->withCount(['commentReply'])->with(['commentReply' => fn ($q) => $q->take(10), 'user']);
    
            $per_page = config('constant.PER_PAGE_LIMIT');
            if ($request->has('per_page') && !empty($request->per_page)) {
                if (is_numeric($request->per_page)) {
                    $per_page = $request->per_page;
                }
                if ($request->per_page == -1) {
                    $per_page = $comment->count();
                }
            }

            $comment = $comment->orderBy('id', 'desc')->paginate($per_page);

           
            $items = CommentResource::collection($comment);
        
            $response = [
                'pagination' => json_pagination_response($items),
                'data' => $items,
            ];
        } else {
            return json_message_response($message, $status_code);
        }
    
        return json_custom_response($response);
    }

    public function saveComment(Request $request)
    {
        $posting = Posting::published()->where('id', request('posting_id'))->first();

        $message = __('message.not_found_entry', ['name' => __('message.posting') ]);
        $status_code = 400;

        if( $posting != null ) {
            $user = auth()->user();

            // Bloqueo de usuario (item 11 del roadmap): ni yo puedo comentar
            // en un post de alguien que bloqueé (o que me bloqueó), en
            // cualquiera de las dos direcciones.
            if ($posting->user_id !== $user->id
                && ($user->hasBlocked($posting->user_id) || $user->isBlockedBy($posting->user_id))) {
                return json_message_response(__('message.not_found_entry', ['name' => __('message.posting')]), 403);
            }

            $data = $request->all();
            $data['user_id'] = $user->id;

            Comment::create($data);
            $message = null;
            $status_code = 200;

        }

        return json_message_response( $message, $status_code);
    }

    public function updateComment(Request $request)
    {
        $posting = Posting::published()->where('id', request('posting_id'))->first();
        
        $message = __('message.not_found_entry', ['name' => __('message.posting') ]);
        $status_code = 400;

        if( $posting != null ) {
            $comment = Comment::myComment()->where('id', request('id'))->where('posting_id', $posting->id)->first();

            $message = __('message.not_found_entry', ['name' => __('message.comment') ]);
            $status_code = 400;

            // CORREGIDO (revision 2026-09-13): estas dos lineas vivian FUERA del
            // if de arriba, asi que un comment_id ajeno o inexistente (incluido
            // el intento de IDOR que myComment() ahora bloquea) devolvia 200
            // "exito" sin haber tocado nada -- ahora sigue devolviendo el 400
            // "not found" ya calculado justo encima cuando no hay comentario.
            if( $comment != null ) {
                // SEGURIDAD (auditoría 2026-09-13): antes hacía
                // fill($request->all()) -- 'user_id' es fillable, así que
                // incluso con myComment() ya corregido, el autor real podía
                // reasignar su propio comentario a otro user_id. Solo el
                // texto es editable.
                $comment->update(['comment' => $request->input('comment')]);
                $message = null;
                $status_code = 200;
            }
        }

        return json_message_response( $message, $status_code);
    }
    public function deleteComment(Request $request)
    {
        $comment = Comment::canBeDeletedBy()->where('id', $request->id)->first();

        $message = __('message.not_found_entry', ['name' => __('message.comment') ]);
        $status_code = 400;
        
        if( $comment != null )
        {
            $comment->delete();
            $status_code = 200;
            $message = __('message.delete_form', ['form' =>  __('message.comment') ]);
        }

        return json_message_response( $message, $status_code);
    }

    /** Mismo patrón que PostingController::reportOnPosting() -- item 11 del roadmap. */
    public function reportOnComment(Request $request)
    {
        $user_id = auth()->id();
        $comment_id = $request->comment_id;

        $comment = Comment::where('id', $comment_id)->first();

        if ($comment == null) {
            return json_message_response(__('message.not_found_entry', ['name' => __('message.comment')]));
        }

        ReportComment::create([
            'user_id'    => $user_id,
            'comment_id' => $comment_id,
            'reason'     => $request->reason ?? null,
        ]);

        return json_message_response(__('message.report_on_comment'));
    }
}