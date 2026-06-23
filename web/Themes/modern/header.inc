<?php
declare(strict_types=1);
namespace MRBS;

use MRBS\Form\ElementInputDate;
use MRBS\Form\ElementInputSearch;
use MRBS\Form\ElementInputSubmit;
use MRBS\Form\Form;

// ============================================================
// MRBS Modern Theme — header functions
// Layout: fixed sidebar (dark) + scrollable main with sticky topbar
// ============================================================


function print_head(bool $simple = false) : void
{
  global $refresh_rate;

  echo "<head>\n";
  echo "<meta charset=\"" . Language::MRBS_CHARSET . "\">\n";
  echo "<meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">\n";
  echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";

  if (!$simple)
  {
    echo "<meta name=\"csrf_token\" content=\"" . escape_html(Form::getToken()) . "\">\n";
  }

  echo "<meta name=\"robots\" content=\"noindex, nofollow, noarchive\">\n";

  if (($refresh_rate != 0) && (this_page(false, '.php') == 'index'))
  {
    echo "<noscript>\n";
    echo "<meta http-equiv=\"Refresh\" content=\"$refresh_rate\">\n";
    echo "</noscript>\n";
  }

  echo "<title>" . get_vocab("mrbs") . "</title>\n";

  // Tabler icon webfont (outline icons, ~5800 icons)
  echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.21.0/tabler-icons.min.css">' . "\n";

  require_once MRBS_ROOT . "/style.inc";

  if (!$simple)
  {
    require_once MRBS_ROOT . "/js.inc";
  }

  echo "</head>\n";
}


// Outputs the logo block used in the sidebar and the simple header.
function print_header_site_info() : void
{
  global $mrbs_company, $mrbs_company_url, $mrbs_company_logo;

  echo "<div class=\"mrbs-logo\">\n";

  // Logo mark — use company logo image if set, otherwise the building icon
  echo "<div class=\"mrbs-logo-mark\">\n";
  if (!empty($mrbs_company_logo))
  {
    $logo_size = @getimagesize($mrbs_company_logo);
    echo '<img src="' . escape_html($mrbs_company_logo) . '" alt="' . escape_html($mrbs_company) . '"';
    if (is_array($logo_size))
    {
      echo ' ' . $logo_size[3];
    }
    echo ' class="mrbs-logo-img">';
  }
  else
  {
    echo '<i class="ti ti-building" aria-hidden="true"></i>';
  }
  echo "</div>\n";

  // Company name + app name
  echo "<div class=\"mrbs-logo-text\">\n";
  if (!empty($mrbs_company_url))
  {
    echo '<a href="' . escape_html($mrbs_company_url) . '" class="mrbs-company-link">';
  }
  echo '<span class="mrbs-company-name">' . escape_html($mrbs_company) . '</span>';
  if (!empty($mrbs_company_url))
  {
    echo "</a>";
  }
  echo "\n";
  echo '<span class="mrbs-app-label">' . get_vocab('mrbs') . "</span>\n";
  echo "</div>\n";

  echo "</div>\n";
}


// Goto-date form (JS shows/hides via js_hidden class).
function print_goto_date(array $context) : void
{
  global $multisite, $site;

  if (!checkAuthorised('index.php', true))
  {
    return;
  }

  $form     = new Form();
  $form_id  = 'form_nav';

  $form->setAttributes(array(
         'id'     => $form_id,
         'class'  => 'js_hidden mrbs-goto-date',
         'action' => multisite('index.php')))
       ->addHiddenInput('view', $context['view']);

  if (isset($context['area']))
  {
    $form->addHiddenInput('area', $context['area']);
  }
  if (isset($context['room']))
  {
    $form->addHiddenInput('room', $context['room']);
  }
  if ($multisite && isset($site) && ($site !== ''))
  {
    $form->addHiddenInput('site', $site);
  }

  $input = new ElementInputDate();
  $input->setAttributes(array(
    'name'        => 'page_date',
    'value'       => format_iso_date($context['year'], $context['month'], $context['day']),
    'class'       => 'navigation',
    'aria-label'  => get_vocab('goto'),
    'required'    => true,
    'data-submit' => $form_id,
  ));
  $form->addElement($input);

  $submit = new ElementInputSubmit();
  $submit->setAttribute('value', get_vocab('goto'));
  $form->addElement($submit);

  $form->render();
}


