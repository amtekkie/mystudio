document.addEventListener('DOMContentLoaded', function(){
  var pollUrl = rootpath + '/themes/mystudio-default/functions/ms_pm_count.php';
  function updateBadge(unread){
    // find the existing server-side or poller badge anywhere in the page
    var existing = document.querySelector('.ms-pm-badge');
    if(unread > 0){
      if(existing){
        existing.textContent = unread;
      } else {
        // append badge to the PM link in the dropdown
        var link = document.querySelector('a[href*="private.php"]');
        if(!link) return;
        var span = document.createElement('span');
        span.className = 'ms-pm-badge badge rounded-pill bg-danger ms-1';
        span.style.fontSize = '9px';
        span.textContent = unread;
        link.appendChild(span);
      }
    } else if(existing){
      existing.remove();
    }
  }

  function poll(){
    fetch(pollUrl, {credentials: 'same-origin'})
      .then(function(r){ if(!r.ok) throw new Error('Network response not ok'); return r.json(); })
      .then(function(data){ if(typeof data.pms_unread !== 'undefined') updateBadge(parseInt(data.pms_unread,10)||0); })
      .catch(function(){});
  }

  // first poll after 5s (server badge already rendered on load), then every 30s
  setTimeout(function(){ poll(); setInterval(poll, 30000); }, 5000);
});
