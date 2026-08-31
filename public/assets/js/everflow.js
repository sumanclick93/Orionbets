(function () {
  var KEY = 'orion_ef_transaction_id';
  var COOKIE = 'ef_transaction_id';
  var TID_COOKIE = 'ef_tid';
  var IMP_COOKIE = 'ef_iid';
  var IMP_JS_COOKIE = 'ef_impression_id';
  var DAYS = 90;

  var param = function (name) {
    try {
      return new URLSearchParams(window.location.search).get(name);
    } catch (err) {
      return null;
    }
  };

  var valid = function (value) {
    return /^[a-fA-F0-9]{32}$/.test(String(value || ''));
  };

  var clip = function (value, max) {
    var text = String(value || '').trim();
    if (!text) return '';
    return text.slice(0, max || 255);
  };

  var emptyTracking = function () {
    return {
      id: '',
      impression_id: '',
      click_type: '',
      sub1: '',
      sub2: '',
      sub3: '',
      sub4: '',
      sub5: '',
      affid: '',
      oid: '',
      source_id: '',
      creative_id: ''
    };
  };

  var readStore = function () {
    try {
      var raw = window.localStorage.getItem(KEY);
      if (!raw) return emptyTracking();
      var data = JSON.parse(raw);
      if (!data) return emptyTracking();
      if (data.exp && Date.now() > Number(data.exp)) {
        window.localStorage.removeItem(KEY);
        return emptyTracking();
      }
      var id = valid(data.id) ? String(data.id) : '';
      return {
        id: id,
        impression_id: valid(data.impression_id) ? String(data.impression_id) : '',
        click_type: clip(data.click_type, 20),
        sub1: clip(data.sub1),
        sub2: clip(data.sub2),
        sub3: clip(data.sub3),
        sub4: clip(data.sub4),
        sub5: clip(data.sub5),
        affid: clip(data.affid || data.affiliate_id, 64),
        oid: clip(data.oid || data.offer_id, 64),
        source_id: clip(data.source_id, 64),
        creative_id: clip(data.creative_id, 64)
      };
    } catch (err) {
      return emptyTracking();
    }
  };

  var writeCookies = function (id) {
    if (!valid(id)) return;
    var maxAge = DAYS * 86400;
    var base = ';path=/;max-age=' + maxAge + ';SameSite=Lax';
    document.cookie = COOKIE + '=' + encodeURIComponent(id) + base;
    document.cookie = TID_COOKIE + '=' + encodeURIComponent(id) + base;
    document.cookie = '_ef_transaction_id=' + encodeURIComponent(id) + base;
    document.cookie = 'orion_ef_tid=' + encodeURIComponent(id) + base;
  };

  var writeImpressionCookie = function (id) {
    if (!valid(id)) return;
    var maxAge = DAYS * 86400;
    var base = ';path=/;max-age=' + maxAge + ';SameSite=Lax';
    document.cookie = IMP_COOKIE + '=' + encodeURIComponent(id) + base;
    document.cookie = IMP_JS_COOKIE + '=' + encodeURIComponent(id) + base;
  };

  var writeStore = function (tracking) {
    var current = Object.assign(emptyTracking(), readStore(), tracking || {});
    if (current.id && !valid(current.id)) current.id = '';
    if (current.impression_id && !valid(current.impression_id)) current.impression_id = '';
    var exp = Date.now() + DAYS * 86400 * 1000;
    try {
      window.localStorage.setItem(KEY, JSON.stringify({
        id: current.id,
        impression_id: current.impression_id,
        click_type: current.click_type,
        sub1: current.sub1,
        sub2: current.sub2,
        sub3: current.sub3,
        sub4: current.sub4,
        sub5: current.sub5,
        affid: current.affid,
        oid: current.oid,
        source_id: current.source_id,
        creative_id: current.creative_id,
        exp: exp
      }));
    } catch (err) {}
    if (current.id && current.click_type !== 'impression') writeCookies(current.id);
    if (current.impression_id) writeImpressionCookie(current.impression_id);
    return current;
  };

  var cookieVal = function (name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[$()*+./?[\\\]^{|}]/g, '\\$&') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
  };

  var cfg = {};
  try {
    var el = document.getElementById('orion-everflow-config');
    if (el && el.textContent) cfg = JSON.parse(el.textContent) || {};
  } catch (err) {}

  var fromUrl = [param('_ef_transaction_id'), param('ef_transaction_id'), param('ef_id'), param('transaction_id')].find(valid) || '';
  var fromImp = [param('impression_id'), param('_ef_impression_id'), param('imp_id'), param('ef_impression_id')].find(valid) || '';
  var fromParams = {
    id: fromUrl,
    impression_id: fromImp,
    click_type: fromUrl ? 'redirect' : (fromImp ? 'impression' : ''),
    sub1: clip(param('sub1')),
    sub2: clip(param('sub2')),
    sub3: clip(param('sub3')),
    sub4: clip(param('sub4')),
    sub5: clip(param('sub5')),
    affid: clip(param('affid') || param('affiliate_id'), 64),
    oid: clip(param('oid') || param('offer_id'), 64),
    source_id: clip(param('source_id') || param('sourceid') || param('sid'), 64),
    creative_id: clip(param('creative_id') || param('creativeid') || param('crid'), 64)
  };
  var stored = readStore();
  var inbound = !!(fromUrl || fromImp || fromParams.sub1 || fromParams.affid || fromParams.oid);
  if (inbound) {
    stored = writeStore(fromParams);
  }

  var tid = fromUrl || stored.id || [cookieVal(TID_COOKIE), cookieVal('orion_ef_tid'), cookieVal(COOKIE), cookieVal('_ef_transaction_id')].find(valid) || '';
  var impressionId = fromImp || stored.impression_id || [cookieVal(IMP_COOKIE), cookieVal(IMP_JS_COOKIE)].find(valid) || '';
  if (!tid && impressionId) {
    tid = impressionId;
    stored = writeStore({ id: tid, impression_id: impressionId, click_type: stored.click_type || 'impression' });
  } else if (tid) {
    stored = writeStore({ id: tid, impression_id: impressionId || stored.impression_id });
  }
  window.orionEverflowTid = tid || '';
  window.orionEverflowTracking = function () {
    var now = readStore();
    return {
      transaction_id: now.id || window.orionEverflowTid || '',
      impression_id: now.impression_id || '',
      click_type: now.click_type || '',
      sub1: now.sub1 || '',
      sub2: now.sub2 || '',
      sub3: now.sub3 || '',
      sub4: now.sub4 || '',
      sub5: now.sub5 || '',
      affid: now.affid || '',
      oid: now.oid || '',
      source_id: now.source_id || '',
      creative_id: now.creative_id || ''
    };
  };

  var ingest = function (tracking) {
    var payload = tracking || window.orionEverflowTracking();
    if (!payload.transaction_id && !payload.impression_id) return;
    var url = cfg.ingest_url || '/everflow/ingest';
    try {
      fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      }).catch(function () {});
    } catch (err) {}
  };

  var ready = function (fn) {
    var tries = 0;
    var timer = window.setInterval(function () {
      tries += 1;
      if (typeof window.EF !== 'undefined') {
        window.clearInterval(timer);
        fn(window.EF);
      } else if (tries > 80) {
        window.clearInterval(timer);
      }
    }, 100);
  };

  var hasDirectParams = !!(fromParams.affid || fromParams.oid || fromParams.sub1 || fromParams.sub2 || fromParams.sub3);
  var capture = cfg.capture !== false;
  var sdkId = function (EF) {
    var candidates = [];
    try {
      if (EF && typeof EF.urlParameter === 'function') {
        candidates.push(
          EF.urlParameter('_ef_transaction_id'),
          EF.urlParameter('ef_transaction_id'),
          EF.urlParameter('transaction_id'),
          EF.urlParameter('impression_id'),
          EF.urlParameter('_ef_impression_id')
        );
      }
    } catch (err) {}
    try {
      if (EF && typeof EF.cookie === 'function') {
        candidates.push(
          EF.cookie('_ef_transaction_id'),
          EF.cookie('ef_transaction_id'),
          EF.cookie('transaction_id'),
          EF.cookie('ef_tid')
        );
      }
    } catch (err) {}
    candidates.push(cookieVal('_ef_transaction_id'), cookieVal(TID_COOKIE), cookieVal(COOKIE), cookieVal('orion_ef_tid'));
    return candidates.find(valid) || '';
  };

  var attachSubs = function (payload) {
    var now = readStore();
    ['sub1', 'sub2', 'sub3', 'sub4', 'sub5'].forEach(function (key) {
      if (now[key]) payload[key] = now[key];
    });
    return payload;
  };

  var adoptId = function (id, type) {
    if (!valid(id)) return false;
    tid = id;
    window.orionEverflowTid = id;
    stored = writeStore({ id: id, click_type: type || stored.click_type || (fromUrl ? 'redirect' : (hasDirectParams ? 'direct' : 'landing')) });
    ingest(window.orionEverflowTracking());
    return true;
  };

  var trackClick = function (EF) {
    if (!capture) return;
    if (adoptId(sdkId(EF), stored.click_type || (fromUrl ? 'redirect' : (fromImp ? 'impression' : '')))) return;
    if (tid) {
      ingest(window.orionEverflowTracking());
      return;
    }
    var oid = (typeof EF.urlParameter === 'function' && EF.urlParameter('oid')) || stored.oid || cfg.offer_id || '';
    var affid = (typeof EF.urlParameter === 'function' && EF.urlParameter('affid')) || stored.affid || cfg.affiliate_id || '';
    if (!oid || !affid) return;
    var payload = attachSubs({ offer_id: Number(oid) || oid, affiliate_id: Number(affid) || affid });
    var type = fromImp ? 'impression' : (hasDirectParams ? 'direct' : 'landing');
    try {
      if (fromImp && typeof EF.impression === 'function') {
        try { EF.impression(payload); } catch (err) {}
      }
      var result = EF.click(payload);
      if (result && typeof result.then === 'function') {
        result.then(function (id) { adoptId(id, type); });
      } else if (!adoptId(result, type)) {
        window.setTimeout(function () { adoptId(sdkId(EF), type); }, 600);
      }
    } catch (err) {
      window.setTimeout(function () { adoptId(sdkId(EF), type); }, 600);
    }
  };

  var fireConversion = function (EF, details) {
    if (!details || !tid) return;
    try {
      EF.conversion(attachSubs({
        transaction_id: tid,
        amount: details.amount,
        order_id: details.order_id
      }));
    } catch (err) {}
  };

  if (typeof window.EF !== 'undefined') trackClick(window.EF);
  else ready(trackClick);

  var thanks = window.orionThankYou;
  if (thanks) {
    if (thanks.everflow_transaction_id && valid(thanks.everflow_transaction_id)) {
      tid = thanks.everflow_transaction_id;
      window.orionEverflowTid = tid;
      stored = writeStore({ id: tid });
    }
    var convert = function () {
      ready(function (EF) {
        fireConversion(EF, thanks);
      });
    };
    if (thanks.paid) {
      convert();
    } else if (thanks.pending && thanks.token) {
      var ticks = 0;
      var poll = window.setInterval(function () {
        ticks += 1;
        fetch((thanks.status_url || '/checkout/status') + '?token=' + encodeURIComponent(thanks.token) + '&probe=1', {
          credentials: 'same-origin',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) { return res.json(); }).then(function (data) {
          if (data && data.status === 'completed') {
            window.clearInterval(poll);
            window.location.reload();
          }
          if (ticks > 40) window.clearInterval(poll);
        }).catch(function () {});
      }, 3000);
    }
  }

  window.orionEverflowId = function () {
    return tid || readStore().id || '';
  };
})();