// Pending-bookings notification badge in the topbar.
function print_outstanding(string $query) : void
{
  $mrbs_user = session()->getCurrentUser();

  if (!isset($mrbs_user))
  {
    return;
  }

  $approval_somewhere = some_area('approval_enabled', TRUE);

  if ($approval_somewhere && ($mrbs_user->level > 0))
  {
    $n_outstanding = get_entries_n_outstanding($mrbs_user);

    $class = 'mrbs-outstanding';
    if ($n_outstanding > 0)
    {
      $class .= ' mrbs-outstanding--attention';
    }

    $url = 'pending.php';
    if ($query !== '')
    {
      $url .= "?$query";
    }

    echo '<a href="' . escape_html(multisite($url)) . '" class="' . $class . '"'
       . ' title="' . get_vocab('outstanding', $n_outstanding) . '">';
    echo '<i class="ti ti-bell" aria-hidden="true"></i>';
    if ($n_outstanding > 0)
    {
      echo '<span class="mrbs-badge">' . $n_outstanding . '</span>';
    }
    echo "</a>\n";
  }
}


// Sidebar navigation links.
function print_menu_items(string $query) : void
{
  global $auth, $kiosk_mode_enabled;

  $current_page = this_page(false, '.php') . '.php';

  $items = array(
    'report'    => array('page' => 'report.php',    'icon' => 'ti-chart-bar'),
    'search'    => array('page' => 'search.php',    'icon' => 'ti-search'),
    'import'    => array('page' => 'import.php',    'icon' => 'ti-upload'),
    'help'      => array('page' => 'help.php',      'icon' => 'ti-help-circle'),
  );

  if ($kiosk_mode_enabled)
  {
    $items['kiosk'] = array('page' => 'kiosk.php', 'icon' => 'ti-device-tv');
  }

  $items['rooms'] = array('page' => 'admin.php', 'icon' => 'ti-building');

  if (auth()->canCreateUsers())
  {
    $items['user_list'] = array('page' => 'edit_users.php', 'icon' => 'ti-users');
  }

  // edit_site_settings.php has no special case in get_page_level(), so it defaults
  // to $max_level (admin only) and will be filtered out below for non-admins.
  $items['site_settings'] = array('page' => 'edit_site_settings.php', 'icon' => 'ti-settings');

  foreach ($items as $token => $item)
  {
    if (!checkAuthorised($item['page'], true))
    {
      continue;
    }

    $url   = $item['page'];
    if ($query !== '')
    {
      $url .= '?' . $query;
    }

    $active = ($current_page === $item['page']);
    $class  = 'mrbs-nav-item' . ($active ? ' mrbs-nav-item--active' : '');

    echo '<a href="' . escape_html(multisite($url)) . '" class="' . $class . '">';
    echo '<i class="ti ' . $item['icon'] . '" aria-hidden="true"></i>';
    echo '<span>' . get_vocab($token) . '</span>';
    echo "</a>\n";
  }
}


