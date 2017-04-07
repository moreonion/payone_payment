<?php

namespace Drupal\payone_payment;

/**
 * Defines form fields and validations for personal data fields.
 *
 * These fields are common to all payone payments.
 */
class PersonalDataFields {

  /**
   * Generate a personal data fieldset.
   */
  public function element(array $element, array &$form_state, \Payment $payment) {
    $element += [
      '#type' => 'fieldset',
      '#title' => t('Personal data'),
      '#weight' => 100,
      '#tree' => TRUE,
    ] + $this->mappedFields($payment);

    // Only display the fieldset if there is fields in it.
    $all_done = TRUE;
    foreach (element_children($element, FALSE) as $c) {
      $e = $element[$c];
      if (!isset($e['#access']) || $e['#access']) {
        $all_done = FALSE;
        break;
      }
    }
    $element['#access'] = !$all_done;
    return $element;
  }

  /**
   * Validate the form values.
   */
  public function validate(array $element, array &$form_state) {
    $p = &drupal_array_get_nested_value($form_state['values'], $element['#parents']);

    if (empty($p['country'])) {
      form_error($element['country'], t('Please select your country.'));
    }

    if (empty($p['firstname']) && empty($p['company'])) {
      form_error($element, t('Either a first name or a company name must be given.'));
    }
    // Only pass non-empty values to the API.
    foreach ($p as $k => $v) {
      if (!$v) {
        unset($p[$k]);
      }
    }
  }

  /**
   * Get fields and set values according to a specific payment.
   *
   * @param \Payment $payment
   *   The payment.
   *
   * @return array
   *   Array of renderable form-API fields.
   */
  protected function mappedFields(\Payment $payment) {
    $fields = static::extraDataFields();
    $field_map = $payment->method->controller_data['field_map'];
    foreach ($fields as $name => &$field) {
      $map = isset($field_map[$name]) ? $field_map[$name] : array();
      $has_value = FALSE;
      foreach ($map as $key) {
        if ($value = $payment->contextObj->value($key)) {
          if (!isset($field['#options']) || isset($field['#options'][$value])) {
            $field['#default_value'] = $value;
            $has_value = TRUE;
            break;
          }
        }
      }
      if (empty($field['#required']) || $has_value) {
        $field['#access'] = FALSE;
      }
      // We can't have required fields in optional parts of the form.
      // This payment method might not be the only way to pay. ;)
      $field['#required'] = FALSE;
    }

    // Show firstname and company fields if both are empty.
    if (empty($fields['firstname']['#default_value']) && empty($fields['company']['#default_value'])) {
      unset($field['firstname']['#access']);
      unset($field['company']['#access']);
    }

    return $fields;
  }

  /**
   * Personal data fields for payone payments.
   *
   * @return array
   *   Array of form-API fields.
   */
  public static function extraDataFields() {
    $fields = array();
    $f = array(
      'salutation' => t('Salutation'),
      'title' => t('Title'),
      'firstname' => t('First name'),
      'lastname' => t('Last name'),
      'company' => t('Company'),
      'street' => t('Street number and name'),
      'addressaddition' => t('Address line 2'),
      'zip' => t('Postal code'),
      'city' => t('City'),
      'state' => t('State'),
      'country' => t('Country'),
      'email' => t('Email'),
      'telephonenumber' => t('Phone number'),
      'birthday' => t('Day of birth'),
      'language' => t('Language'),
      'vatid' => t('VAT identification'),
      'gender' => t('Gender'),
    );
    foreach ($f as $name => $title) {
      $fields[$name] = array(
        '#type' => 'textfield',
        '#title' => $title,
      );
    }

    $fields['lastname']['#required'] = TRUE;
    $fields['country']['#type'] = 'select';
    $fields['country']['#options'] = country_get_list();
    $fields['country']['#required'] = TRUE;
    $fields['gender']['#type'] = 'radios';
    $fields['gender']['#options'] = ['f' => t('female'), 'm' => t('male')];

    return $fields;
  }

}
