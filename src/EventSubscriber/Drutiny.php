<?php

namespace Drutiny\Acquia\AMA\EventSubscriber;

use Drutiny\Acquia\Plugin\AcquiaTelemetry;
use Drutiny\Attribute\UsePlugin;
use Drutiny\Entity\RuntimeDependency;
use Drutiny\Event\RuntimeDependencyCheckEvent;
use Drutiny\Plugin;
use Drutiny\Plugin\PluginRequiredException;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[UsePlugin(name: 'acquia:telemetry')]
class Drutiny implements EventSubscriberInterface
{
    public function __construct(protected Plugin $plugin)
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
        if (!$this->plugin->consent) {
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