// Search form in the topbar.
function print_search(array $context) : void
{
  if (!checkAuthorised('search.php', true))
  {
    return;
  }

  $form = new Form(Form::METHOD_POST);
  $form->setAttributes(array(
         'id'     => 'header_search',
         'class'  => 'mrbs-topbar-search',
         'action' => multisite('search.php')))
       ->addHiddenInputs(array(
         'view'      => $context['view'],
         'year'      => $context['year'],
         'month'     => $context['month'],
         'day'       => $context['day'],
         'from_date' => format_iso_date($context['year'], $context['month'], $context['day']),
       ));

  if (!empty($context['area']))
  {
    $form->addHiddenInput('area', $context['area']);
  }
  if (!empty($context['room']))
  {
    $form->addHiddenInput('room', $context['room']);
  }

  $search_vocab = get_vocab('search');
  $input        = new ElementInputSearch();
  $input->setAttributes(array(
    'name'        => 'search_str',
    'placeholder' => $search_vocab,
    'aria-label'  => $search_vocab,
    'required'    => true,
  ));
  $form->addElement($input);

  $submit = new ElementInputSubmit();
  $submit->setAttributes(array(
    'value' => get_vocab('search_button'),
    'class' => 'js_none',
  ));
  $form->addElement($submit);

  $form->render();
}


// "Show my entries" link in the topbar user chip.
function print_report_link(User $user) : void
{
  if (checkAuthorised('report.php', true))
  {
    $attributes   = array('action' => multisite('report.php'));
    $hidden_inputs = array(
      'phase'        => '2',
      'creatormatch' => $user->username,
    );
  }
  elseif (checkAuthorised('search.php', true))
  {
    $attributes   = array('action' => multisite('search.php'));
    $date_now     = new DateTime();
    $hidden_inputs = array(
      'search_str' => $user->username,
      'from_date'  => $date_now->getISODate(),
    );
  }
  else
  {
    echo '<span class="mrbs-user-display-name">' . escape_html($user->display_name) . '</span>';
    return;
  }

  $form = new Form(Form::METHOD_POST);
  $attributes['id']    = 'show_my_entries';
  $attributes['class'] = 'mrbs-user-form';
  $form->setAttributes($attributes)
       ->addHiddenInputs($hidden_inputs);

  $submit = new ElementInputSubmit();
  $submit->setAttributes(array(
    'title' => get_vocab('show_my_entries'),
    'value' => $user->display_name,
    'class' => 'mrbs-user-name-btn',
  ));
  $form->addElement($submit);
  $form->render();
}


function print_logonoff_button(array $params, string $value) : void
{
  $form = new Form($params['method']);
  $form->setAttributes(array(
    'action' => $params['action'],
    'class'  => 'mrbs-auth-form',
  ));

  if ($params['method'] == Form::METHOD_GET)
  {
    $query_string = parse_url($params['action'], PHP_URL_QUERY);
    if (isset($query_string))
    {
      parse_str($query_string, $query_parameters);
      $form->addHiddenInputs($query_parameters);
    }
  }

  if (isset($params['hidden_inputs']))
  {
    $form->addHiddenInputs($params['hidden_inputs']);
  }

  $element = new ElementInputSubmit();
  $element->setAttributes(array(
    'value' => $value,
    'class' => 'mrbs-auth-btn',
  ));
  $form->addElement($element);
  $form->render();
}


function print_logon() : void
{
  if (method_exists(session(), 'getLogonFormParams'))
  {
    $form_params = session()->getLogonFormParams();
    if (isset($form_params))
    {
      print_logonoff_button($form_params, get_vocab('login'));
    }
  }

  // Self-registration is only meaningful for the 'db' auth type, and only
  // when the admin has opted in via the Site Settings "User Registration" setting.
  if (auth()->canCreateUsers() && (get_site_setting('enable_registration', '0') === '1'))
  {
    $url = multisite('register.php');
    echo '<a href="' . escape_html($url) . '" class="mrbs-sidebar-register">' . get_vocab('register_new_account') . "</a>\n";
  }
}


function print_logoff() : void
{
  if (method_exists(session(), 'getLogoffFormParams'))
  {
    $form_params = session()->getLogoffFormParams();
    if (isset($form_params))
    {
      print_logonoff_button($form_params, get_vocab('logoff'));
    }
  }
}


