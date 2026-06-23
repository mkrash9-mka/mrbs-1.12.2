<?php
declare(strict_types=1);
namespace MRBS;

use MRBS\Form\Element;
use MRBS\Form\ElementFieldset;
use MRBS\Form\ElementP;
use MRBS\Form\FieldDiv;
use MRBS\Form\FieldInputEmail;
use MRBS\Form\FieldInputPassword;
use MRBS\Form\FieldInputSubmit;
use MRBS\Form\FieldInputText;
use MRBS\Form\Form;

require "defaultincludes.inc";


function generate_register_form(?string $error=null) : void
{
  global $pwd_policy;

  $form = new Form(Form::METHOD_POST);
  $form->setAttributes(array(
      'class'  => 'standard',
      'id'     => 'register',
      'action' => multisite('register_handler.php')
    ));

  $fieldset = new ElementFieldset();
  $fieldset->addLegend(get_vocab('register_new_account'));

  if (isset($error))
  {
    $field = new FieldDiv();
    $p = new ElementP();
    $vocab_tag = ($error === 'name_not_unique') ? 'name_not_unique' : $error;
    $p->setText(get_vocab($vocab_tag))
      ->setAttribute('class', 'error');
    $field->addControlElement($p);
    $fieldset->addElement($field);
  }

  $field = new FieldInputText();
  $field->setLabel(get_vocab('users.name'))
        ->setControlAttributes(array(
            'id'           => 'name',
            'name'         => 'name',
            'required'     => true,
            'autofocus'    => true,
            'autocomplete' => 'username',
            'maxlength'    => maxlength('users.name'),
          ));
  $fieldset->addElement($field);

  $field = new FieldInputText();
  $field->setLabel(get_vocab('users.display_name'))
        ->setControlAttributes(array(
            'id'        => 'display_name',
            'name'      => 'display_name',
            'maxlength' => maxlength('users.display_name'),
          ));
  $fieldset->addElement($field);

  $field = new FieldInputEmail();
  $field->setLabel(get_vocab('users.email'))
        ->setControlAttributes(array(
            'id'       => 'email',
            'name'     => 'email',
            'required' => true,
          ));
  $fieldset->addElement($field);

  $p = new ElementP();
  $text = get_vocab('enter_new_password');
  if (isset($pwd_policy))
  {
    $text .= ' ' . get_vocab('pwd_must_contain');
  }
  $field = new FieldDiv();
  $p->setText($text);
  $field->addControlElement($p);

  if (isset($pwd_policy))
  {
    $ul = new Element('ul');
    $ul->setAttribute('id', 'pwd_policy');
    if ($error === 'pwd_invalid')
    {
      $ul->setAttribute('class', 'error');
    }
    foreach ($pwd_policy as $rule => $value)
    {
      if ($value != 0)
      {
        $li = new Element('li');
        $li->setText(get_vocab('policy_' . $rule, $value));
        $ul->addElement($li);
      }
    }
    $field->addControlElement($ul);
  }
  $fieldset->addElement($field);

  for ($i = 0; $i < 2; $i++)
  {
    $field = new FieldInputPassword();
    $field->setLabel(get_vocab('users.password'))
          ->setControlAttributes(array(
              'id'           => "password$i",
              'name'         => "password$i",
              'required'     => true,
              'autocomplete' => 'new-password',
            ));
    $fieldset->addElement($field);
  }

  $form->addElement($fieldset);

  $fieldset = new ElementFieldset();
  $field = new FieldInputSubmit();
  $field->setControlAttributes(array('value' => get_vocab('register_new_account')));
  $fieldset->addElement($field);
  $form->addElement($fieldset);

  $form->render();
}


function generate_register_pending() : void
{
  echo "<h2>" . get_vocab('register_new_account') . "</h2>\n";
  echo "<p>" . get_vocab('registration_pending') . "</p>\n";
}


function generate_register_unavailable() : void
{
  echo "<h2>" . get_vocab('register_new_account') . "</h2>\n";
  echo "<p>" . get_vocab('registration_unavailable') . "</p>\n";
}


$context = array(
    'view'      => $view,
    'view_all'  => $view_all,
    'year'      => $year,
    'month'     => $month,
    'day'       => $day,
    'area'      => $area ?? null,
    'room'      => $room ?? null,
  );

print_header($context);

if (get_site_setting('enable_registration', '0') !== '1')
{
  generate_register_unavailable();
}
else
{
  $result = get_form_var('result', 'string');
  $error  = get_form_var('error', 'string');

  if ($result === 'pending')
  {
    generate_register_pending();
  }
  else
  {
    generate_register_form($error);
  }
}

print_footer();
