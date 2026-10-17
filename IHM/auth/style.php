<style>
  * { box-sizing: border-box; }
  body { margin: 0; font-family: "Segoe UI", system-ui, sans-serif; color: #14213d; }
  .shell { display: grid; grid-template-columns: 1.05fr 1fr; min-height: 100vh; }
  .left { background: linear-gradient(155deg, #0a2540, #0d3a6b); color: #fff;
          padding: 40px 48px; display: flex; flex-direction: column; justify-content: center; }
  .left small { letter-spacing: .12em; color: #7fb4e6; font-weight: 600; }
  .left h2 { font-size: 40px; line-height: 1.12; margin: 14px 0; }
  .left p { color: #b8d3ef; line-height: 1.55; max-width: 440px; }
  .right { display: grid; place-items: center; padding: 30px 20px; }
  form { width: 100%; max-width: 400px; }
  form h1 { margin: 6px 0 4px; font-size: 26px; }
  .muted { color: #6b7a90; font-size: 14px; }
  label { display: block; font-size: 13px; font-weight: 600; margin: 16px 0 6px; }
  input, select { width: 100%; padding: 11px 12px; border: 1px solid #e2e8f0;
                  border-radius: 8px; font: inherit; background: #f8fafc; }
  input:focus, select:focus { outline: 2px solid #1da1f2; background: #fff; }
  button { width: 100%; margin-top: 22px; padding: 12px; border: 0; border-radius: 8px;
           background: #1da1f2; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
  button:hover { background: #0b7fc7; }
  .erreur { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;
            padding: 10px 14px; border-radius: 8px; margin-top: 16px; font-size: 14px; }
  @media (max-width: 800px) { .shell { grid-template-columns: 1fr; } .left { display: none; } }
    .ok { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0;
        padding:10px 14px; border-radius:8px; margin-top:16px; font-size:14px; }
  .row2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  a { color:#0b7fc7; text-decoration:none; }
</style>