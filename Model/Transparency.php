<?php

namespace Omnical\Model;

/** Whether an event takes the time (opaque: busy) or not (transparent: free), iCalendar's TRANSP. */
enum Transparency: string
{
    case Opaque = 'opaque';
    case Transparent = 'transparent';
}
