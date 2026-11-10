<?php

namespace Omnical\Testing;

use Omnical\Config;
use Omnical\Model\Calendar;
use Omnical\Model\Event;
use Omnical\Model\Holiday;
use Omnical\ProviderFactory;
use Omnical\ProviderInterface;

/** FixedProvider's factory: "fixed" (or the name given), answering from what it holds. */
final class FixedProviderFactory extends ProviderFactory
{
    /**
     * @param list<Calendar> $calendars
     * @param list<Event>    $events
     * @param list<Holiday>  $holidays
     */
    public function __construct(private readonly array $calendars = [], private readonly array $events = [], private readonly array $holidays = [], private readonly string $name = 'fixed')
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults(['omnical.factory_name' => $this->name, 'omnical.factory_title' => 'Fixed calendars', 'omnical.required_options' => []]);
    }

    protected function build(Config $c): ProviderInterface
    {
        return new FixedProvider($this->calendars, $this->events, $this->holidays, $this->name);
    }
}
