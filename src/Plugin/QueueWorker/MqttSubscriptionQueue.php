<?php

namespace Drupal\mqtt\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\mqtt\Event\MqttMessageReceivedEvent;
use Drupal\mqtt\Event\MqttSubscriptionCheckEvent;
use karpy47\PhpMqttClient\MQTTClient;

/**
 * Plugin implementation of the mqtt_subscription_queue queueworker.
 *
 * @QueueWorker (
 *   id = "mqtt_subscription_queue",
 *   title = @Translation("Consume MQTT Subscription Data"),
 *   cron = {"time" = 30}
 * )
 */
class MqttSubscriptionQueue extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $broker_config = \Drupal::config('mqtt.mqttbrokersettingsform');

    foreach ($data->subscriptions as $subscription) {
      $subscription_topic = $subscription->getName();
      $subscription_broker = $subscription->get('mqtt_broker')->getValue();
      $subscription_broker_id = $broker_config->get('mqtt_id');
      $broker_id = $subscription_broker[0]["target_id"];
      $broker = \Drupal::entityTypeManager()->getStorage('mqtt_broker')->load($broker_id);
      $broker_host = $broker->get('broker_address')->getValue()[0]["value"];
      $broker_host = (!empty($broker_host)) ? $broker_host : NULL;
      $broker_port = $broker->get('broker_port')->getValue()[0]["value"];
      $broker_port = (!empty($broker_port)) ? $broker_port : NULL;
      if (!empty($broker->get('broker_username')->getValue())) {
        $broker_user = $broker->get('broker_username')->getValue()[0]["value"];
      } else {
        $broker_user = NULL;
      }
      if (!empty($broker->get('broker_password')->getValue())) {
        $broker_password = $broker->get('broker_password')->getValue()[0]["value"];
      } else {
        $broker_password = NULL;
      }

      $timestamp = time();

      $client = new MQTTClient($broker_host, $broker_port);

      if (!empty($broker_host) && !empty($broker_password)) {
        $client->setAuthentication($broker_user, $broker_password);
      }

      // $client->setEncryption('cacerts.pem'); // Todo: Add support for SSL connection!
      $success = $client->sendConnect($subscription_broker_id);
      if ($success) {
        $client->sendSubscribe($subscription_topic);
        $mqtt_messages = $client->getPublishMessages();
        $client->sendDisconnect();

        $event_dispatcher = \Drupal::service('event_dispatcher');
        $event = new MqttSubscriptionCheckEvent($subscription);
        $event_dispatcher->dispatch($event, MqttSubscriptionCheckEvent::EVENT_NAME);

        foreach ($mqtt_messages as $mqtt_message) {
          $message = isset($mqtt_message['message']) ? (string) $mqtt_message['message'] : '';
          if ($message === '') {
            continue;
          }

          $topic = isset($mqtt_message['topic']) ? (string) $mqtt_message['topic'] : $subscription_topic;
          $message_event = new MqttMessageReceivedEvent($subscription, $topic, $message, $mqtt_message, $timestamp);
          $event_dispatcher->dispatch($message_event, MqttMessageReceivedEvent::EVENT_NAME);
          $this->appendMessageToCsv($subscription, $timestamp, $message);
        }
      }
      $client->close();
    }
  }

  /**
   * Appends one message entry to the subscription CSV file.
   *
   * @param \Drupal\mqtt\Entity\MqttSubscription $subscription
   *   The MQTT subscription entity.
   * @param int $timestamp
   *   The message timestamp.
   * @param string $message
   *   The message payload.
   */
  protected function appendMessageToCsv($subscription, $timestamp, $message) {
    $csv_data = $subscription->get('csv_data')->getValue();
    if (!empty($csv_data[0]['target_id'])) {
      $data_file = $csv_data[0]['target_id'];
      $sub_data_file = \Drupal::entityTypeManager()->getStorage('file')->load($data_file);
      if ($sub_data_file) {
        $file = $sub_data_file->getFileUri();
        $handle = fopen($file, 'a');
        fputcsv($handle, [$timestamp, $message]);
        fclose($handle);
      }
      return;
    }

    $subscription_msg_csv = [
      ['timestamp', 'message'],
      [$timestamp, $message],
    ];

    $temp_file = file_directory_temp() . "/sub_$timestamp.csv";
    $fp = fopen($temp_file, 'w');
    foreach ($subscription_msg_csv as $fields) {
      fputcsv($fp, $fields);
    }
    fclose($fp);

    $sub_directory = $subscription->getFieldDefinition('csv_data')->getSetting('file_directory');
    $url_scheme = $subscription->getFieldDefinition('csv_data')->getSetting('uri_scheme');
    $directory = "$url_scheme://$sub_directory";
    file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
    $sub_file = \Drupal::service('file.repository')->writeData(
      fopen($temp_file, 'r'),
      $directory . '/sub_' . $subscription->id() . '.csv',
      FILE_EXISTS_REPLACE
    );
    $subscription->set('csv_data', ['target_id' => $sub_file->id()]);
    $subscription->save();
  }

}
