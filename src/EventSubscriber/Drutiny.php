<?php

namespace Drutiny\Acquia\AMA\EventSubscriber;

use Composer\Semver\Comparator;
use Drutiny\Acquia\Plugin\AcquiaTelemetry;
use Drutiny\Entity\RuntimeDependency;
use Drutiny\Event\RuntimeDependencyCheckEvent;
use Drutiny\Plugin\PluginRequiredException;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Drutiny implements EventSubscriberInterface
{
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
          'status' => 'checkStatus',
        ];
    }

    /**
     * Enforce telemetry.
     */
    public function enforceTelemetry(ConsoleCommandEvent $event)
    {
        if (!file_exists(DRUTINY_LIB.'/flightpath')) {
            return;
        }
        if (!in_array($event->getCommand()->getName(), ['profile:run', 'policy:audit'])) {
            return;
        }
        try {
            $config = $this->plugin->load();
        } catch (PluginRequiredException $e) {
            $this->plugin->setup();
            $config = $this->plugin->load();
        }
        $this->consent = $config['consent'];
        if (!$this->consent) {
            throw new RuntimeException("Consent to send telemetry is required: Please run `./flightpath plugin:setup acquia:telemetry`.\nAll data is anonymous.");
        }
    }

    public function checkStatus(RuntimeDependencyCheckEvent $event)
    {
        $event->addDependency(
            (new RuntimeDependency('Simple XML'))
            ->setValue(extension_loaded('simplexml'))
            ->setDetails('Drutiny requires the SimpleXML extension')
            ->setStatus(extension_loaded('simplexml'))
        );
    }
}
