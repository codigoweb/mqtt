# Changelog

## [1.0.3] - Unreleased

### Added
- New event `mqtt_message_received` (`Drupal\\mqtt\\Event\\MqttMessageReceivedEvent`) with getters for subscription, topic, payload, raw packet and timestamp.

### Changed
- `MqttSubscriptionQueue` now processes all messages returned by `getPublishMessages()` and dispatches one `mqtt_message_received` event per message.
- `MqttSubscriptionQueue` keeps dispatching `mqtt_subscription_check` for backward compatibility.
- `MqttSubscriptionQueue` appends each processed message to CSV through a dedicated helper.
- `mqtt_cron()` now queries `mqtt_subscription`, enqueues in `mqtt_subscription_queue`, and returns early when there are no active subscriptions.

### Notes
- Existing integrations listening to `mqtt_subscription_check` keep working.
- New integrations can react only to real incoming state changes by subscribing to `mqtt_message_received`.