// Sidebar room/area tree — queries DB for enabled areas and their rooms.
function print_sidebar_rooms(int $current_area, int $current_room) : void
{
  // Accent colors cycling through rooms
  $accent_colors = array(
    '#4F46E5', '#0D9488', '#D97706', '#9333EA',
    '#E11D48', '#16A34A', '#2563EB', '#DC2626',
  );
  $color_idx = 0;

  try
  {
    $sql_areas = "SELECT id, area_name FROM " . _tbl('area')
               . " WHERE disabled = 0 ORDER BY area_name";
    $areas = db()->query($sql_areas);
  }
  catch (\Exception $e)
  {
    return;
  }

  while (false !== ($area = $areas->next_row_keyed()))
  {
    $area_id = (int) $area['id'];

    echo '<div class="mrbs-area-group">' . "\n";
    echo '<button class="mrbs-area-header" aria-expanded="true"'
       . ' data-area="' . $area_id . '">';
    echo '<i class="ti ti-chevron-down" aria-hidden="true"></i>';
    echo '<span>' . escape_html($area['area_name']) . '</span>';
    echo "</button>\n";

    echo '<div class="mrbs-area-rooms">' . "\n";

    try
    {
      $sql_rooms = "SELECT id, room_name FROM " . _tbl('room')
                 . " WHERE area_id = ? AND disabled = 0 ORDER BY room_name";
      $rooms = db()->query($sql_rooms, array($area_id));
    }
    catch (\Exception $e)
    {
      echo "</div></div>\n";
      continue;
    }

    while (false !== ($room = $rooms->next_row_keyed()))
    {
      $room_id = (int) $room['id'];
      $color   = $accent_colors[$color_idx % count($accent_colors)];
      $color_idx++;

      $active = ($room_id === $current_room);
      $class  = 'mrbs-room-item' . ($active ? ' mrbs-room-item--active' : '');
      $url    = multisite('index.php?area=' . $area_id . '&room=' . $room_id);

      echo '<a href="' . escape_html($url) . '" class="' . $class . '">';
      echo '<span class="mrbs-room-dot" style="background:' . $color . '"></span>';
      echo '<span>' . escape_html($room['room_name']) . '</span>';
      echo "</a>\n";
    }

    echo "</div>\n"; // mrbs-area-rooms
    echo "</div>\n"; // mrbs-area-group
  }
}


// Unsupported-browser notice — self-contained, shown only for IE ≤ 9.
function print_unsupported_message(?array $context) : void
{
  echo "<div class=\"unsupported_message\">\n";
  echo "<header class=\"banner simple\">\n";
  print_header_site_info();
  echo "</header>\n";
  echo "<div class=\"contents\">\n";
  echo "<p>" . get_vocab('browser_not_supported', get_vocab('mrbs_abbr')) . "</p>\n";
  echo "</div>\n";
  echo "</div>\n";
}


