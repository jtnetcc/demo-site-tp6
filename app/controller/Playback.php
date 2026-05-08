<?php

namespace app\controller;

use app\service\PlaybackDeliveryService;
use app\service\SignedPlaybackService;
use RuntimeException;
use think\Request;

class Playback
{
    public function video(Request $request)
    {
        try {
            $cache = (new SignedPlaybackService())->validate($request);
            return (new PlaybackDeliveryService())->deliver($cache, $request);
        } catch (RuntimeException $e) {
            $code = (int) $e->getCode();
            $status = $code >= 400 && $code < 600 ? $code : 403;

            return response($e->getMessage(), $status);
        }
    }
}
