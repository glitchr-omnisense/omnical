<?php

namespace Omnical;

use Omnical\Exception\InvalidConfigException;
use Omnical\Exception\NotSupportedException;
use Omnical\Policy\GuardedCalendarEvents;
use Omnical\Policy\GuardedFreeBusy;
use Omnical\Policy\GuardedHolidays;
use Omnisense\Contracts\Policy\RateLimiterInterface;
use Omnisense\Contracts\Provider\ProviderInterface as BaseProvider;
use Omnisense\Contracts\Provider\Registry as BaseRegistry;

/**
 * The application's providers by name, each built once from its factory and
 * options, each call through a contract guarded by the provider's usage
 * policy:
 *
 *   $registry = new Registry([new GoogleFactory($http, $tokens), DeclarativeFactory::fromFile($openholidays, $http)], [
 *       'agenda' => ['factory' => 'google', 'options' => ['client_id' => '…', 'client_secret' => 'GOOGLE_CLIENT_SECRET', 'token' => 'user:42:google']],
 *       'holidays' => ['factory' => 'openholidays', 'options' => ['user_agent' => 'MySite/1.0 (https://example.org; contact@example.org)']],
 *   ], $limiter);
 *
 *   $registry->calendarEvents('agenda')->events(new EventQuery('primary', $from, $to));
 *   $registry->freeBusy('agenda')->freeBusy(new FreeBusyQuery(['primary'], $from, $to));
 *   $registry->holidays('holidays')->holidays(HolidayQuery::year('FR', 2027));
 *
 * calendarEvents(), freeBusy() and holidays() give the provider behind its
 * guard (Policy\Guarded*): each call is checked against the policy's rate
 * limits for its operation - the contract's method name, "calendars",
 * "events", "event", "create", "update", "delete", "freeBusy", "holidays" -
 * then counted, and refused with Exception\UsageLimitReachedException once a
 * limit is reached or while the provider asks to wait. A limit counted per
 * user (Google's 600 a minute per user) counts the $user given. get() gives
 * the provider itself, unguarded.
 */
final class Registry extends BaseRegistry
{
    /** @var string */
    public const PREFIX = Config::PREFIX;

    /** @var array<string, CalendarEventsInterface|FreeBusyInterface|HolidaysInterface> */
    private array $guarded = [];

    /**
     * @param iterable<ProviderFactoryInterface>                                                                                         $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>, policy?: array<string, mixed>, usage?: array<string, mixed>}> $config
     * @param ?RateLimiterInterface                                                                                                      $limiter   where every provider's calls are counted: an in-memory one by default, a shared one for an application served by many processes
     */
    public function __construct(iterable $factories, array $config, ?RateLimiterInterface $limiter = null)
    {
        parent::__construct($factories, $config, $limiter);
    }

    public function get(string $name): ProviderInterface
    {
        $provider = parent::get($name);

        return $provider instanceof ProviderInterface ? $provider : throw new InvalidConfigException(\sprintf('The "%s" provider (%s) is no omnical provider.', $name, $provider::class));
    }

    /** @param ?string $user whom a limit per user counts (the site's user id) */
    public function calendarEvents(string $name, ?string $user = null): CalendarEventsInterface
    {
        $guarded = $this->guarded['calendar-events '.$name.' '.$user] ??= new GuardedCalendarEvents($this->contract($name, CalendarEventsInterface::class, 'read calendars'), $this->guard($name), self::context($user));

        return $guarded instanceof CalendarEventsInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    /** @param ?string $user whom a limit per user counts */
    public function freeBusy(string $name, ?string $user = null): FreeBusyInterface
    {
        $guarded = $this->guarded['free-busy '.$name.' '.$user] ??= new GuardedFreeBusy($this->contract($name, FreeBusyInterface::class, 'tell busy time'), $this->guard($name), self::context($user));

        return $guarded instanceof FreeBusyInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    public function holidays(string $name): HolidaysInterface
    {
        $guarded = $this->guarded['holidays '.$name] ??= new GuardedHolidays($this->contract($name, HolidaysInterface::class, 'give holidays'), $this->guard($name));

        return $guarded instanceof HolidaysInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    /**
     * The configured providers keeping a contract ("calendar-events",
     * "free-busy", "holidays"), in the configuration's order; every one when
     * null.
     *
     * @return list<string>
     */
    public function names(?string $contract = null): array
    {
        return parent::names($contract);
    }

    /**
     * The holiday providers that may cover a country - those that name it
     * first, then those that name no country.
     *
     * @return list<string>
     */
    public function covering(string $country): array
    {
        $named = $any = [];
        foreach ($this->names(Capabilities::HOLIDAYS) as $name) {
            $capabilities = $this->get($name)->capabilities();
            if ([] === $capabilities->countries) {
                $any[] = $name;
            } elseif ($capabilities->coversCountry($country)) {
                $named[] = $name;
            }
        }

        return [...$named, ...$any];
    }

    protected function invalid(string $message): \Throwable
    {
        return new InvalidConfigException($message);
    }

    protected function notSupported(BaseProvider $provider, string $name, string $capability): \Throwable
    {
        return NotSupportedException::capability($provider, $name, $capability);
    }

    /** @return array{user?: string} */
    private static function context(?string $user): array
    {
        return null === $user ? [] : ['user' => $user];
    }
}
