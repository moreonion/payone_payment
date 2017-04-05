<?php

namespace Drupal\payone_payment;

class PayoneConfigurationFormTest extends \DrupalUnitTestCase {

  public function testValidate() {
    // Test a valid configuration.
    $controller = new PaypalECController();
    $method = entity_create('payment_method', [
      'controller' => $controller,
      'controller_data' => $controller->controller_data_defaults,
    ]);
    $form_obj = new PayoneConfigurationForm();
    $form_state = [];
    $element = $form_obj->form([], $form_state, $method);
    $element['#parents'] = [];
    $v = $controller->controller_data_defaults;
    $implode = function($x) {
      return implode(', ', $x);
    };
    $v['field_map'] = array_map($implode, $v['field_map']);
    $v['credentials'] = [
      'mid' => '123456',
      'portalid' => '1234567',
      'aid' => '12',
    ];
    $form_state['values'] = $v;
    $form_obj->validate($element, $form_state, $method);
  }

}
