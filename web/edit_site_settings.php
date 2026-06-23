<?php
declare(strict_types=1);
namespace MRBS;

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

  if ($company === '')
  {
    $errors[] = get_vocab('site_settings_err_company_required');
  }

  if (!in_array($mail_backend, array('mail', 'sendmail', 'smtp'), true))
  {
    $mail_backend = 'mail';
  }

  if (($mail_from !== '') && !filter_var($mail_from, FILTER_VALIDATE_EMAIL))
  {
    $errors[] = get_vocab('site_settings_err_invalid_from');
  }

  if (!in_array($smtp_secure, array('', 'tls', 'ssl'), true))
  {
    $smtp_secure = '';
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

$form->addElement($fieldset);

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
