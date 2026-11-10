<?php

namespace Omnical;

use Omnical\Model\Holiday;
use Omnical\Model\HolidayQuery;

/** A country's public (and school) holidays ("holidays"): closing days, delivery dates, the days a booking calendar greys out. */
interface HolidaysInterface extends ProviderInterface
{
    /** @return list<Holiday> by first day; empty for a country the provider does not cover */
    public function holidays(HolidayQuery $query): array;
}
