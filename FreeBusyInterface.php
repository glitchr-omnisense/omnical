<?php

namespace Omnical;

use Omnical\Model\FreeBusy;
use Omnical\Model\FreeBusyQuery;

/** When calendars, or people, are busy ("free-busy"): what an appointment page asks before it offers a time. */
interface FreeBusyInterface extends ProviderInterface
{
    /** @return list<FreeBusy> one per calendar asked, in the order asked */
    public function freeBusy(FreeBusyQuery $query): array;
}
