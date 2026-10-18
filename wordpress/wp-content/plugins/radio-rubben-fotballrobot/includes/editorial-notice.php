<?php
namespace RadioRubben\Fotballrobot;

/** Publisher-approved disclosure, not a claim about a football match. */
final class EditorialNotice {
    const VERSION = '1.0.0';
    const BLOCK = <<<'HTML'
<!-- wp:html -->
<div class="rubben-ai-notice" style="font-size:14px;line-height:1.6;padding:12px 16px;margin-bottom:24px;border:1px solid #626570;border-radius:12px;background-color:#1b1e25;color:#e5e7eb;"><strong>AI-generert artikkel</strong><br>Utarbeidet med hjelp av kunstig intelligens og offentlige kilder. Redaksjonelt ansvar: Thomas Magne Sellevold-Øystad, Radio Rubben.</div>
<!-- /wp:html -->
HTML;

    /** Only the exact leading block is excluded from the review copy. Never change stored HTML. */
    public static function reviewContent(string $html): string {
        $leading=ltrim($html," \t\r\n");
        if(!str_starts_with($leading,self::BLOCK)) return $html;
        // No class-name/DOM/text-only exemption: changed, nested or additional text stays reviewable.
        return substr($leading,strlen(self::BLOCK));
    }
}
