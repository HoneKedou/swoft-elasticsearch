<?php
/**
 * This file is part of Swoft.
 *
 * @link     https://swoft.org
 * @document https://doc.swoft.org
 * @contact  limingxin@swoft.org
 * @license  https://github.com/swoft-cloud/swoft/blob/master/LICENSE
 */
namespace SwoftTest\Cases;

use SwoftTest\Testing\Constants;

class DocumentTest extends AbstractTestCase
{
    public function testGetDocument()
    {
        $params = [
            'index' => Constants::INDEX,
            'type' => Constants::TYPE,
            'id' => 0,
        ];
        $expect = $this->getClient()->get($params);
        go(function () use ($expect, $params) {
            $actual = $this->getClient()->get($params);
            $this->assertEquals($expect, $actual);
        });
    }

    public function testAddDocument()
    {
        try {
            $this->getClient()->delete([
                'index' => Constants::INDEX,
                'type' => Constants::TYPE,
                'id' => 999
            ]);
        } catch (\Exception $ex) {
        }

        $body = [
            'name' => 'Mr.999',
            'age' => 99,
            'birthday' => '1990-01-01',
            'book' => [
                'author' => 'limx',
                'name' => '长寿的秘诀',
                'publish' => '2018-01-01',
                'desc' => '多睡觉'
            ],
            'location' => [
                'lat' => 0,
                'lon' => 0,
            ],
            'randnum' => 999
        ];

        $params = [
            'index' => Constants::INDEX,
            'type' => Constants::TYPE,
            'id' => 999,
            'body' => $body,
        ];

        $expect = $this->getClient()->index($params);
        $this->assertEquals('created', $expect['result']);
        $version = $expect['_version'];

        go(function () use ($expect, $params, $version) {
            $actual = $this->getClient()->index($params);

            $this->assertEquals('updated', $actual['result']);
            $this->assertEquals($version + 1, $actual['_version']);
        });
    }

    public function testCurdDocument()
    {
        $body = [
            'name' => 'Mr.998',
            'age' => 99,
            'birthday' => '1990-01-01',
            'book' => [
                'author' => 'limx',
                'name' => '长寿的秘诀',
                'publish' => '2018-01-01',
                'desc' => '多睡觉'
            ],
            'location' => [
                'lat' => 0,
                'lon' => 0,
            ],
            'randnum' => 998
        ];
        $params = [
            'index' => Constants::INDEX,
            'type' => Constants::TYPE,
            'id' => 998,
            'body' => $body,
        ];

        $this->getClient()->index($params);

        $params = [
            'index' => Constants::INDEX,
            'type' => Constants::TYPE,
            'id' => 998,
            'body' => [
                'doc' => [
                    'book' => [
                        'publish' => '2018-01-02',
                    ],
                ],
                'doc_as_upsert' => true,
            ],
            'retry_on_conflict' => 5,
        ];
        $this->getClient()->update($params);

        $params2 = [
            'index' => Constants::INDEX,
            'type' => Constants::TYPE,
            'id' => 998,
        ];
        $expect = $this->getClient()->get($params2);

        $this->assertEquals('limx', $expect['_source']['book']['author']);
        $this->assertEquals('2018-01-02', $expect['_source']['book']['publish']);

        go(function () use ($expect, $params, $params2) {
            $params['body']['doc']['book']['publish'] = '2018-01-03';
            $this->getClient()->update($params);
            $actual = $this->getClient()->get($params2);

            $this->assertEquals($expect['_version'] + 1, $actual['_version']);
            $this->assertEquals('limx', $actual['_source']['book']['author']);
            $this->assertEquals('2018-01-03', $actual['_source']['book']['publish']);

            // 删除
            $res = $this->getClient()->delete($params2);

            $this->assertEquals('deleted', $res['result']);
            $this->assertEquals(1, $res['_shards']['successful']);
            $this->assertEquals(0, $res['_shards']['failed']);
        });
    }

    public function testBoolGeoQuery()
    {
        $lat = 31.249162;
        $lon = 121.487899;

        $params = [
            'index' => Constants::INDEX,
            'type' => Constants::TYPE,
            'body' => [
                'query' => [
                    'bool' => [
                        'filter' => [
                            'geo_distance' => [
                                'distance' => '1km',
                                'location' => [
                                    'lat' => $lat,
                                    'lon' => $lon
                                ],
                            ],
                        ],
                    ]
                ],
                'from' => 0,
                'size' => 5,
                'sort' => [
                    '_geo_distance' => [
                        'location' => [
                            'lat' => $lat,
                            'lon' => $lon
                        ],
                        'order' => 'asc',
                        'unit' => 'km',
                        'mode' => 'min',
                    ],
                ],
            ],
        ];

        $expect = $this->getClient()->search($params);
        $this->assertEquals(3, $expect['hits']['total']);

        go(function () use ($params, $expect) {
            $actual = $this->getClient()->search($params);
            $this->assertEquals(3, $actual['hits']['total']);
            $this->assertEquals($expect['hits'], $actual['hits']);
        });
    }
}
