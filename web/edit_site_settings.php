<?php
declare(strict_types=1);
namespace MRBS;

use MRBS\Form\Element;
use MRBS\Form\ElementDiv;
use MRBS\Form\ElementFieldset;
use MRBS\Form\ElementImg;
use MRBS\Form\ElementP;
use MRBS\Form\FieldInputCheckbox;
use MRBS\Form\FieldInputEmail;
use MRBS\Form\FieldInputFile;
use MRBS\Form\FieldInputNumber;
use MRBS\Form\FieldInputPassword;
use MRBS\Form\FieldInputSubmit;
use MRBS\Form\FieldInputText;
use MRBS\Form\FieldInputUrl;
use MRBS\Form\FieldSelect;
use MRBS\Form\Form;

require 'defaultincludes.inc';


// Sends a one-off test email using the SMTP/mail settings currently in the
// form (which may not have been saved yet), without disturbing the real
// $mail_settings/$smtp_settings globals used by the rest of the request.
// Calls MailQueue::sendMail() directly (not add()/flush()) so the result is
// known immediately and nothing is left in the deferred queue to be
// re-sent by the real end-of-request shutdown flush.
function send_site_settings_test_email(
  string $backend,
  string $from,
  string $to,
  string $smtp_host,
  int $smtp_port,
  string $smtp_secure,
  bool $smtp_auth_enabled,
  string $smtp_username,
  string $smtp_password
) : array
{
  global $mail_settings, $smtp_settings;

  $orig_mail_settings = $mail_settings;
  $orig_smtp_settings = $smtp_settings;

  $mail_settings['admin_backend'] = $backend;
  if ($from !== '')
  {
    $mail_settings['from'] = $from;
  }

  $smtp_settings['host']     = $smtp_host;
  $smtp_settings['port']     = $smtp_port;
  $smtp_settings['secure']   = $smtp_secure;
  $smtp_settings['auth']     = $smtp_auth_enabled;
  $smtp_settings['username'] = $smtp_username;
  if ($smtp_password !== '')
  {
    $smtp_settings['password'] = $smtp_password;
  }

  $addresses = array(
    'from' => $mail_settings['from'],
    'to'   => $to,
  );

  $subject = get_vocab('site_settings_test_smtp_subject');
  $body = '<p>' . get_vocab('site_settings_test_smtp_body', date('Y-m-d H:i:s')) . "</p>\n";

  $error = null;

  try
  {
    $success = MailQueue::sendMail($addresses, $subject, strip_tags($body), $body, null, Language::MAIL_CHARSET);
    if (!$success)
    {
      $error = MailQueue::getLastError();
    }
  }
  catch (\Throwable $e)
  {
    $success = false;
    $error = $e->getMessage();
  }

  $mail_settings = $orig_mail_settings;
  $smtp_settings  = $orig_smtp_settings;

  return array('success' => $success, 'error' => $error, 'to' => $to);
}

// Admin only - falls through to $max_level in get_page_level() since this
// page has no special case in mrbs_auth.inc.
checkAuthorised(this_page());

global $mrbs_company, $mrbs_company_url, $mail_settings, $smtp_settings, $server;

$errors = array();
$saved = (get_form_var('msg', 'string') === 'saved');

const LOGO_MAX_BYTES = 2 * 1024 * 1024;  // 2 MB
const LOGO_MIME_EXT = array(
  'image/png'  => 'png',
  'image/jpeg' => 'jpg',
  'image/gif'  => 'gif',
  'image/webp' => 'webp',
);
const LOGO_DIR = 'uploaded';


