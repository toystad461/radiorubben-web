<?php
/**
 * Article sharing without third-party scripts.
 */
if (!defined('ABSPATH')) { exit; }
$rr_share_url = get_permalink();
$rr_share_title = html_entity_decode(wp_strip_all_tags(get_the_title()), ENT_QUOTES, 'UTF-8');
$rr_share_facebook = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($rr_share_url);
?>
<aside class="rr-share" aria-label="Del artikkelen" data-share-title="<?php echo esc_attr($rr_share_title); ?>">
<strong>Del</strong>
<div class="rr-share-actions">
<button type="button" class="rr-share-native" hidden title="Del artikkelen" aria-label="Del artikkelen"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 11 8-5M8 13l8 5"/><circle cx="5" cy="12" r="3"/><circle cx="19" cy="4" r="3"/><circle cx="19" cy="20" r="3"/></g></svg><span>Del artikkelen</span></button>
<a class="rr-share-facebook" href="<?php echo esc_url($rr_share_facebook); ?>" target="_blank" rel="noopener noreferrer" title="Facebook" aria-label="Facebook"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M13.5 22v-9h3l.5-3.5h-3.5V7.3c0-1 .3-1.8 1.8-1.8H17V2.3c-.8-.1-1.6-.3-2.5-.3C12 2 10 3.6 10 6.7v2.8H7V13h3v9z"/></svg></a>
<a href="<?php echo esc_url('https://wa.me/?text=' . rawurlencode($rr_share_title . ' ' . $rr_share_url)); ?>" target="_blank" rel="noopener noreferrer" title="WhatsApp" aria-label="WhatsApp"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M20.5 3.5A11.8 11.8 0 0 0 12 0C5.4 0 0 5.4 0 12c0 2.1.6 4.2 1.6 6L0 24l6.2-1.6A12 12 0 0 0 12 24c6.6 0 12-5.4 12-12 0-3.2-1.2-6.2-3.5-8.5zM12 22a10 10 0 0 1-5.1-1.4l-.4-.2-3.7 1 1-3.6-.3-.4A10 10 0 1 1 12 22zm5.5-7.5c-.3-.1-1.8-.9-2.1-1-.3-.1-.5-.1-.7.2l-1 1.2c-.2.2-.4.2-.7.1-1.8-.9-3-2-3.9-3.6-.3-.5.3-.5.9-1.6.1-.2.1-.4 0-.6L9 6.9c-.3-.6-.6-.5-.8-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1.1 2.8 1.2 3c.2.2 2.1 3.3 5.1 4.6 1.9.8 2.6.9 3.5.8.6-.1 1.8-.8 2.1-1.5.3-.8.3-1.4.2-1.5-.1-.1-.3-.2-.6-.3z"/></svg></a>
<a href="<?php echo esc_attr('mailto:?subject=' . rawurlencode($rr_share_title) . '&body=' . rawurlencode($rr_share_title . "\n\n" . $rr_share_url)); ?>" title="E-post" aria-label="E-post"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></g></svg></a>
<button type="button" class="rr-share-copy" hidden title="Kopier lenke" aria-label="Kopier lenke"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.8"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V3H3v13h5"/></g></svg></button>

