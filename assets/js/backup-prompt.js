/* =====================================================================
   Backup prompt — loaded on every page after login (through navbar.php).
   - fills the small Backup tile on the Main Menu (#bk-tile)
   - shows the "Back up before you leave?" window when Logout is pressed
   - opens the same window for "Back up now"
   - warns (Chrome's own "Leave site?" message) if the window/tab is closed while
     there are changes that are not backed up yet
   - runs the automatic backup from the Main Menu when one is due
   Nothing here is needed for the system to work: without JavaScript, Logout simply logs out.
   ===================================================================== */
(function () {
    'use strict';

    var state = { status: null, csrf: '', skipUnload: false, busy: false, autoStarted: false };
    var BK = window.BKPrompt = window.BKPrompt || {};

    // ------------------------------------------------------------ helpers
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function $(sel, root) { return (root || document).querySelector(sel); }

    function injectCss() {
        if ($('#bk-css')) return;
        var css = '' +
        '#bk-tile{background:#fff;border:1px solid #e5dfee;border-left:6px solid #4B2E83;border-radius:14px;padding:.8rem 1rem;box-shadow:0 2px 8px rgba(75,46,131,.08);min-width:300px;max-width:380px}' +
        '#bk-tile.bk-ok{border-left-color:#2e8b57}#bk-tile.bk-warn{border-left-color:#D4A72C}#bk-tile.bk-bad{border-left-color:#c0392b}' +
        '#bk-tile .bk-row{display:flex;align-items:flex-start;gap:.7rem}' +
        '#bk-tile .bk-ico{font-size:1.7rem;line-height:1;color:#4B2E83}#bk-tile.bk-ok .bk-ico{color:#2e8b57}#bk-tile.bk-warn .bk-ico{color:#B88F22}#bk-tile.bk-bad .bk-ico{color:#c0392b}' +
        '#bk-tile .bk-title{font-weight:700;color:#35205E;font-size:.95rem;line-height:1.2}' +
        '#bk-tile .bk-sub{color:#6c757d;font-size:.78rem;margin-top:2px}' +
        '#bk-tile .bk-actions{display:flex;gap:.4rem;margin-top:.55rem;flex-wrap:wrap}' +
        '.bk-btn{border:0;border-radius:8px;padding:.32rem .75rem;font-size:.8rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem}' +
        '.bk-btn-gold{background:#D4A72C;color:#35205E}.bk-btn-gold:hover{background:#B88F22;color:#35205E}' +
        '.bk-btn-ghost{background:#fff;color:#4B2E83;border:1px solid #c9bde0}.bk-btn-ghost:hover{background:#f3eefa;color:#35205E}' +
        '.bk-btn-purple{background:#4B2E83;color:#fff}.bk-btn-purple:hover{background:#35205E;color:#fff}' +
        '.bk-btn[disabled]{opacity:.55;cursor:not-allowed}' +
        '.bk-spin{width:1rem;height:1rem;border:2px solid #c9bde0;border-top-color:#4B2E83;border-radius:50%;display:inline-block;animation:bkspin .8s linear infinite;vertical-align:-2px}' +
        '@keyframes bkspin{to{transform:rotate(360deg)}}' +
        '.bk-overlay{position:fixed;inset:0;background:rgba(30,18,52,.55);z-index:2000;display:flex;align-items:center;justify-content:center;padding:16px}' +
        '.bk-modal{background:#fff;border-radius:16px;width:100%;max-width:520px;max-height:calc(100vh - 32px);overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,.35);font-family:"Segoe UI",system-ui,-apple-system,sans-serif}' +
        '.bk-head{background:#4B2E83;color:#fff;padding:.9rem 1.2rem;border-radius:16px 16px 0 0;display:flex;align-items:center;gap:.6rem;font-weight:700;font-size:1.05rem}' +
        '.bk-body{padding:1.1rem 1.2rem}.bk-foot{padding:.8rem 1.2rem 1.1rem;display:flex;gap:.5rem;flex-wrap:wrap;justify-content:flex-end;border-top:1px solid #eee}' +
        '.bk-last{font-size:.85rem;color:#555;margin-bottom:.8rem}.bk-last.bk-old{color:#c0392b;font-weight:600}' +
        '.bk-opt{display:flex;gap:.7rem;align-items:flex-start;border:2px solid #e5dfee;border-radius:12px;padding:.65rem .8rem;margin-bottom:.55rem;cursor:pointer}' +
        '.bk-opt:hover{border-color:#b9a7dc}.bk-opt.bk-sel{border-color:#4B2E83;background:#f7f4fc}.bk-opt.bk-off{opacity:.6;cursor:not-allowed}' +
        '.bk-opt input[type=radio]{margin-top:.3rem;accent-color:#4B2E83}' +
        '.bk-opt .bk-ot{font-weight:600;color:#35205E}.bk-opt .bk-od{font-size:.78rem;color:#6c757d;word-break:break-all}' +
        '.bk-mailbox{margin-top:.35rem}.bk-mailbox input{width:100%;border:1px solid #cfc6e0;border-radius:8px;padding:.35rem .6rem;font-size:.85rem}' +
        '.bk-res{display:flex;gap:.55rem;align-items:flex-start;padding:.5rem 0;font-size:.9rem}.bk-res i{font-size:1.2rem;line-height:1.2}' +
        '.bk-res.ok i{color:#2e8b57}.bk-res.bad i{color:#c0392b}.bk-res small{display:block;color:#6c757d;word-break:break-all}' +
        '.bk-err{background:#fdecea;border:1px solid #f5c2c0;color:#8a2a24;border-radius:8px;padding:.5rem .7rem;font-size:.85rem;margin-top:.5rem}';
        var st = document.createElement('style');
        st.id = 'bk-css';
        st.textContent = css;
        document.head.appendChild(st);
    }

    function api(action, body) {
        var opt = { credentials: 'same-origin', headers: {} };
        var url = 'backup_run.php';
        if (body) {
            opt.method = 'POST';
            opt.headers['X-BK-CSRF'] = state.csrf;
            var fd = new URLSearchParams();
            fd.append('action', action);
            Object.keys(body).forEach(function (k) { fd.append(k, body[k]); });
            opt.body = fd;
        } else {
            url += '?action=' + encodeURIComponent(action);
        }
        return fetch(url, opt).then(function (r) {
            return r.json().catch(function () { return { ok: false, error: 'The server sent an unexpected answer.' }; });
        });
    }

    function loadStatus() {
        return api('status').then(function (j) {
            if (j && j.ok) { state.status = j; state.csrf = j.csrf; }
            return state.status;
        }).catch(function () { return state.status; });
    }

    // ------------------------------------------------------------ dashboard tile
    function renderTile() {
        var el = $('#bk-tile');
        var s = state.status;
        if (!el || !s) return;
        var cls = 'bk-warn', icon = 'bi-shield-exclamation', title = '', sub = '';
        if (s.state === 'ok')        { cls = 'bk-ok';   icon = 'bi-shield-check';        title = 'Backup: up to date'; }
        else if (s.state === 'none') { cls = 'bk-bad';  icon = 'bi-exclamation-octagon'; title = 'No backup yet'; }
        else if (s.state === 'overdue') { cls = 'bk-bad'; icon = 'bi-shield-exclamation'; title = 'Backup overdue'; }
        else                         { cls = 'bk-warn'; icon = 'bi-shield-exclamation'; title = 'Changes not backed up'; }
        if (s.last_ts) sub = 'Last: ' + esc(s.last_when) + ' · ' + esc(s.last_ago);
        else sub = 'Press Back up now to make the first one.';
        el.className = cls;
        el.innerHTML =
            '<div class="bk-row"><i class="bi ' + icon + ' bk-ico"></i><div style="flex:1;min-width:0">' +
            '<div class="bk-title">' + title + '</div><div class="bk-sub">' + sub + '</div>' +
            '<div class="bk-actions"><button type="button" class="bk-btn bk-btn-gold" data-bk="now"><i class="bi bi-cloud-arrow-up"></i>Back up now</button>' +
            '<a class="bk-btn bk-btn-ghost" href="backup.php"><i class="bi bi-gear"></i>Manage</a></div></div></div>';
        var b = $('[data-bk="now"]', el);
        if (b) b.addEventListener('click', function () { BK.open({ mode: 'manual', onDone: function () { loadStatus().then(renderTile); } }); });
    }

    function renderTileBusy(text) {
        var el = $('#bk-tile');
        if (!el) return;
        el.className = '';
        el.innerHTML = '<div class="bk-row"><span class="bk-spin" style="margin-top:.2rem"></span><div><div class="bk-title">' + esc(text) + '</div><div class="bk-sub">Please keep this page open.</div></div></div>';
    }

    function maybeAutoBackup() {
        var s = state.status;
        if (!s || !s.auto_due || state.autoStarted || !$('#bk-tile')) return;
        state.autoStarted = true;
        renderTileBusy('Automatic backup running…');
        api('auto', {}).then(function () { return loadStatus(); }).then(renderTile).catch(renderTile);
    }

    // ------------------------------------------------------------ the window
    var overlay = null;

    function closeModal() { if (overlay) { overlay.remove(); overlay = null; document.removeEventListener('keydown', onKey); } state.busy = false; }
    function onKey(e) { if (e.key === 'Escape' && overlay && !state.busy) closeModal(); }

    BK.open = function (opts) {
        opts = opts || {};
        if (overlay) return;
        injectCss();
        var logout = opts.mode === 'logout';
        var go = function (s) {
            if (!s) {   // status could not be loaded: don't trap anyone
                if (logout) { state.skipUnload = true; window.location = 'logout.php'; }
                else alert('The backup service could not be reached. Please reload the page and try again.');
                return;
            }
            build(logout, s, opts);
        };
        if (state.status) go(state.status); else loadStatus().then(go);
    };

    function build(logout, s, opts) {
        var target = s.last_target || 'local';
        if (!s.email_ok && target !== 'local') target = 'local';

        overlay = document.createElement('div');
        overlay.className = 'bk-overlay';
        var lastTxt = s.last_ts ? ('Last backup: <strong>' + esc(s.last_when) + '</strong> (' + esc(s.last_ago) + ')') : '<strong>No backup has been made yet.</strong>';
        var old = (s.state === 'none' || s.state === 'overdue') ? ' bk-old' : '';
        var changed = s.dirty ? (s.last_ts ? ' There are changes that are not backed up.' : '') : ' Nothing has changed since then.';
        overlay.innerHTML =
            '<div class="bk-modal" role="dialog" aria-modal="true" aria-label="Back up the system">' +
            '<div class="bk-head"><i class="bi bi-shield-check"></i><span>' + (logout ? 'Back up before you log out?' : 'Back up the system') + '</span></div>' +
            '<div class="bk-body" id="bk-body">' +
            '<div class="bk-last' + old + '">' + lastTxt + changed + '</div>' +
            '<label class="bk-opt" data-t="local"><input type="radio" name="bk-t" value="local"><div><div class="bk-ot"><i class="bi bi-hdd me-1"></i>Save on this computer</div><div class="bk-od">' + esc(s.local_dir) + '</div></div></label>' +
            '<label class="bk-opt' + (s.email_ok ? '' : ' bk-off') + '" data-t="email"><input type="radio" name="bk-t" value="email"' + (s.email_ok ? '' : ' disabled') + '><div style="flex:1"><div class="bk-ot"><i class="bi bi-envelope-at me-1"></i>E-mail to Gmail (cloud copy)</div>' +
            (s.email_ok ? '<div class="bk-od">Sent as an attachment to the address below.</div>' : '<div class="bk-od">E-mail is not set up yet. <a href="backup.php#settings">Set it up</a> (takes about 3 minutes).</div>') + '</div></label>' +
            '<label class="bk-opt' + (s.email_ok ? '' : ' bk-off') + '" data-t="both"><input type="radio" name="bk-t" value="both"' + (s.email_ok ? '' : ' disabled') + '><div><div class="bk-ot"><i class="bi bi-hdd-network me-1"></i>Both</div><div class="bk-od">Save on this computer and send by e-mail.</div></div></label>' +
            '<div class="bk-mailbox" id="bk-mailbox" style="display:none"><input type="email" id="bk-to" placeholder="Send to (e-mail address)" value="' + esc(s.mail_to) + '"></div>' +
            '<div id="bk-msg"></div>' +
            '</div>' +
            '<div class="bk-foot" id="bk-foot"></div></div>';
        document.body.appendChild(overlay);
        document.addEventListener('keydown', onKey);

        var foot = $('#bk-foot', overlay);
        function chooseButtons() {
            foot.innerHTML = '';
            if (logout) foot.appendChild(btn('Log out without backup', 'bk-btn-ghost', function () { state.skipUnload = true; window.location = 'logout.php'; }));
            foot.appendChild(btn('Cancel', 'bk-btn-ghost', closeModal));
            foot.appendChild(btn(logout ? 'Back up, then log out' : 'Back up', 'bk-btn-purple', run));
        }
        function btn(text, cls, fn) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'bk-btn ' + cls; b.textContent = text;
            b.addEventListener('click', fn);
            return b;
        }
        function select(t) {
            target = t;
            [].forEach.call(overlay.querySelectorAll('.bk-opt'), function (o) {
                var on = o.getAttribute('data-t') === t;
                o.classList.toggle('bk-sel', on);
                var r = $('input', o); if (r) r.checked = on;
            });
            $('#bk-mailbox', overlay).style.display = (t === 'email' || t === 'both') ? 'block' : 'none';
        }
        [].forEach.call(overlay.querySelectorAll('.bk-opt'), function (o) {
            o.addEventListener('click', function (e) {
                if (o.classList.contains('bk-off')) return;
                if (e.target.tagName === 'A') return;
                select(o.getAttribute('data-t'));
            });
        });
        select(target);
        chooseButtons();

        function run() {
            if (state.busy) return;
            var to = ($('#bk-to', overlay).value || '').trim();
            if ((target === 'email' || target === 'both') && !to) {
                $('#bk-msg', overlay).innerHTML = '<div class="bk-err">Type the e-mail address that should receive the backup.</div>';
                $('#bk-to', overlay).focus();
                return;
            }
            state.busy = true;
            $('#bk-body', overlay).innerHTML = '<div class="bk-res"><span class="bk-spin"></span><div><strong>Backing up…</strong><small>' +
                (target === 'local' ? 'Saving a copy on this computer.' : 'Saving and sending by e-mail — this can take up to half a minute.') + '</small></div></div>';
            foot.innerHTML = '';
            api('run', { target: target, to: to, type: logout ? 'logout' : 'manual' }).then(function (r) {
                state.busy = false;
                if (r && r.status) state.status = r.status;
                showResult(r || { ok: false, error: 'No answer from the server.' });
            }).catch(function () {
                state.busy = false;
                showResult({ ok: false, error: 'The server could not be reached.' });
            });
        }

        function showResult(r) {
            var h = '';
            if (r.login) { h = '<div class="bk-err">Your session has ended. Please log in again.</div>'; }
            else {
                if (r.local) h += res(r.local.ok, r.local.ok ? 'Saved on this computer' : 'Could not save on this computer', r.local.ok ? (r.file + (r.size ? ' (' + r.size + ')' : '')) : r.local.error);
                if (r.mirror) h += res(r.mirror.ok, r.mirror.ok ? 'Second copy saved' : 'Second copy failed', r.mirror.ok ? r.mirror.dir : r.mirror.error);
                if (r.mail) h += res(r.mail.ok, r.mail.ok ? 'E-mailed' : 'E-mail failed', r.mail.ok ? ('Sent to ' + r.mail.to) : r.mail.error);
                if (!r.local && !r.mail && r.error) h += res(false, 'The backup failed', r.error);
                if (!h) h = res(!!r.ok, r.ok ? 'Backup finished' : 'The backup failed', r.error || '');
            }
            $('#bk-body', overlay).innerHTML = h;
            var allOk = !!r.ok && !(r.mail && !r.mail.ok) && !(r.local && !r.local.ok);
            foot.innerHTML = '';
            if (logout) {
                if (allOk) {
                    foot.appendChild(btn('Log out now', 'bk-btn-purple', function () { state.skipUnload = true; window.location = 'logout.php'; }));
                    setTimeout(function () { if (overlay) { state.skipUnload = true; window.location = 'logout.php'; } }, 1800);
                } else {
                    foot.appendChild(btn('Try again', 'bk-btn-ghost', function () { closeModal(); BK.open(opts); }));
                    foot.appendChild(btn('Log out anyway', 'bk-btn-purple', function () { state.skipUnload = true; window.location = 'logout.php'; }));
                }
            } else {
                foot.appendChild(btn('Close', 'bk-btn-purple', function () { closeModal(); if (opts.onDone) opts.onDone(r); }));
            }
        }
        function res(ok, title, detail) {
            return '<div class="bk-res ' + (ok ? 'ok' : 'bad') + '"><i class="bi ' + (ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill') + '"></i><div><strong>' + esc(title) + '</strong>' + (detail ? '<small>' + esc(detail) + '</small>' : '') + '</div></div>';
        }
    }

    // ------------------------------------------------------------ Logout button
    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a) return;
        var href = a.getAttribute('href') || '';
        if (/(^|\/)logout\.php(\?|#|$)/.test(href)) {
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
            e.preventDefault();
            BK.open({ mode: 'logout' });
            return;
        }
        // moving inside the system is not "leaving": don't show the browser warning for it
        if (href && href.charAt(0) !== '#' && a.target !== '_blank' && !/^(mailto|tel|javascript):/i.test(href)) {
            state.skipUnload = true;
            setTimeout(function () { state.skipUnload = false; }, 2500);
        }
    }, true);
    document.addEventListener('submit', function () {
        state.skipUnload = true;
        setTimeout(function () { state.skipUnload = false; }, 2500);
    }, true);

    // ------------------------------------------------------------ closing the tab / window
    window.addEventListener('beforeunload', function (e) {
        if (!state.busy) {                       // a backup that is running always deserves a warning
            if (state.skipUnload) return;
            if (!state.status || !state.status.dirty) return;
        }
        e.preventDefault();
        e.returnValue = '';
        return '';
    });

    // ------------------------------------------------------------ start
    document.addEventListener('DOMContentLoaded', function () {
        injectCss();
        loadStatus().then(function () {
            renderTile();
            maybeAutoBackup();
        });
    });
    window.addEventListener('pageshow', function (e) { if (e.persisted) loadStatus().then(renderTile); });
})();
