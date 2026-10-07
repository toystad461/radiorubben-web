<section class="rr-wrap rr-member-invite" aria-labelledby="rr-member-invite-title">
  <div><p class="rr-eyebrow">MIN RUBBEN</p><h2 id="rr-member-invite-title">Din plass på Radio Rubben.</h2><p>Spill quiz, ønsk musikk og send oss en hilsen.</p></div>
  <div class="rr-home-member-actions"><a class="rr-btn" href="<?php echo esc_url(home_url('/min-side/')); ?>"><?php echo esc_html(rr_theme_member_label()); ?> →</a>
<?php
$weekly_quiz = (function_exists('rr_quiz_enabled') && rr_quiz_enabled('weekly'));
$live_quiz = (function_exists('rr_quiz_enabled') && rr_quiz_enabled('live'));
if ($weekly_quiz || $live_quiz):
    $quiz_anchor = $weekly_quiz && $live_quiz ? '' : ($weekly_quiz ? '#rr-weekly' : '#rr-live-quiz');
    $quiz_label = $weekly_quiz && $live_quiz ? 'Velg quiz →' : ($weekly_quiz ? 'Spill ukens quiz →' : 'Gå til LIVE-quizen →');
?>
<p><a class="rr-btn-outline rr-home-quiz-link" href="<?php echo esc_url(home_url('/quiz/') . $quiz_anchor); ?>"><?php echo esc_html($quiz_label); ?></a></p>
<?php endif; ?>
</div></section>
