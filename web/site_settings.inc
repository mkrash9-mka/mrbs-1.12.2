<?php
declare(strict_types=1);
namespace MRBS;

// Admin-editable site settings (company branding, outgoing mail server) stored
// in the mrbs_variables table under keys prefixed 'site_setting_'.  These
// override the corresponding $mrbs_company*/$mail_settings/$smtp_settings
// values that would otherwise only be settable by editing config.inc.php.

const SITE_SETTING_PREFIX = 'site_setting_';


function get_site_setting(string $name, ?string $default = null) : ?string
{
  try
  {
    $sql = "SELECT variable_content FROM " . _tbl('variables') . " WHERE variable_name=?";
    $result = db()->query_scalar_non_bool($sql, array(SITE_SETTING_PREFIX . $name));
  }
  catch (\Throwable $e)
  {
    return $default;
  }

  return (($result === false) || ($result === null)) ? $default : (string) $result;
}


// Setting $value to null or '' deletes the override, reverting to the config.inc.php default.
function set_site_setting(string $name, ?string $value) : void
{
  $key = SITE_SETTING_PREFIX . $name;

  if (($value === null) || ($value === ''))
  {
    db()->command("DELETE FROM " . _tbl('variables') . " WHERE variable_name=?", array($key));
    return;
  }

  $exists = db()->query_scalar_non_bool(
    "SELECT 1 FROM " . _tbl('variables') . " WHERE variable_name=?",
    array($key)
  );

  if (($exists === false) || ($exists === null))
  {
    $sql = "INSERT INTO " . _tbl('variables') . " (variable_name, variable_content) VALUES (?, ?)";
    db()->command($sql, array($key, $value));
  }
  else
  {
    $sql = "UPDATE " . _tbl('variables') . " SET variable_content=? WHERE variable_name=?";
    db()->command($sql, array($value, $key));
  }
}


// Reads all site_setting_* rows in one query and overrides the relevant globals.
// Safe to call even if the table is empty or (during very early bootstrap) inaccessible.
function apply_site_settings() : void
{
  global $mrbs_company, $mrbs_company_logo, $mrbs_company_url;
  global $mail_settings, $smtp_settings;

  try
  {
    $result = db()->query("SELECT variable_name, variable_content FROM " . _tbl('variables'));
  }
  catch (\Throwable $e)
  {
    return;
  }

  $settings = array();
  $prefix_len = strlen(SITE_SETTING_PREFIX);

  while (false !== ($row = $result->next_row_keyed()))
  {
    if (strpos($row['variable_name'], SITE_SETTING_PREFIX) === 0)
    {
      $settings[substr($row['variable_name'], $prefix_len)] = $row['variable_content'];
    }
  }

  if (empty($settings))
  {
    return;
  }

  if (!empty($settings['mrbs_company']))
  {
    $mrbs_company = $settings['mrbs_company'];
  }
  if (array_key_exists('mrbs_company_logo', $settings))
  {
    $mrbs_company_logo = $settings['mrbs_company_logo'];
  }
  if (array_key_exists('mrbs_company_url', $settings))
  {
    $mrbs_company_url = $settings['mrbs_company_url'];
  }

  if (isset($settings['mail_admin_backend']))
  {
    $mail_settings['admin_backend'] = $settings['mail_admin_backend'];
  }
  if (isset($settings['mail_from']))
  {
    $mail_settings['from'] = $settings['mail_from'];
  }
  if (isset($settings['mail_recipients']))
  {
    $mail_settings['recipients'] = $settings['mail_recipients'];
  }

  if (isset($settings['smtp_host']))
  {
    $smtp_settings['host'] = $settings['smtp_host'];
  }
  if (isset($settings['smtp_port']))
  {
    $smtp_settings['port'] = (int) $settings['smtp_port'];
  }
  if (isset($settings['smtp_secure']))
  {
    $smtp_settings['secure'] = $settings['smtp_secure'];
  }
  if (isset($settings['smtp_auth']))
  {
    $smtp_settings['auth'] = ($settings['smtp_auth'] === '1');
  }
  if (isset($settings['smtp_username']))
  {
    $smtp_settings['username'] = $settings['smtp_username'];
  }
  if (!empty($settings['smtp_password']))
  {
    $smtp_settings['password'] = $settings['smtp_password'];
  }
}