// ---- Handle form submission ----
if (($server['REQUEST_METHOD'] ?? '') === 'POST')
{
  Form::checkToken();

  $company         = trim(get_form_var('mrbs_company', 'string', '', INPUT_POST));
  $company_url     = trim(get_form_var('mrbs_company_url', 'string', '', INPUT_POST));
  $remove_logo     = get_form_var('remove_logo', 'bool', false, INPUT_POST);

  $mail_backend    = get_form_var('mail_admin_backend', 'string', 'mail', INPUT_POST);
  $mail_from       = trim(get_form_var('mail_from', 'string', '', INPUT_POST));
  $mail_recipients = trim(get_form_var('mail_recipients', 'string', '', INPUT_POST));

  $smtp_host       = trim(get_form_var('smtp_host', 'string', '', INPUT_POST));
  $smtp_port       = get_form_var('smtp_port', 'int', 587, INPUT_POST);
  $smtp_secure     = get_form_var('smtp_secure', 'string', '', INPUT_POST);
  $smtp_auth       = get_form_var('smtp_auth', 'bool', false, INPUT_POST);
  $smtp_username   = trim(get_form_var('smtp_username', 'string', '', INPUT_POST));
  $smtp_password   = get_form_var('smtp_password', 'string', '', INPUT_POST);

  $enable_registration = get_form_var('enable_registration', 'bool', false, INPUT_POST);

  $test_smtp_requested = (get_form_var('test_smtp_button', 'string', null, INPUT_POST) !== null);

  if (!in_array($mail_backend, array('mail', 'sendmail', 'smtp'), true))
  {
    $mail_backend = 'mail';
  }

  if (!in_array($smtp_secure, array('', 'tls', 'ssl'), true))
  {
    $smtp_secure = '';
  }

  if ($test_smtp_requested)
  {
    // The "Send test email" button only tests the mail settings - it doesn't
    // touch company/logo/registration fields and never saves anything.
    $test_to = ($mail_recipients !== '') ? $mail_recipients : $mail_from;

    if ($test_to === '')
    {
      $errors[] = get_vocab('site_settings_err_test_no_recipient');
    }
    else
    {
      $test_result = send_site_settings_test_email(
        $mail_backend,
        $mail_from,
        $test_to,
        $smtp_host,
        $smtp_port,
        $smtp_secure,
        $smtp_auth,
        $smtp_username,
        $smtp_password
      );
    }
    // Fall through to render the page below, with the form's submitted
    // values and the test result both still available for display.
  }
  else
  {
    if ($company === '')
    {
      $errors[] = get_vocab('site_settings_err_company_required');
    }

    if (($mail_from !== '') && !filter_var($mail_from, FILTER_VALIDATE_EMAIL))
    {
      $errors[] = get_vocab('site_settings_err_invalid_from');
    }

    // Logo: current path, possibly replaced or removed below
    $logo_path = get_site_setting('mrbs_company_logo', '');

    if ($remove_logo)
    {
      if ($logo_path !== '')
      {
        @unlink(MRBS_ROOT . '/' . $logo_path);
      }
      $logo_path = '';
    }
    elseif (isset($_FILES['logo']) && ($_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE))
    {
      if (($_FILES['logo']['error'] !== UPLOAD_ERR_OK) || !is_uploaded_file($_FILES['logo']['tmp_name']))
      {
        $errors[] = get_vocab('site_settings_err_logo_upload');
      }
      elseif ($_FILES['logo']['size'] > LOGO_MAX_BYTES)
      {
        $errors[] = get_vocab('site_settings_err_logo_too_large');
      }
      else
      {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['logo']['tmp_name']);
        finfo_close($finfo);

        if (!isset(LOGO_MIME_EXT[$mime]))
        {
          $errors[] = get_vocab('site_settings_err_logo_type');
        }
        else
        {
          $upload_dir = MRBS_ROOT . '/' . LOGO_DIR;
          if (!is_dir($upload_dir))
          {
            mkdir($upload_dir, 0755, true);
          }
          // Remove any previous logo, regardless of its extension
          foreach (LOGO_MIME_EXT as $ext)
          {
            @unlink("$upload_dir/company_logo.$ext");
          }
          $ext  = LOGO_MIME_EXT[$mime];
          $dest = "$upload_dir/company_logo.$ext";
          if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest))
          {
            $logo_path = LOGO_DIR . "/company_logo.$ext";
          }
          else
          {
            $errors[] = get_vocab('site_settings_err_logo_upload');
          }
        }
      }
    }

    if (empty($errors))
    {
      set_site_setting('mrbs_company', $company);
      set_site_setting('mrbs_company_url', $company_url);
      set_site_setting('mrbs_company_logo', $logo_path);

      set_site_setting('mail_admin_backend', $mail_backend);
      set_site_setting('mail_from', $mail_from);
      set_site_setting('mail_recipients', $mail_recipients);

      set_site_setting('smtp_host', $smtp_host);
      set_site_setting('smtp_port', (string) $smtp_port);
      set_site_setting('smtp_secure', $smtp_secure);
      set_site_setting('smtp_auth', $smtp_auth ? '1' : '0');
      set_site_setting('smtp_username', $smtp_username);
      if ($smtp_password !== '')
      {
        // A blank password field means "leave the stored password unchanged"
        set_site_setting('smtp_password', $smtp_password);
      }

      set_site_setting('enable_registration', $enable_registration ? '1' : '0');

      location_header(multisite('edit_site_settings.php?msg=saved'));
    }
  }
}


