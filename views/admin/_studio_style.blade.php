<style>
.pm { --pm-line: var(--zmc-line, #e6e3dc); --pm-sub: var(--zmc-sub, #7a7f8c); --pm-ink: var(--zmc-ink, #232a3b); --pm-brand: var(--zmc-brand, #26345c); --pm-r: var(--zmc-r, 6px); }
.pm [hidden] { display: none !important; }
.pm-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0 0 16px; }
.pm-head p { margin: 0; font-size: 13px; color: var(--pm-sub); }
.pm-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.pm-card { display: flex; flex-direction: column; border: 1px solid var(--pm-line); border-radius: var(--pm-r); background: var(--zmc-surface, #fff); overflow: hidden; color: inherit !important; text-decoration: none !important; transition: border-color .15s, box-shadow .15s; }
.pm-card:hover, .pm-card:focus-visible { border-color: var(--zmc-line-strong, #d6d2c8); box-shadow: 0 6px 18px rgba(35, 42, 59, .08); }
.pm-card-bn { position: relative; display: flex; align-items: flex-end; aspect-ratio: 16 / 7; padding: 14px 16px; background-size: cover !important; background-position: center !important; }
.pm-card-bn strong { font-size: 17px; line-height: 1.35; font-weight: 700; }
.pm-card-bn.is-shadow strong { text-shadow: 0 1px 8px rgba(0, 0, 0, .35); }
.pm-card-bn .pm-state { position: absolute; top: 12px; left: 12px; }
.pm-card-body { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 16px; font-size: 13px; }
.pm-card-body b { font-weight: 600; color: var(--pm-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pm-card-body small { flex: none; color: var(--pm-sub); font-variant-numeric: tabular-nums; }
.pm-card-new { align-items: center; justify-content: center; gap: 8px; min-height: 200px; border-style: dashed; border-width: 1.5px; background: transparent; color: var(--pm-sub) !important; font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
.pm-card-new:hover { color: var(--pm-ink) !important; background: var(--zmc-surface, #fff); }
.pm-card-new i { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 50%; background: var(--zmc-brand-soft, #fbf1d6); color: var(--pm-brand); font-style: normal; font-size: 22px; line-height: 1; }
.pm-state { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 700; background: rgba(255, 255, 255, .92); color: var(--pm-ink); }
.pm-state.is-running { color: #1d7a45; }
.pm-state.is-upcoming { color: #a3690c; }
.pm-state.is-ended, .pm-state.is-hidden { color: var(--pm-sub); }
.pm-empty { padding: 36px 20px; text-align: center; color: var(--pm-sub); font-size: 13.5px; }

.pm-edit { display: grid; grid-template-columns: minmax(0, 560px) minmax(0, 1fr); gap: 20px; align-items: start; }
.pm-sec { margin: 0 0 14px; padding: 18px 20px; border: 1px solid var(--pm-line); border-radius: var(--pm-r); background: var(--zmc-surface, #fff); }
.pm-sec > h3 { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin: 0 0 12px; font-size: 14.5px; font-weight: 700; color: var(--pm-ink); }
.pm-sec > h3 small { font-size: 12.5px; font-weight: 500; color: var(--pm-sub); }
.pm-row { display: grid; grid-template-columns: 110px minmax(0, 1fr); align-items: center; gap: 6px 14px; padding: 10px 0; border-top: 1px solid var(--pm-line); }
.pm-row:first-of-type { border-top: 0; padding-top: 0; }
.pm-row > label { font-size: 13px; font-weight: 600; color: var(--pm-ink); }
.pm-row > label + * { min-width: 0; }
.pm .pm-row input[type=text], .pm .pm-row input[type=date], .pm .pm-row select, .pm .pm-row textarea { width: 100%; box-sizing: border-box; }
.pm-inline { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.pm-inline input[type=date] { width: auto !important; flex: 1; min-width: 130px; }
.pm-quick { display: flex; gap: 6px; margin-top: 8px; }
.pm-quick button, .pm-seg button { padding: 5px 10px; border: 1px solid var(--zmc-line-strong, #d6d2c8); border-radius: 5px; background: var(--zmc-surface, #fff); font: inherit; font-size: 12.5px; color: var(--pm-sub); cursor: pointer; }
.pm-quick button:hover { color: var(--pm-ink); }
.pm-seg { display: inline-flex; }
.pm-seg button { border-radius: 0; margin-left: -1px; }
.pm-seg button:first-child { border-radius: 5px 0 0 5px; margin-left: 0; }
.pm-seg button:last-child { border-radius: 0 5px 5px 0; }
.pm-seg button.is-on { position: relative; background: var(--pm-brand); border-color: var(--pm-brand); color: var(--zmc-on-brand, #fff8e6); }
.pm-colors { display: flex; align-items: center; gap: 6px; }
.pm .pm-colors input[type=color] { width: 40px; height: 32px; padding: 2px; border: 1px solid var(--zmc-line-strong, #d6d2c8); border-radius: 5px; cursor: pointer; }
.pm-img { display: flex; align-items: center; gap: 10px; }
.pm-img-thumb { flex: none; width: 64px; height: 40px; border: 1px solid var(--pm-line); border-radius: 4px; background: var(--zmc-side, #efede8) center / cover no-repeat; }
.pm-img-thumb.is-round { width: 40px; border-radius: 50%; }
.pm-check { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; color: var(--pm-ink); cursor: pointer; }
.pm-hint { margin: 4px 0 0; font-size: 12px; line-height: 1.6; color: var(--pm-sub); }
.pm details > summary { cursor: pointer; font-size: 13px; font-weight: 600; color: var(--pm-sub); list-style: none; padding: 10px 0 0; border-top: 1px solid var(--pm-line); }
.pm details > summary::-webkit-details-marker { display: none; }
.pm details > summary::before { content: '+ '; }
.pm details[open] > summary::before { content: '− '; }

.pm-picked { margin: 0; padding: 0; list-style: none; border: 1px solid var(--pm-line); border-radius: var(--pm-r); max-height: 360px; overflow-y: auto; }
.pm-picked:empty::before { content: attr(data-empty); display: block; padding: 22px 14px; text-align: center; font-size: 13px; color: var(--pm-sub); }
.pm-picked li, .pm-found li { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-top: 1px solid var(--pm-line); font-size: 13px; background: var(--zmc-surface, #fff); }
.pm-picked li:first-child, .pm-found li:first-child { border-top: 0; }
.pm-picked li { cursor: grab; }
.pm-picked li.is-drag { opacity: .45; }
.pm-picked li.is-over { box-shadow: inset 0 2px 0 var(--pm-brand); }
.pm-grip { flex: none; color: var(--zmc-line-strong, #c9c4b8); font-size: 14px; letter-spacing: -2px; }
.pm-no { flex: none; width: 22px; font-size: 12px; color: var(--pm-sub); text-align: right; font-variant-numeric: tabular-nums; }
.pm-th { flex: none; width: 36px; height: 36px; border-radius: 4px; background: var(--zmc-side, #efede8) center / cover no-repeat; }
.pm-nm { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--pm-ink); }
.pm-nm small { display: block; font-size: 11.5px; color: var(--pm-sub); }
.pm-x, .pm-add { flex: none; width: 28px; height: 28px; border: 1px solid var(--zmc-line-strong, #d6d2c8); border-radius: 5px; background: var(--zmc-surface, #fff); font: inherit; font-size: 16px; line-height: 1; color: var(--pm-sub); cursor: pointer; }
.pm-x:hover { color: #c0392b; border-color: #e5b5ae; }
.pm-add { color: var(--pm-brand); font-weight: 700; }
.pm-add:disabled { border-color: transparent; background: transparent; color: #1d7a45; font-size: 12px; cursor: default; }
.pm-finder { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--pm-line); }
.pm-finder-bar { display: grid; grid-template-columns: minmax(0, 1fr) 130px 120px; gap: 6px; margin-bottom: 8px; }
.pm-finder-bar input, .pm-finder-bar select { width: 100%; box-sizing: border-box; }
.pm-found { margin: 0; padding: 0; list-style: none; border: 1px solid var(--pm-line); border-radius: var(--pm-r); max-height: 300px; overflow-y: auto; }
.pm-found-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 8px; font-size: 12.5px; color: var(--pm-sub); }
.pm-savebar { position: sticky; bottom: 0; z-index: 5; display: flex; align-items: center; gap: 8px; padding: 12px 0; background: linear-gradient(to top, var(--zmc-bg, #f7f6f3) 70%, transparent); }
.pm-savebar .pm-grow { flex: 1; }

.pm-view { position: sticky; top: 16px; display: flex; flex-direction: column; border: 1px solid var(--pm-line); border-radius: var(--pm-r); background: var(--zmc-surface, #fff); overflow: hidden; }
.pm-view-bar { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-bottom: 1px solid var(--pm-line); font-size: 13px; }
.pm-view-state { flex: 1; color: var(--pm-sub); }
.pm-view-state.is-busy { color: var(--zmc-warn, #a3690c); }
.pm-view-frame { height: calc(100vh - 90px); min-height: 520px; background: var(--zmc-side, #efede8); display: flex; justify-content: center; }
.pm-view-frame iframe { width: 100%; height: 100%; border: 0; background: #fff; transition: width .2s; }
@media (max-width: 1280px) { .pm-edit { grid-template-columns: 1fr; } .pm-view { position: static; } }
@media (max-width: 600px) { .pm-row { grid-template-columns: 1fr; } .pm-finder-bar { grid-template-columns: 1fr; } }
@media (prefers-reduced-motion: reduce) { .pm-card, .pm-view-frame iframe { transition: none; } }
</style>
