<?php

namespace GeneaLabs\LaravelMixpanel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Mixpanel;
use Sinergi\BrowserDetector\Browser;
use Sinergi\BrowserDetector\Device;
use Sinergi\BrowserDetector\Os;

class LaravelMixpanel extends Mixpanel
{
    private $callbackResults;
    private $defaults;
    private $request;

    public function __construct(Request $request, array $options = [])
    {
        $this->callbackResults = [];

        // ADDED A FEW CONFIGURATIONS
        $this->defaults = [
            'consumer' => config('services.mixpanel.consumer'),
            'connect_timeout' => config('services.mixpanel.connect-timeout', 2),
            'timeout' => config('services.mixpanel.timeout', 2),
            'max_queue_size' => config('services.mixpanel.max_queue_size'),
        ];

        if (config('services.mixpanel.host')) {
            $this->defaults["host"] = config('services.mixpanel.host');
        }

        $this->request = $request;


        parent::__construct(
            config('services.mixpanel.token'),
            array_merge($this->defaults, $options)
        );
    }

    protected function getData() : array
    {
        $browserInfo = new Browser();
        $osInfo = new Os();
        $deviceInfo = new Device();
        $browserVersion = trim(str_replace('unknown', '', $browserInfo->getName() . ' ' . $browserInfo->getVersion()));
        $osVersion = trim(str_replace('unknown', '', $osInfo->getName() . ' ' . $osInfo->getVersion()));
        $hardwareVersion = trim(str_replace('unknown', '', $deviceInfo->getName()));

        $data = [
            'Url' => $this->request->getUri(),
            'Operating System' => $osVersion,
            'Hardware' => $hardwareVersion,
            '$browser' => $browserVersion,
            'Referrer' => $this->request->header('referer'),
            '$referring_domain' => ($this->request->header('referer')
                ? parse_url($this->request->header('referer'))['host']
                : null),
            'ip' => $this->request->ip(),
        ];

        if ((! array_key_exists('$browser', $data)) && $browserInfo->isRobot()) {
            $data['$browser'] = 'Robot';
        }

        return array_filter($data);
    }

    public function track($event, $properties = [])
    {
        $properties = array_filter($properties);
        $data = $properties + $this->getData();

        if ($callbackClass = config("services.mixpanel.data_callback_class")) {
            $data = (new $callbackClass)->process($data);
            $data = array_filter($data);
        }

        parent::track($event, $data);
    }
}
