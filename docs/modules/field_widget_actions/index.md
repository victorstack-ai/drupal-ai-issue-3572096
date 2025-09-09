# Field Widget Actions Module

## What is the Field Widget Actions module?

The Field Widget Actions module provides an easy way to attach action buttons to form fields.

The module doesn't do anything by itself, but is a builder module that allows other modules to provide processors that can be used to trigger processes on form fields that fills out the field or gives suggestions on how to fill out the field.

This works with any field as long as the processor is configured to work with that field type.

## Dependencies

The Field Widget Actions module can be installed by itself, but it does require a processor to be installed to be actually useful.

It also requires the Field UI module to be installed if you want to configure the field widget actions in the UI - however, you can always run the configured field widget actions without the Field UI module if they have been setup.

## Known processors
You can click on the links in the menu to see how to configure the processors for different field types. But the following processors are known:

* AI Automators
* AI Content Suggestions
* [ECA](https://ecaguide.org/plugins/eca/base/events/eca_base_eca_field_widget/)
* AI Agents

## How to configure a Field Widget Action

This will just be general information on how to configure a field widget action, as the actual configuration will depend on the processor you are using.

1. Setup an entity type, node or any other content entity type that is fieldable.
2. Add a field to the entity type that you want to attach the action to.
3. Visit Manage Form Display for the entity type.
4. Click on the cog icon next to the field you want to attach the action to.
5. In the "Field Widget Actions" section, select the processor you want to use.
6. Configure the processor settings as needed.
7. Save the configuration.

## How to create your own Field Widget Action plugin

Create a plugin class in `Plugin\FieldWidgetAction` namespace. It is recommended to extend `FieldWidgetActionBase` class
in order to focus on functionality of your plugin itself and not do any form integration. Of course you can implement
all methods from the `FieldWidgetActionInterface` on your own. For actions with suggestions there are two helper methods
in the base class:
- `returnSuggestions` that will help to display the suggestions in a modal dialog with ability to use selected value in
the form field directly in case the form element selector is provided;
- `getSuggestionsTarget` - that gets the selector for the most common use cases of a suggestions action. In special
cases the plugin can overwrite the method to use its own logic.
- `getTargetElementDelta` - that gets the delta for the field widget form element.
- `getTargetElementFieldName` - that gets the corresponding field name of the form element.

The methods are protected ones, therefore, they are not part of the interface (as not all actions
provide suggestions).