// Main entry point — outputs everything up to and including the opening <div class="contents">.
function print_banner(?array $context, $simple = false, $omit_login = false) : void
{
  global $auth, $kiosk_QR_code;

  // Build query string used in nav links
  $vars = array();
  if (isset($context['view']))
  {
    $vars['view'] = $context['view'];
  }
  if (isset($context['year']) && isset($context['month']) && isset($context['day']))
  {
    $vars['page_date'] = format_iso_date($context['year'], $context['month'], $context['day']);
  }
  if (isset($context['area']))
  {
    $vars['area'] = $context['area'];
  }
  if (isset($context['room']))
  {
    $vars['room'] = $context['room'];
  }
  $query = http_build_query($vars, '', '&');

  $current_area = isset($context['area']) ? (int) $context['area'] : 0;
  $current_room = isset($context['room']) ? (int) $context['room'] : 0;

  // ── App shell ──────────────────────────────────────────────
  $app_class = 'mrbs-app' . ($simple ? ' mrbs-app--simple' : '');
  echo '<div class="' . $app_class . '">' . "\n";

  // ── Sidebar (full mode only) ───────────────────────────────
  if (!$simple)
  {
    echo '<aside class="mrbs-sidebar" aria-label="Main navigation">' . "\n";

    // Logo
    print_header_site_info();

    // Primary nav
    echo '<nav class="mrbs-nav" aria-label="Site navigation">' . "\n";
    echo '<p class="mrbs-nav-section-label">Menu</p>' . "\n";

    // Calendar (always shown)
    $cal_url    = multisite('index.php' . ($query ? '?' . $query : ''));
    $is_cal     = (this_page(false, '.php') === 'index');
    $cal_class  = 'mrbs-nav-item' . ($is_cal ? ' mrbs-nav-item--active' : '');
    echo '<a href="' . escape_html($cal_url) . '" class="' . $cal_class . '">';
    echo '<i class="ti ti-calendar-event" aria-hidden="true"></i>';
    echo '<span>' . get_vocab('mrbs') . '</span>';
    echo "</a>\n";

    // Pending bookings (if approval is enabled and user is logged in)
    $mrbs_user = session()->getCurrentUser();
    if (isset($mrbs_user) && $mrbs_user->level > 0)
    {
      $approval_somewhere = some_area('approval_enabled', TRUE);
      if ($approval_somewhere)
      {
        $n_outstanding  = get_entries_n_outstanding($mrbs_user);
        $pending_url    = multisite('pending.php' . ($query ? '?' . $query : ''));
        $is_pending     = (this_page(false, '.php') === 'pending');
        $pending_class  = 'mrbs-nav-item' . ($is_pending ? ' mrbs-nav-item--active' : '');

        echo '<a href="' . escape_html($pending_url) . '" class="' . $pending_class . '">';
        echo '<i class="ti ti-hourglass" aria-hidden="true"></i>';
        echo '<span>' . get_vocab('outstanding', $n_outstanding) . '</span>';
        if ($n_outstanding > 0)
        {
          echo '<span class="mrbs-sidebar-badge">' . $n_outstanding . '</span>';
        }
        echo "</a>\n";
      }
    }

    print_menu_items($query);
    echo "</nav>\n";

    // Room / area tree
    echo '<div class="mrbs-sidebar-rooms">' . "\n";
    echo '<p class="mrbs-nav-section-label">Rooms</p>' . "\n";
    print_sidebar_rooms($current_area, $current_room);
    echo "</div>\n";

    // User profile at bottom
    echo '<div class="mrbs-sidebar-footer">' . "\n";

    $mrbs_user = session()->getCurrentUser();
    $has_user  = isset($mrbs_user) &&
                 (!$auth['allow_anonymous_booking'] || ($mrbs_user->username !== ''));

    if ($has_user)
    {
      // Build initials
      $initials   = '';
      $name_parts = explode(' ', $mrbs_user->display_name);
      foreach (array_slice($name_parts, 0, 2) as $part)
      {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
      }
      $role = ($mrbs_user->level >= 2) ? 'Admin' : 'User';

      echo '<div class="mrbs-user-chip">';
      echo '<div class="mrbs-avatar">' . escape_html($initials) . '</div>';
      echo '<div class="mrbs-user-meta">';
      print_report_link($mrbs_user);
      echo '<span class="mrbs-user-role">' . $role . '</span>';
      echo '</div>';
      echo '</div>' . "\n";

      echo '<div class="mrbs-sidebar-logoff">';
      print_logoff();
      echo '</div>' . "\n";
    }
    elseif (!$omit_login)
    {
      echo '<div class="mrbs-sidebar-logon">';
      print_logon();
      echo '</div>' . "\n";
    }

    echo "</div>\n"; // mrbs-sidebar-footer
    echo "</aside>\n"; // mrbs-sidebar

    // QR code for kiosk mode
    if (isset($context['kiosk']) &&
        $kiosk_QR_code &&
        (version_compare(PHP_VERSION, '7.4') >= 0) &&
        defined('MB_CASE_UPPER'))
    {
      // QR code rendered via chillerlan library (same as default theme)
      $qr_url = multisite(url_base() . '/index.php?' . $query);
      echo '<div class="mrbs-kiosk-qr" title="' . escape_html($qr_url) . '">' . "\n";
      try
      {
        $qr_options = new \chillerlan\QRCode\QROptions(array(
          'outputType'  => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
          'imageBase64' => false,
        ));
        $qrcode = new \chillerlan\QRCode\QRCode($qr_options);
        echo $qrcode->render($qr_url);
      }
      catch (\Exception $e)
      {
        // silently skip if QR code library unavailable
      }
      echo "</div>\n";
    }
  } // end !$simple

  // ── Main content wrapper ───────────────────────────────────
  echo '<div class="mrbs-main">' . "\n";

  // ── Topbar ─────────────────────────────────────────────────
  echo '<header class="banner' . ($simple ? ' simple' : '') . '">' . "\n";

  if ($simple)
  {
    // Simple mode: logo only (error pages, upgrades)
    print_header_site_info();
  }
  else
  {
    // ── Left: mobile hamburger + date navigation
    echo '<div class="mrbs-topbar-left">' . "\n";

    // Hamburger for mobile (toggled by JS)
    echo '<button class="mrbs-sidebar-toggle" aria-label="Toggle menu" aria-expanded="false">';
    echo '<i class="ti ti-menu-2" aria-hidden="true"></i>';
    echo "</button>\n";

    // Prev / Today / Next (JS populates hrefs via data-view / data-page-date on body)
    echo '<nav class="mrbs-date-nav" aria-label="Date navigation">' . "\n";
    echo '<a href="#" class="mrbs-date-prev" aria-label="Previous period">';
    echo '<i class="ti ti-chevron-left" aria-hidden="true"></i></a>' . "\n";
    echo '<a href="' . escape_html(multisite('index.php' . ($query ? '?' . $query : ''))) . '"'
       . ' class="mrbs-today-btn">' . get_vocab('today') . "</a>\n";
    echo '<a href="#" class="mrbs-date-next" aria-label="Next period">';
    echo '<i class="ti ti-chevron-right" aria-hidden="true"></i></a>' . "\n";
    echo "</nav>\n";

    // Period title (day/week/month label — filled by JS)
    echo '<span class="mrbs-topbar-period" aria-live="polite"></span>' . "\n";

    echo "</div>\n"; // mrbs-topbar-left

    // ── Center: view switcher + goto-date
    echo '<div class="mrbs-topbar-center">' . "\n";

    $views    = array('day', 'week', 'month');
    $page_date = format_iso_date($context['year'], $context['month'], $context['day']);
    echo '<nav class="mrbs-view-switcher" aria-label="Calendar view">' . "\n";
    foreach ($views as $v)
    {
      $v_url   = multisite('index.php?view=' . $v . '&page_date=' . $page_date
                         . (isset($context['area']) ? '&area=' . $context['area'] : '')
                         . (isset($context['room']) ? '&room=' . $context['room'] : ''));
      $v_active = ($context['view'] === $v);
      $v_class  = 'mrbs-view-btn' . ($v_active ? ' mrbs-view-btn--active' : '');
      echo '<a href="' . escape_html($v_url) . '" class="' . $v_class . '">'
         . escape_html(ucfirst($v)) . "</a>\n";
    }
    echo "</nav>\n";

    print_goto_date($context);

    echo "</div>\n"; // mrbs-topbar-center

    // ── Right: search + notifications + new booking
    echo '<div class="mrbs-topbar-right">' . "\n";

    print_search($context);
    print_outstanding($query);

    if (checkAuthorised('edit_entry.php', true))
    {
      $new_url = multisite('edit_entry.php'
                         . (isset($context['area']) ? '?area=' . $context['area'] : '')
                         . (isset($context['room']) ? '&room=' . $context['room'] : ''));
      echo '<a href="' . escape_html($new_url) . '" class="mrbs-new-btn">';
      echo '<i class="ti ti-plus" aria-hidden="true"></i>';
      echo '<span>' . get_vocab('add_entry') . '</span>';
      echo "</a>\n";
    }

    echo "</div>\n"; // mrbs-topbar-right
  }

  echo "</header>\n"; // .banner

  // Note: <div class="contents"> is opened by print_theme_header() after this function returns.
  // It is closed by print_theme_footer() together with .mrbs-main and .mrbs-app.
}