// ---- Load current values for display ----
$current_company      = get_site_setting('mrbs_company', $mrbs_company);
$current_company_url  = get_site_setting('mrbs_company_url', $mrbs_company_url ?? '');
$current_logo          = get_site_setting('mrbs_company_logo', '');

$current_backend       = get_site_setting('mail_admin_backend', $mail_settings['admin_backend']);
$current_from           = get_site_setting('mail_from', $mail_settings['from']);
$current_recipients    = get_site_setting('mail_recipients', $mail_settings['recipients']);

$current_smtp_host     = get_site_setting('smtp_host', $smtp_settings['host']);
$current_smtp_port     = get_site_setting('smtp_port', (string) $smtp_settings['port']);
$current_smtp_secure   = get_site_setting('smtp_secure', $smtp_settings['secure']);
$current_smtp_auth     = get_site_setting('smtp_auth', $smtp_settings['auth'] ? '1' : '0');
$current_smtp_username = get_site_setting('smtp_username', $smtp_settings['username']);

$current_enable_registration = get_site_setting('enable_registration', '0');

// After a "Send test email" click, re-populate the form with what was just
// submitted/tested rather than the unchanged saved values, so it doesn't
// look like typing was lost. (smtp_password is deliberately excluded - blank
// always means "leave unchanged", same as on a normal save.)
if ($test_smtp_requested ?? false)
{
  $current_company       = $company;
  $current_company_url   = $company_url;
  $current_backend       = $mail_backend;
  $current_from          = $mail_from;
  $current_recipients    = $mail_recipients;
  $current_smtp_host     = $smtp_host;
  $current_smtp_port     = (string) $smtp_port;
  $current_smtp_secure   = $smtp_secure;
  $current_smtp_auth     = $smtp_auth ? '1' : '0';
  $current_smtp_username = $smtp_username;
}


$context = array(
  'view'     => $view,
  'view_all' => $view_all,
  'year'     => $year,
  'month'    => $month,
  'day'      => $day,
);

print_header($context);

echo "<div class=\"mrbs-settings-page\">\n";
echo "<h1>" . get_vocab('site_settings') . "</h1>\n";

if ($saved)
{
  echo '<p class="mrbs-settings-flash mrbs-settings-flash--success">'
     . escape_html(get_vocab('site_settings_saved')) . "</p>\n";
}

if (isset($test_result))
{
  if ($test_result['success'])
  {
    echo '<p class="mrbs-settings-flash mrbs-settings-flash--success">'
       . escape_html(get_vocab('site_settings_test_smtp_success', $test_result['to'])) . "</p>\n";
  }
  else
  {
    echo '<p class="mrbs-settings-flash mrbs-settings-flash--error">'
       . escape_html(get_vocab('site_settings_test_smtp_failed', $test_result['error'] ?? get_vocab('site_settings_unknown_error')))
       . "</p>\n";
  }
}

if (!empty($errors))
{
  echo "<ul class=\"error\">\n";
  foreach ($errors as $error)
  {
    echo "<li>" . escape_html($error) . "</li>\n";
  }
  echo "</ul>\n";
}

$form = new Form(Form::METHOD_POST);
$form->setAttributes(array(
  'id'      => 'form_site_settings',
  'class'   => 'standard mrbs-settings-form',
  'action'  => multisite(this_page()),
  'enctype' => 'multipart/form-data',
));

// ---- Branding ----
$fieldset = new ElementFieldset();
$fieldset->addLegend(get_vocab('site_settings_branding'));

$field = new FieldInputText();
$field->setLabel(get_vocab('site_settings_company_name'))
      ->setControlAttributes(array(
          'id'       => 'mrbs_company',
          'name'     => 'mrbs_company',
          'value'    => $current_company,
          'required' => true,
        ));
