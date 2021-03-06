<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\ApiProblem;

use PHPUnit_Framework_TestCase as TestCase;
use ReflectionObject;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\Exception;

class ApiProblemTest extends TestCase
{
    public function statusCodes()
    {
        return array(
            '200' => array(200),
            '201' => array(201),
            '300' => array(300),
            '301' => array(301),
            '302' => array(302),
            '400' => array(400),
            '401' => array(401),
            '404' => array(404),
            '500' => array(500),
        );
    }

    /**
     * @dataProvider statusCodes
     */
    public function testStatusIsUsedVerbatim($status)
    {
        $apiProblem = new ApiProblem($status, 'foo');
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('status', $payload);
        $this->assertEquals($status, $payload['status']);
    }

    public function testExceptionCodeIsUsedForStatus()
    {
        $exception  = new \Exception('exception message', 401);
        $apiProblem = new ApiProblem('500', $exception);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('status', $payload);
        $this->assertEquals($exception->getCode(), $payload['status']);
    }

    public function testDetailStringIsUsedVerbatim()
    {
        $apiProblem = new ApiProblem('500', 'foo');
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('detail', $payload);
        $this->assertEquals('foo', $payload['detail']);
    }

    public function testExceptionMessageIsUsedForDetail()
    {
        $exception  = new \Exception('exception message');
        $apiProblem = new ApiProblem('500', $exception);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('detail', $payload);
        $this->assertEquals($exception->getMessage(), $payload['detail']);
    }

    public function testExceptionsCanTriggerInclusionOfStackTraceInDetails()
    {
        $exception  = new \Exception('exception message');
        $apiProblem = new ApiProblem('500', $exception);
        $apiProblem->setDetailIncludesStackTrace(true);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('trace', $payload);
        $this->assertInternalType('array', $payload['trace']);
        $this->assertEquals($exception->getTrace(), $payload['trace']);
    }

    public function testExceptionsCanTriggerInclusionOfNestedExceptions()
    {
        $exceptionChild  = new \Exception('child exception');
        $exceptionParent = new \Exception('parent exception', null, $exceptionChild);

        $apiProblem = new ApiProblem('500', $exceptionParent);
        $apiProblem->setDetailIncludesStackTrace(true);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('exception_stack', $payload);
        $this->assertInternalType('array', $payload['exception_stack']);
        $expected = array(
            array(
                'code' => $exceptionChild->getCode(),
                'message' => $exceptionChild->getMessage(),
                'trace' => $exceptionChild->getTrace(),
            ),
        );
        $this->assertEquals($expected, $payload['exception_stack']);
    }

    public function testTypeUrlIsUsedVerbatim()
    {
        $apiProblem = new ApiProblem('500', 'foo', 'http://status.dev:8080/details.md');
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('type', $payload);
        $this->assertEquals('http://status.dev:8080/details.md', $payload['type']);
    }

    public function knownStatusCodes()
    {
        return array(
            '404' => array(404),
            '409' => array(409),
            '422' => array(422),
            '500' => array(500),
        );
    }

    /**
     * @dataProvider knownStatusCodes
     */
    public function testKnownStatusResultsInKnownTitle($status)
    {
        $apiProblem = new ApiProblem($status, 'foo');
        $r = new ReflectionObject($apiProblem);
        $p = $r->getProperty('problemStatusTitles');
        $p->setAccessible(true);
        $titles = $p->getValue($apiProblem);

        $payload = $apiProblem->toArray();
        $this->assertArrayHasKey('title', $payload);
        $this->assertEquals($titles[$status], $payload['title']);
    }

    public function testUnknownStatusResultsInUnknownTitle()
    {
        $apiProblem = new ApiProblem(420, 'foo');
        $payload = $apiProblem->toArray();
        $this->assertArrayHasKey('title', $payload);
        $this->assertEquals('Unknown', $payload['title']);
    }

    public function testProvidedTitleIsUsedVerbatim()
    {
        $apiProblem = new ApiProblem('500', 'foo', 'http://status.dev:8080/details.md', 'some title');
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('title', $payload);
        $this->assertEquals('some title', $payload['title']);
    }

    public function testCanPassArbitraryDetailsToConstructor()
    {
        $problem = new ApiProblem(400, 'Invalid input', 'http://example.com/api/problem/400', 'Invalid entity', array('foo' => 'bar'));
        $this->assertEquals('bar', $problem->foo);
    }

    public function testArraySerializationIncludesArbitraryDetails()
    {
        $problem = new ApiProblem(400, 'Invalid input', 'http://example.com/api/problem/400', 'Invalid entity', array('foo' => 'bar'));
        $array   = $problem->toArray();
        $this->assertArrayHasKey('foo', $array);
        $this->assertEquals('bar', $array['foo']);
    }

    public function testArbitraryDetailsShouldNotOverwriteRequiredFieldsInArraySerialization()
    {
        $problem = new ApiProblem(400, 'Invalid input', 'http://example.com/api/problem/400', 'Invalid entity', array('title' => 'SHOULD NOT GET THIS'));
        $array   = $problem->toArray();
        $this->assertArrayHasKey('title', $array);
        $this->assertEquals('Invalid entity', $array['title']);
    }

    public function testUsesTitleFromExceptionWhenProvided()
    {
        $exception  = new Exception\DomainException('exception message', 401);
        $exception->setTitle('problem title');
        $apiProblem = new ApiProblem('401', $exception);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('title', $payload);
        $this->assertEquals($exception->getTitle(), $payload['title']);
    }

    public function testUsesTypeFromExceptionWhenProvided()
    {
        $exception  = new Exception\DomainException('exception message', 401);
        $exception->setType('http://example.com/api/help/401');
        $apiProblem = new ApiProblem('401', $exception);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('type', $payload);
        $this->assertEquals($exception->getType(), $payload['type']);
    }

    public function testUsesAdditionalDetailsFromExceptionWhenProvided()
    {
        $exception  = new Exception\DomainException('exception message', 401);
        $exception->setAdditionalDetails(array('foo' => 'bar'));
        $apiProblem = new ApiProblem('401', $exception);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('foo', $payload);
        $this->assertEquals('bar', $payload['foo']);
    }
}
