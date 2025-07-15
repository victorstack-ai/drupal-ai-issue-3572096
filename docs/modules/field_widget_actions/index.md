# Field Widget Actions Module

## What is the Field Widget Actions module?

The Field Widget Actions module provides an easy way to attach automator-based action buttons to form fields.

The module doesn't do anything by itself, but is a builder module that allows other modules to provider processors that can be used to trigger processes on form fields that fills out the field or gives suggestions on how to fill out the field.

This works with any field as long as the processor is configured to work with that field type.

## Dependencies

The Field Widget Actions module can be installed by itself, but it does require a processor to be installed to be actually useful.

It also requires the Field UI module to be installed if you want to configure the field widget actions in the UI - however, you can always run the configured field widget actions without the Field UI module if they have been setup.

## Known processors
You can click on the links in the menu to see how to configure the processors for different field types. But the following processors are known:

* AI Automators
* AI Content Suggestions
* ECA
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