$fieldset->addElement($field);

$field = new FieldInputUrl();
$field->setLabel(get_vocab('site_settings_company_url'))
      ->setControlAttributes(array(
          'id'    => 'mrbs_company_url',
          'name'  => 'mrbs_company_url',
          'value' => $current_company_url,
        ));
$fieldset->addElement($field);

if ($current_logo !== '')
{
  $preview = new ElementDiv();
  $preview->setAttribute('class', 'mrbs-logo-preview');
  $img = new ElementImg();
  $img->setAttributes(array(
    'src' => escape_html(multisite($current_logo)),
    'alt' => get_vocab('site_settings_company_logo'),
  ));
  $preview->addElement($img);
  $fieldset->addElement($preview);
}

$field = new FieldInputFile();
$field->setLabel(get_vocab('site_settings_company_logo'))
      ->setControlAttributes(array(
          'id'     => 'logo',
          'name'   => 'logo',
          'accept' => implode(',', array_keys(LOGO_MIME_EXT)),
        ));
$fieldset->addElement($field);

if ($current_logo !== '')
{
  $field = new FieldInputCheckbox();
  $field->setLabel(get_vocab('site_settings_remove_logo'))
        ->setControlAttributes(array(
            'id'    => 'remove_logo',
            'name'  => 'remove_logo',
            'value' => '1',
          ));
  $fieldset->addElement($field);
}

$form->addElement($fieldset);

// ---- Outgoing mail ----
$fieldset = new ElementFieldset();
$fieldset->addLegend(get_vocab('site_settings_email'));

$field = new FieldSelect();
$field->setLabel(get_vocab('site_settings_mail_method'))
      ->setControlAttributes(array('id' => 'mail_admin_backend', 'name' => 'mail_admin_backend'))
      ->addSelectOptions(
          array(
            'mail'     => get_vocab('site_settings_mail_method_php'),
            'sendmail' => get_vocab('site_settings_mail_method_sendmail'),
            'smtp'     => get_vocab('site_settings_mail_method_smtp'),
          ),
          $current_backend,
          true
        );
$fieldset->addElement($field);

$field = new FieldInputEmail();
$field->setLabel(get_vocab('site_settings_mail_from'))
      ->setControlAttributes(array(
          'id'    => 'mail_from',
          'name'  => 'mail_from',
          'value' => $current_from,
        ));
$fieldset->addElement($field);

$field = new FieldInputText();
$field->setLabel(get_vocab('site_settings_mail_recipients'))
      ->setControlAttributes(array(
          'id'    => 'mail_recipients',
          'name'  => 'mail_recipients',
          'value' => $current_recipients,
        ));
$fieldset->addElement($field);

$form->addElement($fieldset);

// ---- SMTP server ----
$fieldset = new ElementFieldset();
$fieldset->setAttribute('id', 'smtp_details');
$fieldset->addLegend(get_vocab('site_settings_smtp'));

$field = new FieldInputText();
$field->setLabel(get_vocab('site_settings_smtp_host'))
      ->setControlAttributes(array(
          'id'          => 'smtp_host',
          'name'        => 'smtp_host',
          'value'       => $current_smtp_host,
          'placeholder' => 'smtp.example.com',
        ));
$fieldset->addElement($field);

$field = new FieldInputNumber();
$field->setLabel(get_vocab('site_settings_smtp_port'))
      ->setControlAttributes(array(
          'id'    => 'smtp_port',
          'name'  => 'smtp_port',
          'value' => $current_smtp_port,
          'min'   => 1,
          'max'   => 65535,
        ));
$fieldset->addElement($field);

$field = new FieldSelect();
$field->setLabel(get_vocab('site_settings_smtp_secure'))
      ->setControlAttributes(array('id' => 'smtp_secure', 'name' => 'smtp_secure'))
      ->addSelectOptions(
          array(
            ''    => get_vocab('site_settings_smtp_secure_none'),
            'tls' => 'TLS',
            'ssl' => 'SSL',
          ),
          $current_smtp_secure,
          true
        );
$fieldset->addElement($field);

