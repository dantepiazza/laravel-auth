<?php

namespace DantePiazza\LaravelAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use DantePiazza\LaravelAuth\DTOs\DeviceData;

class CaptureDeviceMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $device = DeviceData::fromHeaders($request->headers->all());

        app()->instance(DeviceData::class, $device);
        
        $request->attributes->set('device', $device);

        return $next($request);
    }
}