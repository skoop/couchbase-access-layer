<?php

declare(strict_types=1);

namespace BowlOfSoup\CouchbaseAccessLayer\Test\Repository\BucketRepository;

use BowlOfSoup\CouchbaseAccessLayer\Factory\ClusterFactory;
use BowlOfSoup\CouchbaseAccessLayer\Repository\BucketRepository;
use BowlOfSoup\CouchbaseAccessLayer\Test\CouchbaseMock\CouchbaseTestCase;
use Couchbase\Bucket;
use Couchbase\Cluster;
use Couchbase\N1qlQuery;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;

class AbstractTestBaseClass extends CouchbaseTestCase
{
    /** @var \Couchbase\Bucket */
    protected $bucket;

    /** @var \BowlOfSoup\CouchbaseAccessLayer\Repository\BucketRepository */
    protected $bucketRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bucket = $this->getDefaultBucket();

        $clusterMock = $this
            ->getMockBuilder(Cluster::class)
            ->onlyMethods(['bucket'])
            ->disableOriginalConstructor()
            ->getMock();
        $clusterMock
            ->expects($this->once())
            ->method('bucket')
            ->with('default')
            ->willReturn($this->bucket);

        /** @var \PHPUnit\Framework\MockObject\MockObject|\BowlOfSoup\CouchbaseAccessLayer\Factory\ClusterFactory $clusterFactoryMock */
        $clusterFactoryMock = $this
            ->getMockBuilder(ClusterFactory::class)
            ->onlyMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();
        $clusterFactoryMock
            ->expects($this->once())
            ->method('create')
            ->willReturn($clusterMock);

        $this->bucketRepository = new BucketRepository('default', $clusterFactoryMock);
    }

    /**
     * Replace the bucket in the BucketRepository as CouchbaseMock can't handle N1ql queries.
     *
     * @param \PHPUnit\Framework\MockObject\MockObject $bucketMock
     *
     * @return \BowlOfSoup\CouchbaseAccessLayer\Repository\BucketRepository|\PHPUnit\Framework\MockObject\MockObject|null
     */
    protected function replaceBucketOnBucketRepositoryMock(MockObject $bucketMock)
    {
        try {
            $bucketRepository = clone $this->bucketRepository;

            $reflectionClass = new \ReflectionClass($bucketRepository);
            $reflectionProperty = $reflectionClass->getProperty('bucket');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($bucketRepository, $bucketMock);

            return $bucketRepository;
        } catch (\ReflectionException $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);

            return null;
        }
    }

    /**
     * @param \PHPUnit\Framework\MockObject\Stub\ReturnCallback $returnCallback
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function createBucketMock(\Closure $returnCallback): MockObject
    {
        $bucketMock = $this->getMockBuilder(Bucket::class)->onlyMethods(['query'])->getMock();
        $bucketMock
            ->expects($this->once())
            ->method('query')
            ->with(
            // Asserting that the input parameters for method 'query' are correct
                $this->callback(function ($query) {
                    Assert::assertInstanceOf(N1qlQuery::class, $query);

                    return $query;
                }),
                $this->callback(function ($jsonAsArray) {
                    Assert::assertTrue($jsonAsArray);

                    return $jsonAsArray;
                })
            )
            ->willReturnCallback($returnCallback);

        return $bucketMock;
    }

    protected function getClusterFactory(): ClusterFactory
    {
        $clusterFactory = new ClusterFactory('couchbase', $this->testUser, $this->testPassword);

        return $clusterFactory;
    }
}