$field = new FieldInputCheckbox();
$field->setLabel(get_vocab('site_settings_smtp_auth'))
      ->setControlAttributes(array('id' => 'smtp_auth', 'name' => 'smtp_auth', 'value' => '1'));
if ($current_smtp_auth === '1')
{
  $field->setChecked(true);
}
$fieldset->addElement($field);

$field = new FieldInputText();
$field->setLabel(get_vocab('site_settings_smtp_username'))
      ->setControlAttributes(array(
          'id'           => 'smtp_username',
          'name'         => 'smtp_username',
          'value'        => $current_smtp_username,
          'autocomplete' => 'off',
        ));
$fieldset->addElement($field);

$field = new FieldInputPassword();
$field->setLabel(get_vocab('site_settings_smtp_password'))
      ->setControlAttributes(array(
          'id'           => 'smtp_password',
          'name'         => 'smtp_password',
          'placeholder'  => get_vocab('site_settings_smtp_password_placeholder'),
          'autocomplete' => 'new-password',
        ));
$fieldset->addElement($field);

// "Send test email" uses whatever is currently in the form above (even if
// not yet saved), so an admin can try a host/port/credential change before
// committing to it. formnovalidate so it isn't blocked by unrelated required
// fields (eg Company name) elsewhere in the same form.
$field = new FieldInputSubmit();
$field->setControlAttributes(array(
    'name'           => 'test_smtp_button',
    'value'          => get_vocab('site_settings_test_smtp'),
    'formnovalidate' => true,
  ));
$field->removeLabelAttribute('for');
$fieldset->addElement($field);

$form->addElement($fieldset);

// ---- Recent mail activity ----
$mail_activity_log = get_mail_activity_log();
if (!empty($mail_activity_log))
{
  $fieldset = new ElementFieldset();
  $fieldset->addLegend(get_vocab('site_settings_mail_activity'));

  $table = new Element('table');
  $table->setAttribute('class', 'mrbs-mail-activity');

  $thead = new Element('tr');
  foreach (array('site_settings_mail_activity_time', 'site_settings_mail_activity_to',
                 'site_settings_mail_activity_subject', 'site_settings_mail_activity_result') as $tag)
  {
    $th = new Element('th');
    $th->setText(get_vocab($tag));
    $thead->addElement($th);
  }
  $table->addElement($thead);

  foreach ($mail_activity_log as $entry)
  {
    $tr = new Element('tr');
    $tr->setAttribute('class', !empty($entry['success']) ? 'mrbs-mail-activity-ok' : 'mrbs-mail-activity-fail');

    $td = new Element('td');
    $td->setText(date('Y-m-d H:i:s', $entry['time'] ?? 0));
    $tr->addElement($td);

    $td = new Element('td');
    $td->setText($entry['to'] ?? '');
    $tr->addElement($td);

    $td = new Element('td');
    $td->setText($entry['subject'] ?? '');
    $tr->addElement($td);

    $td = new Element('td');
    $result_text = !empty($entry['success'])
      ? get_vocab('site_settings_mail_activity_ok')
      : get_vocab('site_settings_mail_activity_failed_with_error', $entry['error'] ?? '');
    $td->setText($result_text);
    $tr->addElement($td);

    $table->addElement($tr);
  }

  $fieldset->addElement($table);
  $form->addElement($fieldset);
}

// ---- User registration ----
$fieldset = new ElementFieldset();
$fieldset->addLegend(get_vocab('site_settings_registration'));

$field = new FieldInputCheckbox();
$field->setLabel(get_vocab('site_settings_enable_registration'))
      ->setControlAttributes(array('id' => 'enable_registration', 'name' => 'enable_registration', 'value' => '1'));
if ($current_enable_registration === '1')
{
  $field->setChecked(true);
}
$fieldset->addElement($field);

$p = new ElementP();
$p->setText(get_vocab('site_settings_registration_note'));
$fieldset->addElement($p);

$form->addElement($fieldset);

// ---- Submit ----
$fieldset = new ElementFieldset();
$field = new FieldInputSubmit();
$field->setControlAttributes(array('value' => get_vocab('save')));
$field->removeLabelAttribute('for');
$fieldset->addElement($field);
$form->addElement($fieldset);

$form->render();

echo "</div>\n"; // mrbs-settings-page

print_footer();
