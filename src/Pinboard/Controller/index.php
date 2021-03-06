<?php

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Pinboard\Utils\IDNaConvert;

$index = $app['controllers_factory'];

$index->get('/', function() use ($app) {
    
    $result = array();

    $hosts = ".*";

    if (isset($app['params']['secure']['enable'])) {
        if ($app['params']['secure']['enable'] == "true") {
            $user = $app['security']->getToken()->getUser();
            $hosts = isset($app['params']['secure']['users'][$user->getUsername()]['hosts'])
                        ? $app['params']['secure']['users'][$user->getUsername()]['hosts'] 
                        : ".*";
            if (trim($hosts) == "") {
                $hosts = ".*";
            }
        }
    }

    $params = array(
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'hosts' => $hosts,
    );
    
    $sql = '
        SELECT
            a.server_name, 
            sum(a.req_count) as req_count, 
            avg(a.req_per_sec) as req_per_sec, 
            (
                SELECT 
                    count(*) 
                FROM 
                    ipm_status_details b 
                WHERE 
                    a.server_name = b.server_name AND b.created_at > :created_at
            ) 
            as error_count
        FROM
            ipm_report_by_hostname_and_server a
        WHERE
            a.created_at > :created_at AND a.server_name REGEXP :hosts
        GROUP BY
            a.server_name
    ';
    
    $stmt = $app['db']->executeQuery($sql, $params, array(), new QueryCacheProfile(5 * 60));
    $result['servers'] = $stmt->fetchAll();
    $stmt->closeCursor();

    $idn = new IDNaConvert(array('idn_version' => 2008));

    foreach($result['servers'] as &$item) {
        if (stripos($item['server_name'], 'xn--') !== false) {
            $item['server_name'] = $idn->decode($item['server_name']);
        }

        $item['req_per_sec'] = number_format($item['req_per_sec'], 3, ',', '');
    }
    
    return $app['twig']->render('index.html.twig', $result);
})
->bind('index');

return $index;