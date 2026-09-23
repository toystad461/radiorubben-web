<?php
if (!defined('ABSPATH') || post_password_required()) { return; }
?>
<section id="comments" class="rrm rrm-comments" aria-label="Kommentarer">
<h2>Praten på Rubben</h2>
<p>Hold en god tone. Første kommentar må godkjennes før den vises.</p>
<?php if (have_comments()) : ?>
<ol class="comment-list"><?php wp_list_comments(['style' => 'ol', 'short_ping' => true, 'avatar_size' => 0]); ?></ol>
<?php the_comments_navigation(); endif; ?>
<?php if (comments_open()) {
    if (is_user_logged_in()) {
        comment_form(['title_reply' => 'Skriv en kommentar', 'label_submit' => 'Send kommentar', 'comment_notes_after' => '<p>Kommentaren kan bli holdt tilbake for gjennomgang.</p>']);
    } else {
        echo '<p><a class="rrm-button" href="' . esc_url(wp_login_url(get_permalink() . '#comments')) . '">Logg inn for å kommentere</a> · <a href="' . esc_url(RRM_Min_Rubben::url()) . '">Opprett leserkonto</a></p>';
    }
} else { echo '<p>Kommentarfeltet er stengt for nye kommentarer.</p>'; }
?>
</section>
