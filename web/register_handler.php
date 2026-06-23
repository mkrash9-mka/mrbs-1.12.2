<?php
declare(strict_types=1);
namespace MRBS;

use MRBS\Form\Form;

require "defaultincludes.inc";

// If self-registration hasn't been enabled by the admin, get out of here.
if (get_site_setting('enable_registration', '0') !== '1')
{
  location_header(multisite('index.php'));
}

// Check the CSRF token.
Form::checkToken();

$name         = trim(mb_strtolower(get_form_var('name', 'string', '', INPUT_POST)));
$display_name = trim(get_form_var('display_name', 'string', '', INPUT_POST));
$email        = trim(get_form_var('email', 'string', '', INPUT_POST));
$password0    = get_form_var('password0', 'string', '', INPUT_POST);
$password1    = get_form_var('password1', 'string', '', INPUT_POST);

if (null !== ($maxlength = maxlength('users.name')))
{
  $name = mb_substr($name, 0, $maxlength);
}
if (null !== ($maxlength = maxlength('users.display_name')))
{
  $display_name = mb_substr($display_name, 0, $maxlength);
}

if ($display_name === '')
{
  $display_name = $name;
}

$error = null;

if ($name === '')
{
  $error = 'name_empty';
}
elseif (($email === '') || !validate_email($email))
{
  $error = 'invalid_email';
}
elseif ($password0 !== $password1)
{
  $error = 'pwd_not_match';
}
elseif (!auth()->validatePassword($password0))
{
  $error = 'pwd_invalid';
}
else
{
  // Check the username isn't already taken
  $sql = "SELECT COUNT(*) FROM " . _tbl('users') . " WHERE name=?";
  if (db()->query1($sql, array($name)) > 0)
  {
    $error = 'name_not_unique';
  }
}

if (isset($error))
{
  location_header(multisite("register.php?error=$error"));
}

// All good - create the account, pending admin approval.
$sql = "INSERT INTO " . _tbl('users') . " (name, display_name, email, password_hash, level, approved)
        VALUES (?, ?, ?, ?, 1, 0)";
db()->command($sql, array(
  $name,
  $display_name,
  $email,
  password_hash($password0, PASSWORD_DEFAULT),
));

notify_admin_of_registration($name, $display_name, $email);

location_header(multisite('register.php?result=pending'));


// Queues a notification email to the configured admin recipient(s) about the
// new pending registration, with a direct link to review it on edit_users.php.
function notify_admin_of_registration(string $name, string $display_name, string $email) : void
{
  global $mail_settings;

  $recipients = get_site_setting('mail_recipients', $mail_settings['recipients']);

  if (empty($recipients))
  {
    return;
  }

  $addresses = array(
    'from' => $mail_settings['from'],
    'to'   => $recipients,
  );

  $subject = get_vocab('mail_subject_user_pending');

  $href = url_base() . multisite('edit_users.php');
  $body = '<p>' . get_vocab('mail_body_user_pending', escape_html($display_name), escape_html($name), escape_html($email)) . "</p>\n";
  $body .= '<p><a href="' . escape_html($href) . '">' . escape_html($href) . "</a></p>\n";

  MailQueue::add(
    $addresses,
    $subject,
    strip_tags($body),
    $body,
    null,
    Language::MAIL_CHARSET
  );
}
