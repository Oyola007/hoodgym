<!-- =========================================================
     HoodGym AI Chat Widget
     Include this near the end of <body> on any page, e.g.:
       <?php include __DIR__ . '/includes/chat_widget.php'; ?>
     It relies on the CSS variables already defined in your site's
     :root (--bg, --panel, --hazard, --chalk, --muted, --line), with
     safe fallbacks in case a page hasn't declared them.
     ========================================================= -->
<style>
  #hg-chat-toggle{
    position:fixed; bottom:24px; right:24px; z-index:1000;
    width:58px; height:58px; border-radius:50%;
    background:var(--hazard,#ffcc00); color:#0e0e0d;
    border:none; cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    font-size:26px;
    box-shadow:0 6px 18px rgba(0,0,0,.45);
    transition:transform .15s ease;
  }
  #hg-chat-toggle:hover{ transform:scale(1.06); }

  #hg-chat-panel{
    position:fixed; bottom:94px; right:24px; z-index:1000;
    width:340px; max-width:calc(100vw - 32px);
    height:460px; max-height:calc(100vh - 140px);
    background:var(--panel,#181816);
    border:1px solid var(--line,#2c2c29);
    border-radius:12px;
    display:none;
    flex-direction:column;
    overflow:hidden;
    box-shadow:0 12px 30px rgba(0,0,0,.5);
    font-family:'Inter',sans-serif;
  }
  #hg-chat-panel.open{ display:flex; }

  #hg-chat-head{
    background:var(--hazard,#ffcc00); color:#0e0e0d;
    padding:14px 16px;
    display:flex; align-items:center; justify-content:space-between;
    font-family:'Bebas Neue',sans-serif; font-size:18px; letter-spacing:.5px;
  }
  #hg-chat-head button{
    background:none; border:none; font-size:18px; cursor:pointer; color:#0e0e0d;
    line-height:1;
  }

  #hg-chat-body{
    flex:1; overflow-y:auto; padding:14px;
    display:flex; flex-direction:column; gap:10px;
    background:var(--bg,#0e0e0d);
  }
  .hg-msg{
    max-width:85%; padding:9px 12px; border-radius:10px;
    font-size:13.5px; line-height:1.45; white-space:pre-wrap;
  }
  .hg-msg.bot{
    background:var(--panel-raised,#212120); color:var(--chalk,#f3f2ec);
    align-self:flex-start; border:1px solid var(--line,#2c2c29);
  }
  .hg-msg.user{
    background:var(--hazard,#ffcc00); color:#0e0e0d;
    align-self:flex-end; font-weight:600;
  }
  .hg-msg.typing{
    background:var(--panel-raised,#212120); color:var(--muted,#a9a79f);
    align-self:flex-start; border:1px solid var(--line,#2c2c29);
    font-family:'JetBrains Mono',monospace; font-size:12px;
  }

  #hg-chat-form{
    display:flex; gap:8px; padding:12px;
    border-top:1px solid var(--line,#2c2c29);
    background:var(--panel,#181816);
  }
  #hg-chat-input{
    flex:1; padding:10px 12px; border-radius:8px;
    border:1px solid var(--line,#2c2c29);
    background:var(--bg,#0e0e0d); color:var(--chalk,#f3f2ec);
    font-size:13.5px; outline:none;
  }
  #hg-chat-input:focus{ border-color:var(--hazard,#ffcc00); }
  #hg-chat-send{
    background:var(--hazard,#ffcc00); color:#0e0e0d; border:none;
    border-radius:8px; padding:0 14px; font-weight:700; cursor:pointer;
  }
  #hg-chat-send:disabled{ opacity:.5; cursor:default; }
</style>

<button id="hg-chat-toggle" aria-label="Open chat">💬</button>

<div id="hg-chat-panel">
  <div id="hg-chat-head">
    <span>HoodGym Assistant</span>
    <button id="hg-chat-close" aria-label="Close chat">✕</button>
  </div>
  <div id="hg-chat-body"></div>
  <form id="hg-chat-form">
    <input id="hg-chat-input" type="text" placeholder="Ask about classes, hours..." autocomplete="off" />
    <button id="hg-chat-send" type="submit">Send</button>
  </form>
</div>

<script>
(function(){
  // Path to the api/ folder relative to THIS page. Since chat_widget.php
  // is included from pages at the project root (index.php, login.php, etc.),
  // a relative path works no matter what folder XAMPP serves the project
  // from (e.g. http://localhost/hoodgym/ vs http://localhost/).
  // If you include this widget from a page inside a subfolder (e.g.
  // /admin/dashboard.php), change this to '../api/chat.php'.
  const HG_CHAT_URL = 'api/chat.php';

  const toggle = document.getElementById('hg-chat-toggle');
  const panel  = document.getElementById('hg-chat-panel');
  const closeBtn = document.getElementById('hg-chat-close');
  const body   = document.getElementById('hg-chat-body');
  const form   = document.getElementById('hg-chat-form');
  const input  = document.getElementById('hg-chat-input');
  const sendBtn = document.getElementById('hg-chat-send');

  let opened = false;

  function addMessage(text, cls){
    const div = document.createElement('div');
    div.className = 'hg-msg ' + cls;
    div.textContent = text;
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
    return div;
  }

  toggle.addEventListener('click', () => {
    panel.classList.toggle('open');
    if (!opened) {
      opened = true;
      addMessage("Hey! I'm the HoodGym assistant. Ask me about classes, hours, or how to join.", 'bot');
    }
  });
  closeBtn.addEventListener('click', () => panel.classList.remove('open'));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = input.value.trim();
    if (!msg) return;

    addMessage(msg, 'user');
    input.value = '';
    sendBtn.disabled = true;
    const typingEl = addMessage('typing...', 'typing');

    try {
      const res = await fetch(HG_CHAT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: msg })
      });
      const data = await res.json();
      typingEl.remove();

      if (data.error) {
        addMessage("Sorry, something went wrong: " + data.error, 'bot');
      } else {
        addMessage(data.reply, 'bot');
      }
    } catch (err) {
      typingEl.remove();
      addMessage("Sorry, I couldn't reach the server. Please try again.", 'bot');
    } finally {
      sendBtn.disabled = false;
      input.focus();
    }
  });
})();
</script>
