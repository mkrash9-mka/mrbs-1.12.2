<?php
declare(strict_types=1);
namespace MRBS;

// Modern theme footer — closes the three wrappers opened by print_banner():
//   .contents   (opened in print_theme_header)
//   .mrbs-main  (opened in print_banner)
//   .mrbs-app   (opened in print_banner)

function print_theme_footer() : void
{
  echo "</div>\n";  // .contents
  echo "</div>\n";  // .mrbs-main
  echo "</div>\n";  // .mrbs-app

  // Mobile sidebar overlay + toggle script
  echo '<div class="mrbs-sidebar-overlay" id="mrbs-overlay"></div>' . "\n";
  echo '<script>' . "\n";
  echo '(function(){' . "\n";
  echo '  var toggle  = document.querySelector(".mrbs-sidebar-toggle");' . "\n";
  echo '  var sidebar = document.querySelector(".mrbs-sidebar");' . "\n";
  echo '  var overlay = document.getElementById("mrbs-overlay");' . "\n";
  echo '  function open(){ sidebar.classList.add("open"); overlay.classList.add("open"); toggle && toggle.setAttribute("aria-expanded","true"); }' . "\n";
  echo '  function close(){ sidebar.classList.remove("open"); overlay.classList.remove("open"); toggle && toggle.setAttribute("aria-expanded","false"); }' . "\n";
  echo '  if(toggle){ toggle.addEventListener("click", function(){ sidebar.classList.contains("open") ? close() : open(); }); }' . "\n";
  echo '  if(overlay){ overlay.addEventListener("click", close); }' . "\n";
  echo '  // Wire prev/next topbar arrows using body data attributes' . "\n";
  echo '  var body = document.body;' . "\n";
  echo '  var view = body.dataset.view || "week";' . "\n";
  echo '  var pd   = body.dataset.pageDate || "";' . "\n";
  echo '  var area = body.dataset.area || "";' . "\n";
  echo '  var room = body.dataset.room || "";' . "\n";
  echo '  function buildUrl(offset){' . "\n";
  echo '    if(!pd) return "#";' . "\n";
  echo '    var d = new Date(pd + "T00:00:00");' . "\n";
  echo '    var days = {day:1,week:7,month:30};' . "\n";
  echo '    d.setDate(d.getDate() + (days[view]||7) * offset);' . "\n";
  echo '    var iso = d.toISOString().slice(0,10);' . "\n";
  echo '    var base = "index.php?view="+view+"&page_date="+iso;' . "\n";
  echo '    if(area) base += "&area="+area;' . "\n";
  echo '    if(room)  base += "&room="+room;' . "\n";
  echo '    return base;' . "\n";
  echo '  }' . "\n";
  echo '  var prev = document.querySelector(".mrbs-date-prev");' . "\n";
  echo '  var next = document.querySelector(".mrbs-date-next");' . "\n";
  echo '  if(prev) prev.href = buildUrl(-1);' . "\n";
  echo '  if(next) next.href = buildUrl(1);' . "\n";
  echo '  // Period label' . "\n";
  echo '  var lbl = document.querySelector(".mrbs-topbar-period");' . "\n";
  echo '  if(lbl && pd){' . "\n";
  echo '    var d2 = new Date(pd + "T00:00:00");' . "\n";
  echo '    var opts = {month:"long",year:"numeric"};' . "\n";
  echo '    if(view==="day") opts.day="numeric";' . "\n";
  echo '    lbl.textContent = d2.toLocaleDateString(undefined, opts);' . "\n";
  echo '  }' . "\n";
  echo '})();' . "\n";
  echo '</script>' . "\n";

  echo "</body>\n";
  echo "</html>\n";
}
