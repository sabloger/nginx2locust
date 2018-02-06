<?php

$acceptedPaths = [];
if (key_exists(3, $argv))
    if ($argv[3][0] != '-')
        include $argv[3];


$results = [];
$days = [];

$reg = '/^(?<ip>(?:\d{1,3}\.?){4})(?:\s-){2}\s(?:[[](?<dt>[^]]*)[]])\s(?:"(?<met>\w*)\s(?<uri>[^\s?]*)(?:\?(?<qs>\S*))?\s[^"]*")\s(?<stat>\d*)\s(?:[\d.]*\s){3}(?:"[^"]*"\s?){3}(?:"(?<post>[^"]*)")/';

$handleLog = fopen($argv[1], "r");
// Extract
while (($line = fgets($handleLog)) !== false) {
    $matches = [];
    preg_match_all($reg, $line, $matches);

    if (array_search($matches['uri'][0], $acceptedPaths) === false && count($acceptedPaths))
        continue;

    $curNode = &$results;
    try {
        //______________________________________________________________________________________________________________
        // Date part
        $dateTime = $dateTime = new DateTime($matches['dt'][0]);

        if (!key_exists($dateTime->format('Y-m-d'), $curNode))
            $curNode[$dateTime->format('Y-m-d')] = [];
        $curNode = &$curNode[$dateTime->format('Y-m-d')];

        if (array_search($dateTime->format('Y-m-d'), $days) === false)
            $days[] = $dateTime->format('Y-m-d');
        //______________________________________________________________________________________________________________
        // Path part
        if (!key_exists($matches['uri'][0], $curNode))
            $curNode[$matches['uri'][0]] = [];
        $curNode = &$curNode[$matches['uri'][0]];
        //______________________________________________________________________________________________________________
        // Data part
        key_exists('cnt', $curNode) ? $curNode['cnt']++ : $curNode['cnt'] = 1;
        if ($matches['stat'][0] == 200) {
            empty($matches['met'][0]) ? $curNode['met'] = "" : $curNode['met'] = $matches['met'][0];
            empty($matches['qs'][0]) ? $curNode['qs'] = "" : $curNode['qs'] = $matches['qs'][0];
            empty($matches['post'][0]) ?: $matches['post'][0] == '-' ? $curNode['post'] = "" : $curNode['post'] = $matches['post'][0];
        }

    } catch (\Exception $exception) {
        echo "Exception:\n";
        print_r($exception);
        print_r($line);
        throw $exception;
    }
}
fclose($handleLog);

foreach ($results as $day => &$apis) {
    foreach ($apis as $api => $data) {
        if (!is_string($api)) continue;
        $data['uri'] = $api;
        $results[$day][] = $data;
        unset($results[$day][$api]);
    }
    array_multisort($apis);
}


if (array_search("--locust", $argv) !== false) {
    $handleReport = fopen($argv[2], "w");
    print_r($days);
    $i = readline("Witch day? \n");
    foreach ($results[$days[$i]] as $data) {
        if (!key_exists('met', $data)) continue;
        switch (strtolower($data['met'])) {
            case 'post':
                fputs($handleReport, genPostLocust($data['uri'], $data) . "\n\n");
                break;
            case 'get':
                fputs($handleReport, genGetLocust($data['uri'], $data) . "\n\n");
                break;
            default:
                echo "\n\n Method!!!: " . $data['met'];
        }
    }
    fclose($handleReport);
}

$handleJson = fopen($argv[2] . ".json", "w");
fputs($handleJson, json_encode($results, JSON_PRETTY_PRINT));
fclose($handleJson);


function getCookies()
{
    global $argv;
    $res = "";
    array_walk($argv, function ($item) use (&$res) {
        if (strpos($item, "--locust-cookie=") === 0)
            $res = ", cookies=" . explode('=', $item)[1];
    });
    return $res;
}

function genGetLocust($uri, $data)
{
    return sprintf(<<<PYTHON
    @task(%d)
    def %s(self):
        params = "%s"
        self.client.get("%s"+params %s)
PYTHON
        ,
        $data['cnt'],
        str_replace(['/', '-'], '_', substr($uri, 1)),
        !$data['qs'] ? '' : '?' . $data['qs'],
        $uri,
        getCookies()
    );
}

function genPostLocust($uri, $data)
{
//    $post = empty($data['post']) ? '' : ',"' . $data['post'].'"';

    $params = empty($data['qs']) ? '' : '?' . $data['qs'];
    empty($params) ? empty($data['post']) ?: $params = '?' . $data['post'] : $params .= '&' . $data['post'];


    return sprintf(<<<PYTHON
    @task(%d)
    def %s(self):
        params = "%s"
        self.client.post("%s"+params %s)
PYTHON
        ,
        $data['cnt'],
        str_replace(['/', '-'], '_', substr($uri, 1)),
        $params,
        $uri,
        getCookies()
    );
}

