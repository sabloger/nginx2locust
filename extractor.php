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
            key_exists('met', $curNode) ?: $curNode['met'] = $matches['met'][0];
            key_exists('qs', $curNode) ?: $curNode['qs'] = $matches['qs'][0];
            key_exists('post', $curNode) ?: $matches['post'][0] == '-' ? $curNode['post'] = "" : $curNode['post'] = $matches['post'][0];
        }

    } catch (\Exception $exception) {
        echo "Exception:\n";
        print_r($exception);
        print_r($line);
        throw $exception;
    }
//        die(json_encode($matches, JSON_PRETTY_PRINT));
}
fclose($handleLog);

$handleReport = fopen($argv[2], "w");

if (array_search("--locust", $argv) !== false) {
    print_r($days);
    $i = readline("Witch day?\n");
    foreach ($results[$days[$i]] as $uri => $data) {
        if(!key_exists('met',$data)) continue;
        switch (strtolower($data['met'])) {
            case 'post':
                fputs($handleReport, genPostLocust($uri, $data) . "\n\n");
                break;
            case 'get':
                fputs($handleReport, genGetLocust($uri, $data) . "\n\n");
                break;
            default:
                echo "\n\nNadaram ke!!!: " . $data['met'];
        }
    }
    die;
} else
    fputs($handleReport, json_encode($results, JSON_PRETTY_PRINT));

function getCookies()
{
    global $argv;
//    if(array_search("--locust-cookie=", $argv) !== false);
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
    $post = "";
    if ($data['post']) {
        $param = explode('&', $data['post']);
        foreach ($param as &$item) {
            $p = explode('=', $item);
            if (count($p) == 1)
                $p[1] = "";
            $item = implode('":"', $p);
        }
        $post = implode('","', $param);
//        $post = str_replace('&', '","', $post);
//        $post = str_replace('=', '":"', $post);
        $post = ',{"' . $post . '"}';
    }

    return sprintf(<<<PYTHON
    @task(%d)
    def %s(self):
        params = "%s"
        self.client.post("%s"+params %s %s)
PYTHON
        ,
        $data['cnt'],
        str_replace(['/', '-'], '_', substr($uri, 1)),
        !$data['qs'] ? '' : '?' . $data['qs'],
        $uri,
        $post,
        getCookies()
    );
}

