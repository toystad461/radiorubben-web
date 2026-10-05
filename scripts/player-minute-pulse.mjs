// External minute pulse for the existing WordPress cron queue. No credentials or dependencies.
// The WordPress plugin owns scheduling, locks, fetch budgets and publication approval.
const origin = 'https://www.radiorubben.no';
export async function pulse(fetcher = fetch, now = Date.now) {
  const request = {redirect:'error',cache:'no-store',headers:{'Cache-Control':'no-cache','User-Agent':'RadioRubben-MatchScheduler/1.0'},signal:AbortSignal.timeout(15000)};
  // Do not supply doing_wp_cron: WordPress must acquire its own matching cron lock.
  const tick=await fetcher(`${origin}/wp-cron.php?rr_player_pulse=${now()}`,request);
  if(!tick.ok) throw new Error(`WordPress cron returned HTTP ${tick.status}`);
  await tick.text();
  const response=await fetcher(`${origin}/wp-json/rr-player-widget/v1/widget`,{...request,signal:AbortSignal.timeout(15000)});
  if(!response.ok) throw new Error(`Widget health returned HTTP ${response.status}`);
  const data=await response.json();
  if(typeof data.html!=='string' || !Number.isFinite(data.updated_at) || data.updated_at<=0) throw new Error('Widget response is invalid');
  const age=Math.max(0,Math.floor(now()/1000-data.updated_at));
  // Cron can complete after its HTTP response. Alert on a sustained gap, not one in-flight tick.
  if(age>300) throw new Error(`Widget scheduler has not completed for ${age} seconds`);
  return {scheduler:'radiorubben-player-minute',last_completed_seconds_ago:age};
}
if(process.argv[1] && import.meta.url===new URL(process.argv[1],'file:').href) {
  pulse().then(result=>console.log(JSON.stringify(result))).catch(error=>{console.error(error.message);process.exitCode=1;});
}
