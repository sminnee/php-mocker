<?php

require_once(dirname(dirname(__FILE__)) . '/Mocker.php');

class MockerTest extends PHPUnit_Framework_TestCase {
	function testSimpleMethodCall() {
		$mock = new TestMock;

		$mock->method('fibonacci')
			->withArgs(4)->returns(array(1,1,2,3));

		$this->assertEquals(array(1,1,2,3), $mock->fibonacci(4));
	}

	function testUnknownArgumentsThrowException() {
		$this->setExpectedException('Exception');

		$mock = new TestMock;
		$mock->method('fibonacci')
			->withArgs(4)->returns(array(1,1,2,3));

		// Throws an exception
		$mock->fibonacci(5);
	}

	function testMultipleMethodsCanBeDefined() {
		$mock = new TestMock;

		$mock->method('fibonacci')
			->withArgs(4)->returns(array(1,1,2,3));
		$mock->method('primes')
			->withArgs(4)->returns(array(2,3,5,7));

		$this->assertEquals(array(1,1,2,3), $mock->fibonacci(4));
		$this->assertEquals(array(2,3,5,7), $mock->primes(4));
	}

	function testMultipleArgumentsCanBeDefined() {
		$mock = new TestMock;

		$mock
			->method('fibonacci')
				->withArgs(1)->returns(array(1))
				->withArgs(2)->returns(array(1,1))
				->withArgs(3)->returns(array(1,1,2))
				->withArgs(4)->returns(array(1,1,2,3))
			->method('primes')
				->withArgs(1)->returns(array(2))
				->withArgs(2)->returns(array(2,3))
				->withArgs(3)->returns(array(2,3,5))
				->withArgs(4)->returns(array(2,3,5,7));

		$this->assertEquals(array(1,1), $mock->fibonacci(2));
		$this->assertEquals(array(1,1,2,3), $mock->fibonacci(4));
		$this->assertEquals(array(2,3), $mock->primes(2));
		$this->assertEquals(array(2,3,5,7), $mock->primes(4));
	}

	function testIsExpected() {
		$mock = new TestMock;

		$mock->method('fibonacci')
			->withArgs(4)->returns(array(1,1,2,3))
			->isExpected();


		$mockThatGetsCalled = new TestMock;
		$mockThatGetsCalled->method('fibonacci')
			->withArgs(4)->returns(array(1,1,2,3))
			->isExpected();

		// checkExpectations() won't throw an exception
		$this->assertEquals(array(1,1,2,3), $mockThatGetsCalled->fibonacci(4));
		$mockThatGetsCalled->checkExpectations();

		// isExpected() set will mean that $mock->checkExpectations() throws an exception
		$this->setExpectedException('Exception');
		$mock->checkExpectations();
	}

	function testCanExpectedSpecificArguments() {
		$mock = new TestMock;

		$mock->method('fibonacci')
			->withArgs(2)->returns(array(1,1))
			->withArgs(4)->returns(array(1,1,2,3))
			->isExpected();

		$this->assertEquals(array(1,1), $mock->fibonacci(2));

		// isExpected() was set on withArgs(4) so this is still thrown
		$this->setExpectedException('Exception');
		$mock->checkExpectations();
	}
}

class TestMock {
	function __construct() {
		$this->mocker = new SS_Mocker;
	}
	
	function __call($method, $args) {
		return $this->mocker->call($method, $args);
	}

	function method($method) {
		return $this->mocker->method($method);
	}

	function checkExpectations() {
		return $this->mocker->checkExpectations();
	}
}