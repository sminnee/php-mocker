<?php

/**
 * Handler class for creating mock objects.
 * 
 * Usage - creating a mock class (note :code generation would be a nice enhancement here)
 * 
 *  - Create a mock object, subclassing the class that you're mocking
 * 
 *  - In its constructor, create a new $this->mocker property
 * 
 *		function __construct() {
 *			$this->mocker = new SS_Mocker;
 *		}
 * 
 *  - Override any methods that need mocking, with a method of the following form:
 * 
 * 		function methodName($params) {
 *			$args = func_get_args();
 * 			return $this->mocker->call('methodName', $args);
 *		}
 * 
 *  - Pass through method() and checkExpectations() to the mocker
 * 
 *		function method($method) {
 *			return $this->mocker->method($method);
 *		}
 *	
 *		function checkExpectations() {
 *			return $this->mocker->checkExpectations();
 *		}
 * 
 * Usage - defining stub methods:
 * 
 * This short example says defines some return values for getPrimes() and getFiboacci() and specifies
 * that each of these calls are expected, and should throw an exception if the calls are missed.
 * 
 *		$this->myMock
 *			->method('getPrimes')
 *				->withArgs(4)
 *					->returns(array(2, 3, 5, 7))
 *					->isExpected()
 *			->method('getFibonacci')
 *				->withArgs(6)
 *					->returns(array(1, 1, 2, 3, 5, 8))
 *					->isExpected()
 *				->withArgs(3)
 *					->returns(array(1, 1, 2))
 *					->isExpected();
 * 
 * In order for the expectations to be triggered, checkExpectations() should be called.
 * 
 * 		$this->myMock->checkExpectations();
 */
class SS_Mocker {
	protected $stubs;

	/**
	 * Stub a new method, defining expected arguments and return values using a fluent syntax.
	 * Your mock object should pass calls to stubMethod() through to this.
	 */
	function method($method) {
		if(!isset($this->stubs[$method])) {
			$this->stubs[$method] = new SS_Mocker_StubMethod($method, $this);
		}
		return $this->stubs[$method];
	}
	
	/**
	 * Call handler for mocked method.  Your mock object should pass calls through to this method.
	 */
	function call($method, $args) {
		if(!isset($this->stubs[$method])) throw new Exception("Method $method not stubbed!");
		return $this->stubs[$method]->call($args);
	}

	/**
	 * Assert that a method was called.
	 * 
	 * Will only pass if assertMethodCalled() is called in the right order - you must assert *all*
	 * of the calls.
	 * 
	 * If args is omitted, then the assertion will pass no matter what the arguments were.
	 * 
	 * Your mock object should pass calls to assertMethodCalled() through to this.
	 */
	function assertMethodCalled($method, $args = null) {
		if(!isset($this->stubs[$method])) throw new Exception("Method $method not stubbed!");
		return $this->stubs[$method]->assertCalled($args);
	}
	
	/**
	 * Check that the expectations of this mocker have been met
	 */
	function checkExpectations() {
		foreach($this->stubs as $method) $method->checkExpectations();
		return $this;
	}
}

/**
 * Single stub method
 */
class SS_Mocker_StubMethod {
	protected $currentArgs = 'default';
	protected $methodName, $mocker;
	
	protected $arguments = array();
	protected $returns = array();
	protected $expected = false;
	
	function __construct($methodName, $mocker = null) {
		$this->methodName = $methodName;
		$this->mocker = $mocker;
	}

	/**
	 * Set the arguments
	 */
	function withArgs() {
		$arguments = func_get_args();
		$hash = sha1(serialize($arguments));
		
		if(!isset($this->arguments[$hash])) {
			$this->arguments[$hash] = new SS_Mocker_StubMethod_Arguments($arguments, $this);
		}
		
		return $this->arguments[$hash];
	}
	
	/**
	 * Set the return value for a method call with any arguments
	 */
	function returns($returnValue) {
		$this->returns['default'] = $returnValue;

		return $this;
	}
	
	/**
	 * Handle a method call.
	 */
	function call($arguments) {
		$this->callLog[] = $arguments;
		
		$hash = sha1(serialize($arguments));
		if(isset($this->arguments[$hash])) {
			return $this->arguments[$hash]->call();
		} else if(array_key_exists('default', $this->returns)) {
			return $this->returns['default'];
		}
		
		// Failure
		ob_start();
		var_dump($arguments);
		$actual = ob_get_clean();

		$expected = array();
		foreach($this->arguments as $argSet) {
			$expected[] = $argSet->argString();
		}
			
		$expectedSuffix = $expected ? "\nThese arguments were expected:\n" . implode("\n\nOR\n\n", $expected) : "";
		throw new Exception("A call to $this->methodName() with the following arguments wasn't expected:\n$actual" . $expectedSuffix);
		
	}
	
	function getCallLog() {
		return $this->callLog;
	}
	
	function isExpected() {
		$this->expected = true;
		return $this;
	}
	
	/**
	 * Assert that this method was called with the given arguments.
	 */
	function assertCalled() {
		if(!$this->callLog) {
			if(class_exists('PHPUnit_Framework_AssertionFailedError')) $ex = 'PHPUnit_Framework_AssertionFailedError';
			else $ex = 'Exception';
			throw new $ex("A call to $this->methodName() was expected but was never made.");
		}
		return $this;
	}

	/**
	 * Check that the expectations of this mocker have been met
	 */
	function checkExpectations() {
		if($this->expected) $this->assertCalled();
		foreach($this->arguments as $argSet) $argSet->checkExpectations();
		return $this;
	}
	
	//////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Stub a new method (passes back to the mocker)
	 */
	function method($method) {
		return $this->mocker->method($method);
	}
}

/**
 * Single argument set for stub method
 */
class SS_Mocker_StubMethod_Arguments {
	protected $method, $arguments;
	protected $callCount =  0;
	protected $return = null;
	protected $expected = false;
	
	function __construct($arguments, $method = null) {
		$this->arguments = $arguments;
		$this->method = $method;
	}

	/**
	 * Set the return value for the arugments just defined
	 */
	function returns($returnValue) {
		$this->return = $returnValue;
		return $this;
	}
	
	/**
	 * Handle a method call.
	 */
	function call() {
		$this->callCount++;
		return $this->return;
	}
	
	/**
	 * Set that this method is accepted
	 */
	function isExpected() {
		$this->expected = true;
		return $this;
	}
	
	/**
	 * Assert that this method was called with the given arguments.
	 */
	function assertCalled() {
		if(!$this->callCount) {
			ob_start();
			var_dump($this->arguments);
			$expected = ob_get_clean();
			if(class_exists('PHPUnit_Framework_AssertionFailedError')) $ex = 'PHPUnit_Framework_AssertionFailedError';
			else $ex = 'Exception';
			throw new $ex("A call to $this->methodName() with these arguments was never made:\n" . $expected);
		}
		return $this;
	}

	/**
	 * Check that the expectations of this mocker have been met
	 */
	function checkExpectations() {
		if($this->expected) $this->assertCalled();
		return $this;
	}

	/**
	 * Returns a string representation of these arguments.
	 */
	function argString() {
		ob_start();
		var_dump($this->arguments);
		return ob_get_clean();
	}

	//////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Set the arguments (passes back to the method)
	 */
	function withArgs() {
		$arguments = func_get_args();
		return call_user_func_array(array($this->method,'withArgs'), $arguments);
	}
	
	/**
	 * Stub a new method (passes back to the mocker)
	 */
	function method($method) {
		return $this->method->method($method);
	}

}