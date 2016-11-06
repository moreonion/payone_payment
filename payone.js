(function ($) {

  'use strict';

  Drupal.behaviors.payone_payment = {
    attach: function (context, settings) {
      if (!Drupal.payment_handler) {
        Drupal.payment_handler = {};
      }
      var self = this;
      for (var pmid in settings.payone_payment) {
        Drupal.payment_handler[pmid] = function (pmid, $method, submitter) {
          self.validateHandler(pmid, $method, submitter, settings.payone_payment[pmid]);
        };
      }
    },

    validateHandler: function (pmid, $method, submitter, params) {
      var form_id = $method.closest('form').attr('id');

      $('.mo-dialog-wrapper').addClass('visible');
      if (typeof Drupal.clientsideValidation !== 'undefined') {
        $('#clientsidevalidation-' + form_id + '-errors ul').empty();
      }

      var getField = function (name) {
        if (name instanceof Array) { name = name.join(']['); }
        return $method.find('[name$="[' + name + ']"]');
      };

      params['cardholder'] = getField('holder').val();
      params['cardpan'] = getField('credit_card_number').val();
      params['cardtype'] = getField('issuer').val();
      params['cardexpiremonth'] = getField(['expiry_date', 'month']).val();
      params['cardexpireyear'] = getField(['expiry_date', 'year']).val();
      params['cardcvc2'] = getField('secure_code').val();

      // We have to register a global callback function.
      var self = this;
      window['payone_payment_callback'] = function (response) {
        if (response.get('status') === 'VALID') {
          getField('credit_card_number').val('');
          getField('secure_code').val('');
          $method.find('.payone-pseudocardpan').val(response.get('pseudocardpan'));
          submitter.ready();
        }
        else {
          self.errorHandler(response.get('customermessage'), form_id);
          submitter.error();
        }
      };

      var request = new PayoneRequest(params, {
        return_type: 'object',
        callback_function_name: 'payone_payment_callback'
      });
      request.checkAndStore();
    },

    errorHandler: function (error, form_id) {
      var settings;
      var wrapper;
      var child;
      if (typeof Drupal.clientsideValidation !== 'undefined') {
        settings = Drupal.settings.clientsideValidation['forms'][form_id];
        wrapper = document.createElement(settings.general.wrapper);
        child = document.createElement(settings.general.errorElement);
        child.className = settings.general.errorClass;
        child.innerHTML = error;
        wrapper.appendChild(child);

        $('#clientsidevalidation-' + form_id + '-errors ul')
          .append(wrapper).show()
          .parent().show();
      }
      else {
        if ($('#messages').length === 0) {
          $('<div id="messages"><div class="section clearfix">' +
            '</div></div>').insertAfter('#header');
        }
        $('<div class="messages error">' + error + '</div>')
          .appendTo('#messages .clearfix');
      }
    }

  };

}(jQuery));
