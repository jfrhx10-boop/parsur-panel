<style>
  .mn-hero{background:var(--dark);border-radius:16px;padding:20px 22px;color:#fff;box-shadow:inset 0 -4px 0 var(--yel)}
  .mn-hero h2{margin:0 0 4px;font-size:19px;color:#fff}
  .mn-hero p{margin:0 0 14px;color:#cfcfcc;font-size:13.5px}
  .mn-search{display:flex;gap:10px;flex-wrap:wrap}
  .mn-search input[name=q]{flex:1 1 320px;font-size:17px;padding:13px 14px;border:0;border-radius:10px}
  .mn-search select{flex:0 1 260px;border:0;border-radius:10px}
  .mn-eg{margin-top:10px;font-size:12.5px;color:#bdbdb9;display:flex;gap:6px;flex-wrap:wrap;align-items:center}
  .mn-eg a{color:#fff;background:rgba(255,255,255,.12);border-radius:999px;padding:3px 10px;text-decoration:none;font-weight:600}
  .mn-eg a:hover{background:var(--yel);color:var(--dark)}
  .mn-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
  @media (max-width:820px){.mn-steps{grid-template-columns:1fr}}
  .mn-step{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px;display:flex;gap:12px;align-items:flex-start}
  .mn-step b{flex:0 0 30px;height:30px;border-radius:50%;background:var(--yel);color:var(--dark);display:flex;align-items:center;justify-content:center;font-size:15px}
  .mn-step strong{display:block;margin-bottom:2px}
  .mn-step span{font-size:13px;color:var(--muted);line-height:1.45}
  .mn-books{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px}
  .mn-book{display:flex;gap:14px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px;text-decoration:none;color:inherit}
  .mn-book:hover{border-color:var(--yel);box-shadow:0 0 0 2px rgba(250,176,1,.25)}
  .mn-book canvas{flex:0 0 auto;border:1px solid var(--line);border-radius:6px;background:#f3f3f1;width:86px;height:112px}
  .mn-book h3{margin:0 0 4px;font-size:15.5px;line-height:1.25}
  .mn-book .st{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
  .mn-h{font-size:12px;text-transform:uppercase;letter-spacing:.07em;color:var(--gray);font-weight:800;margin:22px 0 8px}
  .mn-hit{display:flex;gap:14px;padding:12px;border:1px solid var(--line);border-radius:12px;margin-top:8px;text-decoration:none;background:#fff;color:inherit;align-items:flex-start}
  .mn-hit:hover{border-color:var(--yel);box-shadow:0 0 0 2px rgba(250,176,1,.25)}
  .mn-hit.top{border-left:5px solid var(--yel)}
  .mn-hit canvas{flex:0 0 auto;width:92px;height:120px;border:1px solid var(--line);border-radius:6px;background:#f3f3f1}
  .mn-hit .t{font-weight:700;font-size:15.5px;line-height:1.3}
  .mn-hit .m{font-size:12.5px;color:var(--muted);margin-top:3px;display:flex;gap:6px;flex-wrap:wrap;align-items:center}
  .mn-hit .s{font-size:13px;color:#444;margin-top:6px;line-height:1.5}
  .mn-hit mark,.mn-info mark{background:var(--yel);color:var(--dark);padding:0 2px;border-radius:3px}
  @media (max-width:560px){.mn-hit canvas{width:64px;height:84px}}
  .mn-info{border:1px solid var(--line);border-left:5px solid var(--yel);background:#fff;border-radius:12px;padding:14px 16px;margin-top:14px}
  .mn-info h3{margin:0 0 8px;font-size:17px}
  .mn-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px}
  .mn-fact{background:var(--cream);border-radius:10px;padding:10px 12px;font-size:13px;line-height:1.45}
  .mn-fact .k{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--gray);font-weight:800;display:block;margin-bottom:2px}
  .mn-fact.ok{background:var(--okbg)} .mn-fact.no{background:#f1f1ee;color:#666}
  .mn-note{background:#fffbea;border:1px solid #f1e3a8;border-radius:10px;padding:9px 12px;margin-top:8px;font-size:13.5px;line-height:1.45}
  .mn-note .by{font-size:11.5px;color:var(--muted);margin-top:4px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .mn-note form{display:inline;margin:0}
  .mn-x{background:none;border:0;color:#a02020;cursor:pointer;font-size:12px;padding:0;text-decoration:underline}
  .mn-tag{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;border-radius:999px;padding:2px 8px;white-space:nowrap}
  .mn-tag.fs{background:var(--okbg);color:var(--ok)} .mn-tag.v{background:#e8f0ff;color:#1d4f9c} .mn-tag.c{background:#fff4dc;color:#8a5a00} .mn-tag.p{background:#ecece9;color:#555} .mn-tag.q{background:var(--dark);color:#fff} .mn-tag.n{background:#fffbea;color:#7a6200;border:1px solid #f1e3a8}
  .mn-prog{height:8px;background:#ecece9;border-radius:99px;overflow:hidden;margin-top:10px}
  .mn-prog span{display:block;height:100%;width:0;background:var(--yel);transition:width .2s}
  .mn-info code,.rf code,.mn-hit code{background:none;color:inherit;padding:0;border:0}
</style>
