'use strict';

define(
    [
        'underscore',
        'backbone',
        'pim/form',
        'oro/mediator',
        'oro/messenger',
        'pim/field-manager',
        'pim/product-edit-form/attributes/validation-error',
        'pim/user-context'
    ],
    function (_, Backbone, BaseForm, mediator, messenger, FieldManager, ValidationError, UserContext) {
        return BaseForm.extend({
            initialize: function () {
                mediator.off(null, null, 'form:product:validation');
                mediator.on('validation_error', _.bind(this.validationError, this), 'form:product:validation');
                mediator.on(
                    'product:action:post_update',
                    _.bind(this.removeValidationErrors, this),
                    'form:product:validation'
                );

                BaseForm.prototype.initialize.apply(this, arguments);
            },
            validationError: function (validationErrors) {
                this.removeValidationErrors();
                this.addValidationErrors(validationErrors);

                mediator.trigger('product:action:post_validation_error');
            },
            removeValidationErrors: function () {
                var fields = FieldManager.getFields();

                _.each(fields, function (field) {
                    field.setValid(true);
                    field.removeElement('footer', 'validation');
                });
            },
            addValidationErrors: function (data) {
                _.each(data.values, _.bind(function (fieldErrors, attributeCode) {
                    FieldManager.getField(attributeCode).done(_.bind(function (field) {
                        var validationError = new ValidationError(fieldErrors, this);

                        field.addElement(
                            'footer',
                            'validation',
                            validationError
                        );

                        field.setValid(false);
                    }, this));
                }, this));

                // Global errors with an empty property path
                if (data[''] && data[''].message) {
                    messenger.notificationFlashMessage('error', data[''].message);
                }
            },
            changeContext: function (locale, scope) {
                if (locale) {
                    UserContext.set('catalogLocale', locale);
                }

                if (scope) {
                    UserContext.set('catalogScope', scope);
                }
            }
        });
    }
);