<?php

namespace Drutiny\Acquia\AMA\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Drutiny\Acquia\Plugin\AcquiaTelemetry;
use Drutiny\Plugin\PluginRequiredException;
use Symfony\Component\Console\Exception\RuntimeException;

class Drutiny implements EventSubscriberInterface {

  protected AcquiaTelemetry $plugin;
  protected $consent = false;

  public function __construct(AcquiaTelemetry $plugin)
  {
    $this->plugin = $plugin;
  }

  public static function getSubscribedEvents()
  {
    return [
      'console.command' => 'enforceTelemetry',
    ];
  }

  /**
   * Enforce telemetry.
   */
  public function enforceTelemetry(ConsoleCommandEvent $event)
  {
     if (!in_array($event->getCommand()->getName(), ['profile:run', 'policy:audit'])) {
       return;
     }
     try {
       $config = $this->plugin->load();
     }
     catch (PluginRequiredException $e) {
       $this->plugin->setup();
       $config = $this->plugin->load();
     }
     $this->consent = $config['consent'];
     if (!$this->consent) {
       throw new RuntimeException("Consent to send telemetry is required: Please run `./flightpath plugin:setup acquia:telemetry`.\nAll data is anonymous.");
     }
  }
}
