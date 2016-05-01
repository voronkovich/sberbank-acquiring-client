<?php

namespace Voronkovich\SberbankAcquiring;

/**
 * Order statuses.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
class OrderStatus
{
    // An order was successfully registered, but is'nt paid yet
    const REGISTERED = 0;

    // An order's amount was successfully holded (for two-stage payments only)
    const APPROVED = 1;

    // An order was authorized successfully
    const AUTHORIZED = 2;

    // An order authorization was reversed
    const AUTHORIZATION_REVERSED = 3;

    // An order authorization was initialized by card emitter's ACS
    const AUTHORIZATION_INITIALIZED = 5;

    // An authorization was rejected
    const AUTHORIZATION_REJECTED = 6;

    // An order was refunded
    const REFUNDED = 4;

    public static function isRegistered($orderStatus)
    {
        return self::REGISTERED == $orderStatus;
    }

    public static function isApproved($orderStatus)
    {
        return self::APPROVED == $orderStatus;
    }

    public static function isAuthorized($orderStatus)
    {
        return self::AUTHORIZED == $orderStatus;
    }

    public static function isAuthorizationReversed($orderStatus)
    {
        return self::AUTHORIZATION_REVERSED == $orderStatus;
    }

    public static function isAuthorizationInitialized($orderStatus)
    {
        return self::AUTHORIZATION_INITIALIZED == $orderStatus;
    }

    public static function isAuthorizationRejected($orderStatus)
    {
        return self::AUTHORIZATION_REJECTED == $orderStatus;
    }

    public static function isRefunded($orderStatus)
    {
        return self::REFUNDED == $orderStatus;
    }
}
