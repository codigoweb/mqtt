<?php

namespace Drupal\mqtt\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\mqtt\Entity\MqttSubscription;

/**
 * Event fired when a MQTT message is received from a subscription.
 */
class MqttMessageReceivedEvent extends Event {

  const EVENT_NAME = 'mqtt_message_received';

  /**
   * @var \Drupal\mqtt\Entity\MqttSubscription
   */
  protected $subscription;

  /**
   * @var string
   */
  protected $topic;

  /**
   * @var string
   */
  protected $message;

  /**
   * @var array
   */
  protected $rawMessage;

  /**
   * @var int
   */
  protected $timestamp;

  /**
   * Constructs the message event.
   *
   * @param \Drupal\mqtt\Entity\MqttSubscription $subscription
   *   The MQTT subscription entity.
   * @param string $topic
   *   The topic where the message was received.
   * @param string $message
   *   The raw message payload.
   * @param array $raw_message
   *   The full decoded message packet from MQTT client.
   * @param int $timestamp
   *   The timestamp when the message was processed.
   */
  public function __construct(MqttSubscription $subscription, $topic, $message, array $raw_message = [], $timestamp = 0) {
    $this->subscription = $subscription;
    $this->topic = (string) $topic;
    $this->message = (string) $message;
    $this->rawMessage = $raw_message;
    $this->timestamp = (int) $timestamp;
  }

  /**
   * Gets the subscription entity.
   *
   * @return \Drupal\mqtt\Entity\MqttSubscription
   *   The subscription.
   */
  public function getSubscription() {
    return $this->subscription;
  }

  /**
   * Gets the MQTT topic.
   *
   * @return string
   *   The topic.
   */
  public function getTopic() {
    return $this->topic;
  }

  /**
   * Gets the message payload.
   *
   * @return string
   *   The message payload.
   */
  public function getMessage() {
    return $this->message;
  }

  /**
   * Gets raw decoded message data.
   *
   * @return array
   *   Decoded MQTT packet array.
   */
  public function getRawMessage() {
    return $this->rawMessage;
  }

  /**
   * Gets event processing timestamp.
   *
   * @return int
   *   Unix timestamp.
   */
  public function getTimestamp() {
    return $this->timestamp;
  }

}
