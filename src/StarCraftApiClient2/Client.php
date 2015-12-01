<?php namespace StarCraftApiClient2;

/**
 * @author neun
 * @since  2015-10-29
 */
class Client
{
    const SC2_PROFILE_URL = "https://%s.api.battle.net/sc2/profile/%s/1/%s/%s?locale=%s&apikey=%s";
    const SC2_LADDER_URL = "https://%s.api.battle.net/sc2/ladder/%s?locale=%s&apikey=%s";
    const SC2_DATA_URL = "https://%s.api.battle.net/sc2/data/%s?locale=%s&apikey=%s";

    /**
     * @var array
     */
    private $curlOptions = [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_HEADER => 1,
    ];

    /**
     * @var array
     */
    protected $responses = [];

    /**
     * @var array
     */
    protected $urls = [];

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var int
     */
    private $concurrency;

    /**
     * @var float
     */
    private $runtime;

    /**
     * @param string $apiKey
     * @param int $concurrency
     * @param string $locale
     */
    public function __construct($apiKey, $concurrency = 10, $locale = 'en_GB')
    {
        if (1 !== preg_match('/^\w{32}$/', $apiKey)) {
            throw new \InvalidArgumentException("Invalid api key");
        }
        $this->apiKey = $apiKey;

        $this->concurrency = (int) $concurrency;

        $this->locale = $locale;
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $region
     */
    public function addAllProfileRequests($id, $name, $region = 'eu')
    {
        $this->addProfileMatchesRequest($id, $name, $region);
        $this->addProfileLaddersRequest($id, $name, $region);
        $this->addProfileProfileRequest($id, $name, $region);
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $region
     */
    public function addProfileMatchesRequest($id, $name, $region = 'eu')
    {
        $this->addProfileApiRequest('matches', $id, $name, $region);
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $region
     */
    public function addProfileLaddersRequest($id, $name, $region = 'eu')
    {
        $this->addProfileApiRequest('ladders', $id, $name, $region);
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $region
     */
    public function addProfileProfileRequest($id, $name, $region = 'eu')
    {
        $this->addProfileApiRequest('profile', $id, $name, $region);
    }

    /**
     * @param string $type
     * @param int $id
     * @param string $name
     * @param string $region
     */
    private function addProfileApiRequest($type, $id, $name, $region = 'eu')
    {
        $requestId = 'profile/'.$id.'/'.$type;
        $this->urls[$requestId] = sprintf(self::SC2_PROFILE_URL,
            $region, $id, $name, $type != 'profile' ? $type : null, $this->locale, $this->apiKey);
    }

    /**
     * @param int $ladder
     * @param string $region
     */
    public function addLadderRequest($ladder, $region = 'eu')
    {
        $requestId = 'ladder/'.$ladder;
        $this->urls[$requestId] = sprintf(self::SC2_LADDER_URL,
            $region, $ladder, $this->locale, $this->apiKey);
    }

    /**
     * @param string $region
     */
    public function addAchievementsRequest($region = 'eu')
    {
        $requestId = 'achievements/'.$region;
        $this->urls[$requestId] = sprintf(self::SC2_DATA_URL,
            $region, 'achievements', $this->locale, $this->apiKey);
    }

    /**
     * @param string $region
     */
    public function addRewardsRequest($region = 'eu')
    {
        $requestId = 'rewards/'.$region;
        $this->urls[$requestId] = sprintf(self::SC2_DATA_URL,
            $region, 'rewards', $this->locale, $this->apiKey);
    }

    /**
     * Execute the requests for all $urls.
     */
    public function run()
    {
        $t = -microtime(1);
        $mh = curl_multi_init();

        $chs = $this->hydrateCurlHandles();
        $this->performCurlRequests($chs, $mh);
        $this->fetchCurlRequestData($chs, $mh);

        curl_multi_close($mh);
        $this->runtime = $t + microtime(1);
    }

    /**
     * Create curl handles with URLs and CURLOPTS.
     *
     * @return resource[]
     */
    private function hydrateCurlHandles()
    {
        $chs = [];

        foreach ($this->urls as $key => $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, $this->curlOptions);
            $chs[$key] = $ch;
        }

        return $chs;
    }

    /**
     * Perform the actual api requests.
     *
     * @param resource[] $chs
     * @param resource $mh
     */
    private function performCurlRequests($chs, $mh)
    {
        $open = 0;
        $tmp = $chs;

        while (!empty($tmp) || $open > 0) {
            if (!empty($tmp) && $open < $this->concurrency) {
                curl_multi_add_handle($mh, array_pop($tmp));
            }

            curl_multi_exec($mh, $open);
            usleep(11111);
        }
    }

    /**
     * Extract responses from each cURL request.
     *
     * @param resource[] $chs
     * @param resource $mh
     */
    private function fetchCurlRequestData($chs, $mh)
    {
        foreach ($chs as $key => $ch) {
            $curlInfo = curl_getinfo($ch);
            $response = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);

            $this->responses[$key]['header'] = substr($response, 0, $curlInfo['header_size']);
            $this->responses[$key]['body'] = substr($response, $curlInfo['header_size']);
            $this->responses[$key]['info'] = $curlInfo;
            $this->responses[$key]['key'] = $key;
        }
    }

    /**
     * @return array[]
     */
    public function responses()
    {
        return isset($this->responses) ? $this->responses : [];
    }

    /**
     * @return array[]
     */
    public function urls()
    {
        return $this->urls;
    }

    /**
     * @param $response
     * @return array
     */
    public function get($response)
    {
        return isset($this->responses[$response]) ? $this->responses[$response] : [];
    }

    /**
     * @return float
     */
    public function runtime()
    {
        return $this->runtime;
    }
}