</div>
<p class="rr-share-help" hidden>Velg app i delingsmenyen. Mangler appen, kan du kopiere lenken.</p>
<p class="rr-share-web" hidden><a href="<?php echo esc_url($rr_share_facebook); ?>" target="_blank" rel="noopener noreferrer">Åpne Facebook-deling i nettleseren</a></p>
<p class="rr-share-status" role="status" aria-live="polite"></p>
<input class="rr-share-fallback" type="text" readonly aria-label="Artikkellenke – marker og kopier" value="<?php echo esc_attr($rr_share_url); ?>" hidden>
</aside>
<style>
.rr-article-hero>.rr-wrap{display:grid;grid-template-columns:minmax(0,1fr) auto;column-gap:2rem}.rr-article-hero>.rr-wrap>:not(.rr-share){grid-column:1}.rr-article-hero>.rr-wrap>.rr-share{grid-column:2;grid-row:1 / span 4;align-self:start;max-width:280px}.rr-share{margin:0;padding:0;border:0;text-align:right}.rr-share>strong{font-size:.85rem}.rr-share-actions a,.rr-share-actions button{width:44px;height:44px;padding:0!important;border-radius:50%!important}.rr-share-actions svg{flex-shrink:0}.rr-share-actions{justify-content:flex-end}@media(max-width:760px){.rr-article-hero>.rr-wrap{display:block}.rr-article-hero>.rr-wrap>.rr-share{max-width:none;margin-top:1.25rem;text-align:left}.rr-share-actions{justify-content:flex-start}}
.rr-share-actions{display:flex;flex-wrap:wrap;gap:.6rem;margin-top:.8rem}
.rr-share-actions a,.rr-share-actions button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:.55rem 1rem;border:1px solid #767676;border-radius:8px;background:#171717;color:#fff;font:inherit;font-size:1rem;line-height:1.4;text-decoration:none;cursor:pointer}
.rr-share-actions a:hover,.rr-share-actions button:hover{background:#383838}
.rr-share-actions :focus-visible{outline:3px solid #e53935;outline-offset:3px}
.rr-share [hidden]{display:none!important}
.rr-share-status:empty{display:none}
.rr-share-status{margin:.75rem 0 0}
.rr-share-fallback{width:100%;box-sizing:border-box;margin-top:.75rem;padding:.75rem;font:inherit}
.rr-share-actions .rr-share-native{width:auto;gap:.5rem;padding:.55rem .9rem!important;border-radius:8px!important}
.rr-share-help,.rr-share-web{font-size:.85rem;line-height:1.45;margin:.65rem 0 0}
.rr-share-web a{color:inherit;text-decoration:underline}
.rr-share-actions button:disabled{opacity:.65;cursor:wait}
</style>
<script>
(function(){
// Scope each instance: the same partial may be used more than once.
document.querySelectorAll('.rr-share').forEach(function(box){
 if(box.dataset.shareReady)return;
 box.dataset.shareReady='1';
 var field=box.querySelector('.rr-share-fallback');
 var url=field.value;
 var title=box.getAttribute('data-share-title');
 var status=box.querySelector('.rr-share-status');
 var copy=box.querySelector('.rr-share-copy');
 var native=box.querySelector('.rr-share-native');
 var facebook=box.querySelector('.rr-share-facebook');
 var data={title:title,url:url};
 var pending=false;
 function fallback(message){
  field.hidden=false;field.focus();field.select();
  field.setSelectionRange(0,field.value.length);
  status.textContent=message || 'Marker og kopier lenken nedenfor.';
 }
 copy.hidden=false;
 copy.addEventListener('click',function(){
  status.textContent='';
  try{
   if(navigator.clipboard && window.isSecureContext){
    navigator.clipboard.writeText(url).then(function(){
     status.textContent='Lenken er kopiert!';
    },function(){fallback();});
   }else{fallback();}
  }catch(error){fallback();}
 });
 var canShare=false;
 try{
  canShare=window.isSecureContext && typeof navigator.share==='function' &&
   (typeof navigator.canShare!=='function' || navigator.canShare(data));
 }catch(error){canShare=false;}
 if(!canShare)return;
 native.hidden=false;
 box.querySelector('.rr-share-help').hidden=false;
 box.querySelector('.rr-share-web').hidden=false;
 facebook.title='Facebook – velg Facebook i delingsmenyen';
 facebook.setAttribute('aria-label',facebook.title);
 function finished(){pending=false;native.disabled=false;}
 function failed(error){
  finished();
  // A cancelled chooser must never navigate or copy on the user's behalf.
  if(error && error.name==='AbortError'){status.textContent='';return;}
  fallback('Delingsmenyen kunne ikke åpnes. Kopier lenken nedenfor, eller bruk Facebook-lenken.');
 }
 function share(){
  if(pending)return;
  pending=true;native.disabled=true;status.textContent='';
  try{
   // Invoke within the click: awaiting clipboard/network first loses activation.
   navigator.share(data).then(function(){
    finished();
    // The OS reports a handoff, not proof that the user published a post.
   },failed);
  }catch(error){failed(error);}
 }
 native.addEventListener('click',share);
 facebook.addEventListener('click',function(event){
  // Preserve ordinary new-tab gestures and the no-JS web share URL.
  if(event.button!==0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)return;
  event.preventDefault();
  share();
 });
});
})();
</script>
