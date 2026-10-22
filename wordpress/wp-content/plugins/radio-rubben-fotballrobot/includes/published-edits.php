<?php
namespace RadioRubben\Fotballrobot;

/** Reject an unreviewed live update before WordPress writes or changes status. */
final class PublishedEdits {
    const MESSAGE='Den publiserte artikkelen er beholdt. Endret tekst må lagres som et separat endringsforslag, kontrolleres og godkjennes før den erstatter saken.';
    public static function blocked(array $incoming): bool {
        $id=(int)($incoming['ID']??0);if(!$id||!PublicationGate::managed($id))return false;
        $old=get_post($id);
        if(!$old||$old->post_status!=='publish')return false;
        // Deliberate withdrawal remains possible. An ordinary Update must never silently withdraw.
        if(($incoming['post_status']??'publish')!=='publish')return false;
        $next=array_replace((array)$old,$incoming);
        return !PublicationGate::canPublish($id,$next);
    }
    public static function rest($prepared,$request){
        if(is_wp_error($prepared))return $prepared;
        $incoming=(array)$prepared;
        $incoming['ID']=(int)($incoming['ID']??$request->get_param('id'));
        if(self::blocked($incoming))return new \WP_Error('rrfr_published_revision_required',self::MESSAGE,['status'=>409]);
        return $prepared;
    }
    public static function beforeWrite(bool $empty,array $postarr): bool {
        if($empty||!self::blocked(wp_unslash($postarr)))return $empty;
        set_transient('rrfr_quality_notice_'.get_current_user_id(),self::MESSAGE,120);
        return true; // Core returns WP_Error before touching the live row, terms or metadata.
    }
}