function print_theme_header(?array $context = null, bool $simple = false, bool $omit_login = false) : void
{
  global $multisite, $site, $default_view, $default_view_all, $view_week_number, $style_weekends;

  if ($simple)
  {
    $data    = array();
    $classes = array();
  }
  else
  {
    if (!isset($context))
    {
      $context = array();
    }

    if (empty($context['area']))
    {
      $context['area'] = get_default_area();
    }

    if (empty($context['room']))
    {
      $context['room'] = get_default_room($context['area']);
    }

    if (!isset($context['view']))
    {
      $context['view'] = isset($default_view) ? $default_view : 'day';
    }

    if (!isset($context['view_all']))
    {
      $context['view_all'] = isset($default_view_all) ? $default_view_all : true;
    }

    get_area_settings($context['area']);

    if (!isset($context['year']))
    {
      $context['year'] = (int) date('Y');
    }
    if (!isset($context['month']))
    {
      $context['month'] = (int) date('m');
    }
    if (!isset($context['day']))
    {
      $context['day'] = (int) date('d');
    }

    Form::getToken();

    $page = this_page(false, '.php');

    $lang_preferences = Language::getInstance()->getPreferences();
    array_unshift($lang_preferences, mb_strtolower(Language::getInstance()->getWebLocale()));
    $lang_preferences = array_values(array_unique($lang_preferences));

    $data = array(
      'view'          => $context['view'],
      'view_all'      => $context['view_all'],
      'area'          => $context['area'],
      'room'          => $context['room'],
      'page'          => $page,
      'page-date'     => format_iso_date($context['year'], $context['month'], $context['day']),
      'is-admin'      => is_admin() ? 'true' : 'false',
      'is-book-admin' => is_book_admin() ? 'true' : 'false',
      'lang-prefs'    => json_encode($lang_preferences),
    );

    if ($multisite && isset($site) && ($site !== ''))
    {
      $data['site'] = $site;
    }

    if (isset($context['kiosk']))
    {
      $data['kiosk'] = $context['kiosk'];
    }

    $mrbs_user = session()->getCurrentUser();
    if (isset($mrbs_user))
    {
      $data['username'] = $mrbs_user->username;
    }

    $timetohighlight = get_form_var('timetohighlight', 'int');
    if (isset($timetohighlight))
    {
      $data['timetohighlight'] = $timetohighlight;
    }

    $classes = array($page);
    if (isset($mrbs_user))
    {
      $classes[] = 'logged_in';
    }
    if ($view_week_number)
    {
      $classes[] = 'view_week_number';
    }
    if ($style_weekends)
    {
      $classes[] = 'style_weekends';
    }
  }

  $headers = array("Content-Type: text/html; charset=" . Language::MRBS_CHARSET);
  http_headers($headers);

  echo DOCTYPE . "\n";

  $mrbs_lang = Language::getInstance()->getWebLang();
  echo "<!--[if lte IE 9]>\n";
  echo "<html lang=\"" . escape_html($mrbs_lang) . "\" class=\"unsupported_browser\">\n";
  echo "<![endif]-->\n";
  echo "<!--[if (!IE)|(gt IE 9)]><!-->\n";
  echo "<html lang=\"" . escape_html($mrbs_lang) . "\">\n";
  echo "<!--<![endif]-->\n";

  print_head($simple);

  echo '<body class="' . escape_html(implode(' ', $classes)) . '"';
  foreach ($data as $key => $value)
  {
    if (isset($value))
    {
      if (is_bool($value))
      {
        $value = (int) $value;
      }
      echo ' data-' . $key . '="' . escape_html((string) $value) . '"';
    }
  }
  echo ">\n";

  print_unsupported_message($context);

  print_banner($context, $simple, $omit_login);

  echo "<div class=\"contents\">\n";
}
